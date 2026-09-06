<?php

namespace Tests\Feature;

use App\Models\Database;
use App\Models\Table;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RowQuery;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->post('/demo')->assertRedirect('/dashboard');
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Acme Inc')
            ->assertSee('All workspaces');
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
        ])->assertRedirect('/dashboard');

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

        $this->actingAs($user)
            ->get(route('tables.show', $table))
            ->assertOk()
            ->assertSee('Clients')
            ->assertSee('Northwind Labs')
            ->assertSee('Pipeline');

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
}
