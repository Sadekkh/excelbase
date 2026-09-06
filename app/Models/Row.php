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

    public function rawValue(Field $field): mixed
    {
        if ($field->type === 'created_on') {
            return $this->created_at?->toIso8601String();
        }
        if ($field->type === 'last_modified') {
            return $this->updated_at?->toIso8601String();
        }

        return $this->data[(string) $field->id] ?? $this->data[$field->id] ?? null;
    }

    public function value(Field $field): mixed
    {
        $parent = $this->parentTable();
        $fields = $parent?->relationLoaded('fields')
            ? $parent->fields
            : ($parent?->fields ?? collect());

        return match ($field->type) {
            'formula' => \App\Support\FormulaEngine::evaluate($this, $field, $fields),
            'ai' => \App\Support\FormulaEngine::ai($this, $field, $fields),
            'lookup' => $this->lookupValue($field),
            'count' => $this->countValue($field),
            default => $this->rawValue($field),
        };
    }

    public function parentTable(): ?Table
    {
        if ($this->relationLoaded('table')) {
            $related = $this->getRelation('table');

            return $related instanceof Table ? $related : null;
        }

        return $this->table()->with('fields')->first();
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RowComment::class);
    }

    private function countValue(Field $field): int
    {
        $link = $this->linkField($field);
        if (! $link) {
            return 0;
        }
        $raw = $this->rawValue($link);

        return is_array($raw) ? count($raw) : 0;
    }

    private function linkField(Field $field): ?Field
    {
        $id = (int) ($field->options['link_field_id'] ?? 0);

        return $this->parentTable()?->fields->firstWhere('id', $id);
    }

    private function lookupValue(Field $field): string
    {
        $link = $this->linkField($field);
        if (! $link) {
            return '';
        }
        $ids = array_map('intval', (array) $this->rawValue($link));
        if ($ids === []) {
            return '';
        }
        $targetTableId = (int) ($link->options['linked_table_id'] ?? 0);
        $targetFieldId = (int) ($field->options['lookup_field_id'] ?? 0);
        $related = \App\Models\Row::query()->where('table_id', $targetTableId)->whereIn('id', $ids)->get();
        $target = \App\Models\Field::query()->find($targetFieldId);
        if (! $target) {
            return implode(', ', $ids);
        }

        return $related->map(fn (Row $row) => \App\Support\RowQuery::display($row, $target))->filter()->implode(', ');
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
            'multiple_select', 'link_row' => array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== '')),
            'file' => array_values(array_filter((array) $value, fn ($v) => is_array($v) && ! empty($v['url']))),
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
