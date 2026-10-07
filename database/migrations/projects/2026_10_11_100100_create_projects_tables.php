<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_number', 40)->unique();
            $table->string('name');
            $table->foreignId('customer_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('business_line_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('project_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_status_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('project_phase_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('source_lead_id')->nullable()->constrained('leads')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->text('site_address')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('plot_no', 60)->nullable();
            $table->decimal('land_area', 12, 4)->nullable();
            $table->foreignId('land_area_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->unsignedSmallInteger('floors')->nullable();
            $table->unsignedSmallInteger('basements')->nullable();
            $table->decimal('built_up_area_sft', 14, 2)->nullable();
            $table->foreignId('project_manager_id')->nullable()->index()->constrained('employees')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('support_officer_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->date('start_date')->nullable()->index();
            $table->date('expected_end_date')->nullable();
            $table->date('handover_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('contract_value', 18, 2)->default(0);
            $table->decimal('budget_cost', 18, 2)->default(0);
            $table->decimal('retention_pct', 7, 4)->nullable();
            $table->foreignId('hold_reason_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->decimal('completion_pct', 5, 2)->default(0);
            $table->json('legacy_project_ids')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();

            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['name', 'site_address']);
            }
        });

        Schema::create('project_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('rate', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->unsignedBigInteger('vat_rate_id')->nullable();
            $table->foreignId('project_service_status_id')->constrained()->restrictOnDelete();
            $table->date('delivered_on')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('project_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('project_role_id')->constrained()->restrictOnDelete();
            $table->decimal('allocation_pct', 5, 2)->nullable();
            $table->date('assigned_on');
            $table->date('released_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->index(['project_id', 'employee_id', 'project_role_id', 'is_active'], 'project_employees_active_role_index');
        });

        Schema::create('project_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('project_statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->nullable()->constrained('project_statuses')->restrictOnDelete();
            $table->foreignId('from_phase_id')->nullable()->constrained('project_phases')->restrictOnDelete();
            $table->foreignId('to_phase_id')->nullable()->constrained('project_phases')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_status_histories');
        Schema::dropIfExists('project_employees');
        Schema::dropIfExists('project_services');
        Schema::dropIfExists('projects');
    }
};
