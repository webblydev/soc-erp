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
        Schema::create('company_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('short_name', 40)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 150)->nullable();
            $table->string('tin', 30)->nullable();
            $table->string('bin', 30)->nullable();
            $table->string('trade_license_no', 60)->nullable();
            $table->foreignId('base_currency_id')->constrained('currencies');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(7);
            $table->string('print_footer')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profile');
    }
};
