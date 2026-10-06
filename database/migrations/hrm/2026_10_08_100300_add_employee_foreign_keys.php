<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points the employee columns that Foundation and Catalog created before HRM existed at the
 * employees table (spec H8, H11).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->foreign('manager_employee_id')->references('id')->on('employees')->restrictOnDelete();
        });

        Schema::table('business_lines', function (Blueprint $table) {
            $table->foreign('manager_employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_lines', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });
    }
};
