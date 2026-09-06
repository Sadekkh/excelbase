<?php

namespace App\Http\Controllers;

use App\Models\View;
use Illuminate\Http\Request;

class PublicFormController extends Controller
{
    public function show(string $slug)
    {
        $view = View::query()
            ->where('public_slug', $slug)
            ->where('public', true)
            ->where('type', 'form')
            ->with(['table.fields'])
            ->firstOrFail();

        $config = $view->form_config ?? [];
        $hidden = $view->hidden_fields ?? [];
        $fields = $view->table->fields->filter(function ($field) use ($hidden) {
            return ! $field->isReadOnly() && ! in_array($field->id, $hidden, false);
        })->values();

        return view('form.public', [
            'view' => $view,
            'table' => $view->table,
            'fields' => $fields,
            'config' => $config,
        ]);
    }

    public function store(Request $request, string $slug)
    {
        $view = View::query()
            ->where('public_slug', $slug)
            ->where('public', true)
            ->where('type', 'form')
            ->with(['table.fields'])
            ->firstOrFail();

        $hidden = $view->hidden_fields ?? [];
        $fields = $view->table->fields->filter(fn ($f) => ! $f->isReadOnly() && ! in_array($f->id, $hidden, false));
        $values = $request->input('values', []);
        $order = (float) ($view->table->rows()->max('order') ?? 0) + 1;
        $row = $view->table->rows()->create(['data' => [], 'order' => $order]);

        foreach ($fields as $field) {
            if (array_key_exists((string) $field->id, $values) || array_key_exists($field->id, $values)) {
                $row->setValue($field, $values[(string) $field->id] ?? $values[$field->id] ?? null);
            }
        }
        $row->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $view->form_config['success_message'] ?? 'Thank you for submitting!',
            ]);
        }

        return back()->with('submitted', true);
    }
}
