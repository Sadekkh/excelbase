<?php

namespace Database\Seeders;

use App\Models\Database;
use App\Models\Field;
use App\Models\Row;
use App\Models\Table;
use App\Models\User;
use App\Models\View;
use App\Models\Workspace;
use App\Support\FieldTypes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'demo@baserow.io'],
            [
                'name' => 'Alex Rivera',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        if ($user->workspaces()->exists()) {
            return;
        }

        $workspace = Workspace::createForUser($user, 'Acme Inc');
        $crm = Database::create([
            'workspace_id' => $workspace->id,
            'name' => 'CRM',
            'order' => 1,
        ]);
        $product = Database::create([
            'workspace_id' => $workspace->id,
            'name' => 'Product',
            'order' => 2,
        ]);

        $clients = $this->clientsTable($crm);
        $this->dealsTable($crm, $clients);
        $this->tasksTable($crm);
        $this->featuresTable($product);

        $personal = Workspace::createForUser($user, 'Personal');
        Database::createWithTable($personal, 'Notes', 'Ideas');
    }

    private function clientsTable(Database $database): Table
    {
        $table = Table::create(['database_id' => $database->id, 'name' => 'Clients', 'order' => 1]);
        $status = $this->selectOptions([
            ['value' => 'Lead', 'color' => 'yellow'],
            ['value' => 'Qualified', 'color' => 'blue'],
            ['value' => 'Customer', 'color' => 'green'],
            ['value' => 'Churned', 'color' => 'red'],
        ]);

        $fields = $this->createFields($table, [
            ['Name', 'text', true, 220],
            ['Email', 'email', false, 220],
            ['Phone', 'phone', false, 160],
            ['Status', 'single_select', false, 140, ['options' => $status]],
            ['Rating', 'rating', false, 140, ['max' => 5, 'style' => 'star']],
            ['Website', 'url', false, 200],
            ['Employees', 'number', false, 120, ['decimal_places' => 0]],
            ['Signed', 'date', false, 140, ['include_time' => false, 'format' => 'ISO']],
            ['Active', 'boolean', false, 90],
            ['Notes', 'long_text', false, 280],
        ]);

        $statusIds = array_column($status, 'id', 'value');
        $rows = [
            ['Northwind Labs', 'ava@northwindlabs.io', '+1 415 555 0142', 'Customer', 5, 'https://northwindlabs.io', 48, '2024-02-12', true, 'Flagship account. Quarterly business review next month.'],
            ['Harbor & Pine', 'noah@harborpine.com', '+1 206 555 0198', 'Qualified', 4, 'https://harborpine.com', 12, '2025-11-03', true, 'Interested in the enterprise plan after the pilot.'],
            ['Lumen Studio', 'maya@lumen.studio', '+44 20 7946 0991', 'Lead', 3, 'https://lumen.studio', 7, null, false, 'Found us via the open-source community.'],
            ['Cedarline Health', 'james@cedarline.health', '+1 617 555 0110', 'Customer', 5, 'https://cedarline.health', 210, '2023-08-21', true, 'HIPAA workspace. Do not enable public shares.'],
            ['Brightline Freight', 'sofia@brightline.freight', '+1 312 555 0177', 'Qualified', 2, 'https://brightline.freight', 85, null, true, 'Needs CSV import for 12k shipment rows.'],
            ['Paperkite', 'leo@paperkite.co', '+61 2 5550 4421', 'Lead', 1, 'https://paperkite.co', 4, null, false, 'Design agency. Asking about the gallery view.'],
            ['Orbit Analytics', 'priya@orbitanalytics.com', '+1 650 555 0133', 'Customer', 4, 'https://orbitanalytics.com', 33, '2024-09-01', true, 'Wants webhooks on deal stage changes.'],
            ['Moss & Copper', 'elena@mossandcopper.com', '+34 91 555 2201', 'Churned', 2, 'https://mossandcopper.com', 9, '2022-04-18', false, 'Left after budget cut. Revisit in Q1.'],
        ];

        foreach ($rows as $i => $r) {
            Row::create([
                'table_id' => $table->id,
                'order' => $i + 1,
                'data' => [
                    (string) $fields['Name']->id => $r[0],
                    (string) $fields['Email']->id => $r[1],
                    (string) $fields['Phone']->id => $r[2],
                    (string) $fields['Status']->id => $statusIds[$r[3]],
                    (string) $fields['Rating']->id => $r[4],
                    (string) $fields['Website']->id => $r[5],
                    (string) $fields['Employees']->id => $r[6],
                    (string) $fields['Signed']->id => $r[7],
                    (string) $fields['Active']->id => $r[8],
                    (string) $fields['Notes']->id => $r[9],
                ],
            ]);
        }

        $this->gridView($table, 'Grid');
        $this->galleryView($table, 'Directory');
        $kanban = View::create([
            'table_id' => $table->id,
            'name' => 'Pipeline',
            'type' => 'kanban',
            'filters' => [],
            'sorts' => [],
            'groups' => [],
            'hidden_fields' => [],
            'kanban_field_id' => $fields['Status']->id,
            'row_height' => 'small',
            'order' => 3,
        ]);
        unset($kanban);

        return $table;
    }

    private function dealsTable(Database $database, Table $clients): Table
    {
        $table = Table::create(['database_id' => $database->id, 'name' => 'Deals', 'order' => 2]);
        $stage = $this->selectOptions([
            ['value' => 'Discovery', 'color' => 'light-gray'],
            ['value' => 'Proposal', 'color' => 'blue'],
            ['value' => 'Negotiation', 'color' => 'orange'],
            ['value' => 'Won', 'color' => 'green'],
            ['value' => 'Lost', 'color' => 'red'],
        ]);
        $priority = $this->selectOptions([
            ['value' => 'Low', 'color' => 'light-gray'],
            ['value' => 'Medium', 'color' => 'yellow'],
            ['value' => 'High', 'color' => 'red'],
        ]);

        $fields = $this->createFields($table, [
            ['Name', 'text', true, 240],
            ['Client', 'text', false, 180],
            ['Stage', 'single_select', false, 150, ['options' => $stage]],
            ['Amount', 'number', false, 130, ['decimal_places' => 0, 'prefix' => '$', 'suffix' => '']],
            ['Priority', 'single_select', false, 130, ['options' => $priority]],
            ['Close date', 'date', false, 140],
            ['Owner', 'text', false, 140],
            ['Notes', 'long_text', false, 260],
        ]);

        $stageIds = array_column($stage, 'id', 'value');
        $prioIds = array_column($priority, 'id', 'value');
        $deals = [
            ['Enterprise rollout', 'Cedarline Health', 'Negotiation', 48000, 'High', '2026-10-15', 'Alex Rivera', 'Security review almost done.'],
            ['Analytics add-on', 'Orbit Analytics', 'Proposal', 9200, 'Medium', '2026-09-30', 'Alex Rivera', 'Waiting on legal redlines.'],
            ['Pilot workspace', 'Harbor & Pine', 'Discovery', 2400, 'Medium', '2026-11-01', 'Sam Chen', 'Need 3 more stakeholder interviews.'],
            ['Freight tracker', 'Brightline Freight', 'Proposal', 18500, 'High', '2026-10-08', 'Sam Chen', 'CSV import is the blocker.'],
            ['Brand kit library', 'Lumen Studio', 'Discovery', 1500, 'Low', '2026-12-01', 'Alex Rivera', null],
            ['Renewal 2026', 'Northwind Labs', 'Won', 12000, 'High', '2026-08-01', 'Alex Rivera', 'Closed after QBR.'],
            ['Retail POS', 'Moss & Copper', 'Lost', 6400, 'Low', '2025-12-12', 'Sam Chen', 'Budget frozen.'],
        ];

        foreach ($deals as $i => $r) {
            Row::create([
                'table_id' => $table->id,
                'order' => $i + 1,
                'data' => [
                    (string) $fields['Name']->id => $r[0],
                    (string) $fields['Client']->id => $r[1],
                    (string) $fields['Stage']->id => $stageIds[$r[2]],
                    (string) $fields['Amount']->id => $r[3],
                    (string) $fields['Priority']->id => $prioIds[$r[4]],
                    (string) $fields['Close date']->id => $r[5],
                    (string) $fields['Owner']->id => $r[6],
                    (string) $fields['Notes']->id => $r[7],
                ],
            ]);
        }

        $this->gridView($table, 'Grid');
        View::create([
            'table_id' => $table->id,
            'name' => 'Board',
            'type' => 'kanban',
            'filters' => [],
            'sorts' => [],
            'groups' => [],
            'hidden_fields' => [],
            'kanban_field_id' => $fields['Stage']->id,
            'row_height' => 'small',
            'order' => 2,
        ]);
        View::create([
            'table_id' => $table->id,
            'name' => 'Calendar',
            'type' => 'calendar',
            'filters' => [],
            'sorts' => [],
            'groups' => [],
            'hidden_fields' => [],
            'kanban_field_id' => $fields['Close date']->id,
            'row_height' => 'small',
            'order' => 3,
        ]);

        return $table;
    }

    private function tasksTable(Database $database): Table
    {
        $table = Table::create(['database_id' => $database->id, 'name' => 'Tasks', 'order' => 3]);
        $status = $this->selectOptions([
            ['value' => 'Todo', 'color' => 'light-gray'],
            ['value' => 'In progress', 'color' => 'blue'],
            ['value' => 'Review', 'color' => 'orange'],
            ['value' => 'Done', 'color' => 'green'],
        ]);
        $fields = $this->createFields($table, [
            ['Name', 'text', true, 260],
            ['Status', 'single_select', false, 150, ['options' => $status]],
            ['Assignee', 'text', false, 150],
            ['Due', 'date', false, 140],
            ['Done', 'boolean', false, 90],
            ['Details', 'long_text', false, 280],
        ]);
        $ids = array_column($status, 'id', 'value');
        $tasks = [
            ['Prepare security questionnaire', 'In progress', 'Alex Rivera', '2026-09-12', false, 'Cedarline Health follow-up.'],
            ['Import freight sample CSV', 'Todo', 'Sam Chen', '2026-09-09', false, '12k rows, watch performance.'],
            ['Record product demo', 'Review', 'Alex Rivera', '2026-09-08', false, 'Harbor & Pine walkthrough.'],
            ['Publish changelog', 'Done', 'Sam Chen', '2026-09-04', true, 'Shipped Friday.'],
            ['Design form for inbound leads', 'Todo', 'Alex Rivera', '2026-09-18', false, 'Public form on the website.'],
            ['Fix date filter on Deals', 'In progress', 'Sam Chen', '2026-09-10', false, null],
        ];
        foreach ($tasks as $i => $r) {
            Row::create([
                'table_id' => $table->id,
                'order' => $i + 1,
                'data' => [
                    (string) $fields['Name']->id => $r[0],
                    (string) $fields['Status']->id => $ids[$r[1]],
                    (string) $fields['Assignee']->id => $r[2],
                    (string) $fields['Due']->id => $r[3],
                    (string) $fields['Done']->id => $r[4],
                    (string) $fields['Details']->id => $r[5],
                ],
            ]);
        }
        $this->gridView($table, 'Grid');
        View::create([
            'table_id' => $table->id,
            'name' => 'Kanban',
            'type' => 'kanban',
            'filters' => [],
            'sorts' => [],
            'groups' => [],
            'hidden_fields' => [],
            'kanban_field_id' => $fields['Status']->id,
            'row_height' => 'small',
            'order' => 2,
        ]);
        $form = View::create([
            'table_id' => $table->id,
            'name' => 'Request a task',
            'type' => 'form',
            'filters' => [],
            'sorts' => [],
            'groups' => [],
            'hidden_fields' => [$fields['Done']->id, $fields['Status']->id],
            'public' => true,
            'public_slug' => 'task-request',
            'form_config' => [
                'title' => 'Request a task',
                'description' => 'Tell the Acme team what you need. We will triage it in Tasks.',
                'submit_text' => 'Submit request',
                'success_message' => 'Thanks — your task is in the queue.',
                'cover' => '#5190ef',
            ],
            'row_height' => 'small',
            'order' => 3,
        ]);
        unset($form);

        return $table;
    }

    private function featuresTable(Database $database): Table
    {
        $table = Table::create(['database_id' => $database->id, 'name' => 'Features', 'order' => 1]);
        $status = $this->selectOptions([
            ['value' => 'Idea', 'color' => 'purple'],
            ['value' => 'Planned', 'color' => 'blue'],
            ['value' => 'Shipped', 'color' => 'green'],
        ]);
        $fields = $this->createFields($table, [
            ['Name', 'text', true, 260],
            ['Status', 'single_select', false, 140, ['options' => $status]],
            ['Votes', 'number', false, 100],
            ['Description', 'long_text', false, 320],
        ]);
        $ids = array_column($status, 'id', 'value');
        $items = [
            ['Public form branding', 'Shipped', 42, 'Match cover color and submit copy to the brand.'],
            ['Calendar view', 'Shipped', 38, 'Plot date fields on a month calendar.'],
            ['Field summaries', 'Planned', 27, 'Count, sum, and average in the grid footer.'],
            ['Link to table', 'Idea', 61, 'Relate rows across tables like Baserow.'],
        ];
        foreach ($items as $i => $r) {
            Row::create([
                'table_id' => $table->id,
                'order' => $i + 1,
                'data' => [
                    (string) $fields['Name']->id => $r[0],
                    (string) $fields['Status']->id => $ids[$r[1]],
                    (string) $fields['Votes']->id => $r[2],
                    (string) $fields['Description']->id => $r[3],
                ],
            ]);
        }
        $this->gridView($table, 'Grid');
        $this->galleryView($table, 'Gallery');

        return $table;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: bool, 3: int, 4?: array}>  $defs
     * @return array<string, Field>
     */
    private function createFields(Table $table, array $defs): array
    {
        $out = [];
        foreach ($defs as $i => $def) {
            $field = Field::create([
                'table_id' => $table->id,
                'name' => $def[0],
                'type' => $def[1],
                'primary' => $def[2],
                'width' => $def[3],
                'options' => $def[4] ?? FieldTypes::defaultOptions($def[1]),
                'order' => $i + 1,
            ]);
            $out[$def[0]] = $field;
        }

        return $out;
    }

    private function selectOptions(array $items): array
    {
        return array_map(fn ($item) => [
            'id' => (string) Str::ulid(),
            'value' => $item['value'],
            'color' => $item['color'],
        ], $items);
    }

    private function gridView(Table $table, string $name): View
    {
        return View::create([
            'table_id' => $table->id,
            'name' => $name,
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

    private function galleryView(Table $table, string $name): View
    {
        return View::create([
            'table_id' => $table->id,
            'name' => $name,
            'type' => 'gallery',
            'filters' => [],
            'sorts' => [],
            'groups' => [],
            'hidden_fields' => [],
            'row_height' => 'small',
            'order' => 2,
        ]);
    }
}
