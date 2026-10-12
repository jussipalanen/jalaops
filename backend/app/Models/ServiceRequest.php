<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property RequestPriority $priority
 * @property RequestStatus $status
 * @property Carbon|null $due_date
 * @property string|null $street_address
 * @property string|null $postal_code
 * @property string|null $city
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'street_address',
        'postal_code',
        'city',
        'latitude',
        'longitude',
    ];

    protected $attributes = [
        'priority' => 'normal',
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'priority' => RequestPriority::class,
            'status' => RequestStatus::class,
            'due_date' => 'date:Y-m-d',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Only requests with the given status.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, RequestStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Only requests with the given priority.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withPriority(Builder $query, RequestPriority $priority): void
    {
        $query->where('priority', $priority);
    }

    /**
     * Only requests whose title, street address, postal code or city contains the text.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $text): void
    {
        // "%" and "_" in the search match literally. The escape character is
        // given explicitly, because MariaDB and SQLite have different defaults.
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';

        $query->where(fn (Builder $query) => $query
            ->whereRaw("title like ? escape '!'", [$pattern])
            ->orWhereRaw("street_address like ? escape '!'", [$pattern])
            ->orWhereRaw("postal_code like ? escape '!'", [$pattern])
            ->orWhereRaw("city like ? escape '!'", [$pattern]));
    }

    /**
     * Only requests due in the given period: overdue (not completed and due
     * before today), today, week (today and the next 7 days), month (the next
     * 30 days) or none (no due date).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dueIn(Builder $query, string $period): void
    {
        $today = today();

        match ($period) {
            'overdue' => $query->whereDate('due_date', '<', $today)->where('status', '!=', RequestStatus::Completed),
            'today' => $query->whereDate('due_date', $today),
            'week' => $query->dueBetween($today->toDateString(), $today->copy()->addDays(7)->toDateString()),
            'month' => $query->dueBetween($today->toDateString(), $today->copy()->addDays(30)->toDateString()),
            'none' => $query->whereNull('due_date'),
        };
    }

    /**
     * Only requests due between the dates (inclusive); either end can be left open.
     * whereDate compares just the date, also in SQLite, which stores a time too.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dueBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query
            ->when($from, fn (Builder $query) => $query->whereDate('due_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('due_date', '<=', $to));
    }

    /**
     * Sorts by a field, with "-" in front for descending. Requests without a
     * due date come last either way; priority and status sort in their logical
     * order (low → high, open → completed). The id breaks ties, so pages are stable.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function sortedBy(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        match ($field) {
            'due_date' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date', $direction),
            'priority' => $query->orderByRaw("CASE priority WHEN 'low' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END {$direction}"),
            'status' => $query->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END {$direction}"),
            default => $query->orderBy($field, $direction),
        };

        $query->orderBy('id', $direction);
    }
}
