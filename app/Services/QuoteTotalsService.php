<?php

namespace App\Services;

use App\Models\Quote;

final class QuoteTotalsService
{
    public static function recalculate(Quote $quote): void
    {
        $quote->unsetRelation('lines');
        $quote->load('lines');

        $subtotal = round((float) $quote->lines->sum('line_total'), 2);
        $discount = round(max(0, (float) $quote->discount_amount), 2);
        $tax = round(max(0, (float) $quote->tax_amount), 2);
        $afterDiscount = max(0.0, round($subtotal - $discount, 2));
        $total = round($afterDiscount + $tax, 2);

        $quote->update([
            'subtotal_amount' => number_format($subtotal, 2, '.', ''),
            'total_amount' => number_format($total, 2, '.', ''),
        ]);
    }
}
