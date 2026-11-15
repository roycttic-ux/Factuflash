<?php

namespace App\Services;

class CuitValidator
{
    /** Valid AFIP/ARCA CUIT prefixes */
    public const VALID_PREFIXES = ['20', '23', '24', '27', '30', '33', '34', '50', '51', '55'];

    /**
     * Validates an Argentine CUIT/CUIL using the official Modulo 11 check digit algorithm.
     */
    public static function isValid(?string $cuit): bool
    {
        if ($cuit === null) {
            return false;
        }

        $clean = preg_replace('/\D/', '', $cuit);
        if (strlen($clean) !== 11) {
            return false;
        }

        // Avoid trivial repetitions like 00000000000, 11111111111, etc.
        if (preg_match('/^(\d)\1{10}$/', $clean)) {
            return false;
        }

        $prefix = substr($clean, 0, 2);
        if (!in_array($prefix, self::VALID_PREFIXES, true)) {
            return false;
        }

        $multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $clean[$i]) * $multipliers[$i];
        }

        $remainder = 11 - ($sum % 11);
        $expectedCheckDigit = match ($remainder) {
            11 => 0,
            10 => 9,
            default => $remainder,
        };

        return ((int) $clean[10]) === $expectedCheckDigit;
    }

    /**
     * Normalizes a CUIT to digits only.
     */
    public static function clean(?string $cuit): string
    {
        return preg_replace('/\D/', '', (string) $cuit);
    }

    /**
     * Formats a clean 11-digit CUIT as XX-XXXXXXXX-X.
     */
    public static function format(?string $cuit): string
    {
        $clean = self::clean($cuit);
        if (strlen($clean) !== 11) {
            return (string) $cuit;
        }
        return substr($clean, 0, 2) . '-' . substr($clean, 2, 8) . '-' . substr($clean, 10, 1);
    }
}
