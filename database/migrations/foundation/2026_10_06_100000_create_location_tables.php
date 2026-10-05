<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_levels', function (Blueprint $table) {
            $table->lookupColumns();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('location_level_id')->constrained('location_levels');
            $table->string('name', 120);
            $table->string('name_bn', 120)->nullable();
            $table->string('full_path', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['parent_id', 'name']);
            $table->index('parent_id');
        });

        // Prefix index (docs/01 §3.7); the prefix syntax is MySQL-only.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX locations_full_path_index ON locations (full_path(191))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('location_levels');
    }
};
