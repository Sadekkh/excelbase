<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\RowController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\WorkspaceController;
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

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/workspace/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::patch('/workspace/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspace/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

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
});
