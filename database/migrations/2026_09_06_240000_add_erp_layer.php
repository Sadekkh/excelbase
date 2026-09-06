<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->json('manifest')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('workspace_invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('legal_name')->nullable();
            $table->text('address')->nullable();
            $table->string('siret')->nullable();
            $table->string('tva_number')->nullable();
            $table->string('ape')->nullable();
            $table->string('rcs')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('franchise_tva')->default(false);
            $table->unsignedInteger('default_vat')->default(2000);
            $table->unsignedInteger('payment_days')->default(30);
            $table->string('late_penalty')->nullable();
            $table->string('number_prefix')->default('FA');
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedInteger('year')->nullable();
            $table->foreignId('client_table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->string('kind')->default('facture');
            $table->string('status')->default('draft');
            $table->string('client_name');
            $table->text('client_address')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_siret')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->json('lines');
            $table->unsignedInteger('total_ht')->default(0);
            $table->unsignedInteger('total_tva')->default(0);
            $table->unsignedInteger('total_ttc')->default(0);
            $table->text('notes')->nullable();
            $table->string('source_template')->nullable();
            $table->foreignId('client_row_id')->nullable()->constrained('rows')->nullOnDelete();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('workspace_invoice_settings');
        Schema::dropIfExists('workspace_templates');
    }
};
