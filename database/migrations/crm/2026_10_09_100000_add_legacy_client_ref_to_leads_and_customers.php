<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The v1 client row id, kept on leads and customers copied from v1. The v1 client code
 * (legacy_client_id) repeats and is sometimes blank, so it cannot identify a row.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['leads', 'customers'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('legacy_client_ref')->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['leads', 'customers'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['legacy_client_ref']);
                $table->dropColumn('legacy_client_ref');
            });
        }
    }
};
