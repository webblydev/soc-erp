<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Actions\UpdateCompanyProfile;
use App\Modules\Foundation\Models\CompanyProfile;
use App\Support\Facades\Lookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Company profile')]
class Company extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $short_name = '';

    public string $address = '';

    public string $phone = '';

    public string $email = '';

    public string $website = '';

    public string $tin = '';

    public string $bin = '';

    public string $trade_license_no = '';

    public ?int $base_currency_id = null;

    public int $fiscal_year_start_month = 7;

    public string $print_footer = '';

    /** @var UploadedFile|null */
    public $logo = null;

    public function mount(): void
    {
        $this->authorize('admin.company.view');

        $profile = CompanyProfile::current();

        foreach (UpdateCompanyProfile::FIELDS as $field) {
            $value = $profile?->getAttribute($field);

            if ($value === null) {
                continue;
            }

            $this->{$field} = in_array($field, ['base_currency_id', 'fiscal_year_start_month'], true) ? (int) $value : (string) $value;
        }
    }

    public function updatingLogo(): void
    {
        $this->authorize('admin.company.update');
    }

    public function save(UpdateCompanyProfile $updateCompanyProfile): void
    {
        $this->authorize('admin.company.update');

        $updateCompanyProfile->handle($this->only(UpdateCompanyProfile::FIELDS), $this->logo instanceof UploadedFile ? $this->logo : null);

        $this->reset('logo');
        $this->dispatch('toast', type: 'success', description: __('Company profile saved.'));
    }

    public function render(): View
    {
        return view('livewire.admin.company', [
            'company' => CompanyProfile::current(),
            'currencies' => Lookup::options('currencies', $this->base_currency_id),
            'readOnly' => ! auth()->user()->can('admin.company.update'),
        ]);
    }
}
