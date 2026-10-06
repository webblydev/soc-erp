<?php

namespace App\Modules\Crm;

use App\Models\User;
use App\Modules\Crm\Models\ActivityOutcome;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CustomerStatus;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\LeadLevel;
use App\Modules\Crm\Models\LeadPriority;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Models\LostReason;
use App\Modules\Crm\Models\PaymentTerm;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CrmServiceProvider extends ServiceProvider
{
    /**
     * Resources whose `view` gate means "any data scope" (spec R6).
     */
    private const SCOPED_RESOURCES = ['leads', 'customers', 'activities'];

    /**
     * Bootstrap CRM services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/crm'));

        Relation::morphMap([
            'lead_source' => LeadSource::class,
            'lead_status' => LeadStatus::class,
            'lead_priority' => LeadPriority::class,
            'lead_level' => LeadLevel::class,
            'lost_reason' => LostReason::class,
            'activity_type' => ActivityType::class,
            'activity_outcome' => ActivityOutcome::class,
            'customer_type' => CustomerType::class,
            'customer_status' => CustomerStatus::class,
            'payment_term' => PaymentTerm::class,
        ]);

        foreach (self::SCOPED_RESOURCES as $resource) {
            Gate::define("crm.{$resource}.view", fn (User $user): bool => $user->hasPermission("crm.{$resource}.view_own")
                || $user->hasPermission("crm.{$resource}.view_team")
                || $user->hasPermission("crm.{$resource}.view_all"));
        }

        Livewire::addLocation(classNamespace: 'App\\Modules\\Crm\\Livewire');
    }
}
