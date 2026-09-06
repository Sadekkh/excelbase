<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('views', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false)->after('public');
            $table->foreignId('user_id')->nullable()->after('is_personal')->constrained()->nullOnDelete();
        });

        Schema::create('row_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('row_comments');
        Schema::table('views', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('is_personal');
        });
    }
};
