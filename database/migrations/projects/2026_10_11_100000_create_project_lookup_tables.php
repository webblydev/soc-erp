<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lookups without extra columns (docs/04 §3.1, spec §3).
     *
     * @var list<string>
     */
    private array $plain = ['project_phases', 'project_roles', 'task_priorities', 'approval_authorities', 'hold_reasons', 'project_service_statuses', 'contract_statuses', 'schedule_triggers', 'schedule_statuses'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->plain as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->lookupColumns();
            });
        }

        Schema::create('project_types', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_billable')->default(true);
        });

        Schema::create('project_statuses', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('is_open')->default(true);
            $table->boolean('is_closed')->default(false);
            $table->boolean('allows_billing')->default(true);
            $table->boolean('allows_costing')->default(true);
        });

        Schema::create('task_types', function (Blueprint $table) {
            $table->lookupColumns();
            $table->unsignedSmallInteger('default_estimated_hours')->nullable();
        });

        Schema::create('task_statuses', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('is_done')->default(false);
            $table->boolean('is_cancelled')->default(false);
        });

        Schema::create('approval_types', function (Blueprint $table) {
            $table->lookupColumns();
            $table->unsignedSmallInteger('typical_days')->nullable();
            $table->text('default_checklist')->nullable();
        });

        Schema::create('approval_statuses', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('is_final')->default(false);
            $table->boolean('is_success')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['approval_statuses', 'approval_types', 'task_statuses', 'task_types', 'project_statuses', 'project_types', ...array_reverse($this->plain)] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
