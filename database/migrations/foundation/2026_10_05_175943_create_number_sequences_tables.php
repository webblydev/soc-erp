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
        Schema::create('number_sequence_formats', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40)->unique();
            $table->string('format', 80);
            $table->string('reset_policy', 20)->default('never');
            $table->string('scope_by', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40);
            $table->string('scope_key', 60)->default('');
            $table->string('format', 80);
            $table->unsignedInteger('next_number')->default(1);
            $table->string('reset_policy', 20);
            $table->timestamps();

            $table->unique(['document_type', 'scope_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('number_sequence_formats');
    }
};
