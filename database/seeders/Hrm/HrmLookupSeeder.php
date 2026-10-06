<?php

namespace Database\Seeders\Hrm;

use App\Modules\Hrm\Models\BloodGroup;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmployeeType;
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

        $this->seed(Designation::class, [
            ['MD', 'Managing Director'], ['DIRECTOR', 'Director'], ['GM', 'General Manager'], ['MANAGER', 'Manager'], ['ARCHITECT', 'Architect'],
            ['STRUCTURAL_ENGINEER', 'Structural Engineer'], ['PROJECT_ENGINEER', 'Project Engineer'], ['SITE_ENGINEER', 'Site Engineer'],
            ['DRAFTSMAN', 'Draftsman'], ['SURVEYOR', 'Surveyor'], ['ACCOUNTS_OFFICER', 'Accounts Officer'], ['HR_ADMIN_OFFICER', 'HR & Admin Officer'],
            ['MARKETING_OFFICER', 'Marketing Officer'], ['CR_OFFICER', 'Customer Relation Officer'], ['OFFICE_ASSISTANT', 'Office Assistant'],
        ]);

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
