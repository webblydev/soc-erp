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
        Schema::create('project_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_authority_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_type_id')->constrained()->restrictOnDelete();
            $table->string('reference_no', 100)->nullable();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('approval_status_id')->index()->constrained()->restrictOnDelete();
            $table->date('prepared_on')->nullable();
            $table->date('submitted_on')->nullable();
            $table->date('expected_on')->nullable();
            $table->date('approved_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->decimal('authority_fee', 18, 2)->nullable();
            $table->unsignedBigInteger('fee_expense_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
        });

        Schema::create('project_approval_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_approval_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_status_id')->constrained()->restrictOnDelete();
            $table->date('event_date');
            $table->text('note')->nullable();
            $table->foreignId('attachment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('approval_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_approval_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('attachment_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('sort_order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_checklist_items');
        Schema::dropIfExists('project_approval_events');
        Schema::dropIfExists('project_approvals');
    }
};
