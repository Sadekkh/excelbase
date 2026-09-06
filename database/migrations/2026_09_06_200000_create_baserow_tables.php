<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('admin');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });

        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });

        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->boolean('primary')->default(false);
            $table->json('options')->nullable();
            $table->unsignedInteger('width')->default(200);
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });

        Schema::create('rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->json('data');
            $table->double('order')->default(1);
            $table->timestamps();
            $table->index(['table_id', 'order']);
        });

        Schema::create('views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('grid');
            $table->json('filters')->nullable();
            $table->json('sorts')->nullable();
            $table->json('groups')->nullable();
            $table->json('hidden_fields')->nullable();
            $table->json('field_options')->nullable();
            $table->json('form_config')->nullable();
            $table->string('row_height')->default('small');
            $table->boolean('public')->default(false);
            $table->string('public_slug')->nullable()->unique();
            $table->foreignId('kanban_field_id')->nullable()->constrained('fields')->nullOnDelete();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('views');
        Schema::dropIfExists('rows');
        Schema::dropIfExists('fields');
        Schema::dropIfExists('tables');
        Schema::dropIfExists('databases');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
