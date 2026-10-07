<?php

namespace Database\Seeders\Projects;

use Illuminate\Database\Seeder;

class ProjectsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ProjectLookupSeeder::class, ProjectSettingSeeder::class, TaskTemplateSeeder::class]);
    }
}
