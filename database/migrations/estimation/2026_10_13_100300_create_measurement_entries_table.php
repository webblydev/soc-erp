<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Measurement Book (docs/05 §3.7). Work order and running bill ids have no FK until 06 / 07 (spec E2).
     */
    public function up(): void
    {
        Schema::create('measurement_entries', function (Blueprint $table) {
            $table->id();
            $table->string('mb_number', 40)->unique();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('mb_direction_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('work_order_id')->nullable()->index();
            $table->unsignedBigInteger('work_order_item_id')->nullable();
            $table->foreignId('estimate_line_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('mb_book_no', 30)->nullable();
            $table->string('mb_page_no', 30)->nullable();
            $table->date('measured_on');
            $table->foreignId('measured_by')->constrained('employees')->restrictOnDelete();
            $table->foreignId('work_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description');
            $table->string('location', 120)->nullable();
            $table->string('measurement_formula', 20);
            $table->decimal('nos', 18, 4)->nullable();
            $table->decimal('length', 18, 4)->nullable();
            $table->decimal('width', 18, 4)->nullable();
            $table->decimal('height', 18, 4)->nullable();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('rate', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->decimal('achievement_pct', 7, 4)->nullable();
            $table->foreignId('mb_status_id')->constrained()->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('running_bill_line_id')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
            $table->index(['project_id', 'mb_direction_id', 'mb_status_id'], 'mb_project_direction_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measurement_entries');
    }
};
