<?php

namespace App\Support;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Field;
use App\Models\Notification;
use App\Models\Row;
use App\Models\Table;
use App\Models\User;

class AutomationEngine
{
    private static int $depth = 0;

    /**
     * @param  list<int|string>  $changedFieldIds
     */
    public static function dispatch(Table $table, Row $row, string $event, array $changedFieldIds = []): void
    {
        if (self::$depth >= 3) {
            return;
        }

        $table->loadMissing(['database.workspace', 'fields']);
        $workspace = $table->database->workspace;
        $automations = Automation::query()
            ->where('workspace_id', $workspace->id)
            ->where('table_id', $table->id)
            ->where('enabled', true)
            ->get();

        foreach ($automations as $automation) {
            if (! self::matches($automation, $row, $event, $changedFieldIds, $table)) {
                continue;
            }
            self::$depth++;
            try {
                $message = self::run($automation, $table, $row);
                AutomationRun::create([
                    'automation_id' => $automation->id,
                    'row_id' => $row->id,
                    'status' => 'ok',
                    'message' => $message,
                ]);
            } catch (\Throwable $e) {
                AutomationRun::create([
                    'automation_id' => $automation->id,
                    'row_id' => $row->id,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
            }
            self::$depth--;
        }
    }

    /**
     * @param  list<int|string>  $changedFieldIds
     */
    private static function matches(Automation $automation, Row $row, string $event, array $changedFieldIds, Table $table): bool
    {
        $trigger = $automation->trigger;
        if ($trigger === 'row_created' && $event !== 'row_created') {
            return false;
        }
        if ($trigger === 'row_updated' && $event !== 'row_updated') {
            return false;
        }
        if ($trigger === 'field_changed') {
            $fieldId = (int) ($automation->trigger_config['field_id'] ?? 0);
            if ($event !== 'row_updated' || ! in_array($fieldId, array_map('intval', $changedFieldIds), true)) {
                return false;
            }
        }

        $fieldId = (int) ($automation->trigger_config['if_field_id'] ?? 0);
        if (! $fieldId) {
            return true;
        }
        $field = $table->fields->firstWhere('id', $fieldId);
        if (! $field) {
            return false;
        }
        $expected = (string) ($automation->trigger_config['if_value'] ?? '');
        $actual = $row->rawValue($field);

        return (string) (is_array($actual) ? json_encode($actual) : $actual) === $expected;
    }

    private static function run(Automation $automation, Table $table, Row $row): string
    {
        $row->setRelation('table', $table);
        $config = $automation->action_config ?? [];

        return match ($automation->action) {
            'update_field' => self::updateField($table, $row, $config),
            'create_row' => self::createRow($config),
            'notify' => self::notify($automation, $table, $row, $config),
            default => 'No action',
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function updateField(Table $table, Row $row, array $config): string
    {
        $field = $table->fields->firstWhere('id', (int) ($config['field_id'] ?? 0));
        if (! $field instanceof Field || $field->isReadOnly()) {
            return 'Skipped missing field';
        }
        $row->setValue($field, $config['value'] ?? null);
        $row->save();

        return 'Updated '.$field->name;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function createRow(array $config): string
    {
        $target = Table::query()->with('fields')->find((int) ($config['table_id'] ?? 0));
        if (! $target) {
            return 'Skipped missing table';
        }
        $order = (float) ($target->rows()->max('order') ?? 0) + 1;
        $created = $target->rows()->create(['data' => [], 'order' => $order]);
        $primary = $target->fields->firstWhere('primary');
        if ($primary) {
            $created->setValue($primary, $config['value'] ?? 'Automated row');
            $created->save();
        }
        $created->setRelation('table', $target);
        self::dispatch($target, $created, 'row_created');

        return 'Created row in '.$target->name;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function notify(Automation $automation, Table $table, Row $row, array $config): string
    {
        $workspace = $table->database->workspace;
        $role = $config['role'] ?? 'builder';
        $users = $workspace->members()->get()->filter(function (User $user) use ($role) {
            $current = $user->pivot->role;
            if ($role === 'builders') {
                return in_array($current, ['owner', 'admin', 'builder'], true);
            }
            if ($role === 'everyone') {
                return true;
            }

            return $current === $role;
        });
        $primary = $table->fields->firstWhere('primary');
        $label = $primary ? (string) $row->rawValue($primary) : 'Row #'.$row->id;
        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'title' => $automation->name,
                'body' => ($config['message'] ?? 'A workflow ran').' — '.$label,
                'url' => '/table/'.$table->id,
            ]);
        }

        return 'Notified '.$users->count().' people';
    }
}
