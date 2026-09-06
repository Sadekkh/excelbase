<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['table_id', 'data', 'order'])]
class Row extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'order' => 'float',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function value(Field $field): mixed
    {
        if ($field->type === 'created_on') {
            return $this->created_at?->toIso8601String();
        }
        if ($field->type === 'last_modified') {
            return $this->updated_at?->toIso8601String();
        }

        return $this->data[(string) $field->id] ?? $this->data[$field->id] ?? null;
    }

    public function setValue(Field $field, mixed $value): void
    {
        if ($field->isReadOnly()) {
            return;
        }

        $data = $this->data ?? [];
        $data[(string) $field->id] = self::normalize($field, $value);
        $this->data = $data;
    }

    public static function normalize(Field $field, mixed $value): mixed
    {
        return match ($field->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number', 'rating' => $value === '' || $value === null ? null : (is_numeric($value) ? 0 + $value : null),
            'multiple_select' => array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== '')),
            'single_select' => $value === '' ? null : $value,
            default => $value === '' ? null : $value,
        };
    }

    public function toApi(iterable $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[(string) $field->id] = $this->value($field);
        }

        return [
            'id' => $this->id,
            'order' => $this->order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'values' => $values,
        ];
    }
}
