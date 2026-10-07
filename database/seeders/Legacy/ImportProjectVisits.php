<?php

namespace Database\Seeders\Legacy;

use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Support\NumberSequenceService;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * v1 project visits (tbl_project_visit, tbl_project_visit_details) → site inspections and findings
 * (legacy seed spec L19, L20). Visits whose project was not imported are skipped; imported rows are
 * not touched again.
 */
class ImportProjectVisits
{
    /**
     * Titles dropped before matching an engineer's name to an employee.
     */
    private const TITLES = ['md', 'mr', 'mrs', 'ms', 'engr', 'eng', 'mohammad', 'mohammed'];

    /** @var array<int, list<string>> employee id → name words */
    private array $employeeWords = [];

    public function __construct(private NumberSequenceService $numbers) {}

    public function run(LegacyContext $context): int
    {
        $created = 0;
        $legacy = $context->legacy();
        $this->employeeWords = Employee::query()->pluck('full_name', 'id')->map(fn ($name): array => self::words((string) $name))->all();

        foreach ($legacy->table('tbl_project_visit')->where('status', 'a')->orderBy('Project_Visit_SlNo')->get() as $row) {
            $projectId = $context->projects[(int) $row->project_id] ?? null;

            if ($projectId === null || SiteInspection::withTrashed()->where('legacy_visit_ref', $row->Project_Visit_SlNo)->exists()) {
                continue;
            }

            $details = $legacy->table('tbl_project_visit_details')->where('project_visit_id', (string) $row->Project_Visit_SlNo)
                ->where('status', 'a')->orderBy('Project_Visit_Details_SlNo')->get();

            DB::transaction(fn () => $this->import($context, $row, Project::withTrashed()->findOrFail($projectId), $details->all()));
            $created++;
        }

        return $created;
    }

    /**
     * @param  array<int, stdClass>  $details
     */
    private function import(LegacyContext $context, stdClass $row, Project $project, array $details): void
    {
        $addedAt = LegacyMap::dateTime($row->AddTime);
        $date = LegacyMap::date($row->inspection_date) ?? $addedAt?->toDateString() ?? today()->toDateString();
        $createdAt = $addedAt ?? Carbon::parse($date.' 10:00:00');
        $author = $project->created_by ?? $context->fallbackUserId();
        $engineerName = self::text($row->project_eng_name);
        $phone = self::text($row->field_office_phone);

        $inspection = new SiteInspection;
        $inspection->forceFill([
            'inspection_number' => $this->numbers->next('site_inspection', ['date' => $date]),
            'project_id' => $project->id,
            'inspection_type_id' => $context->idFor(InspectionType::class, trim((string) $row->inspection_type_weekly) === '1' ? InspectionType::WEEKLY : InspectionType::EVENT),
            'inspection_date' => $date,
            'start_time' => self::time($row->start_time),
            'end_time' => self::time($row->end_time),
            'site_address' => ($address = self::text($row->location)) !== null ? Str::limit($address, 255, '') : null,
            'permittee_name' => ($permittee = self::text($row->permitee_name)) !== null ? Str::limit($permittee, 150, '') : null,
            'contractor_name' => ($contractor = self::text($row->constructor_name)) !== null ? Str::limit($contractor, 150, '') : null,
            'project_engineer_id' => $this->engineerFor($engineerName),
            'project_engineer_name' => $engineerName !== null ? Str::limit($engineerName, 150, '') : null,
            'field_office_phone' => Phone::isValid($phone) ? Phone::normalise($phone) : ($phone !== null ? mb_substr($phone, 0, 30) : null),
            'work_progress_summary' => self::text($row->description),
            'inspection_status_id' => $context->idFor(InspectionStatus::class, InspectionStatus::SUBMITTED),
            'legacy_visit_ref' => (int) $row->Project_Visit_SlNo,
            'created_by' => $author,
            'updated_by' => $author,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $anyOpen = false;

        foreach ($details as $index => $detail) {
            [$statusCode, $note] = match (Str::lower(trim((string) $detail->visit_regarding))) {
                'yes' => [FindingStatus::RESOLVED, 'Action taken (v1)'],
                'no_application' => [FindingStatus::ACCEPTED, 'Not applicable (v1)'],
                default => [FindingStatus::OPEN, null],
            };
            $anyOpen = $anyOpen || $statusCode === FindingStatus::OPEN;
            $location = self::text($detail->visit_location);

            (new SiteInspectionFinding)->forceFill([
                'site_inspection_id' => $inspection->id,
                'project_id' => $project->id,
                'location' => $location !== null ? Str::limit($location, 150, '') : null,
                'description' => self::text($detail->visit_description) ?? $location ?? 'Finding',
                'found_by_name' => ($foundBy = self::text($detail->visit_finding)) !== null ? Str::limit($foundBy, 150, '') : null,
                'finding_severity_id' => $context->idFor(FindingSeverity::class, FindingSeverity::MEDIUM),
                'finding_status_id' => $context->idFor(FindingStatus::class, $statusCode),
                'closed_on' => $note !== null ? $date : null,
                'closure_note' => $note,
                'legacy_detail_ref' => (int) $detail->Project_Visit_Details_SlNo,
                'sort_order' => $index + 1,
                'created_by' => $author,
                'updated_by' => $author,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        if (! $anyOpen) {
            $inspection->forceFill(['inspection_status_id' => $context->idFor(InspectionStatus::class, InspectionStatus::CLOSED)])->save();
        }
    }

    /**
     * The one employee whose name contains every word of the v1 name (titles dropped, at least 5
     * letters), else null; the v1 text is kept either way (spec L19).
     */
    private function engineerFor(?string $name): ?int
    {
        $words = self::words($name);

        if (mb_strlen(implode('', $words)) < 5) {
            return null;
        }

        $matches = array_keys(array_filter($this->employeeWords, fn (array $employeeWords): bool => array_diff($words, $employeeWords) === []));

        return count($matches) === 1 ? (int) $matches[0] : null;
    }

    /**
     * @return list<string>
     */
    private static function words(?string $name): array
    {
        $words = preg_split('/\s+/', trim((string) preg_replace('/[^a-z]+/', ' ', Str::lower((string) $name)))) ?: [];

        return array_values(array_filter($words, fn (string $word): bool => $word !== '' && ! in_array($word, self::TITLES, true)));
    }

    private static function time(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $value === '00:00:00' || ! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? null : substr($value.':00', 0, 8);
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || in_array(Str::lower($value), ['n/a', 'na', 'not found'], true) ? null : $value;
    }
}
