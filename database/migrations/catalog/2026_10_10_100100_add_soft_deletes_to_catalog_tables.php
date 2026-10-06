<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every delete is a soft delete: work items, materials and the Catalog lookup tables keep deleted rows.
 * Lookup tables created after lookupColumns gained deleted_at already have it.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['work_items', 'materials', 'business_lines', 'service_categories', 'pricing_bases', 'units', 'unit_kinds', 'work_item_categories', 'material_categories'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasColumn($name, 'deleted_at')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
