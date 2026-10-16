<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * The payment QR code of an invoice: an EPC QR code ("SEPA credit transfer",
 * version 002) that banking apps read to fill in the payment. The app and the
 * invoice documents both draw it from this text.
 */
class PaymentQr
{
    // The largest amount the format allows, 999 999 999,99 €.
    private const MAX_CENTS = 99_999_999_999;

    /**
     * The QR code's text, or null when it's turned off (INVOICE_PAYMENT_QR)
     * or the invoice has nothing to pay this way: no valid seller IBAN, no
     * amount, or already paid.
     */
    public static function forInvoice(Invoice $invoice): ?string
    {
        if (! config('billing.payment_qr')) {
            return null;
        }

        $seller = config('billing.seller');
        $iban = self::compact($seller['iban'] ?? null);
        $bic = self::compact($seller['bic'] ?? null);
        $cents = $invoice->totalsCents()['total'];

        if (
            $invoice->status === InvoiceStatus::Paid
            || ! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)
            || $cents <= 0 || $cents > self::MAX_CENTS
        ) {
            return null;
        }

        return implode("\n", [
            'BCD',
            '002',
            '1', // UTF-8
            'SCT',
            preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/', $bic) ? $bic : '',
            mb_substr(trim((string) $seller['name']), 0, 70),
            $iban,
            'EUR'.number_format($cents / 100, 2, '.', ''),
            '', // No purpose code.
            ReferenceNumber::toRf($invoice->reference_number),
        ]);
    }

    private static function compact(?string $value): string
    {
        return strtoupper(preg_replace('/\s/', '', (string) $value));
    }
}
