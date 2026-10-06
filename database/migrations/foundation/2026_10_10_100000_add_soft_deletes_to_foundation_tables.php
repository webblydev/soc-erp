<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every delete is a soft delete: roles and the Administration lookup tables keep deleted rows.
 * Lookup tables created after lookupColumns gained deleted_at already have it.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['roles', 'branches', 'currencies', 'location_levels', 'document_types'];

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
