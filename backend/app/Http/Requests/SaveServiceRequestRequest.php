<?php

namespace App\Http\Requests;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creating (POST) and fully updating (PUT) a service request.
 */
class SaveServiceRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(RequestPriority::class)],
            'status' => ['required', Rule::enum(RequestStatus::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'street_address' => ['nullable', 'string', 'max:255'],
            // Mostly Finnish five-digit codes, but other countries' formats are allowed.
            'postal_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/'],
            'city' => ['nullable', 'string', 'max:100'],
            // A point needs both coordinates, so one is required when the other is given.
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            // Sign-off, only for completed requests. Leave completion_signature out to keep the
            // current signature; null removes it. A signature needs the signer's name.
            'signed_by' => ['nullable', 'string', 'max:100', 'required_with:completion_signature', 'prohibited_unless:status,completed'],
            'completion_signature' => ['nullable', 'string', 'max:400000', 'prohibited_unless:status,completed', $this->pngDataUrl(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signed_by.prohibited_unless' => 'Kuittaajan voi antaa vain valmiille pyynnölle.',
            'completion_signature.prohibited_unless' => 'Allekirjoituksen voi lisätä vain valmiille pyynnölle.',
            'completion_signature.max' => 'Allekirjoitus on liian suuri.',
        ];
    }

    /**
     * The signature must be a PNG image as a data URL.
     */
    private function pngDataUrl(string $attribute, mixed $value, Closure $fail): void
    {
        $base64 = is_string($value) && preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#', $value, $match) ? $match[1] : null;

        if ($base64 === null || ! str_starts_with((string) base64_decode($base64, true), "\x89PNG\r\n\x1a\n")) {
            $fail('Allekirjoituksen on oltava PNG-kuva.');
        }
    }
}
