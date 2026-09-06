<?php

namespace App\Support\Erp;

class Money
{
    public static function fromDecimal(float|string|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);

        return (int) round(((float) $normalized) * 100);
    }

    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return $sign.number_format($abs / 100, 2, ',', ' ').' €';
    }

    public static function vatLabel(int $bps): string
    {
        return rtrim(rtrim(number_format($bps / 100, 2, ',', ''), '0'), ',').' %';
    }
}
