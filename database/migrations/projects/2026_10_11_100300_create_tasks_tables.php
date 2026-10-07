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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_number', 40)->unique();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->restrictOnDelete();
            $table->foreignId('task_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_phase_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_number', 60)->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('support_officer_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('reviewer_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('task_priority_id')->constrained()->restrictOnDelete();
            $table->boolean('is_important')->default(false);
            $table->foreignId('task_status_id')->constrained()->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->default(0);
            $table->unsignedTinyInteger('progress_pct')->default(0);
            $table->dateTime('archived_at')->nullable()->index();
            $table->string('blocked_reason')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
            $table->index(['project_id', 'task_status_id']);
            $table->index(['assignee_employee_id', 'task_status_id', 'due_date']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->smallInteger('sort_order')->default(0);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->decimal('hours', 6, 2);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('task_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['task_id', 'user_id']);
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->unique(['task_id', 'depends_on_task_id']);
        });

        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('project_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
        });

        Schema::create('task_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('task_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_phase_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('project_role_id')->nullable()->constrained()->restrictOnDelete();
            $table->smallInteger('offset_days_start')->default(0);
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->json('checklist')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->foreignId('depends_on_item_id')->nullable()->constrained('task_template_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['task_template_items', 'task_templates', 'task_dependencies', 'task_watchers', 'task_time_logs', 'task_comments', 'task_checklist_items', 'tasks'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
