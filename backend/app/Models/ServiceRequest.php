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
     * Only requests whose street address, postal code or city contains the text.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function locatedAt(Builder $query, string $text): void
    {
        // "%" and "_" in the search match literally. The escape character is
        // given explicitly, because MariaDB and SQLite have different defaults.
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';

        $query->where(fn (Builder $query) => $query
            ->whereRaw("street_address like ? escape '!'", [$pattern])
            ->orWhereRaw("postal_code like ? escape '!'", [$pattern])
            ->orWhereRaw("city like ? escape '!'", [$pattern]));
    }
}
