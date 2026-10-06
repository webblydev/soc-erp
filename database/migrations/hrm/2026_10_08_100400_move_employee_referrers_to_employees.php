<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CRM employee referrers stored a user id until HRM existed; they now store an employee id
 * (spec H12). No employee rows exist to map to, so the user's name is kept as the typed
 * referrer name and the id is cleared.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('leads')->where('referrer_type', 'employee')->whereNotNull('referrer_id')->orderBy('id')
            ->each(function (object $lead): void {
                DB::table('leads')->where('id', $lead->id)->update([
                    'referrer_name' => $lead->referrer_name ?: DB::table('users')->where('id', $lead->referrer_id)->value('name'),
                    'referrer_id' => null,
                ]);
            });
    }

    /**
     * The cleared user ids cannot be restored; the names stay.
     */
    public function down(): void {}
};
