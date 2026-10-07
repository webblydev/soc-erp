<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->restrictOnDelete();
            $table->string('contract_number', 60)->nullable();
            $table->date('agreement_date');
            $table->decimal('deed_amount', 18, 2)->default(0);
            $table->boolean('vat_inclusive')->default(false);
            $table->decimal('advance_pct', 7, 4)->nullable();
            $table->decimal('retention_pct', 7, 4)->nullable();
            $table->unsignedSmallInteger('defect_liability_months')->nullable();
            $table->string('signed_by_customer', 150)->nullable();
            $table->foreignId('signed_by_company_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('contract_status_id')->constrained()->restrictOnDelete();
            $table->text('terms')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('project_contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_contract_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('amendment_no');
            $table->date('amendment_date');
            $table->text('reason');
            $table->string('status', 20)->default('draft');
            $table->decimal('value_change', 18, 2)->nullable();
            $table->decimal('new_deed_amount', 18, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
            $table->unique(['project_contract_id', 'amendment_no']);
        });

        Schema::create('project_contract_amendment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_contract_amendment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('rate', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->foreignId('project_service_status_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->string('milestone_name', 200);
            $table->foreignId('schedule_trigger_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('trigger_ref_id')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('percent', 7, 4)->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->foreignId('schedule_status_id')->index()->constrained()->restrictOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
        Schema::dropIfExists('project_contract_amendment_lines');
        Schema::dropIfExists('project_contract_amendments');
        Schema::dropIfExists('project_contracts');
    }
};
