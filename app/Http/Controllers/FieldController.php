<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Field;
use App\Models\Table;
use App\Support\FieldTypes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FieldController extends Controller
{
    use AuthorizesWorkspace;

    public function store(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(FieldTypes::all()))],
            'options' => ['nullable', 'array'],
        ]);

        $field = $table->fields()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'primary' => false,
            'options' => $data['options'] ?? FieldTypes::defaultOptions($data['type']),
            'width' => 200,
            'order' => ((int) $table->fields()->max('order')) + 1,
        ]);

        return response()->json($this->serialize($field));
    }

    public function update(Request $request, Field $field)
    {
        $this->fieldForUser($field);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', Rule::in(array_keys(FieldTypes::all()))],
            'options' => ['nullable', 'array'],
            'width' => ['sometimes', 'integer', 'min:80', 'max:800'],
            'order' => ['sometimes', 'integer', 'min:1'],
            'primary' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['primary'])) {
            $field->table->fields()->update(['primary' => false]);
            $data['primary'] = true;
        }

        if (isset($data['type']) && $data['type'] !== $field->type && empty($data['options'])) {
            $data['options'] = FieldTypes::defaultOptions($data['type']);
        }

        $field->update($data);

        return response()->json($this->serialize($field->fresh()));
    }

    public function destroy(Field $field)
    {
        $this->fieldForUser($field);
        if ($field->primary) {
            return response()->json(['message' => 'The primary field cannot be deleted.'], 422);
        }
        $field->delete();

        return response()->json(['ok' => true]);
    }

    public function storeOption(Request $request, Field $field)
    {
        $this->fieldForUser($field);
        abort_unless($field->isSelect(), 422);
        $data = $request->validate([
            'value' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json($field->addOption($data['value'], $data['color'] ?? null));
    }

    private function serialize(Field $field): array
    {
        return [
            'id' => $field->id,
            'name' => $field->name,
            'type' => $field->type,
            'primary' => $field->primary,
            'options' => $field->options,
            'width' => $field->width,
            'order' => $field->order,
            'label' => $field->label(),
            'icon' => $field->icon(),
            'read_only' => $field->isReadOnly(),
        ];
    }
}
