<?php

namespace App\Support;

/**
 * Normalizes customer address fields shared by web + API.
 */
final class CustomerGeo
{
    /**
     * Map USA / United States spellings to ISO 3166-1 alpha-2 {@code US}.
     */
    public static function normalizeCountry(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $t = trim($value);
        $u = strtoupper($t);
        if (in_array($u, ['USA', 'US'], true)) {
            return 'US';
        }
        if (preg_match('/^United States(\s+of\s+America)?$/i', $t) === 1) {
            return 'US';
        }
        if (strlen($u) === 2) {
            return $u;
        }

        return $t;
    }
}
