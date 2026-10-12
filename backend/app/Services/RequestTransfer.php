<?php

namespace App\Services;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Http\Requests\SaveServiceRequestRequest;
use App\Models\ServiceRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use JsonException;

/**
 * Exports requests as CSV or JSON, and imports them back from either format.
 * Imports are validated row by row with the request form's rules and always
 * create new requests.
 */
class RequestTransfer
{
    public const MAX_ROWS = 1000;

    /**
     * CSV columns, in order. Import reads the editable ones by name and ignores the rest.
     */
    private const CSV_COLUMNS = [
        'id', 'title', 'description', 'priority', 'status', 'due_date',
        'street_address', 'postal_code', 'city', 'latitude', 'longitude',
        'signed_by', 'signed_at', 'created_at', 'updated_at',
    ];

    /**
     * Fields an import can set; the rest (ids, timestamps) come from the app.
     */
    private const IMPORTED_FIELDS = [
        'title', 'description', 'priority', 'status', 'due_date',
        'street_address', 'postal_code', 'city', 'latitude', 'longitude',
        'signed_by', 'completion_signature',
    ];

    /**
     * Semicolon-separated UTF-8 with a byte order mark, which Excel in Finnish
     * opens with the columns and the letters ä and ö right.
     *
     * @param  Collection<int, ServiceRequest>  $requests
     */
    public function csv(Collection $requests): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::CSV_COLUMNS, ';', '"', '');

        foreach ($requests as $request) {
            fputcsv($handle, array_map(fn (string $column) => $this->csvValue($request, $column), self::CSV_COLUMNS), ';', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * A full copy, including the signatures, that imports back as is.
     *
     * @param  Collection<int, ServiceRequest>  $requests
     */
    public function json(Collection $requests): string
    {
        return json_encode([
            'app' => 'JalaOps',
            'version' => config('scramble.info.version'),
            'exported_at' => now()->toIso8601String(),
            'requests' => $requests->map(fn (ServiceRequest $request) => [
                'id' => $request->id,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority->value,
                'status' => $request->status->value,
                'due_date' => $request->due_date?->toDateString(),
                'street_address' => $request->street_address,
                'postal_code' => $request->postal_code,
                'city' => $request->city,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'signed_by' => $request->signed_by,
                'signed_at' => $request->signed_at?->toIso8601String(),
                'completion_signature' => $request->completion_signature,
                'created_at' => $request->created_at?->toIso8601String(),
                'updated_at' => $request->updated_at?->toIso8601String(),
            ])->values(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Reads the rows of a CSV or JSON file, with the row numbers a person sees
     * (the CSV header is row 1).
     *
     * @return array<int, array<string, mixed>> rows by row number
     *
     * @throws ImportException when the file can't be read
     */
    public function parse(UploadedFile $file): array
    {
        $content = (string) file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $isJson = strtolower($file->getClientOriginalExtension()) === 'json' || preg_match('/^\s*[\[{]/', $content);

        $rows = $isJson ? $this->parseJson($content) : $this->parseCsv($content);

        if ($rows === []) {
            throw new ImportException('Tiedostossa ei ole yhtään pyyntöä.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new ImportException('Tiedostossa on yli '.self::MAX_ROWS.' pyyntöä. Jaa se pienempiin osiin.');
        }

        return $rows;
    }

    /**
     * Validates each row with the request form's rules.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{valid: array<int, array<string, mixed>>, errors: list<array{row: int, title: string|null, messages: list<string>}>}
     */
    public function validate(array $rows): array
    {
        $form = new SaveServiceRequestRequest;
        $valid = [];
        $errors = [];

        foreach ($rows as $number => $row) {
            $data = $this->normalise($row);
            $validator = Validator::make($data, $form->rules(), $form->messages());

            if ($validator->fails()) {
                $errors[] = [
                    'row' => $number,
                    'title' => is_string($data['title'] ?? null) ? $data['title'] : null,
                    'messages' => $validator->errors()->all(),
                ];
            } else {
                $valid[$number] = $validator->validated();
            }
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    /**
     * Saves the rows as new requests, all or none.
     *
     * @param  array<int, array<string, mixed>>  $rows  validated rows
     */
    public function import(array $rows): int
    {
        return DB::transaction(function () use ($rows) {
            foreach ($rows as $data) {
                (new ServiceRequest)->saveWithSignOff($data);
            }

            return count($rows);
        });
    }

    private function csvValue(ServiceRequest $request, string $column): string
    {
        $value = match ($column) {
            'priority' => $request->priority->value,
            'status' => $request->status->value,
            'due_date' => $request->due_date?->toDateString(),
            'signed_at', 'created_at', 'updated_at' => $request->{$column}?->toIso8601String(),
            default => $request->{$column},
        };

        $value = (string) ($value ?? '');

        // A leading = + - @ would make Excel run the cell as a formula; the
        // apostrophe keeps it text. Import removes it again.
        return is_string($request->{$column}) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseJson(string $content): array
    {
        try {
            $data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ImportException('JSON-tiedostoa ei voitu lukea. Tarkista, että se on JalaOpsin vientitiedosto tai muuten kelvollista JSONia.');
        }

        $requests = is_array($data) && array_is_list($data) ? $data : ($data['requests'] ?? null);

        if (! is_array($requests)) {
            throw new ImportException('JSON-tiedostosta ei löytynyt pyyntöjä (odotettiin listaa tai kenttää "requests").');
        }

        $rows = [];
        foreach (array_values($requests) as $index => $request) {
            $rows[$index + 1] = is_array($request) ? $request : [];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        $firstLine = $lines[0] ?? '';
        // Excel in Finnish saves with semicolons; other tools use commas.
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle, null, $delimiter, '"', '');
        $header = array_map(fn ($name) => strtolower(trim((string) $name)), $header ?: []);

        if (! in_array('title', $header, true)) {
            fclose($handle);
            throw new ImportException('CSV-tiedoston ensimmäisellä rivillä pitää olla sarakkeiden nimet, vähintään title, priority ja status.');
        }

        $rows = [];
        $number = 1;
        while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $number++;

            if ($values === [null] || implode('', $values) === '') {
                continue;
            }

            $rows[$number] = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Turns a row into what the request form accepts: known fields only, empty
     * cells as null, Finnish dates, decimal commas and Finnish labels.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalise(array $row): array
    {
        $data = [];

        foreach (self::IMPORTED_FIELDS as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }

            $value = $row[$field];

            if (is_string($value)) {
                $value = trim($value);
                // The apostrophe that the export adds in front of formula characters.
                $value = preg_replace('/^\'(?=[=+\-@])/', '', $value);
                $value = $value === '' ? null : $value;
            }

            $data[$field] = $value;
        }

        foreach (['latitude', 'longitude'] as $field) {
            if (is_string($data[$field] ?? null) && preg_match('/^-?\d+,\d+$/', $data[$field])) {
                $data[$field] = str_replace(',', '.', $data[$field]);
            }
        }

        if (is_string($data['due_date'] ?? null) && preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $data['due_date'], $date)) {
            $data['due_date'] = sprintf('%04d-%02d-%02d', $date[3], $date[2], $date[1]);
        }

        $data['priority'] = $this->enumValue($data['priority'] ?? null, RequestPriority::cases());
        $data['status'] = $this->enumValue($data['status'] ?? null, RequestStatus::cases());

        return $data;
    }

    /**
     * Accepts the API value ("high") or the Finnish label ("Korkea").
     *
     * @param  list<RequestPriority|RequestStatus>  $cases
     */
    private function enumValue(mixed $value, array $cases): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        foreach ($cases as $case) {
            if (strcasecmp($value, $case->value) === 0 || mb_strtolower($value) === mb_strtolower($case->label())) {
                return $case->value;
            }
        }

        return $value;
    }
}
