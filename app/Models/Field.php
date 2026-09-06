<?php

namespace App\Models;

use App\Support\FieldTypes;
use App\Support\SelectColors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['table_id', 'name', 'type', 'primary', 'options', 'width', 'order'])]
class Field extends Model
{
    protected function casts(): array
    {
        return [
            'primary' => 'boolean',
            'options' => 'array',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function isSelect(): bool
    {
        return in_array($this->type, ['single_select', 'multiple_select'], true);
    }

    public function isReadOnly(): bool
    {
        return FieldTypes::isReadOnly($this->type);
    }

    public function option(string $id): ?array
    {
        foreach ($this->options['options'] ?? [] as $option) {
            if ((string) ($option['id'] ?? '') === $id) {
                return $option;
            }
        }

        return null;
    }

    public function addOption(string $value, ?string $color = null): array
    {
        $options = $this->options ?? [];
        $list = $options['options'] ?? [];
        $colors = SelectColors::cycle();
        $option = [
            'id' => (string) Str::ulid(),
            'value' => $value,
            'color' => $color ?? $colors[count($list) % count($colors)],
        ];
        $list[] = $option;
        $options['options'] = $list;
        $this->options = $options;
        $this->save();

        return $option;
    }

    public function label(): string
    {
        return FieldTypes::all()[$this->type]['label'] ?? $this->type;
    }

    public function icon(): string
    {
        return FieldTypes::all()[$this->type]['icon'] ?? 'text';
    }
}
