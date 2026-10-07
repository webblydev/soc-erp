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
        Schema::create('project_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('material_name', 200)->nullable();
            $table->string('description')->nullable();
            $table->decimal('budget_qty', 18, 4)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('budget_amount', 18, 2);
            $table->foreignId('source_estimate_id')->nullable()->constrained('estimates')->restrictOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->auditColumns();
            $table->index(['project_id', 'cost_category_id'], 'budget_lines_project_category_index');
        });

        Schema::create('project_budget_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('revision_no');
            $table->string('reason', 500)->nullable();
            $table->decimal('old_total', 18, 2);
            $table->decimal('new_total', 18, 2);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at');
            $table->timestamps();
            $table->unique(['project_id', 'revision_no'], 'budget_revisions_project_revision_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_budget_revisions');
        Schema::dropIfExists('project_budget_lines');
    }
};
