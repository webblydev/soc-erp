<?php

namespace Database\Seeders\Catalog;

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\PricingBasis;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceCategory;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Legacy sellable services (docs/02 §3.3). The category and business line mapping is the
 * spec §3.1 proposal and must be confirmed by SOC.
 */
class ServiceSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3?: array<string, mixed>}> code => [name, category, business line, extra] */
    private const SERVICES = [
        'BD' => ['Building Design Work', 'DESIGN', 'BD'],
        'BDRA' => ['Building Design & RAJUK Approval Work', 'APPROVAL', 'BDRA', ['requires_approval_tracking' => true]],
        'INT-DESIGN' => ['Interior Design Work', 'DESIGN', 'INT'],
        'INT-WORK' => ['Interior Design & Work', 'WORKS', 'INT'],
        'INT-EXT' => ['Interior & Exterior Work', 'WORKS', 'INT'],
        'RENOVATION' => ['Building Renovation Work', 'WORKS', 'CON'],
        'ENGG' => ['Engineering Service', 'ENGG', 'CON'],
        'STRUCT' => ['Structural Design & Supervision', 'ENGG', 'CON'],
        'BLE' => ['Bank Loan Estimate', 'ESTIMATE', 'CON-BLE'],
        'UPS' => ['Union Parishad Sheet', 'ESTIMATE', 'CON-UPS'],
        'DSW' => ['Digital Survey Work', 'SURVEY', 'DSW'],
        'SOIL' => ['Soil Test Work', 'SURVEY', 'CON'],
        'ESTIMATE' => ['Estimating Work', 'ESTIMATE', 'CON'],
        'CETP' => ['CETP & ATP Program', 'TRAINING', 'CETP', ['pricing' => PricingBasis::PER_UNIT, 'unit' => 'participant']],
    ];

    public function run(): void
    {
        $categories = ServiceCategory::query()->pluck('id', 'code');
        $lines = BusinessLine::query()->pluck('id', 'code');
        $bases = PricingBasis::query()->pluck('id', 'code');
        $units = Unit::query()->pluck('id', 'code');

        foreach (self::SERVICES as $code => $service) {
            $extra = $service[3] ?? [];

            Service::query()->withTrashed()->firstOrCreate(['code' => $code], [
                'name' => $service[0],
                'service_category_id' => $categories[$service[1]],
                'business_line_id' => $lines[$service[2]],
                'pricing_basis_id' => $bases[$extra['pricing'] ?? PricingBasis::FIXED],
                'default_unit_id' => isset($extra['unit']) ? $units[$extra['unit']] : null,
                'requires_approval_tracking' => $extra['requires_approval_tracking'] ?? false,
            ]);
        }
    }
}
