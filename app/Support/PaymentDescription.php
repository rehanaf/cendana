<?php

namespace App\Support;

class PaymentDescription
{
    public static function make(string $prefix, string $type, ?string $notes, ?string $personName): string
    {
        if ($type === 'langganan') {
            return trim($prefix.' '.($personName ?: ''));
        }

        if (filled(trim((string) $notes))) {
            return trim($prefix.' '.trim($notes));
        }

        return trim($prefix.' '.($personName ?: ''));
    }
}
