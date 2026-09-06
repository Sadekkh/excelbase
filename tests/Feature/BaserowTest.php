<?php

namespace Tests\Feature;

use App\Models\Database;
use App\Models\RowComment;
use App\Models\Table;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Access;
use App\Support\FormulaEngine;
use App\Support\RowQuery;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BaserowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('Sign in')->assertSee('Baserow');
    }

    public function test_demo_login_and_dashboard(): void
    {
        $this->seed(DemoSeeder::class);

        $this->post('/demo')->assertRedirect('/app');
        $this->get('/app')
            ->assertOk()
            ->assertSee('Acme Inc')
            ->assertSee('Use');
    }

    public function test_guest_cannot_open_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/');
    }

    public function test_register_creates_workspace_and_database(): void
    {
        $this->post('/signup', [
            'name' => 'Jordan Lee',
            'email' => 'jordan@example.com',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])->assertRedirect('/app');

        $this->assertDatabaseHas('users', ['email' => 'jordan@example.com']);
        $this->assertDatabaseHas('workspaces', ['name' => "Jordan Lee's workspace"]);
        $this->assertDatabaseHas('databases', ['name' => 'Database']);
        $this->assertDatabaseHas('tables', ['name' => 'Table']);
        $this->assertDatabaseHas('fields', ['name' => 'Name', 'primary' => 1]);
    }

    public function test_table_grid_and_row_crud(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::query()->where('email', 'demo@baserow.io')->first();
        $table = Table::query()->where('name', 'Clients')->first();

        $workspace = $table->database->workspace;
        $this->actingAs($user)
            ->get(route('tables.show', $table))
            ->assertRedirect(route('workspaces.show', $workspace));
        $this->actingAs($user)
            ->getJson(route('workspaces.panel.sheet', [$workspace, $table]))
            ->assertOk()
            ->assertJsonPath('bootstrap.table.name', 'Clients')
            ->assertJsonPath('bootstrap.view.name', 'Grid');
        $this->assertTrue(
            collect($this->actingAs($user)->getJson(route('workspaces.panel.sheet', [$workspace, $table]))->json('bootstrap.rows'))
                ->contains(fn ($row) => collect($row['values'])->contains('Northwind Labs'))
        );

        $field = $table->fields()->where('primary', true)->first();
        $create = $this->actingAs($user)->postJson(route('rows.store', $table), [
            'values' => [(string) $field->id => 'New prospect'],
        ]);
        $create->assertCreated();
        $rowId = $create->json('id');

        $this->actingAs($user)->patchJson(route('rows.update', $rowId), [
            'values' => [(string) $field->id => 'Renamed prospect'],
        ])->assertOk()->assertJsonPath('values.'.$field->id, 'Renamed prospect');

        $this->actingAs($user)->deleteJson(route('rows.destroy', $rowId))->assertOk();
        $this->assertDatabaseMissing('rows', ['id' => $rowId]);
    }

    public function test_field_create_and_public_form(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::query()->where('email', 'demo@baserow.io')->first();
        $table = Table::query()->where('name', 'Tasks')->first();

        $this->actingAs($user)->postJson(route('fields.store', $table), [
            'name' => 'Effort',
            'type' => 'number',
        ])->assertOk()->assertJsonPath('name', 'Effort');

        $this->get('/form/task-request')
            ->assertOk()
            ->assertSee('Request a task')
            ->assertSee('Submit request');

        $name = $table->fields()->where('primary', true)->first();
        $this->post('/form/task-request', [
            'values' => [(string) $name->id => 'From the public form'],
        ])->assertRedirect();

        $this->assertTrue(
            $table->rows()->get()->contains(fn ($row) => $row->value($name) === 'From the public form')
        );
    }

    public function test_user_cannot_see_foreign_workspace(): void
    {
        $this->seed(DemoSeeder::class);
        $other = User::factory()->create();
        $workspace = Workspace::query()->where('name', 'Acme Inc')->first();

        $this->actingAs($other)
            ->get(route('workspaces.show', $workspace))
            ->assertForbidden();
    }

    public function test_nested_workspaces_and_build_mode(): void
    {
        $this->seed(DemoSeeder::class);
        $owner = User::query()->where('email', 'demo@baserow.io')->first();
        $member = User::query()->where('email', 'maya@baserow.io')->first();
        $builder = User::query()->where('email', 'sam@baserow.io')->first();
        $acme = Workspace::query()->where('name', 'Acme Inc')->first();
        $sales = Workspace::query()->where('name', 'Sales')->first();
        $this->assertNotNull($sales);
        $this->assertSame($acme->id, $sales->parent_id);
        $this->assertTrue($sales->members->contains('id', $owner->id));
        $this->assertTrue($sales->members->contains('id', $builder->id));
        $this->assertFalse($sales->members->contains('id', $member->id));

        $this->actingAs($member)->get(route('workspaces.show', $sales))->assertForbidden();
        $this->actingAs($member)->withSession(['workspace_id' => $sales->id])->getJson(route('app.boot'))
            ->assertForbidden();

        $opps = Table::query()->where('name', 'Opportunities')->first();
        $this->actingAs($member)->getJson(route('workspaces.panel.sheet', [$sales, $opps]))->assertForbidden();

        $acmeBoot = $this->actingAs($member)->withSession(['workspace_id' => $acme->id])->getJson(route('app.boot'))
            ->assertOk()
            ->assertJsonPath('workspace.name', 'Acme Inc');
        $this->assertFalse(collect($acmeBoot->json('tree'))->pluck('name')->contains('Sales'));

        $this->actingAs($owner)->getJson(route('workspaces.panel.people', $acme))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Sales'])
            ->assertJsonFragment(['email' => 'maya@baserow.io']);

        $this->actingAs($member)->postJson(route('members.child', $acme), [
            'child_id' => $sales->id,
            'user_id' => $member->id,
            'role' => 'member',
        ])->assertForbidden();

        $stranger = User::factory()->create();
        $this->actingAs($owner)->postJson(route('members.child', $acme), [
            'child_id' => $sales->id,
            'user_id' => $stranger->id,
            'role' => 'member',
        ])->assertStatus(422);

        $this->actingAs($owner)->postJson(route('members.child', $acme), [
            'child_id' => $sales->id,
            'user_id' => $member->id,
            'role' => 'member',
        ])->assertOk();

        $this->actingAs($member)->get(route('workspaces.show', $sales))->assertRedirect(route('app'));
        $this->actingAs($member)->withSession(['workspace_id' => $sales->id])->getJson(route('app.boot'))
            ->assertOk()
            ->assertJsonPath('workspace.name', 'Sales')
            ->assertJsonPath('can_build', false)
            ->assertJsonPath('workspace.brand_color', '#0eaa42');

        $this->actingAs($owner)->withSession(['workspace_id' => $acme->id])
            ->postJson(route('app.surface'), ['surface' => 'builder'])
            ->assertOk()
            ->assertJsonPath('surface', 'builder')
            ->assertJsonPath('can_build', true);

        $this->actingAs($owner)->withSession([
            'workspace_id' => $sales->id,
            Access::surfaceKey($acme) => 'builder',
        ])->getJson(route('app.boot'))
            ->assertOk()
            ->assertJsonPath('workspace.name', 'Sales')
            ->assertJsonPath('surface', 'app');

        $this->actingAs($owner)->withSession(['workspace_id' => $sales->id])
            ->postJson(route('app.surface'), ['surface' => 'builder'])
            ->assertOk()
            ->assertJsonPath('surface', 'builder');

        $table = Table::query()->where('name', 'Clients')->first();
        $this->actingAs($owner)->withSession([
            'workspace_id' => $acme->id,
            Access::surfaceKey($acme) => 'builder',
        ])->getJson(route('app.sheet', $table))
            ->assertOk()
            ->assertJsonPath('bootstrap.canBuild', true);

        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $this->actingAs($owner)->post(route('workspaces.look', $sales), [
            'name' => 'Sales',
            'tagline' => 'Repainted for the sales pod',
            'brand_color' => '#c45c26',
            'sidebar_color' => '#fff7f0',
            'logo' => UploadedFile::fake()->createWithContent('sales-mark.png', $png),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('workspace.brand_color', '#c45c26')
            ->assertJsonPath('workspace.tagline', 'Repainted for the sales pod');
        $this->assertNotNull($sales->fresh()->logo_path);

        $this->actingAs($owner)->withSession(['workspace_id' => $acme->id])->postJson(route('workspaces.store'), [
            'name' => 'Marketing',
            'parent_id' => $acme->id,
        ])->assertOk()->assertJsonPath('name', 'Marketing');
        $marketing = Workspace::query()->where('name', 'Marketing')->first();
        $this->assertSame($acme->id, $marketing->parent_id);
        $this->assertFalse($marketing->members()->where('users.id', $member->id)->exists());
    }

    public function test_row_query_filters_and_sorts(): void
    {
        $this->seed(DemoSeeder::class);
        $table = Table::query()->where('name', 'Clients')->first();
        $status = $table->fields()->where('name', 'Status')->first();
        $lead = collect($status->options['options'])->firstWhere('value', 'Lead');
        $view = $table->views()->where('type', 'grid')->first();
        $view->filters = [['field_id' => $status->id, 'operator' => 'equal', 'value' => $lead['id']]];
        $view->sorts = [['field_id' => $table->primaryField()->id, 'direction' => 'asc']];

        $rows = RowQuery::apply($table->rows, $view, $table->fields);
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($lead['id'], $row->value($status));
        }
    }

    public function test_deals_link_to_clients_and_public_share(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::query()->where('email', 'demo@baserow.io')->first();
        $table = Table::query()->where('name', 'Deals')->first();
        $clientField = $table->fields()->where('type', 'link_row')->first();
        $this->assertNotNull($clientField);
        $this->assertNotNull($clientField->options['linked_table_id']);

        $view = $table->views()->where('type', 'grid')->first();
        $this->actingAs($user)->patchJson(route('views.update', $view), [
            'public' => true,
        ])->assertOk();

        $view->refresh();
        $this->get(route('views.shared', $view->public_slug))
            ->assertOk()
            ->assertSee('Shared view')
            ->assertSee('Deals');
    }

    public function test_database_creates_default_table(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::createForUser($user, 'Ops');

        $this->actingAs($user)->post(route('databases.store', $workspace), [
            'name' => 'Inventory',
        ])->assertRedirect();

        $database = Database::query()->where('name', 'Inventory')->first();
        $this->assertNotNull($database);
        $this->assertSame('Table', $database->tables()->first()->name);
    }

    public function test_saas_roles_admin_and_automations(): void
    {
        $this->seed(DemoSeeder::class);
        $owner = User::query()->where('email', 'demo@baserow.io')->first();
        $member = User::query()->where('email', 'maya@baserow.io')->first();
        $workspace = Workspace::query()->where('name', 'Acme Inc')->first();
        $table = Table::query()->where('name', 'Deals')->first();

        $this->actingAs($owner)->get(route('admin.index'))->assertOk()->assertSee('Platform');
        $this->actingAs($member)->get(route('admin.index'))->assertForbidden();

        $this->actingAs($member)->get(route('workspaces.show', $workspace))
            ->assertRedirect(route('app'));
        $this->actingAs($member)->get(route('app'))
            ->assertOk()
            ->assertSee('Acme Inc')
            ->assertSee('Use');

        $this->actingAs($member)->getJson(route('workspaces.panel.sheet', [$workspace, $table]))
            ->assertOk()
            ->assertJsonPath('kind', 'sheet')
            ->assertJsonPath('bootstrap.table.name', 'Deals');

        $this->actingAs($owner)->postJson(route('workspaces.surface', $workspace), ['surface' => 'builder'])
            ->assertOk()
            ->assertJsonPath('surface', 'builder')
            ->assertJsonPath('can_build', true);

        $this->actingAs($member)->getJson(route('workspaces.panel.boot', $workspace))
            ->assertOk()
            ->assertJsonPath('can_build', false)
            ->assertJsonPath('surface', 'app');
        $this->actingAs($member)->postJson(route('workspaces.surface', $workspace), ['surface' => 'builder'])
            ->assertForbidden();
        $this->actingAs($member)->getJson(route('workspaces.panel.automations', $workspace))
            ->assertForbidden();

        $this->actingAs($member)->postJson(route('fields.store', $table), [
            'name' => 'Secret',
            'type' => 'text',
        ])->assertForbidden();

        $this->actingAs($owner)->get(route('automations.index', $workspace))
            ->assertRedirect(route('workspaces.show', $workspace));
        $this->actingAs($owner)->getJson(route('workspaces.panel.automations', $workspace))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Notify builders of new deals']);

        $primary = $table->fields()->where('primary', true)->first();
        $before = \App\Models\Notification::query()->count();
        $this->actingAs($owner)->postJson(route('rows.store', $table), [
            'values' => [(string) $primary->id => 'Automation deal'],
        ])->assertCreated();
        $this->assertGreaterThan($before, \App\Models\Notification::query()->count());

        $this->actingAs($owner)->get(route('workspaces.billing', $workspace))
            ->assertRedirect(route('workspaces.show', $workspace));
        $this->actingAs($owner)->getJson(route('workspaces.panel.plan', $workspace))
            ->assertOk()
            ->assertJsonPath('kind', 'plan');
    }

    public function test_formula_lookup_count_and_ai_fields(): void
    {
        $this->seed(DemoSeeder::class);
        $table = Table::query()->where('name', 'Deals')->first();
        $table->load(['fields', 'rows']);
        $table->rows->each(fn ($row) => $row->setRelation('table', $table));

        $amount = $table->fields->firstWhere('name', 'Amount');
        $commission = $table->fields->firstWhere('name', 'Commission');
        $lookup = $table->fields->firstWhere('name', 'Client email');
        $count = $table->fields->firstWhere('name', 'Count');
        $count = $count ?: $table->fields->firstWhere('name', '# Clients');
        $ai = $table->fields->firstWhere('name', 'AI summary');
        $this->assertNotNull($commission);
        $this->assertNotNull($lookup);
        $this->assertNotNull($count);
        $this->assertNotNull($ai);

        $won = $table->rows->first(fn ($row) => $row->rawValue($table->fields->firstWhere('name', 'Name')) === 'Renewal 2026');
        $this->assertNotNull($won);
        $this->assertSame('1200', $won->value($commission));
        $this->assertSame('ava@northwindlabs.io', $won->value($lookup));
        $this->assertSame(1, $won->value($count));
        $this->assertNotSame('', $won->value($ai));

        $generated = FormulaEngine::generate('Commission is 10% of Amount', $table->fields);
        $this->assertSame('{Amount} * 0.1', $generated);
    }

    public function test_row_comments_and_export_formats(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::query()->where('email', 'demo@baserow.io')->first();
        $table = Table::query()->where('name', 'Tasks')->first();
        $row = $table->rows()->first();

        $this->actingAs($user)->getJson(route('comments.index', $row))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Need the latest SOC 2 packet before we send this to Cedarline.']);

        $this->actingAs($user)->postJson(route('comments.store', $row), [
            'body' => 'Ship it tomorrow.',
        ])->assertCreated()->assertJsonPath('body', 'Ship it tomorrow.');

        $this->assertSame(3, RowComment::query()->where('row_id', $row->id)->count());

        $this->actingAs($user)->get(route('tables.export', ['table' => $table, 'format' => 'json']))
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->actingAs($user)->get(route('tables.export', ['table' => $table, 'format' => 'xml']))
            ->assertOk()
            ->assertHeader('content-type', 'application/xml');
    }

    public function test_survey_form_and_timeline_view(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::query()->where('email', 'demo@baserow.io')->first();
        $table = Table::query()->where('name', 'Tasks')->first();

        $this->get('/form/task-survey')
            ->assertOk()
            ->assertSee('How can we help?')
            ->assertSee('survey-next')
            ->assertDontSee('public-form__brand', false);

        $workspace = $table->database->workspace;
        $timeline = $table->views()->where('type', 'timeline')->value('id');
        $this->actingAs($user)
            ->get(route('tables.show', ['table' => $table, 'view' => $timeline]))
            ->assertRedirect(route('workspaces.show', $workspace));
        $this->actingAs($user)
            ->getJson(route('workspaces.panel.sheet', [$workspace, $table]).'?view='.$timeline)
            ->assertOk()
            ->assertJsonPath('bootstrap.view.type', 'timeline');

        $this->actingAs($user)->post(route('views.store', $table), [
            'type' => 'graph',
        ])->assertRedirect();

        $this->assertDatabaseHas('views', ['table_id' => $table->id, 'type' => 'graph']);
    }

    public function test_personal_views_are_hidden_from_teammates(): void
    {
        $this->seed(DemoSeeder::class);
        $owner = User::query()->where('email', 'demo@baserow.io')->first();
        $teammate = User::factory()->create();
        $workspace = Workspace::query()->where('name', 'Acme Inc')->first();
        $workspace->members()->attach($teammate->id, ['role' => 'admin']);
        $table = Table::query()->where('name', 'Clients')->first();
        $personal = $table->views()->where('is_personal', true)->first();
        $this->assertNotNull($personal);

        $this->actingAs($owner)
            ->getJson(route('workspaces.panel.sheet', [$workspace, $table]).'?view='.$personal->id)
            ->assertOk()
            ->assertJsonPath('bootstrap.view.name', 'My leads');

        $this->actingAs($teammate)
            ->getJson(route('workspaces.panel.sheet', [$workspace, $table]).'?view='.$personal->id)
            ->assertOk()
            ->assertJsonMissing(['name' => 'My leads']);

        $this->actingAs($teammate)
            ->patchJson(route('views.update', $personal), ['name' => 'Hacked'])
            ->assertForbidden();
    }
}
