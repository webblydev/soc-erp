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
        Schema::create('departments', function (Blueprint $table) {
            $table->lookupColumns();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->restrictOnDelete();
            // The FK to employees is added with the employees table.
            $table->unsignedBigInteger('head_employee_id')->nullable()->index();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->lookupColumns();
            $table->string('grade', 20)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
        });

        Schema::create('employee_statuses', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('is_active_employment')->default(true);
            $table->boolean('is_exit')->default(false);
        });

        Schema::create('employee_document_types', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('has_expiry')->default(false);
        });

        foreach (['employee_types', 'genders', 'marital_statuses', 'blood_groups', 'employment_event_types', 'exit_reasons'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->lookupColumns();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['exit_reasons', 'employment_event_types', 'blood_groups', 'marital_statuses', 'genders', 'employee_types', 'employee_document_types', 'employee_statuses', 'designations', 'departments'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
