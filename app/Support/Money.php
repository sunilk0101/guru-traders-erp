<?php

namespace App\Support;

/**
 * H-08: amounts were printed as bare numbers everywhere ('Amount 15.21',
 * 'Buyer Outstanding 0.00') even though every document already carries a
 * currency — ambiguous the moment more than one currency is in play, which
 * is every day for an export trading house. Also not thousand-grouped in
 * most tables (a raw '1511' next to a grouped '1,511.00' elsewhere).
 *
 * One helper, used everywhere an amount is printed: currency code + a
 * thousand-grouped, 2-decimal number — 'INR 15.21', 'USD 1,511.00'.
 */
class Money
{
    /**
     * @param  float|int|string|null  $amount
     * @param  string|null  $currencyCode  ISO code (e.g. 'INR', 'USD'). Falls
     *                                     back to 'INR' — Guru Traders' home
     *                                     currency — when a record has none
     *                                     (e.g. a draft with no currency picked yet).
     */
    public static function format($amount, ?string $currencyCode = null): string
    {
        $code = $currencyCode ?: 'INR';

        return $code.' '.number_format((float) ($amount ?? 0), 2);
    }
}
