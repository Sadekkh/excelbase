<?php

namespace App\Models;

use App\Support\FieldTypes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['database_id', 'name', 'order'])]
class Table extends Model
{
    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class)->orderBy('order');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(Row::class)->orderBy('order');
    }

    public function views(): HasMany
    {
        return $this->hasMany(View::class)->orderBy('order');
    }

    public function primaryField(): ?Field
    {
        return $this->fields()->where('primary', true)->first();
    }

    public static function createWithDefaults(Database $database, string $name): self
    {
        $order = ((int) $database->tables()->max('order')) + 1;
        $table = self::create([
            'database_id' => $database->id,
            'name' => $name,
            'order' => $order,
        ]);

        Field::create([
            'table_id' => $table->id,
            'name' => 'Name',
            'type' => 'text',
            'primary' => true,
            'options' => FieldTypes::defaultOptions('text'),
            'width' => 220,
            'order' => 1,
        ]);

        View::create([
            'table_id' => $table->id,
            'name' => 'Grid',
            'type' => 'grid',
            'filters' => [],
            'sorts' => [],
            'groups' => [],
            'hidden_fields' => [],
            'field_options' => [],
            'row_height' => 'small',
            'order' => 1,
        ]);

        return $table;
    }

    public function duplicate(): self
    {
        $copy = self::createWithDefaults($this->database, $this->name.' 2');
        $copy->fields()->delete();
        $copy->views()->delete();

        $fieldMap = [];
        foreach ($this->fields as $field) {
            $newField = $field->replicate();
            $newField->table_id = $copy->id;
            $newField->save();
            $fieldMap[$field->id] = $newField->id;
        }

        foreach ($this->rows as $row) {
            $data = [];
            foreach ($row->data ?? [] as $fieldId => $value) {
                if (isset($fieldMap[$fieldId])) {
                    $data[$fieldMap[$fieldId]] = $value;
                }
            }
            Row::create([
                'table_id' => $copy->id,
                'data' => $data,
                'order' => $row->order,
            ]);
        }

        foreach ($this->views as $view) {
            $newView = $view->replicate();
            $newView->table_id = $copy->id;
            $newView->public_slug = $view->public ? Str::random(12) : null;
            if ($view->kanban_field_id && isset($fieldMap[$view->kanban_field_id])) {
                $newView->kanban_field_id = $fieldMap[$view->kanban_field_id];
            }
            $newView->filters = self::remapIds($view->filters, $fieldMap);
            $newView->sorts = self::remapIds($view->sorts, $fieldMap);
            $newView->groups = self::remapIds($view->groups, $fieldMap);
            $newView->hidden_fields = array_values(array_filter(array_map(
                fn ($id) => $fieldMap[$id] ?? null,
                $view->hidden_fields ?? []
            )));
            $newView->save();
        }

        return $copy->fresh(['fields', 'views']);
    }

    private static function remapIds(?array $items, array $fieldMap): array
    {
        $out = [];
        foreach ($items ?? [] as $item) {
            if (! isset($item['field_id'])) {
                $out[] = $item;

                continue;
            }
            if (! isset($fieldMap[$item['field_id']])) {
                continue;
            }
            $item['field_id'] = $fieldMap[$item['field_id']];
            $out[] = $item;
        }

        return $out;
    }
}
