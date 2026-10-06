<?php

namespace Database\Seeders\Hrm;

use App\Modules\Hrm\Models\BloodGroup;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmployeeType;
use App\Modules\Hrm\Models\EmploymentEvent;
use App\Modules\Hrm\Models\EmploymentEventType;
use App\Modules\Hrm\Models\ExitReason;
use App\Modules\Hrm\Models\Gender;
use App\Modules\Hrm\Models\MaritalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * HRM lookups (docs/09 §3.1, spec §3.1). Existing rows keep admin edits; only missing rows are created.
 */
class HrmLookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed(Department::class, [
            ['DESIGN', 'Design'], ['PROJECT_OPS', 'Project Operation'], ['MKT_SALES', 'Marketing & Sales'], ['ACCOUNTS', 'Accounts & Finance'],
            ['HR_ADMIN', 'HR & Admin'], ['CUSTOMER_REL', 'Customer Relation'], ['SUPPLY_CHAIN', 'Supply Chain Management'],
            ['LOGISTIC', 'Logistic & Estate Management'],
        ]);

        $this->seedDesignations();

        $this->seed(EmployeeType::class, [
            ['PERMANENT', 'Permanent'], ['PROBATION', 'Probation', ['is_system' => true]], ['CONTRACT', 'Contract'],
            ['PART_TIME', 'Part-time'], ['INTERN', 'Intern'], ['CONSULTANT', 'Consultant'],
        ]);

        $this->seed(EmployeeStatus::class, [
            ['ACTIVE', 'Active', ['is_active_employment' => true, 'is_system' => true, 'color' => 'success']],
            ['ON_LEAVE', 'On leave', ['is_active_employment' => true, 'is_system' => true, 'color' => 'info']],
            ['SUSPENDED', 'Suspended', ['is_active_employment' => false, 'is_system' => true, 'color' => 'warning']],
            ['RESIGNED', 'Resigned', ['is_active_employment' => false, 'is_exit' => true, 'is_system' => true]],
            ['TERMINATED', 'Terminated', ['is_active_employment' => false, 'is_exit' => true, 'is_system' => true, 'color' => 'danger']],
            ['RETIRED', 'Retired', ['is_active_employment' => false, 'is_exit' => true, 'is_system' => true]],
        ]);

        $this->seed(Gender::class, [['MALE', 'Male'], ['FEMALE', 'Female'], ['OTHER', 'Other']]);
        $this->seed(MaritalStatus::class, [['SINGLE', 'Single'], ['MARRIED', 'Married'], ['DIVORCED', 'Divorced'], ['WIDOWED', 'Widowed']]);
        $this->seed(BloodGroup::class, [
            ['A_POS', 'A+'], ['A_NEG', 'A−'], ['B_POS', 'B+'], ['B_NEG', 'B−'], ['AB_POS', 'AB+'], ['AB_NEG', 'AB−'], ['O_POS', 'O+'], ['O_NEG', 'O−'],
        ]);

        $this->seed(EmployeeDocumentType::class, [
            ['NID', 'NID'], ['PASSPORT', 'Passport', ['has_expiry' => true]], ['PHOTO', 'Photo'], ['CV', 'CV'], ['CERTIFICATE', 'Certificates'],
            ['APPOINTMENT_LETTER', 'Appointment letter'], ['IEB_IAB', 'IEB/IAB membership', ['has_expiry' => true]],
            ['DRIVING_LICENCE', 'Driving licence', ['has_expiry' => true]], ['OTHER', 'Other'],
        ]);

        $this->seed(EmploymentEventType::class, array_map(fn (array $row): array => [$row[0], $row[1], ['is_system' => true]], [
            ['JOINED', 'Joined'], ['CONFIRMED', 'Confirmed'], ['PROMOTED', 'Promoted'], ['TRANSFERRED', 'Transferred'],
            ['SALARY_REVISED', 'Salary revised'], ['DESIGNATION_CHANGED', 'Designation changed'], ['RESIGNED', 'Resigned'],
            ['TERMINATED', 'Terminated'], ['RETIRED', 'Retired'], ['REJOINED', 'Rejoined'],
        ]));

        $this->seed(ExitReason::class, $this->named(['Resignation', 'Termination', 'Contract end', 'Retirement', 'Other']));
    }

    /**
     * The v1 posts (tbl_post, legacy seed spec L4), in v1 order. "Head of …" posts belong to their
     * department. The earlier sample designations are removed when unused, else deactivated.
     */
    private function seedDesignations(): void
    {
        $posts = [
            ['MD', 'Managing Director'], ['CEO', 'CEO'], ['ED', 'Executive Director'], ['CHAIRMAN', 'Chairman'],
            ['HEAD_DESIGN', 'Head of Design Department', 'DESIGN'],
            ['HEAD_PROJECT_OPS', 'Head of Project Operation Department', 'PROJECT_OPS'],
            ['HEAD_ACCOUNTS', 'Head of Accounts & Finance', 'ACCOUNTS'],
            ['HEAD_HR_ADMIN', 'Head of HR & Admin Department', 'HR_ADMIN'],
            ['HEAD_SUPPLY_CHAIN', 'Head of Supply Chain Management', 'SUPPLY_CHAIN'],
            ['HEAD_MKT_SALES', 'Head of Marketing & Sales', 'MKT_SALES'],
            ['STRUCTURAL_ENGINEER', 'Structural Engineer'],
            ['HEAD_CUSTOMER_REL', 'Head of Customer Relation', 'CUSTOMER_REL'],
            ['PROJECT_ENGINEER', 'Project Engineer'], ['ARCHITECT', 'Architect'],
            ['JR_PROJECT_ENGINEER', 'Jr. Project Engineer'], ['DEPUTY_PROJECT_ENGINEER', 'Deputy Project Engineer'],
            ['TECH_SUPPORT_ENGINEER', 'Technical Support Engineer'],
            ['HEAD_LOGISTIC', 'Head of Logistic & Estate', 'LOGISTIC'],
        ];

        $departments = Department::query()->pluck('id', 'code');

        $this->seed(Designation::class, array_map(fn (array $post): array => [
            $post[0], $post[1], isset($post[2]) ? ['department_id' => $departments[$post[2]] ?? null] : [],
        ], $posts));

        $retired = ['DIRECTOR', 'GM', 'MANAGER', 'SITE_ENGINEER', 'DRAFTSMAN', 'SURVEYOR', 'ACCOUNTS_OFFICER', 'HR_ADMIN_OFFICER', 'MARKETING_OFFICER', 'CR_OFFICER', 'OFFICE_ASSISTANT'];

        Designation::query()->whereIn('code', $retired)->get()->each(function (Designation $designation): void {
            $inUse = Employee::withTrashed()->where('designation_id', $designation->id)->exists()
                || EmploymentEvent::query()->where('from_designation_id', $designation->id)->orWhere('to_designation_id', $designation->id)->exists();

            $inUse ? $designation->update(['is_active' => false]) : $designation->delete();
        });
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array{0: string, 1: string, 2?: array<string, mixed>}>  $rows
     */
    private function seed(string $model, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $model::query()->firstOrCreate(['code' => $row[0]], ['name' => $row[1], 'sort_order' => $index + 1, ...($row[2] ?? [])]);
        }
    }

    /**
     * Rows whose code is derived from the name (upper snake case).
     *
     * @param  list<string>  $names
     * @return list<array{0: string, 1: string}>
     */
    private function named(array $names): array
    {
        return array_map(fn (string $name): array => [Str::upper(Str::snake(Str::of($name)->replaceMatches('/[^A-Za-z ]/', ' ')->squish()->value())), $name], $names);
    }
}
