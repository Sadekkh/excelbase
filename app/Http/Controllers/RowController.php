<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Field;
use App\Models\Row;
use App\Models\Table;
use Illuminate\Http\Request;

class RowController extends Controller
{
    use AuthorizesWorkspace;

    public function index(Table $table)
    {
        $this->tableForUser($table);
        $fields = $table->fields;

        return response()->json([
            'rows' => $table->rows()->orderBy('order')->get()->map(fn (Row $row) => $row->toApi($fields)),
        ]);
    }

    public function store(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $fields = $table->fields;
        $values = $request->input('values', []);
        $after = $request->input('after_id');

        $order = (float) ($table->rows()->max('order') ?? 0) + 1;
        if ($after) {
            $afterRow = $table->rows()->whereKey($after)->first();
            $next = $table->rows()->where('order', '>', $afterRow?->order ?? 0)->orderBy('order')->first();
            $order = $next
                ? (($afterRow->order + $next->order) / 2)
                : (($afterRow->order ?? 0) + 1);
        }

        $row = $table->rows()->create(['data' => [], 'order' => $order]);
        foreach ($fields as $field) {
            if (array_key_exists((string) $field->id, $values) || array_key_exists($field->id, $values)) {
                $row->setValue($field, $values[(string) $field->id] ?? $values[$field->id] ?? null);
            }
        }
        $row->save();
        $fresh = $row->fresh();
        $fresh->setRelation('table', $table->loadMissing('fields'));

        return response()->json($fresh->toApi($fields), 201);
    }

    public function update(Request $request, Row $row)
    {
        $this->rowForUser($row);
        $fields = $row->table->fields()->get()->keyBy('id');
        $values = $request->input('values', []);

        foreach ($values as $fieldId => $value) {
            $field = $fields->get((int) $fieldId);
            if ($field) {
                $row->setValue($field, $value);
            }
        }

        if ($request->has('order')) {
            $row->order = (float) $request->input('order');
        }

        $row->save();
        $fresh = $row->fresh();
        $table = $row->table->loadMissing('fields');
        $fresh->setRelation('table', $table);

        return response()->json($fresh->toApi($table->fields));
    }

    public function destroy(Row $row)
    {
        $this->rowForUser($row);
        $row->delete();

        return response()->json(['ok' => true]);
    }

    public function destroyMany(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        $table->rows()->whereIn('id', $ids)->delete();

        return response()->json(['ok' => true, 'deleted' => count($ids)]);
    }

    public function duplicate(Row $row)
    {
        $this->rowForUser($row);
        $next = $row->table->rows()->where('order', '>', $row->order)->orderBy('order')->first();
        $order = $next ? (($row->order + $next->order) / 2) : ($row->order + 1);

        $copy = $row->table->rows()->create([
            'data' => $row->data,
            'order' => $order,
        ]);

        $copy->setRelation('table', $row->table->loadMissing('fields'));

        return response()->json($copy->toApi($row->table->fields), 201);
    }

    public function batchUpdate(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $items = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id' => ['required', 'integer'],
            'rows.*.order' => ['sometimes', 'numeric'],
            'rows.*.values' => ['sometimes', 'array'],
        ])['rows'];

        $fields = $table->fields()->get()->keyBy('id');
        $out = [];
        foreach ($items as $item) {
            $row = $table->rows()->whereKey($item['id'])->first();
            if (! $row) {
                continue;
            }
            if (isset($item['order'])) {
                $row->order = (float) $item['order'];
            }
            foreach ($item['values'] ?? [] as $fieldId => $value) {
                $field = $fields->get((int) $fieldId);
                if ($field instanceof Field) {
                    $row->setValue($field, $value);
                }
            }
            $row->save();
            $out[] = $row->fresh()->toApi($fields);
        }

        return response()->json(['rows' => $out]);
    }
}
