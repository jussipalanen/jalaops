<?php

namespace App\Models;

use App\Enums\RequestItemType;
use Database\Factories\RequestItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of work or material on a request. Money is in whole cents: the net
 * amount is quantity × unit price, and the VAT is rounded per row, as on a
 * Finnish invoice.
 *
 * @property int $id
 * @property int $service_request_id
 * @property RequestItemType $type
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property int $unit_price_cents
 * @property int $vat_rate_basis_points
 */
class RequestItem extends Model
{
    /** @use HasFactory<RequestItemFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'description',
        'quantity',
        'unit',
        'unit_price_cents',
        'vat_rate_basis_points',
    ];

    protected function casts(): array
    {
        return [
            'type' => RequestItemType::class,
            'quantity' => 'decimal:2',
            'unit_price_cents' => 'integer',
            'vat_rate_basis_points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function netCents(): int
    {
        return (int) round((float) $this->quantity * $this->unit_price_cents);
    }

    public function vatCents(): int
    {
        return (int) round($this->netCents() * $this->vat_rate_basis_points / 10000);
    }

    public function totalCents(): int
    {
        return $this->netCents() + $this->vatCents();
    }
}
