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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 40);
            $table->unsignedBigInteger('attachable_id');
            $table->foreignId('document_type_id')->nullable()->constrained();
            $table->string('title', 200)->nullable();
            $table->string('disk', 20);
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('replaces_attachment_id')->nullable()->constrained('attachments');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
