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
        Schema::create('sales_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('business_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('monthly_target_amount', 18, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('sales_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'left_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_team_members');
        Schema::dropIfExists('sales_teams');
    }
};
