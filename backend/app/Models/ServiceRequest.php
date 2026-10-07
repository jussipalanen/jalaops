<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
