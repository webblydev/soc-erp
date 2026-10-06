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
}

/**
 * Insert a v1 row and return its id.
 *
 * @param  array<string, mixed>  $attributes
 */
function legacyRow(string $table, array $attributes): int
{
    DB::connection('legacy')->table($table)->insert($attributes);

    return (int) $attributes['id'];
}
