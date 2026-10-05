<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\NumberSequenceFormat;
use Illuminate\Database\Seeder;

class NumberSequenceFormatSeeder extends Seeder
{
    /**
     * Default formats from docs/00 §5. Existing rows (possibly edited by an admin) are kept.
     *
     * @var array<string, array{0: string, 1: string, 2?: string}>
     */
    private const FORMATS = [
        'lead' => ['L-{seq:6}', 'never'],
        'customer' => ['C-{seq:6}', 'never'],
        'project' => ['{bl_prefix}-{seq:4}', 'never', 'business_line'],
        'task' => ['T-{yy}-{seq:5}', 'fiscal_year'],
        'estimate' => ['EST-{yy}-{seq:4}', 'fiscal_year'],
        'site_inspection' => ['SI-{yy}-{seq:4}', 'fiscal_year'],
        'mb_entry' => ['MB-{yy}-{seq:5}', 'fiscal_year'],
        'customer_running_bill' => ['RB-{yy}-{seq:4}', 'fiscal_year'],
        'invoice' => ['INV-{yy}-{seq:5}', 'fiscal_year'],
        'receipt' => ['RCV-{yy}-{seq:5}', 'fiscal_year'],
        'credit_note' => ['CN-{yy}-{seq:4}', 'fiscal_year'],
        'work_order' => ['WO-{yy}-{seq:4}', 'fiscal_year'],
        'vendor_bill' => ['BILL-{yy}-{seq:5}', 'fiscal_year'],
        'payment_voucher' => ['PV-{yy}-{seq:5}', 'fiscal_year'],
        'expense' => ['EXP-{yy}-{seq:5}', 'fiscal_year'],
        'employee_advance' => ['ADV-{yy}-{seq:4}', 'fiscal_year'],
        'contra' => ['CT-{yy}-{seq:5}', 'fiscal_year'],
        'journal' => ['JV-{yy}-{seq:5}', 'fiscal_year'],
        'employee' => ['EMP-{seq:4}', 'never'],
        'vendor' => ['V-{seq:4}', 'never'],
    ];

    public function run(): void
    {
        foreach (self::FORMATS as $type => $definition) {
            NumberSequenceFormat::query()->firstOrCreate(['document_type' => $type], [
                'format' => $definition[0],
                'reset_policy' => $definition[1],
                'scope_by' => $definition[2] ?? null,
            ]);
        }
    }
}
