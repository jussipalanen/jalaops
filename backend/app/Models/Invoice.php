<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An invoice for completed requests. It keeps copies of the customer's billing
 * details and of the invoiced rows, so it never changes afterwards.
 *
 * @property int $id
 * @property int $number
 * @property string $reference_number
 * @property int $customer_id
 * @property string $customer_name
 * @property string|null $customer_business_id
 * @property string|null $billing_street_address
 * @property string|null $billing_postal_code
 * @property string|null $billing_city
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property InvoiceStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $paid_at
 * @property string|null $notes
 */
class Invoice extends Model
{
    protected $fillable = [
        'number',
        'reference_number',
        'customer_id',
        'customer_name',
        'customer_business_id',
        'billing_street_address',
        'billing_postal_code',
        'billing_city',
        'issue_date',
        'due_date',
        'status',
        'notes',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'issue_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'status' => InvoiceStatus::class,
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('id');
    }

    /**
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * Sent and past its due date (by the date in Finland).
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Sent && $this->due_date->toDateString() < today(config('app.display_timezone'))->toDateString();
    }

    /**
     * Only sent invoices past their due date.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Sent)->whereDate('due_date', '<', today(config('app.display_timezone'))->toDateString());
    }

    /**
     * The sums of the lines in cents, and the VAT by rate (basis points →
     * net and VAT), which a Finnish invoice must show.
     *
     * @return array{net: int, vat: int, total: int, by_rate: array<int, array{net: int, vat: int}>}
     */
    public function totalsCents(): array
    {
        $byRate = [];
        foreach ($this->lines as $line) {
            $byRate[$line->vat_rate_basis_points]['net'] = ($byRate[$line->vat_rate_basis_points]['net'] ?? 0) + $line->netCents();
            $byRate[$line->vat_rate_basis_points]['vat'] = ($byRate[$line->vat_rate_basis_points]['vat'] ?? 0) + $line->vatCents();
        }
        krsort($byRate);

        $net = array_sum(array_column($byRate, 'net'));
        $vat = array_sum(array_column($byRate, 'vat'));

        return ['net' => $net, 'vat' => $vat, 'total' => $net + $vat, 'by_rate' => $byRate];
    }
}
