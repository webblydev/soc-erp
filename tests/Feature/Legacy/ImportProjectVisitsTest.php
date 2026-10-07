<?php

use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use Database\Seeders\Estimation\EstimationSeeder;
use Database\Seeders\Legacy\ImportProjectVisits;
use Database\Seeders\Legacy\LegacyContext;

beforeEach(function () {
    useLegacyDatabase();
    seedProjects();
    $this->seed(EstimationSeeder::class);
    superAdmin(['username' => 'admin']);
    $this->engineer = Employee::factory()->create(['first_name' => 'Hafizul Islam', 'last_name' => 'Shohag', 'full_name' => 'Hafizul Islam Shohag']);
    Employee::factory()->create(['first_name' => 'Delower', 'last_name' => 'Hossain', 'full_name' => 'Delower Hossain']);
    Employee::factory()->create(['first_name' => 'Delower', 'last_name' => 'Hossain Khan', 'full_name' => 'Delower Hossain Khan']);
    $this->project = Project::factory()->create(['legacy_project_ref' => 51]);
    $this->context = new LegacyContext;
    $this->context->projects = [51 => $this->project->id];

    $this->visit = fn (array $row) => legacyRow('tbl_project_visit', [
        'project_id' => '51', 'constructor_name' => 'Shah Alam ', 'permitee_name' => 'MD : Atiar Rahman ', 'location' => 'Satarkul',
        'field_office_phone' => '01794544173', 'inspection_date' => '2025-01-29', 'start_time' => '08:30:00', 'end_time' => '19:10:00',
        'inspection_type_event' => '1', 'description' => 'Twelve columns cast', 'AddTime' => '2025-01-29 21:37:18', ...$row,
    ]);
    $this->detail = fn (array $row) => legacyRow('tbl_project_visit_details', ['visit_location' => '4TH FLOOR', 'visit_description' => 'Column lapping position', 'visit_finding' => 'MD. DELOWER HOSSAIN', 'visit_regarding' => 'yes', ...$row]);
});

test('a v1 visit becomes an inspection with the v1 fields (L19)', function () {
    ($this->visit)(['Project_Visit_SlNo' => 18, 'project_eng_name' => 'MD Hafizul Islam Shohag ']);
    ($this->visit)(['Project_Visit_SlNo' => 6, 'project_eng_name' => 'Deloar Hossain', 'inspection_type_weekly' => '1', 'inspection_date' => '0000-00-00', 'start_time' => '00:00:00', 'field_office_phone' => '0121555465112']);
    ($this->visit)(['Project_Visit_SlNo' => 2, 'status' => 'd']);
    ($this->visit)(['Project_Visit_SlNo' => 30, 'project_id' => '999']);

    expect(app(ImportProjectVisits::class)->run($this->context))->toBe(2);

    $event = SiteInspection::query()->where('legacy_visit_ref', 18)->firstOrFail();
    $weekly = SiteInspection::query()->where('legacy_visit_ref', 6)->firstOrFail();

    expect($event)
        ->inspection_number->toStartWith('SI-')
        ->project_engineer_id->toBe($this->engineer->id)
        ->project_engineer_name->toBe('MD Hafizul Islam Shohag')
        ->contractor_name->toBe('Shah Alam')
        ->site_address->toBe('Satarkul')
        ->field_office_phone->toBe('01794544173')
        ->start_time->toStartWith('08:30')
        ->work_progress_summary->toBe('Twelve columns cast')
        ->and($event->type->code)->toBe(InspectionType::EVENT)
        ->and($event->inspection_date->toDateString())->toBe('2025-01-29')
        ->and($weekly->type->code)->toBe(InspectionType::WEEKLY)
        ->and($weekly->inspection_date->toDateString())->toBe('2025-01-29')
        ->and($weekly->start_time)->toBeNull()
        ->and($weekly->project_engineer_id)->toBeNull()
        ->and($weekly->project_engineer_name)->toBe('Deloar Hossain')
        ->and($weekly->field_office_phone)->toBe('0121555465112');
});

test('visit details become findings with the regarding answer as their status (L20)', function () {
    ($this->visit)(['Project_Visit_SlNo' => 11]);
    ($this->detail)(['Project_Visit_Details_SlNo' => 1, 'project_visit_id' => '11']);
    ($this->detail)(['Project_Visit_Details_SlNo' => 2, 'project_visit_id' => '11', 'visit_regarding' => 'no_application', 'visit_description' => '']);
    ($this->detail)(['Project_Visit_Details_SlNo' => 3, 'project_visit_id' => '11', 'visit_regarding' => 'no']);
    ($this->detail)(['Project_Visit_Details_SlNo' => 4, 'project_visit_id' => '11', 'status' => 'd']);
    ($this->visit)(['Project_Visit_SlNo' => 12]);
    ($this->detail)(['Project_Visit_Details_SlNo' => 5, 'project_visit_id' => '12']);

    app(ImportProjectVisits::class)->run($this->context);

    $open = SiteInspection::query()->where('legacy_visit_ref', 11)->firstOrFail();
    $findings = $open->findings()->with(['status', 'severity'])->get();

    expect($findings->map(fn ($finding) => [$finding->status->code, $finding->description, $finding->found_by_name])->all())->toBe([
        [FindingStatus::RESOLVED, 'Column lapping position', 'MD. DELOWER HOSSAIN'],
        [FindingStatus::ACCEPTED, '4TH FLOOR', 'MD. DELOWER HOSSAIN'],
        [FindingStatus::OPEN, 'Column lapping position', 'MD. DELOWER HOSSAIN'],
    ])->and($findings[0]->closure_note)->toBe('Action taken (v1)')
        ->and($findings[0]->closed_on->toDateString())->toBe('2025-01-29')
        ->and($findings[0]->severity->code)->toBe('MEDIUM')
        ->and($open->status->code)->toBe(InspectionStatus::SUBMITTED)
        ->and(SiteInspection::query()->where('legacy_visit_ref', 12)->first()->status->code)->toBe(InspectionStatus::CLOSED);
});

test('running again adds nothing', function () {
    ($this->visit)(['Project_Visit_SlNo' => 11]);
    ($this->detail)(['Project_Visit_Details_SlNo' => 1, 'project_visit_id' => '11']);

    app(ImportProjectVisits::class)->run($this->context);

    expect(app(ImportProjectVisits::class)->run($this->context))->toBe(0)
        ->and(SiteInspection::query()->count())->toBe(1);
});
