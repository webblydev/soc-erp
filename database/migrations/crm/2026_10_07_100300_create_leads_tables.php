<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number', 40)->unique();
            $table->date('lead_date')->index();
            $table->string('name', 150);
            $table->string('company_name', 200)->nullable();
            $table->string('phone', 30)->index();
            $table->string('office_phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable()->index();
            $table->string('email', 150)->nullable()->index();
            $table->text('address')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lead_source_id')->index()->constrained()->restrictOnDelete();
            $table->string('referrer_type', 20)->nullable();
            $table->unsignedBigInteger('referrer_id')->nullable();
            $table->string('referrer_name', 150)->nullable();
            $table->foreignId('business_line_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('lead_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lead_status_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('lead_priority_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_team_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->decimal('expected_value', 18, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('site_location_text')->nullable();
            $table->string('land_area', 60)->nullable();
            $table->unsignedSmallInteger('floors_planned')->nullable();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->dateTime('last_activity_at')->nullable();
            $table->timestamp('stale_notified_at')->nullable();
            $table->foreignId('lost_reason_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('lost_note')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->unsignedBigInteger('converted_project_id')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('legacy_client_id', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
            $table->index(['referrer_type', 'referrer_id']);

            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['name', 'company_name']);
            }
        });

        Schema::create('lead_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->string('notes')->nullable();
            $table->unique(['lead_id', 'service_id']);
        });

        Schema::create('lead_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('lead_statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->constrained('lead_statuses')->restrictOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->text('note')->nullable();
        });

        Schema::create('lead_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->string('reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_assignment_histories');
        Schema::dropIfExists('lead_status_histories');
        Schema::dropIfExists('lead_services');
        Schema::dropIfExists('leads');
    }
};
