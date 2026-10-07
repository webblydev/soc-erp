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
        Schema::create('site_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_number', 40)->unique();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_type_id')->constrained()->restrictOnDelete();
            $table->date('inspection_date')->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('site_address')->nullable();
            $table->string('permittee_name', 150)->nullable();
            $table->string('contractor_name', 150)->nullable();
            $table->unsignedBigInteger('contractor_vendor_id')->nullable();
            $table->foreignId('project_engineer_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('project_engineer_name', 150)->nullable();
            $table->string('field_office_phone', 30)->nullable();
            $table->string('weather', 60)->nullable();
            $table->unsignedSmallInteger('workers_on_site')->nullable();
            $table->text('work_progress_summary')->nullable();
            $table->foreignId('inspection_status_id')->index()->constrained()->restrictOnDelete();
            $table->string('client_representative', 150)->nullable();
            $table->unsignedInteger('legacy_visit_ref')->nullable()->unique();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
        });

        Schema::create('site_inspection_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('location', 150)->nullable();
            $table->text('description');
            $table->text('finding')->nullable();
            $table->foreignId('finding_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('finding_severity_id')->constrained()->restrictOnDelete();
            $table->text('action_required')->nullable();
            $table->string('responsible_type', 20)->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->date('due_date')->nullable();
            $table->string('found_by_name', 150)->nullable();
            $table->foreignId('finding_status_id')->constrained()->restrictOnDelete();
            $table->date('closed_on')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('closure_note')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->unsignedInteger('legacy_detail_ref')->nullable()->unique();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->auditColumns();
            $table->index(['project_id', 'finding_status_id'], 'findings_project_status_index');
            $table->index(['responsible_type', 'responsible_id'], 'findings_responsible_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_inspection_findings');
        Schema::dropIfExists('site_inspections');
    }
};
