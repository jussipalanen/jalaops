<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Database\Factories\ServiceRequestFactory;
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
        ];
    }
}
