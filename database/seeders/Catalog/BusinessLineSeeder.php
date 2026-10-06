<?php

namespace Database\Seeders\Catalog;

use App\Modules\Catalog\Models\BusinessLine;
use Illuminate\Database\Seeder;

/**
 * Business lines from docs/02 §3.1. AMZ, EBC, CON-PWE and TSE carry their code as the name until
 * SOC confirms their meaning (docs/02 open question 1). Existing rows are never overwritten.
 */
class BusinessLineSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3?: bool}> */
    private const LINES = [
        ['BD', 'Building Design', 'SOC-BD'],
        ['BDRA', 'Building Design & RAJUK Approval', 'SOC-BD&RA'],
        ['CON', 'Construction & Engineering Services', 'SOC-CON'],
        ['CON-BLE', 'Bank Loan Estimate', 'SOC-CON-BLE'],
        ['CON-UPS', 'Union Parishad Sheet', 'SOC-CON-UPS'],
        ['CON-PWE', 'CON-PWE', 'SOC-CON-PWE'],
        ['INT', 'Interior Design & Work', 'SOC-INT'],
        ['CETP', 'Civil Engineering Training Program', 'SOC-CETP'],
        ['DSW', 'Digital Survey Work', 'SOC-DSW'],
        ['AMZ', 'AMZ', 'SOC-AMZ'],
        ['EBC', 'EBC', 'SOC-EBC'],
        ['TSE', 'TSE (M&S)', 'SOC-TSE'],
        ['AGENT', 'Agent / Referral Channel', 'SOC-AGENT'],
        ['MGT', 'Management', 'SOC-MANAGEMENT', true],
        ['MS', 'Marketing & Sales', 'SOC-M&S', true],
    ];

    public function run(): void
    {
        foreach (self::LINES as $order => $line) {
            BusinessLine::query()->firstOrCreate(['code' => $line[0]], [
                'name' => $line[1],
                'project_prefix' => $line[2],
                'is_internal' => $line[3] ?? false,
                'sort_order' => $order + 1,
            ]);
        }
    }
}
