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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('default_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->decimal('default_rate', 18, 4)->nullable();
            $table->foreignId('pricing_basis_id')->constrained('pricing_bases')->restrictOnDelete();
            $table->unsignedBigInteger('revenue_account_id')->nullable()->index();
            $table->unsignedBigInteger('vat_rate_id')->nullable()->index();
            $table->boolean('requires_approval_tracking')->default(false);
            $table->unsignedBigInteger('default_task_template_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
