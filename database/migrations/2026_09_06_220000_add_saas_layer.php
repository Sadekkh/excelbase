<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('price_monthly')->default(0);
            $table->string('tagline')->nullable();
            $table->json('features');
            $table->unsignedInteger('order')->default(1);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->foreignId('plan_id')->nullable()->after('slug')->constrained('plans')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('password');
        });

        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->json('config')->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('trigger');
            $table->json('trigger_config')->nullable();
            $table->string('action');
            $table->json('action_config')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('row_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('ok');
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automations');
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('dashboards');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('slug');
        });
        Schema::dropIfExists('plans');
    }
};
