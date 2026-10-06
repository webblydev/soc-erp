<?php

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;

final class BlueprintMacros
{
    /**
     * Register the schema macros for the shared column blocks in docs/00 §4.2.
     */
    public static function register(): void
    {
        Blueprint::macro('lookupColumns', function (): void {
            /** @var Blueprint $this */
            $this->id();
            $this->string('code', 40)->unique();
            $this->string('name', 120);
            $this->string('description')->nullable();
            $this->smallInteger('sort_order')->default(0);
            $this->string('color', 20)->nullable();
            $this->boolean('is_active')->default(true);
            $this->boolean('is_system')->default(false);
            $this->timestamps();
            $this->softDeletes();
        });

        Blueprint::macro('auditColumns', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users');
            $this->foreignId('updated_by')->nullable()->constrained('users');
        });
    }
}
