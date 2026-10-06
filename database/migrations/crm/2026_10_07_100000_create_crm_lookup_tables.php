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
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('requires_referrer')->default(false);
        });

        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->lookupColumns();
            $table->unsignedTinyInteger('probability_pct')->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('is_closed')->default(false);
        });

        foreach (['lead_priorities', 'lead_levels', 'lost_reasons', 'activity_outcomes', 'customer_types'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->lookupColumns();
            });
        }

        Schema::create('activity_types', function (Blueprint $table) {
            $table->lookupColumns();
            $table->string('icon', 40)->nullable();
            $table->boolean('requires_duration')->default(false);
            $table->boolean('counts_as_contact')->default(false);
        });

        Schema::create('customer_statuses', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('is_blocked')->default(false);
        });

        Schema::create('payment_terms', function (Blueprint $table) {
            $table->lookupColumns();
            $table->unsignedSmallInteger('days')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['payment_terms', 'customer_statuses', 'activity_types', 'customer_types', 'activity_outcomes', 'lost_reasons', 'lead_levels', 'lead_priorities', 'lead_statuses', 'lead_sources'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
