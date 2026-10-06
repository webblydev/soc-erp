<?php

namespace Database\Seeders\Legacy;

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerStatus;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadLevel;
use App\Modules\Crm\Models\LeadPriority;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Foundation\Models\Location;
use App\Support\NumberSequenceService;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * v1 clients (tbl_client) → leads with services, and a customer for each sold client (legacy
 * seed spec L8, docs/11 §4.3). Deleted clients are skipped; imported clients are not touched again.
 */
class ImportClients
{
    /** @var array<int, int|null> legacy area id → location id */
    private array $locations = [];

    public function __construct(private NumberSequenceService $numbers) {}

    public function run(LegacyContext $context): int
    {
        $created = 0;
        $this->locations = $this->locationMap();
        $withDetails = array_flip($context->legacy()->table('tbl_clientdetails')->distinct()->pluck('client_id')->map(fn ($id): int => (int) $id)->all());

        foreach ($context->legacy()->table('tbl_client')->where('status', '<>', 'd')->orderBy('id')->get() as $row) {
            $lead = Lead::withTrashed()->where('legacy_client_ref', $row->id)->first();

            if ($lead === null) {
                $lead = DB::transaction(fn (): Lead => $this->import($context, $row, isset($withDetails[(int) $row->id])));
                $created++;
            }

            $context->leads[(int) $row->id] = [
                'lead' => $lead->id,
                'customer' => $lead->converted_customer_id,
                'owner' => (int) $lead->assigned_to,
                'open' => $lead->converted_customer_id === null,
            ];
        }

        return $created;
    }

    private function import(LegacyContext $context, object $row, bool $hasDetails): Lead
    {
        $ownerId = $context->users[(int) $row->add_by] ?? $context->fallbackUserId();
        $addedAt = LegacyMap::dateTime($row->add_time) ?? now();
        $sold = $row->status === 's';
        $statusCode = match (true) {
            $sold => LeadStatus::WON,
            $hasDetails || filled(trim((string) $row->comment)) || filled(trim((string) $row->note)) || LegacyMap::date($row->reminder) !== null => 'CONTACTED',
            default => LeadStatus::NEW,
        };
        $statusId = $context->idFor(LeadStatus::class, $statusCode);
        $businessLine = LegacyMap::businessLineFor($row->client_id);

        $lead = new Lead;
        $lead->forceFill([
            'lead_number' => $this->numbers->next('lead'),
            'lead_date' => LegacyMap::date($row->date) ?? $addedAt->toDateString(),
            'name' => $this->text($row->client_name) ?? 'Unnamed',
            'company_name' => $this->text($row->org_name),
            'phone' => $this->phone($row->phone) ?? (string) preg_replace('/\D/', '', (string) $row->phone),
            'office_phone' => $this->phone($row->org_mobile),
            'whatsapp' => $this->phone($row->w_number),
            'email' => $this->email($row->email),
            'address' => $this->text($row->address),
            'location_id' => $this->locations[(int) $row->area_id] ?? null,
            'lead_source_id' => $context->idFor(LeadSource::class, LegacyMap::sourceFor($row->source)),
            'business_line_id' => $businessLine !== null ? $context->idFor(BusinessLine::class, $businessLine) : null,
            'lead_level_id' => ($level = LegacyMap::levelFor($row->level)) !== null ? $context->idFor(LeadLevel::class, $level) : null,
            'lead_status_id' => $statusId,
            'lead_priority_id' => $context->idFor(LeadPriority::class, LeadPriority::NORMAL),
            'sales_team_id' => $context->teamOfUser[$ownerId] ?? null,
            'assigned_to' => $ownerId,
            'assigned_at' => $addedAt,
            'won_at' => $sold ? $addedAt : null,
            'legacy_client_id' => ($code = $this->text($row->client_id)) !== null ? mb_substr($code, 0, 40) : null,
            'legacy_client_ref' => (int) $row->id,
            'notes' => $this->text($row->note),
            'created_at' => $addedAt,
            'updated_at' => $addedAt,
        ])->save();

        foreach ($this->serviceIds($context, (string) $row->requirement) as $serviceId) {
            $lead->services()->create(['service_id' => $serviceId]);
        }

        $lead->statusHistories()->create(['to_status_id' => $statusId, 'changed_by' => $ownerId, 'changed_at' => $addedAt, 'note' => 'Imported from v1']);

        if ($sold) {
            $customer = $this->customer($context, $row, $lead, $addedAt);
            $lead->forceFill(['converted_customer_id' => $customer->id, 'converted_at' => $addedAt, 'converted_by' => $ownerId])->save();
        }

        return $lead;
    }

    private function customer(LegacyContext $context, object $row, Lead $lead, Carbon $addedAt): Customer
    {
        $existing = Customer::withTrashed()->where('phone', $lead->phone)->first();

        if ($existing !== null) {
            return $existing;
        }

        $customer = new Customer;
        $customer->forceFill([
            'customer_number' => $this->numbers->next('customer'),
            'customer_type_id' => $context->idFor(CustomerType::class, $lead->company_name !== null ? 'COMPANY' : 'INDIVIDUAL'),
            'name' => $lead->name,
            'company_name' => $lead->company_name,
            'phone' => $lead->phone,
            'alternate_phone' => $lead->office_phone,
            'whatsapp' => $lead->whatsapp,
            'email' => $lead->email,
            'address' => $lead->address,
            'location_id' => $lead->location_id,
            'business_line_id' => $lead->business_line_id,
            'account_manager_user_id' => $lead->assigned_to,
            'source_lead_id' => $lead->id,
            'lead_source_id' => $lead->lead_source_id,
            'acquired_by_user_id' => $lead->assigned_to,
            'customer_status_id' => $context->idFor(CustomerStatus::class, CustomerStatus::ACTIVE),
            'legacy_client_id' => $lead->legacy_client_id,
            'legacy_client_ref' => (int) $row->id,
            'created_at' => $addedAt,
            'updated_at' => $addedAt,
        ])->save();

        return $customer;
    }

    /**
     * Service ids from the comma-separated v1 software ids, skipping internal and unknown ones.
     *
     * @return list<int>
     */
    private function serviceIds(LegacyContext $context, string $requirement): array
    {
        $ids = [];

        foreach (array_filter(array_map('trim', explode(',', $requirement)), 'is_numeric') as $softwareId) {
            $code = LegacyMap::SERVICES[(int) $softwareId] ?? null;
            $id = $code !== null ? $context->idFor(Service::class, $code) : null;

            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * legacy area id → location id, through the paths in data/legacy_areas.php.
     *
     * @return array<int, int|null>
     */
    private function locationMap(): array
    {
        /** @var array<int, list<string>|null> $areas */
        $areas = require database_path('seeders/Foundation/data/legacy_areas.php');
        $ids = Location::query()->pluck('id', 'full_path');

        return array_map(
            fn (?array $path): ?int => $path === null ? null : ($ids[implode(Location::PATH_SEPARATOR, $path)] ?? null),
            $areas,
        );
    }

    private function phone(mixed $value): ?string
    {
        $phone = Phone::normalise(is_string($value) ? $value : null);

        return Phone::isValid($phone) ? $phone : null;
    }

    private function email(mixed $value): ?string
    {
        $email = Str::lower(trim((string) $value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
