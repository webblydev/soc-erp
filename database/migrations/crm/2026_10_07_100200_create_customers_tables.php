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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number', 40)->unique();
            $table->foreignId('customer_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 200);
            $table->string('company_name', 200)->nullable();
            $table->string('phone', 30)->index();
            $table->string('alternate_phone', 30)->nullable()->index();
            $table->string('whatsapp', 30)->nullable()->index();
            $table->string('email', 150)->nullable()->index();
            $table->text('address')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('nid_or_reg_no', 40)->nullable();
            $table->string('tin', 30)->nullable();
            $table->string('bin', 30)->nullable();
            $table->foreignId('business_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('account_manager_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            // leads is created after customers, so the first-lead link has no FK constraint.
            $table->unsignedBigInteger('source_lead_id')->nullable()->index();
            $table->foreignId('lead_source_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('acquired_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('credit_limit', 18, 2)->nullable();
            $table->unsignedBigInteger('receivable_account_id')->nullable()->index();
            $table->foreignId('customer_status_id')->index()->constrained()->restrictOnDelete();
            $table->boolean('is_also_vendor')->default(false);
            $table->foreignId('merged_into_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('legacy_client_id', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();

            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['name', 'company_name']);
            }
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('designation', 100)->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email', 150)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
    }
};
