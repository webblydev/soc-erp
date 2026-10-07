<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lookups without extra columns (docs/05 §3.1, spec §3).
     *
     * @var list<string>
     */
    private array $plain = ['mb_directions', 'inspection_statuses', 'finding_categories'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->plain as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->lookupColumns();
            });
        }

        Schema::create('estimate_kinds', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('has_work_lines')->default(true);
            $table->boolean('has_material_lines')->default(false);
            $table->boolean('is_customer_facing')->default(false);
        });

        Schema::create('estimate_statuses', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_approved')->default(false);
        });

        Schema::create('cost_categories', function (Blueprint $table) {
            $table->lookupColumns();
            $table->unsignedBigInteger('default_account_id')->nullable();
        });

        Schema::create('mb_statuses', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('is_billable')->default(false);
            $table->boolean('is_locked')->default(false);
        });

        Schema::create('inspection_types', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('allowed_after_completion')->default(false);
        });

        Schema::create('finding_severities', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('requires_follow_up')->default(false);
        });

        Schema::create('finding_statuses', function (Blueprint $table) {
            $table->lookupColumns();
            $table->boolean('is_closed')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['finding_statuses', 'finding_severities', 'inspection_types', 'mb_statuses', 'cost_categories', 'estimate_statuses', 'estimate_kinds', ...array_reverse($this->plain)] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
