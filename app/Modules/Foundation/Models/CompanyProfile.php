<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $short_name
 * @property string|null $logo_path
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $tin
 * @property string|null $bin
 * @property string|null $trade_license_no
 * @property int $base_currency_id
 * @property int $fiscal_year_start_month
 * @property string|null $print_footer
 */
#[Fillable(['name', 'short_name', 'logo_path', 'address', 'phone', 'email', 'website', 'tin', 'bin', 'trade_license_no', 'base_currency_id', 'fiscal_year_start_month', 'print_footer'])]
class CompanyProfile extends Model
{
    use Auditable, TracksAuthors;

    protected $table = 'company_profile';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fiscal_year_start_month' => 'integer'];
    }

    /**
     * The single company profile row (docs/01 §3.3).
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    public static function fiscalYearStartMonth(): int
    {
        return (int) (static::query()->value('fiscal_year_start_month') ?? 7);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }
}
