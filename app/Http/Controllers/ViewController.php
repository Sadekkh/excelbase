<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Table;
use App\Models\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ViewController extends Controller
{
    use AuthorizesWorkspace;

    public function store(Request $request, Table $table)
    {
        $this->tableForUser($table);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(['grid', 'gallery', 'kanban', 'form', 'calendar', 'timeline', 'survey', 'graph'])],
            'kanban_field_id' => ['nullable', 'integer'],
            'is_personal' => ['sometimes', 'boolean'],
        ]);

        $type = $data['type'] === 'survey' ? 'form' : $data['type'];

        $view = $table->views()->create([
            'name' => ($data['name'] ?? '') ?: View::defaultName($data['type']),
            'is_personal' => (bool) ($data['is_personal'] ?? false),
            'user_id' => $request->user()->id,
            'type' => $type,
            'filters' => [],
            'sorts' => [],
            'groups' => [],
            'hidden_fields' => [],
            'field_options' => [],
            'form_config' => in_array($data['type'], ['form', 'survey'], true) ? [
                'title' => $table->name,
                'description' => '',
                'submit_text' => 'Submit',
                'success_message' => 'Thank you for submitting!',
                'cover' => '#5190ef',
                'mode' => $data['type'] === 'survey' ? 'survey' : 'form',
                'hide_branding' => false,
            ] : [],
            'row_height' => 'small',
            'kanban_field_id' => $data['kanban_field_id'] ?? $table->fields()->where('type', 'single_select')->value('id'),
            'order' => ((int) $table->views()->max('order')) + 1,
        ]);

        return $request->expectsJson()
            ? response()->json($view)
            : redirect()->route('tables.show', ['table' => $table, 'view' => $view->id]);
    }

    public function update(Request $request, View $view)
    {
        $this->viewForUser($view);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'filters' => ['sometimes', 'array'],
            'sorts' => ['sometimes', 'array'],
            'groups' => ['sometimes', 'array'],
            'hidden_fields' => ['sometimes', 'array'],
            'field_options' => ['sometimes', 'array'],
            'form_config' => ['sometimes', 'array'],
            'row_height' => ['sometimes', Rule::in(['small', 'medium', 'large', 'extra_large'])],
            'kanban_field_id' => ['nullable', 'integer'],
            'public' => ['sometimes', 'boolean'],
            'is_personal' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('public', $data) && $data['public']) {
            $view->enablePublic();
            unset($data['public']);
        }

        if (! empty($data['is_personal'])) {
            $data['user_id'] = $view->user_id ?: $request->user()->id;
        }

        $view->update($data);

        return response()->json($view->fresh());
    }

    public function destroy(Request $request, View $view)
    {
        $this->viewForUser($view);
        $table = $view->table;
        if ($table->views()->count() <= 1) {
            return response()->json(['message' => 'A table must have at least one view.'], 422);
        }
        $view->delete();
        $next = $table->views()->first();

        return $request->expectsJson()
            ? response()->json(['redirect' => route('tables.show', ['table' => $table, 'view' => $next->id])])
            : redirect()->route('tables.show', ['table' => $table, 'view' => $next->id]);
    }

    public function duplicate(Request $request, View $view)
    {
        $this->viewForUser($view);
        $copy = $view->replicate();
        $copy->name = $view->name.' 2';
        $copy->public = false;
        $copy->public_slug = null;
        $copy->order = ((int) $view->table->views()->max('order')) + 1;
        $copy->save();

        return $request->expectsJson()
            ? response()->json($copy)
            : redirect()->route('tables.show', ['table' => $view->table, 'view' => $copy->id]);
    }
}
