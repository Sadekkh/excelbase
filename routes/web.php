<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\RowController;
use App\Http\Controllers\SurfaceController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspacePanelController;
use Illuminate\Support\Facades\Route;

Route::get('/form/{slug}', [PublicFormController::class, 'show'])->name('forms.public');
Route::post('/form/{slug}', [PublicFormController::class, 'store'])->name('forms.public.submit');
Route::get('/shared/{slug}', [PublicShareController::class, 'show'])->name('views.shared');

Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/demo', [LoginController::class, 'demo'])->name('login.demo');
    Route::get('/signup', [RegisterController::class, 'create'])->name('register');
    Route::post('/signup', [RegisterController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/surface', [SurfaceController::class, 'switch'])->name('surface.switch');
    Route::get('/inbox', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/inbox/{notification}', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/app', AppController::class)->name('app');
    Route::get('/app/boot', [WorkspacePanelController::class, 'currentBoot'])->name('app.boot');
    Route::post('/app/open', [AppController::class, 'open'])->name('app.open');
    Route::post('/app/surface', [WorkspacePanelController::class, 'currentSurface'])->name('app.surface');
    Route::get('/app/sheet/{table}', [WorkspacePanelController::class, 'currentSheet'])->name('app.sheet');
    Route::get('/app/board/{dashboard?}', [WorkspacePanelController::class, 'currentBoard'])->name('app.board');
    Route::get('/app/people', [WorkspacePanelController::class, 'currentPeople'])->name('app.people');
    Route::get('/app/automations', [WorkspacePanelController::class, 'currentAutomations'])->name('app.automations');
    Route::get('/app/plan', [WorkspacePanelController::class, 'currentPlan'])->name('app.plan');
    Route::get('/app/structure', [WorkspacePanelController::class, 'currentStructure'])->name('app.structure');
    Route::get('/app/templates', [TemplateController::class, 'index'])->name('app.templates');
    Route::post('/app/templates', [TemplateController::class, 'install'])->name('app.templates.install');
    Route::delete('/app/templates/{slug}', [TemplateController::class, 'destroy'])->name('app.templates.destroy');
    Route::post('/app/assistant', [TemplateController::class, 'assistant'])->name('app.assistant');
    Route::post('/app/assistant/apply', [TemplateController::class, 'applyAssistant'])->name('app.assistant.apply');
    Route::get('/app/invoices', [InvoiceController::class, 'index'])->name('app.invoices');
    Route::post('/app/invoices', [InvoiceController::class, 'store'])->name('app.invoices.store');
    Route::patch('/app/invoices/{invoice}', [InvoiceController::class, 'update'])->name('app.invoices.update');
    Route::post('/app/invoices/settings', [InvoiceController::class, 'settings'])->name('app.invoices.settings');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');

    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/workspace/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::patch('/workspace/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::post('/workspace/{workspace}/look', [WorkspaceController::class, 'appearance'])->name('workspaces.look');
    Route::delete('/workspace/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::get('/workspace/{workspace}/boot', [WorkspacePanelController::class, 'boot'])->name('workspaces.panel.boot');
    Route::get('/workspace/{workspace}/panel/sheet/{table}', [WorkspacePanelController::class, 'sheet'])->name('workspaces.panel.sheet');
    Route::get('/workspace/{workspace}/panel/board/{dashboard?}', [WorkspacePanelController::class, 'board'])->name('workspaces.panel.board');
    Route::get('/workspace/{workspace}/panel/people', [WorkspacePanelController::class, 'people'])->name('workspaces.panel.people');
    Route::get('/workspace/{workspace}/panel/automations', [WorkspacePanelController::class, 'automations'])->name('workspaces.panel.automations');
    Route::get('/workspace/{workspace}/panel/plan', [WorkspacePanelController::class, 'plan'])->name('workspaces.panel.plan');
    Route::get('/workspace/{workspace}/panel/structure', [WorkspacePanelController::class, 'structure'])->name('workspaces.panel.structure');
    Route::post('/workspace/{workspace}/surface', [WorkspacePanelController::class, 'switchSurface'])->name('workspaces.surface');
    Route::get('/workspace/{workspace}/app', [SurfaceController::class, 'app'])->name('workspaces.app');
    Route::get('/workspace/{workspace}/plan', [SurfaceController::class, 'billing'])->name('workspaces.billing');
    Route::post('/workspace/{workspace}/plan', [SurfaceController::class, 'choosePlan'])->name('workspaces.plan');

    Route::get('/workspace/{workspace}/people', [MemberController::class, 'index'])->name('members.index');
    Route::post('/workspace/{workspace}/people', [MemberController::class, 'store'])->name('members.store');
    Route::post('/workspace/{workspace}/people/child', [MemberController::class, 'addToChild'])->name('members.child');
    Route::patch('/workspace/{workspace}/people/{user}', [MemberController::class, 'update'])->name('members.update');
    Route::delete('/workspace/{workspace}/people/{user}', [MemberController::class, 'destroy'])->name('members.destroy');

    Route::get('/workspace/{workspace}/dashboards', [BoardController::class, 'index'])->name('dashboards.index');
    Route::post('/workspace/{workspace}/dashboards', [BoardController::class, 'store'])->name('dashboards.store');
    Route::get('/workspace/{workspace}/dashboards/{dashboard}', [BoardController::class, 'show'])->name('dashboards.show');
    Route::delete('/workspace/{workspace}/dashboards/{dashboard}', [BoardController::class, 'destroy'])->name('dashboards.destroy');
    Route::post('/workspace/{workspace}/dashboards/{dashboard}/widgets', [BoardController::class, 'storeWidget'])->name('dashboards.widgets.store');
    Route::delete('/workspace/{workspace}/dashboards/{dashboard}/widgets/{widget}', [BoardController::class, 'destroyWidget'])->name('dashboards.widgets.destroy');

    Route::get('/workspace/{workspace}/automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::post('/workspace/{workspace}/automations', [AutomationController::class, 'store'])->name('automations.store');
    Route::patch('/workspace/{workspace}/automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
    Route::delete('/workspace/{workspace}/automations/{automation}', [AutomationController::class, 'destroy'])->name('automations.destroy');

    Route::post('/workspace/{workspace}/databases', [DatabaseController::class, 'store'])->name('databases.store');
    Route::get('/database/{database}', [DatabaseController::class, 'show'])->name('databases.show');
    Route::patch('/database/{database}', [DatabaseController::class, 'update'])->name('databases.update');
    Route::delete('/database/{database}', [DatabaseController::class, 'destroy'])->name('databases.destroy');

    Route::post('/database/{database}/tables', [TableController::class, 'store'])->name('tables.store');
    Route::get('/table/{table}', [TableController::class, 'show'])->name('tables.show');
    Route::patch('/table/{table}', [TableController::class, 'update'])->name('tables.update');
    Route::delete('/table/{table}', [TableController::class, 'destroy'])->name('tables.destroy');
    Route::post('/table/{table}/duplicate', [TableController::class, 'duplicate'])->name('tables.duplicate');
    Route::get('/table/{table}/export', [TableController::class, 'export'])->name('tables.export');
    Route::post('/table/{table}/import', [TableController::class, 'import'])->name('tables.import');

    Route::post('/table/{table}/fields', [FieldController::class, 'store'])->name('fields.store');
    Route::patch('/fields/{field}', [FieldController::class, 'update'])->name('fields.update');
    Route::delete('/fields/{field}', [FieldController::class, 'destroy'])->name('fields.destroy');
    Route::post('/fields/{field}/options', [FieldController::class, 'storeOption'])->name('fields.options.store');
    Route::post('/files', [FileController::class, 'store'])->name('files.store');

    Route::get('/table/{table}/rows', [RowController::class, 'index'])->name('rows.index');
    Route::post('/table/{table}/rows', [RowController::class, 'store'])->name('rows.store');
    Route::patch('/table/{table}/rows', [RowController::class, 'batchUpdate'])->name('rows.batch');
    Route::delete('/table/{table}/rows', [RowController::class, 'destroyMany'])->name('rows.destroy-many');
    Route::patch('/rows/{row}', [RowController::class, 'update'])->name('rows.update');
    Route::delete('/rows/{row}', [RowController::class, 'destroy'])->name('rows.destroy');
    Route::post('/rows/{row}/duplicate', [RowController::class, 'duplicate'])->name('rows.duplicate');
    Route::get('/rows/{row}/comments', [CommentController::class, 'index'])->name('comments.index');
    Route::post('/rows/{row}/comments', [CommentController::class, 'store'])->name('comments.store');

    Route::post('/table/{table}/views', [ViewController::class, 'store'])->name('views.store');
    Route::patch('/views/{view}', [ViewController::class, 'update'])->name('views.update');
    Route::delete('/views/{view}', [ViewController::class, 'destroy'])->name('views.destroy');
    Route::post('/views/{view}/duplicate', [ViewController::class, 'duplicate'])->name('views.duplicate');

    Route::middleware('platform')->prefix('admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('admin.index');
        Route::get('/workspaces', [AdminController::class, 'workspaces'])->name('admin.workspaces');
        Route::post('/workspaces/{workspace}/plan', [AdminController::class, 'assignPlan'])->name('admin.workspaces.plan');
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('/users/{user}/admin', [AdminController::class, 'toggleAdmin'])->name('admin.users.toggle');
        Route::get('/plans', [AdminController::class, 'plans'])->name('admin.plans');
        Route::patch('/plans/{plan}', [AdminController::class, 'updatePlan'])->name('admin.plans.update');
    });
});
