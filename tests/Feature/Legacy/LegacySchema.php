<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| A small stand-in for the v1 database: the tables and columns the legacy importers read, on an
| in-memory SQLite `legacy` connection. Rows in tests are invented.
*/

/**
 * Point the `legacy` connection at a fresh in-memory database with the v1 tables.
 */
function useLegacyDatabase(): void
{
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
    DB::purge('legacy');

    $schema = Schema::connection('legacy');

    $schema->create('tbl_employee', function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->string('code')->nullable();
        $table->string('name')->nullable();
        $table->integer('post_id')->nullable();
        $table->integer('department_id')->nullable();
        $table->string('father_name')->nullable();
        $table->string('mother_name')->nullable();
        $table->string('dob')->nullable();
        $table->string('gender')->nullable();
        $table->string('marital_status')->nullable();
        $table->string('present_address')->nullable();
        $table->string('permanent_address')->nullable();
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        $table->string('reference')->nullable();
        $table->string('status')->default('a');
        $table->string('added_date')->nullable();
        $table->string('update_date')->nullable();
    });

    $schema->create('tbl_user', function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->string('name')->nullable();
        $table->string('user_name');
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('type')->default('u');
        $table->string('team_name')->nullable();
        $table->string('status')->default('a');
        $table->integer('employee_id')->nullable();
        $table->string('add_time')->nullable();
    });

    $schema->create('tbl_client', function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->string('client_id')->nullable();
        $table->integer('client_type_id')->nullable();
        $table->integer('project_id')->nullable();
        $table->string('client_name')->nullable();
        $table->string('org_name')->nullable();
        $table->string('phone')->nullable();
        $table->string('org_mobile')->nullable();
        $table->string('email')->nullable();
        $table->string('w_number')->nullable();
        $table->string('address')->nullable();
        $table->integer('area_id')->nullable();
        $table->string('requirement')->nullable();
        $table->string('sold')->nullable();
        $table->text('note')->nullable();
        $table->string('date')->nullable();
        $table->string('level')->nullable();
        $table->string('source')->nullable();
        $table->string('reminder')->nullable();
        $table->text('comment')->nullable();
        $table->string('status')->default('p');
        $table->integer('add_by')->nullable();
        $table->string('add_time')->nullable();
    });

    $schema->create('tbl_clientdetails', function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->integer('client_id');
        $table->string('date')->nullable();
        $table->text('note')->nullable();
        $table->string('added_by')->nullable();
        $table->string('added_date')->nullable();
    });

    $schema->create('tbl_type', function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->string('name');
        $table->string('status')->default('a');
    });

    $schema->create('tbl_project', function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->string('project_id');
        $table->integer('project_type_id')->default(0);
        $table->string('name');
        $table->string('status')->default('a');
        $table->string('add_by')->nullable();
        $table->string('add_time')->nullable();
        $table->string('update_time')->nullable();
    });

    $schema->create('tbl_task', function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->string('client_id')->default('');
        $table->string('file_number')->default('');
        $table->string('entry_date')->nullable();
        $table->string('project_id')->nullable();
        $table->integer('projectId')->default(0);
        $table->string('project_name')->default('');
        $table->string('client_name')->default('');
        $table->string('client_phone')->default('');
        $table->text('task_detail')->nullable();
        $table->integer('type_id')->default(0);
        $table->integer('client_list_id')->default(0);
        $table->integer('assign_to')->default(0);
        $table->integer('support_id')->default(0);
        $table->string('deadline')->nullable();
        $table->string('completed_date')->nullable();
        $table->text('completed_by_comment')->nullable();
        $table->integer('assign_by')->nullable();
        $table->string('status')->default('p');
        $table->string('is_important')->default('false');
    });

    foreach (['tbl_work_estimate' => 'Work_Estimate_SlNo', 'tbl_project_material_estimate' => 'Material_Estimate_SlNo'] as $name => $key) {
        $schema->create($name, function (Blueprint $table) use ($key) {
            $table->integer($key)->primary();
            $table->string('project_id');
            $table->string('work_name')->nullable();
            $table->string('address')->nullable();
            $table->string('date')->nullable();
            $table->string('status')->default('a');
            $table->string('AddTime')->nullable();
        });
    }

    $schema->create('tbl_work_estimate_details', function (Blueprint $table) {
        $table->integer('Work_Estimate_Details_SlNo')->primary();
        $table->string('work_estimate_id');
        $table->string('work_description')->nullable();
        $table->string('level')->nullable();
        $table->string('location')->default('');
        $table->string('measurement')->default('');
        $table->float('length')->default(0);
        $table->float('width')->default(0);
        $table->float('height')->default(0);
        $table->float('nose')->default(0);
        $table->string('unit')->default('');
        $table->decimal('quantity', 12, 2)->default(0);
        $table->string('status')->default('a');
    });

    $schema->create('tbl_project_material_estimate_details', function (Blueprint $table) {
        $table->integer('Estimate_Details_SlNo')->primary();
        $table->string('material_estimate_id');
        $table->string('material_name')->nullable();
        $table->integer('material_id')->default(0);
        $table->string('unit')->nullable();
        $table->string('total_estimated_qty')->default('');
        $table->string('purpose_estimate')->default('');
        $table->string('status')->default('a');
    });

    $schema->create('tbl_project_visit', function (Blueprint $table) {
        $table->integer('Project_Visit_SlNo')->primary();
        $table->string('project_id');
        $table->string('construction_id')->default('');
        $table->string('constructor_name')->nullable();
        $table->string('project_eng_name')->nullable();
        $table->string('permitee_name')->nullable();
        $table->string('inspection_type_weekly')->nullable();
        $table->string('location')->nullable();
        $table->string('field_office_phone')->default('');
        $table->string('inspection_date')->default('0000-00-00');
        $table->string('start_time')->nullable();
        $table->string('end_time')->nullable();
        $table->string('inspection_type')->default('');
        $table->string('inspection_type_event')->nullable();
        $table->text('description')->nullable();
        $table->string('status')->default('a');
        $table->string('AddTime')->nullable();
    });

    $schema->create('tbl_project_visit_details', function (Blueprint $table) {
        $table->integer('Project_Visit_Details_SlNo')->primary();
        $table->string('project_visit_id');
        $table->string('visit_location')->nullable();
        $table->string('visit_description')->nullable();
        $table->string('visit_finding')->default('');
        $table->string('visit_regarding')->default('');
        $table->string('status')->default('a');
    });
}

/**
 * Insert a v1 row and return its id (the `id` column, else the first column given).
 *
 * @param  array<string, mixed>  $attributes
 */
function legacyRow(string $table, array $attributes): int
{
    DB::connection('legacy')->table($table)->insert($attributes);

    return (int) ($attributes['id'] ?? reset($attributes));
}
