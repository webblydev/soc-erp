<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\CompanyProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Edits the single company profile row (docs/01 §3.3, §5.5).
 */
class UpdateCompanyProfile
{
    public const FIELDS = ['name', 'short_name', 'address', 'phone', 'email', 'website', 'tin', 'bin', 'trade_license_no', 'base_currency_id', 'fiscal_year_start_month', 'print_footer'];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?UploadedFile $logo = null): CompanyProfile
    {
        $input = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $input);

        /** @var array<string, mixed> $data */
        $data = Validator::make([...$input, 'logo' => $logo], [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url', 'max:150'],
            'tin' => ['nullable', 'string', 'max:30'],
            'bin' => ['nullable', 'string', 'max:30'],
            'trade_license_no' => ['nullable', 'string', 'max:60'],
            'base_currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->whereNull('deleted_at')],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'print_footer' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:20480'],
        ])->validate();

        $newLogo = $logo?->store('company', 'public');
        $oldLogo = null;

        try {
            $profile = DB::transaction(function () use ($data, $newLogo, &$oldLogo): CompanyProfile {
                $profile = CompanyProfile::query()->lockForUpdate()->first() ?? new CompanyProfile;
                $oldLogo = $profile->logo_path;
                $profile->fill(Arr::only($data, self::FIELDS));

                if ($newLogo !== null && $newLogo !== false) {
                    $profile->logo_path = $newLogo;
                }

                $profile->save();

                return $profile;
            });
        } catch (Throwable $exception) {
            if ($newLogo) {
                Storage::disk('public')->delete($newLogo);
            }

            throw $exception;
        }

        if ($newLogo && $oldLogo !== null && $oldLogo !== $newLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        return $profile;
    }
}
