<?php

namespace App\Support;

use App\Models\Field;
use App\Models\Row;
use App\Models\View;
use Illuminate\Support\Collection;

class RowQuery
{
    /**
     * @param  Collection<int, Field>  $fields
     * @return Collection<int, Row>
     */
    public static function apply(Collection $rows, View $view, Collection $fields, ?string $search = null): Collection
    {
        $fieldMap = $fields->keyBy('id');

        if ($search) {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function (Row $row) use ($fieldMap, $needle) {
                foreach ($fieldMap as $field) {
                    $raw = self::display($row, $field);
                    if ($raw !== '' && str_contains(mb_strtolower($raw), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        foreach ($view->filters ?? [] as $filter) {
            $field = $fieldMap->get((int) ($filter['field_id'] ?? 0));
            if (! $field) {
                continue;
            }
            $operator = $filter['operator'] ?? 'equal';
            $expected = $filter['value'] ?? null;
            $rows = $rows->filter(fn (Row $row) => self::matches($row->value($field), $field, $operator, $expected));
        }

        $sorts = $view->sorts ?? [];
        if ($sorts !== []) {
            $rows = $rows->sort(function (Row $a, Row $b) use ($sorts, $fieldMap) {
                foreach ($sorts as $sort) {
                    $field = $fieldMap->get((int) ($sort['field_id'] ?? 0));
                    if (! $field) {
                        continue;
                    }
                    $av = $a->value($field);
                    $bv = $b->value($field);
                    $cmp = self::compare($av, $bv, $field->type);
                    if ($cmp !== 0) {
                        return ($sort['direction'] ?? 'asc') === 'desc' ? -$cmp : $cmp;
                    }
                }

                return $a->order <=> $b->order;
            })->values();
        } else {
            $rows = $rows->sortBy('order')->values();
        }

        return $rows;
    }

    public static function matches(mixed $value, Field $field, string $operator, mixed $expected): bool
    {
        $empty = FieldTypes::emptyValue($value, $field->type);

        return match ($operator) {
            'empty' => $empty,
            'not_empty' => ! $empty,
            'contains' => ! $empty && str_contains(mb_strtolower(self::scalar($value)), mb_strtolower((string) $expected)),
            'not_contains' => $empty || ! str_contains(mb_strtolower(self::scalar($value)), mb_strtolower((string) $expected)),
            'higher_than' => ! $empty && self::numeric($value) > self::numeric($expected),
            'lower_than' => ! $empty && self::numeric($value) < self::numeric($expected),
            'not_equal' => self::scalar($value) !== self::scalar($expected),
            default => self::scalar($value) === self::scalar($expected),
        };
    }

    public static function compare(mixed $a, mixed $b, string $type): int
    {
        if (in_array($type, ['number', 'rating'], true)) {
            return self::numeric($a) <=> self::numeric($b);
        }

        return strcasecmp(self::scalar($a), self::scalar($b));
    }

    public static function display(Row $row, Field $field): string
    {
        $value = $row->value($field);

        if ($field->type === 'created_on') {
            return optional($row->created_at)->toDateTimeString() ?? '';
        }
        if ($field->type === 'last_modified') {
            return optional($row->updated_at)->toDateTimeString() ?? '';
        }
        if (is_array($value)) {
            if ($field->type === 'file') {
                return collect($value)->pluck('name')->filter()->implode(', ');
            }
            if ($field->isSelect()) {
                $labels = [];
                foreach ((array) $value as $id) {
                    $opt = $field->option((string) $id);
                    if ($opt) {
                        $labels[] = $opt['value'];
                    }
                }

                return implode(', ', $labels);
            }

            return implode(', ', array_map('strval', $value));
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value === null ? '' : (string) $value;
    }

    public static function scalar(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_map('strval', $value));
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value === null ? '' : (string) $value;
    }

    public static function numeric(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * @param  Collection<int, Row>  $rows
     */
    public static function summarize(Collection $rows, Field $field, string $type): ?string
    {
        $count = $rows->count();
        $values = $rows->map(fn (Row $row) => $row->value($field));
        $empty = $values->filter(fn ($v) => FieldTypes::emptyValue($v, $field->type))->count();
        $filled = $count - $empty;

        return match ($type) {
            'count' => (string) $count,
            'empty' => (string) $empty,
            'filled' => (string) $filled,
            'unique' => (string) $values->map(fn ($v) => self::scalar($v))->unique()->count(),
            'percent_empty' => $count ? round($empty / $count * 100).'%' : '—',
            'percent_filled' => $count ? round($filled / $count * 100).'%' : '—',
            'sum' => (string) $values->reduce(fn ($c, $v) => $c + self::numeric($v), 0),
            'average' => $filled ? (string) round($values->reduce(fn ($c, $v) => $c + self::numeric($v), 0) / $filled, 2) : '—',
            'min' => $filled ? (string) $values->map(fn ($v) => self::numeric($v))->min() : '—',
            'max' => $filled ? (string) $values->map(fn ($v) => self::numeric($v))->max() : '—',
            default => null,
        };
    }
}
