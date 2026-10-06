<?php

namespace Database\Factories\Foundation;

use App\Modules\Foundation\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('doc_????'),
            'name' => fake()->words(2, true),
            'allowed_mimes' => null,
            'max_size_mb' => 20,
            'is_active' => true,
        ];
    }
}
