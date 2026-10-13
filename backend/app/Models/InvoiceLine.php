<?php

namespace App\Models;

use App\Enums\RequestItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A copied row of work or material on an invoice. Amounts are computed like a
 * request's rows: cents, with VAT rounded per line.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $service_request_id
 * @property string $request_title
 * @property Carbon|null $delivered_on
 * @property RequestItemType $type
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property int $unit_price_cents
 * @property int $vat_rate_basis_points
 */
class InvoiceLine extends Model
{
    protected $fillable = [
        'service_request_id',
        'request_title',
        'delivered_on',
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
            'delivered_on' => 'date:Y-m-d',
            'quantity' => 'decimal:2',
            'unit_price_cents' => 'integer',
            'vat_rate_basis_points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
