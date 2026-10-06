<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Foundation\Models\Attachment;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\Currency;
use App\Modules\Foundation\Models\DocumentType;
use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use App\Modules\Foundation\Models\Note;
use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use App\Modules\Foundation\Models\Role;
use App\Modules\Foundation\Models\Setting;
use App\Support\Database\BlueprintMacros;
use App\Support\Facades\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        BlueprintMacros::register();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $rule = Password::min((int) Settings::get('general.password_min_length', 8));

            return app()->isProduction()
                ? $rule->letters()->mixedCase()->numbers()->uncompromised()
                : $rule;
        });
    }

    /**
     * Register morph aliases so polymorphic columns never store class names (docs/00 §4.5).
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'currency' => Currency::class,
            'branch' => Branch::class,
            'company' => CompanyProfile::class,
            'setting' => Setting::class,
            'role' => Role::class,
            'location' => Location::class,
            'location_level' => LocationLevel::class,
            'number_sequence' => NumberSequence::class,
            'number_sequence_format' => NumberSequenceFormat::class,
            'document_type' => DocumentType::class,
            'attachment' => Attachment::class,
            'note' => Note::class,
        ]);
    }
}
