<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            $table->string('brand_color', 7)->nullable()->after('parent_id');
            $table->string('sidebar_color', 7)->nullable()->after('brand_color');
            $table->string('logo_path')->nullable()->after('sidebar_color');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'brand_color', 'sidebar_color', 'logo_path']);
        });
    }
};
