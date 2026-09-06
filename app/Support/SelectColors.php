<?php

namespace App\Support;

class SelectColors
{
    /**
     * Baserow select option colors (from core/assets/scss/colors.scss).
     *
     * @return array<string, array{bg: string, text: string}>
     */
    public static function all(): array
    {
        return [
            'light-blue' => ['bg' => '#f0f4fc', 'text' => '#083663'],
            'light-green' => ['bg' => '#ecfcf1', 'text' => '#075521'],
            'light-cyan' => ['bg' => '#cff5fa', 'text' => '#07525c'],
            'light-orange' => ['bg' => '#fffbf0', 'text' => '#66501b'],
            'light-yellow' => ['bg' => '#fffbf0', 'text' => '#66501b'],
            'light-red' => ['bg' => '#fff2f0', 'text' => '#66241b'],
            'light-brown' => ['bg' => '#f5e6dc', 'text' => '#5a3a22'],
            'light-purple' => ['bg' => '#f9f1fd', 'text' => '#46205e'],
            'light-pink' => ['bg' => '#f7e1f2', 'text' => '#6b215c'],
            'light-gray' => ['bg' => '#f5f5f5', 'text' => '#202128'],
            'blue' => ['bg' => '#dae4fd', 'text' => '#083663'],
            'green' => ['bg' => '#d0f6dc', 'text' => '#075521'],
            'cyan' => ['bg' => '#a0ebf5', 'text' => '#07525c'],
            'orange' => ['bg' => '#fff4da', 'text' => '#66501b'],
            'yellow' => ['bg' => '#ffe9b4', 'text' => '#66501b'],
            'red' => ['bg' => '#ffdeda', 'text' => '#66241b'],
            'brown' => ['bg' => '#f5ceb0', 'text' => '#5a3a22'],
            'purple' => ['bg' => '#efdcfb', 'text' => '#46205e'],
            'pink' => ['bg' => '#f7b2e7', 'text' => '#6b215c'],
            'gray' => ['bg' => '#d7d8d9', 'text' => '#202128'],
            'dark-blue' => ['bg' => '#acc8f8', 'text' => '#083663'],
            'dark-green' => ['bg' => '#a0eeba', 'text' => '#075521'],
            'dark-cyan' => ['bg' => '#70e0ef', 'text' => '#07525c'],
            'dark-orange' => ['bg' => '#ffe9b4', 'text' => '#66501b'],
            'dark-yellow' => ['bg' => '#ffdd8f', 'text' => '#66501b'],
            'dark-red' => ['bg' => '#ffbdb4', 'text' => '#66241b'],
            'dark-brown' => ['bg' => '#f5c098', 'text' => '#5a3a22'],
            'dark-purple' => ['bg' => '#dfb9f7', 'text' => '#46205e'],
            'dark-pink' => ['bg' => '#f285d9', 'text' => '#6b215c'],
            'dark-gray' => ['bg' => '#cdcecd', 'text' => '#202128'],
        ];
    }

    public static function hex(string $name): array
    {
        return self::all()[$name] ?? self::all()['light-gray'];
    }

    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function cycle(): array
    {
        return ['blue', 'green', 'orange', 'red', 'purple', 'cyan', 'yellow', 'pink', 'brown', 'gray'];
    }
}
