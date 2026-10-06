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
        Schema::create('business_lines', function (Blueprint $table) {
            $table->lookupColumns();
            $table->string('project_prefix', 30)->unique();
            $table->unsignedBigInteger('revenue_account_id')->nullable()->index();
            $table->unsignedBigInteger('manager_employee_id')->nullable()->index();
            $table->boolean('is_internal')->default(false);
        });

        foreach (['service_categories', 'pricing_bases', 'unit_kinds', 'work_item_categories', 'material_categories'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->lookupColumns();
            });
        }

        Schema::create('units', function (Blueprint $table) {
            $table->lookupColumns();
            $table->string('symbol', 15);
            $table->foreignId('unit_kind_id')->constrained('unit_kinds')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['units', 'material_categories', 'work_item_categories', 'unit_kinds', 'pricing_bases', 'service_categories', 'business_lines'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
