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
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_document_type_id')->constrained()->restrictOnDelete();
            $table->string('document_no', 60)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->foreignId('attachment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamp('expiry_notified_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('employment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employment_event_type_id')->constrained()->restrictOnDelete();
            $table->date('effective_date');
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('from_designation_id')->nullable()->constrained('designations')->restrictOnDelete();
            $table->foreignId('to_designation_id')->nullable()->constrained('designations')->restrictOnDelete();
            $table->decimal('from_salary', 18, 2)->nullable();
            $table->decimal('to_salary', 18, 2)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->auditColumns();
            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('employee_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('institution', 200);
            $table->string('degree', 150);
            $table->unsignedSmallInteger('from_year')->nullable();
            $table->unsignedSmallInteger('to_year')->nullable();
            $table->string('result', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('employee_experience', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('company', 200);
            $table->string('position', 150);
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['employee_experience', 'employee_education', 'employment_events', 'employee_documents'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
