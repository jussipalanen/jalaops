<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Support\PaymentQr;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts in euros, computed from the copied lines' whole cents.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totals = $this->totalsCents();
        $iban = preg_replace('/\s/', '', (string) config('billing.seller.iban'));

        return [
            'id' => $this->id,
            'number' => $this->number,
            'reference_number' => ReferenceNumber::format($this->reference_number),
            'customer' => ['id' => $this->customer_id, 'name' => $this->customer_name],
            'customer_business_id' => $this->customer_business_id,
            'billing_street_address' => $this->billing_street_address,
            'billing_postal_code' => $this->billing_postal_code,
            'billing_city' => $this->billing_city,
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status,
            'overdue' => $this->isOverdue(),
            'sent_at' => $this->sent_at,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'totals' => ['net' => $totals['net'] / 100, 'vat' => $totals['vat'] / 100, 'total' => $totals['total'] / 100],
            // VAT by rate, as a Finnish invoice must show it.
            'vat_breakdown' => collect($totals['by_rate'])->map(fn ($sums, $rate) => [
                'rate' => $rate / 100,
                'net' => $sums['net'] / 100,
                'vat' => $sums['vat'] / 100,
            ])->values(),
            // The seller's bank details for paying by hand, and the payment QR
            // code's text (EPC QR), which is null when it isn't shown.
            'payment' => [
                'recipient' => config('billing.seller.name'),
                'iban' => $iban !== '' ? trim(chunk_split(strtoupper($iban), 4, ' ')) : null,
                'bic' => config('billing.seller.bic') ?: null,
                'qr' => PaymentQr::forInvoice($this->resource),
            ],
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'service_request_id' => $line->service_request_id,
                'request_title' => $line->request_title,
                'delivered_on' => $line->delivered_on?->toDateString(),
                'type' => $line->type,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price_cents / 100,
                'vat_rate' => $line->vat_rate_basis_points / 100,
                'net' => $line->netCents() / 100,
                'vat' => $line->vatCents() / 100,
                'total' => $line->totalCents() / 100,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
