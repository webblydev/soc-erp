<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Document types from docs/01 §3.9. Null extensions allow the global list (FD-BR-08).
 */
class DocumentTypeSeeder extends Seeder
{
    private const TYPES = [
        'drawing' => ['Drawing', ['pdf', 'dwg', 'dxf', 'jpg', 'jpeg', 'png']],
        'approval_letter' => ['Approval Letter', null],
        'deed' => ['Deed / Agreement', null],
        'invoice' => ['Invoice', null],
        'bill' => ['Bill', null],
        'money_receipt' => ['Money Receipt', null],
        'site_photo' => ['Site Photo', ['jpg', 'jpeg', 'png', 'webp']],
        'soil_report' => ['Soil Report', null],
        'id_copy' => ['ID Copy', ['pdf', 'jpg', 'jpeg', 'png', 'webp']],
        'land_document' => ['Land Document', null],
        'estimate' => ['Estimate', null],
        'other' => ['Other', null],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::TYPES as $code => [$name, $extensions]) {
            DocumentType::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'allowed_mimes' => $extensions, 'sort_order' => ++$order, 'is_system' => true,
            ]);
        }
    }
}
