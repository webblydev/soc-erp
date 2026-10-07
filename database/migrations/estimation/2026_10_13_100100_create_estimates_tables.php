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
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->string('estimate_number', 40)->unique();
            $table->foreignId('estimate_kind_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('site_address')->nullable();
            $table->date('estimate_date');
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->foreignId('root_estimate_id')->nullable()->constrained('estimates')->restrictOnDelete();
            $table->foreignId('revised_from_id')->nullable()->constrained('estimates')->restrictOnDelete();
            $table->string('revision_purpose', 500)->nullable();
            $table->foreignId('estimate_status_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('employees')->restrictOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('overhead_pct', 7, 4)->nullable();
            $table->decimal('overhead_amount', 18, 2)->default(0);
            $table->decimal('profit_pct', 7, 4)->nullable();
            $table->decimal('profit_amount', 18, 2)->default(0);
            $table->decimal('vat_pct', 7, 4)->nullable();
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->boolean('is_customer_facing')->default(false);
            $table->text('notes')->nullable();
            $table->text('rejection_note')->nullable();
            $table->string('legacy_ref', 40)->nullable()->unique();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
            $table->index(['project_id', 'root_estimate_id'], 'estimates_project_root_index');
        });

        Schema::create('estimate_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('estimate_status_id')->constrained()->restrictOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('changed_at');
            $table->timestamps();
        });

        Schema::create('estimate_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('estimate_section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('origin_line_id')->nullable()->constrained('estimate_lines')->nullOnDelete();
            $table->string('line_no', 20);
            $table->foreignId('work_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description');
            $table->string('level', 60)->nullable();
            $table->string('location', 120)->nullable();
            $table->string('measurement_formula', 20);
            $table->decimal('nos', 18, 4)->default(1);
            $table->decimal('length', 18, 4)->nullable();
            $table->decimal('width', 18, 4)->nullable();
            $table->decimal('height', 18, 4)->nullable();
            $table->boolean('deduction')->default(false);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->boolean('quantity_is_manual')->default(false);
            $table->decimal('rate', 18, 4)->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->foreignId('cost_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('remarks')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('estimate_material_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('origin_line_id')->nullable()->constrained('estimate_material_lines')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('material_name', 200)->nullable();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('estimated_qty', 18, 4)->default(0);
            $table->decimal('wastage_pct', 7, 4)->nullable();
            $table->decimal('total_qty', 18, 4)->default(0);
            $table->decimal('rate', 18, 4)->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('purpose')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['estimate_material_lines', 'estimate_lines', 'estimate_sections', 'estimate_status_histories', 'estimates'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
