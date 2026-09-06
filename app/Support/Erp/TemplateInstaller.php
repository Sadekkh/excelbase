<?php

namespace App\Support\Erp;

use App\Models\Automation;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Database;
use App\Models\Field;
use App\Models\InvoiceSetting;
use App\Models\Row;
use App\Models\Table;
use App\Models\View;
use App\Models\Workspace;
use App\Models\WorkspaceTemplate;
use App\Support\FieldTypes;
use Illuminate\Support\Str;

class TemplateInstaller
{
    /**
     * @return array<string, mixed>
     */
    public static function catalog(?Workspace $workspace = null): array
    {
        $installed = $workspace
            ? $workspace->templates()->get()->keyBy('slug')
            : collect();

        return collect(TemplateCatalog::all())->map(function ($pack) use ($installed) {
            $row = $installed->get($pack['slug']);

            return [
                'slug' => $pack['slug'],
                'name' => $pack['name'],
                'sector' => $pack['sector'],
                'summary' => $pack['summary'],
                'audience' => $pack['audience'],
                'tables' => count($pack['tables'] ?? []),
                'installed' => (bool) $row,
                'install_id' => $row?->id,
                'installed_at' => $row?->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    public static function install(Workspace $workspace, string $slug): WorkspaceTemplate
    {
        if ($workspace->templates()->where('slug', $slug)->exists()) {
            throw new \RuntimeException('This template is already installed. Remove it first, or adjust it in Build.');
        }
        $pack = TemplateCatalog::get($slug);
        $manifest = [
            'database_ids' => [],
            'dashboard_ids' => [],
            'automation_ids' => [],
            'tables' => [],
        ];

        $tables = [];
        $fields = [];
        $selects = [];
        $rowIds = [];

        $database = Database::create([
            'workspace_id' => $workspace->id,
            'name' => $pack['database'],
            'order' => ((int) $workspace->databases()->max('order')) + 1,
        ]);
        $manifest['database_ids'][] = $database->id;

        foreach ($pack['tables'] as $key => $def) {
            $table = Table::create([
                'database_id' => $database->id,
                'name' => $def['name'],
                'order' => $def['order'] ?? ((int) $database->tables()->max('order')) + 1,
            ]);
            $tables[$key] = $table;
            $manifest['tables'][$key] = $table->id;
            $order = 0;
            foreach ($def['fields'] as $fieldDef) {
                $order++;
                $type = $fieldDef[1];
                $options = $fieldDef[4] ?? FieldTypes::defaultOptions($type);
                if (in_array($type, ['single_select', 'multiple_select'], true)) {
                    $options['options'] = self::selectOptions($options['options'] ?? []);
                }
                if ($type === 'link_row') {
                    $options = FieldTypes::defaultOptions('link_row');
                }
                $field = Field::create([
                    'table_id' => $table->id,
                    'name' => $fieldDef[0],
                    'type' => $type,
                    'primary' => (bool) $fieldDef[2],
                    'width' => $fieldDef[3] ?? 180,
                    'options' => $options,
                    'order' => $order,
                ]);
                $fields[$key.'.'.$field->name] = $field;
                if ($field->isSelect()) {
                    foreach ($field->options['options'] ?? [] as $opt) {
                        $selects[$key.'.'.$field->name.'.'.$opt['value']] = $opt['id'];
                    }
                }
            }
            View::create([
                'table_id' => $table->id,
                'name' => 'Grille',
                'type' => 'grid',
                'filters' => [],
                'sorts' => [],
                'groups' => [],
                'hidden_fields' => [],
                'field_options' => [],
                'row_height' => 'small',
                'order' => 1,
            ]);
        }

        foreach ($pack['tables'] as $key => $def) {
            foreach ($def['fields'] as $fieldDef) {
                $field = $fields[$key.'.'.$fieldDef[0]];
                $extra = $fieldDef[4] ?? [];
                if ($field->type === 'link_row' && ! empty($extra['link'])) {
                    $target = $tables[$extra['link']] ?? null;
                    if ($target) {
                        $field->options = ['linked_table_id' => $target->id];
                        $field->save();
                    }
                }
                if ($field->type === 'lookup' && ! empty($extra['link_field'])) {
                    $link = $fields[$key.'.'.$extra['link_field']] ?? null;
                    $lookup = $fields[($extra['lookup_table'] ?? $extra['link'] ?? '').'.'.($extra['lookup_field'] ?? '')] ?? null;
                    if ($link && $lookup) {
                        $field->options = [
                            'link_field_id' => $link->id,
                            'lookup_field_id' => $lookup->id,
                        ];
                        $field->save();
                    }
                }
                if ($field->type === 'count' && ! empty($extra['link_field'])) {
                    $link = $fields[$key.'.'.$extra['link_field']] ?? null;
                    if ($link) {
                        $field->options = ['link_field_id' => $link->id];
                        $field->save();
                    }
                }
                if ($field->type === 'ai' && ! empty($extra['source'])) {
                    $source = $fields[$key.'.'.$extra['source']] ?? null;
                    $field->options = [
                        'mode' => $extra['mode'] ?? 'summarize',
                        'source_field_id' => $source?->id,
                    ];
                    $field->save();
                }
            }
        }

        foreach ($pack['tables'] as $key => $def) {
            $table = $tables[$key]->load('fields');
            $writable = $table->fields->filter(fn ($field) => ! FieldTypes::isReadOnly($field->type))->values();
            foreach ($def['rows'] ?? [] as $i => $raw) {
                $data = [];
                $offset = 0;
                foreach ($writable as $field) {
                    $value = $raw[$offset] ?? null;
                    $offset++;
                    $data[(string) $field->id] = self::cell($field, $value, $key, $selects, $rowIds);
                }
                $row = Row::create([
                    'table_id' => $table->id,
                    'order' => $i + 1,
                    'data' => $data,
                ]);
                $primary = $table->primaryField();
                if ($primary) {
                    $rowIds[$key.'.'.$row->rawValue($primary)] = $row->id;
                }
            }
            $table->unsetRelation('fields');
            $table->unsetRelation('rows');
        }

        foreach ($pack['views'] ?? [] as $viewDef) {
            $table = $tables[$viewDef['table']] ?? null;
            if (! $table) {
                continue;
            }
            $kanban = null;
            if (! empty($viewDef['kanban'])) {
                $kanban = $fields[$viewDef['table'].'.'.$viewDef['kanban']]->id ?? null;
            }
            View::create([
                'table_id' => $table->id,
                'name' => $viewDef['name'],
                'type' => $viewDef['type'],
                'filters' => [],
                'sorts' => [],
                'groups' => [],
                'hidden_fields' => [],
                'field_options' => $viewDef['field_options'] ?? [],
                'kanban_field_id' => $kanban,
                'row_height' => 'small',
                'order' => ((int) $table->views()->max('order')) + 1,
            ]);
        }

        if (! empty($pack['dashboard'])) {
            $board = Dashboard::create([
                'workspace_id' => $workspace->id,
                'name' => $pack['dashboard']['name'],
                'description' => $pack['dashboard']['description'] ?? null,
                'is_default' => true,
                'order' => ((int) $workspace->dashboards()->max('order')) + 1,
            ]);
            $manifest['dashboard_ids'][] = $board->id;
            foreach ($pack['dashboard']['widgets'] ?? [] as $i => $widget) {
                $config = ['metric' => $widget[3] ?? 'count'];
                if (($widget[0] ?? '') === 'invoice_stat') {
                    $config = ['metric' => $widget[2] ?? 'ttc'];
                } elseif (! empty($widget[2]) && isset($tables[$widget[2]])) {
                    $config['table_id'] = $tables[$widget[2]]->id;
                    if (! empty($widget[4]) && isset($fields[$widget[2].'.'.$widget[4]])) {
                        $config['field_id'] = $fields[$widget[2].'.'.$widget[4]]->id;
                    }
                }
                DashboardWidget::create([
                    'dashboard_id' => $board->id,
                    'type' => $widget[0],
                    'title' => $widget[1],
                    'config' => $config,
                    'order' => $i + 1,
                ]);
            }
        }

        foreach ($pack['automations'] ?? [] as $auto) {
            $table = $tables[$auto['table']] ?? null;
            if (! $table) {
                continue;
            }
            $created = Automation::create([
                'workspace_id' => $workspace->id,
                'table_id' => $table->id,
                'name' => $auto['name'],
                'enabled' => true,
                'trigger' => $auto['trigger'] ?? 'row_updated',
                'trigger_config' => [],
                'action' => $auto['action'] ?? 'notify',
                'action_config' => [
                    'role' => 'everyone',
                    'message' => $auto['message'] ?? $auto['name'],
                ],
            ]);
            $manifest['automation_ids'][] = $created->id;
        }

        $settings = InvoiceSetting::for($workspace);
        $invoice = $pack['invoice'] ?? [];
        $settings->fill([
            'legal_name' => $invoice['legal_name'] ?? $settings->legal_name,
            'address' => $invoice['address'] ?? $settings->address,
            'siret' => $invoice['siret'] ?? $settings->siret,
            'tva_number' => $invoice['tva_number'] ?? $settings->tva_number,
            'ape' => $invoice['ape'] ?? $settings->ape,
            'franchise_tva' => (bool) ($invoice['franchise_tva'] ?? $settings->franchise_tva),
            'default_vat' => (int) (($invoice['default_vat'] ?? 20) * 100),
            'payment_days' => $invoice['payment_days'] ?? $settings->payment_days,
            'number_prefix' => $invoice['prefix'] ?? $settings->number_prefix,
            'client_table_id' => isset($invoice['clients'], $tables[$invoice['clients']]) ? $tables[$invoice['clients']]->id : $settings->client_table_id,
        ]);
        $settings->save();

        if (! empty($pack['look'])) {
            $workspace->fill(array_filter([
                'tagline' => $pack['look']['tagline'] ?? null,
                'brand_color' => $pack['look']['brand_color'] ?? null,
                'sidebar_color' => $pack['look']['sidebar_color'] ?? null,
            ], fn ($v) => $v !== null));
            $workspace->save();
        }

        return WorkspaceTemplate::create([
            'workspace_id' => $workspace->id,
            'slug' => $pack['slug'],
            'name' => $pack['name'],
            'manifest' => $manifest,
        ]);
    }

    public static function uninstall(Workspace $workspace, string $slug): void
    {
        $row = $workspace->templates()->where('slug', $slug)->first();
        if (! $row) {
            throw new \RuntimeException('This template is not installed.');
        }
        $manifest = $row->manifest ?? [];
        foreach ($manifest['automation_ids'] ?? [] as $id) {
            Automation::query()->where('workspace_id', $workspace->id)->whereKey($id)->delete();
        }
        foreach ($manifest['dashboard_ids'] ?? [] as $id) {
            Dashboard::query()->where('workspace_id', $workspace->id)->whereKey($id)->delete();
        }
        foreach ($manifest['database_ids'] ?? [] as $id) {
            Database::query()->where('workspace_id', $workspace->id)->whereKey($id)->delete();
        }
        $row->delete();
    }

    /**
     * @param  list<array{0?: string, 1?: string}|array{value: string, color?: string}>  $items
     * @return list<array{id: string, value: string, color: string}>
     */
    private static function selectOptions(array $items): array
    {
        return array_map(function ($item) {
            if (isset($item['value'])) {
                return [
                    'id' => (string) Str::ulid(),
                    'value' => $item['value'],
                    'color' => $item['color'] ?? 'light-gray',
                ];
            }

            return [
                'id' => (string) Str::ulid(),
                'value' => $item[0] ?? '',
                'color' => $item[1] ?? 'light-gray',
            ];
        }, $items);
    }

    /**
     * @param  array<string, string>  $selects
     * @param  array<string, int>  $rowIds
     */
    private static function cell(Field $field, mixed $value, string $tableKey, array $selects, array $rowIds): mixed
    {
        if ($value === null) {
            return FieldTypes::defaultValue($field->type);
        }
        if ($field->type === 'single_select') {
            return $selects[$tableKey.'.'.$field->name.'.'.$value] ?? null;
        }
        if ($field->type === 'multiple_select') {
            $names = is_array($value) ? $value : [$value];

            return array_values(array_filter(array_map(
                fn ($name) => $selects[$tableKey.'.'.$field->name.'.'.$name] ?? null,
                $names
            )));
        }
        if ($field->type === 'link_row') {
            $names = is_array($value) ? $value : ($value === '' ? [] : [$value]);
            $ids = [];
            foreach ($names as $name) {
                foreach ($rowIds as $key => $id) {
                    if (str_ends_with($key, '.'.$name)) {
                        $ids[] = $id;
                    }
                }
            }

            return $ids;
        }
        if ($field->type === 'boolean') {
            return (bool) $value;
        }

        return $value;
    }
}
