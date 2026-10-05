<?php

namespace Database\Factories\Foundation;

use App\Modules\Foundation\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'code' => Str::snake(Str::limit($name, 30, '')),
            'name' => $name,
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
