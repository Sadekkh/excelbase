<?php

namespace App\Support;

class FieldTypes
{
    /**
     * @return array<string, array{label: string, icon: string, group: string}>
     */
    public static function all(): array
    {
        return [
            'text' => ['label' => 'Single line text', 'icon' => 'text', 'group' => 'text'],
            'long_text' => ['label' => 'Long text', 'icon' => 'long-text', 'group' => 'text'],
            'number' => ['label' => 'Number', 'icon' => 'number', 'group' => 'number'],
            'rating' => ['label' => 'Rating', 'icon' => 'rating', 'group' => 'number'],
            'boolean' => ['label' => 'Boolean', 'icon' => 'boolean', 'group' => 'choice'],
            'date' => ['label' => 'Date', 'icon' => 'date', 'group' => 'date'],
            'single_select' => ['label' => 'Single select', 'icon' => 'single-select', 'group' => 'choice'],
            'multiple_select' => ['label' => 'Multiple select', 'icon' => 'multiple-select', 'group' => 'choice'],
            'url' => ['label' => 'URL', 'icon' => 'url', 'group' => 'text'],
            'email' => ['label' => 'Email', 'icon' => 'email', 'group' => 'text'],
            'phone' => ['label' => 'Phone number', 'icon' => 'phone', 'group' => 'text'],
            'created_on' => ['label' => 'Created on', 'icon' => 'created', 'group' => 'system'],
            'last_modified' => ['label' => 'Last modified', 'icon' => 'modified', 'group' => 'system'],
        ];
    }

    public static function labels(): array
    {
        return array_map(fn ($t) => $t['label'], self::all());
    }

    public static function isReadOnly(string $type): bool
    {
        return in_array($type, ['created_on', 'last_modified'], true);
    }

    public static function defaultValue(string $type): mixed
    {
        return match ($type) {
            'boolean' => false,
            'number', 'rating' => null,
            'multiple_select' => [],
            default => null,
        };
    }

    public static function defaultOptions(string $type): array
    {
        return match ($type) {
            'number' => ['decimal_places' => 0, 'prefix' => '', 'suffix' => ''],
            'date' => ['include_time' => false, 'format' => 'ISO'],
            'rating' => ['max' => 5, 'style' => 'star'],
            'single_select', 'multiple_select' => ['options' => []],
            default => [],
        };
    }

    public static function operators(string $type): array
    {
        $text = [
            'equal' => 'is',
            'not_equal' => 'is not',
            'contains' => 'contains',
            'not_contains' => 'does not contain',
            'empty' => 'is empty',
            'not_empty' => 'is not empty',
        ];

        return match ($type) {
            'number', 'rating' => [
                'equal' => 'is',
                'not_equal' => 'is not',
                'higher_than' => 'is higher than',
                'lower_than' => 'is lower than',
                'empty' => 'is empty',
                'not_empty' => 'is not empty',
            ],
            'boolean' => [
                'equal' => 'is',
                'empty' => 'is empty',
                'not_empty' => 'is not empty',
            ],
            'date' => [
                'equal' => 'is',
                'not_equal' => 'is not',
                'higher_than' => 'is after',
                'lower_than' => 'is before',
                'empty' => 'is empty',
                'not_empty' => 'is not empty',
            ],
            'single_select' => [
                'equal' => 'is',
                'not_equal' => 'is not',
                'empty' => 'is empty',
                'not_empty' => 'is not empty',
            ],
            'multiple_select' => [
                'contains' => 'contains',
                'not_contains' => 'does not contain',
                'empty' => 'is empty',
                'not_empty' => 'is not empty',
            ],
            default => $text,
        };
    }

    public static function summaries(string $type): array
    {
        $common = [
            'count' => 'Count',
            'empty' => 'Empty',
            'filled' => 'Filled',
            'unique' => 'Unique',
            'percent_empty' => 'Percent empty',
            'percent_filled' => 'Percent filled',
        ];

        if (in_array($type, ['number', 'rating'], true)) {
            return $common + [
                'sum' => 'Sum',
                'average' => 'Average',
                'min' => 'Min',
                'max' => 'Max',
            ];
        }

        return $common;
    }

    public static function emptyValue(mixed $value, string $type): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if ($type === 'multiple_select' && is_array($value) && $value === []) {
            return true;
        }

        if ($type === 'boolean') {
            return $value !== true && $value !== 1 && $value !== '1' && $value !== 'true';
        }

        return false;
    }
}
