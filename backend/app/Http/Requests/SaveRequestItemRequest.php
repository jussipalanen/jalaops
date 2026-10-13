<?php

namespace App\Http\Requests;

use App\Enums\RequestItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a row of work or material. Prices are euros without VAT and the
 * VAT rate is a percentage, both with at most two decimals.
 */
class SaveRequestItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** `work` or `material`. */
            'type' => ['required', Rule::enum(RequestItemType::class)],
            'description' => ['required', 'string', 'max:255'],
            /** E.g. 1.5 (hours) or 2 (pieces). */
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999.99', 'decimal:0,2'],
            /** E.g. h, kpl, m. */
            'unit' => ['required', 'string', 'max:20'],
            /** Euros without VAT, e.g. 65.00. */
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            /** Percent, e.g. 25.5. */
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ];
    }

    /**
     * A row's total (quantity × unit price) is at most 9 999 999,99 €, so it
     * fits invoices and documents.
     *
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['quantity', 'unit_price'])) {
                return;
            }

            if ((float) $this->input('quantity') * (float) $this->input('unit_price') > 9999999.99) {
                $validator->errors()->add('unit_price', 'Rivin summa saa olla enintään 9 999 999,99 €.');
            }
        }];
    }

    /**
     * The validated row as stored: cents and basis points.
     *
     * @return array<string, mixed>
     */
    public function itemData(): array
    {
        $data = $this->validated();

        return [
            'type' => $data['type'],
            'description' => $data['description'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'unit_price_cents' => (int) round($data['unit_price'] * 100),
            'vat_rate_basis_points' => (int) round($data['vat_rate'] * 100),
        ];
    }
}
