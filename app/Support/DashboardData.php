<?php

namespace App\Support;

use App\Models\DashboardWidget;
use App\Models\Field;
use App\Models\Table;

class DashboardData
{
    /**
     * @return array{value: string, detail: string, bars?: list<array{label: string, value: int, color: string}>, rows?: list<array{id: int, label: string, href: string}>}
     */
    public static function resolve(DashboardWidget $widget): array
    {
        $config = $widget->config ?? [];
        $table = Table::query()->with(['fields', 'rows', 'database'])->find((int) ($config['table_id'] ?? 0));
        if (! $table) {
            return ['value' => '—', 'detail' => 'Table missing'];
        }
        $table->rows->each(fn ($row) => $row->setRelation('table', $table));

        return match ($widget->type) {
            'stat' => self::stat($table, $config),
            'chart' => self::chart($table, $config),
            'list' => self::list($table),
            default => ['value' => '—', 'detail' => 'Unknown widget'],
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{value: string, detail: string}
     */
    private static function stat(Table $table, array $config): array
    {
        $field = $table->fields->firstWhere('id', (int) ($config['field_id'] ?? 0));
        $metric = $config['metric'] ?? 'count';
        if ($metric === 'count' || ! $field) {
            return ['value' => (string) $table->rows->count(), 'detail' => $table->name];
        }
        $sum = $table->rows->sum(fn ($row) => (float) $row->rawValue($field));

        return ['value' => self::format($sum, $field), 'detail' => ($metric === 'sum' ? 'Sum of ' : '').$field->name];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{value: string, detail: string, bars: list<array{label: string, value: int, color: string}>}
     */
    private static function chart(Table $table, array $config): array
    {
        $field = $table->fields->firstWhere('id', (int) ($config['field_id'] ?? 0))
            ?? $table->fields->firstWhere('type', 'single_select');
        if (! $field || $field->type !== 'single_select') {
            return ['value' => (string) $table->rows->count(), 'detail' => $table->name, 'bars' => []];
        }
        $colors = SelectColors::all();
        $bars = [];
        foreach ($field->options['options'] ?? [] as $opt) {
            $count = $table->rows->filter(fn ($row) => (string) $row->rawValue($field) === (string) $opt['id'])->count();
            $c = $colors[$opt['color'] ?? 'light-gray'] ?? $colors['light-gray'];
            $bars[] = [
                'label' => $opt['value'],
                'value' => $count,
                'color' => $c['text'] ?? '#5190ef',
            ];
        }

        return [
            'value' => (string) $table->rows->count(),
            'detail' => $table->name.' by '.$field->name,
            'bars' => $bars,
        ];
    }

    /**
     * @return array{value: string, detail: string, rows: list<array{id: int, label: string, href: string}>}
     */
    private static function list(Table $table): array
    {
        $primary = $table->fields->firstWhere('primary');
        $rows = $table->rows->sortByDesc('updated_at')->take(6)->values()->map(function ($row) use ($primary, $table) {
            return [
                'id' => $row->id,
                'label' => $primary ? (RowQuery::display($row, $primary) ?: 'Untitled') : 'Row '.$row->id,
                'href' => '#',
                'table_id' => $table->id,
            ];
        })->all();

        return [
            'value' => (string) $table->rows->count(),
            'detail' => 'Latest in '.$table->name,
            'rows' => $rows,
        ];
    }

    private static function format(float $n, Field $field): string
    {
        $prefix = $field->options['prefix'] ?? '';
        $suffix = $field->options['suffix'] ?? '';

        return $prefix.(fmod($n, 1.0) === 0.0 ? (string) (int) $n : (string) round($n, 2)).$suffix;
    }
}
