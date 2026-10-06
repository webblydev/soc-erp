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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 40)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('full_name', 160);
            $table->string('father_name', 150)->nullable();
            $table->string('mother_name', 150)->nullable();
            $table->foreignId('gender_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->foreignId('marital_status_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('blood_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('nid_number', 30)->nullable()->unique();
            $table->string('phone', 30)->index();
            $table->string('personal_email', 150)->nullable();
            $table->string('official_email', 150)->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_relation', 60)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('reference', 255)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->foreignId('department_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('designation_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('employee_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_status_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->index()->constrained('employees')->restrictOnDelete();
            $table->date('joining_date');
            $table->date('confirmation_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->foreignId('exit_reason_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_account_no', 60)->nullable();
            $table->string('mobile_wallet_no', 30)->nullable();
            $table->decimal('gross_salary', 18, 2)->nullable();
            $table->string('tin', 30)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('legacy_employee_id')->nullable();
            $table->timestamp('probation_notified_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();

            if (DB::getDriverName() === 'mysql') {
                $table->fullText('full_name');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_employee_id']);
        });

        Schema::dropIfExists('employees');
    }
};
