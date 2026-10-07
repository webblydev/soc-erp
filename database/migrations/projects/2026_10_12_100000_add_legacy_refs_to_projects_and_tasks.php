<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The v1 row id, kept on projects and tasks copied from v1 (legacy seed spec L13).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('legacy_project_ref')->nullable()->index();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('legacy_task_ref')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['legacy_task_ref']);
            $table->dropColumn('legacy_task_ref');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['legacy_project_ref']);
            $table->dropColumn('legacy_project_ref');
        });
    }
};
