<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'table_id', 'name', 'type', 'filters', 'sorts', 'groups', 'hidden_fields',
    'field_options', 'form_config', 'row_height', 'public', 'public_slug',
    'kanban_field_id', 'order', 'is_personal', 'user_id',
])]
class View extends Model
{
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'sorts' => 'array',
            'groups' => 'array',
            'hidden_fields' => 'array',
            'field_options' => 'array',
            'form_config' => 'array',
            'public' => 'boolean',
            'is_personal' => 'boolean',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function kanbanField(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'kanban_field_id');
    }

    public function isFieldHidden(int $fieldId): bool
    {
        return in_array($fieldId, $this->hidden_fields ?? [], false)
            || in_array((string) $fieldId, $this->hidden_fields ?? [], true);
    }

    public function enablePublic(): string
    {
        $this->public = true;
        $this->public_slug = $this->public_slug ?: Str::lower(Str::random(10));
        $this->save();

        return $this->public_slug;
    }

    public static function defaultName(string $type): string
    {
        return match ($type) {
            'gallery' => 'Gallery',
            'kanban' => 'Kanban',
            'form' => 'Form',
            'calendar' => 'Calendar',
            'timeline' => 'Timeline',
            'survey' => 'Survey',
            'graph' => 'Graph',
            default => 'Grid',
        };
    }
}
