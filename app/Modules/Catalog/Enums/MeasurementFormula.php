<?php

namespace App\Modules\Catalog\Enums;

use Illuminate\Support\Str;

/**
 * How a work item's quantity is measured (docs/02 §3.6). Estimation (05) multiplies the listed
 * dimensions; Manual means the quantity is typed in.
 */
enum MeasurementFormula: string
{
    case NosLWH = 'nos_l_w_h';
    case NosLW = 'nos_l_w';
    case NosL = 'nos_l';
    case Nos = 'nos';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::NosLWH => 'Nos × L × W × H',
            self::NosLW => 'Nos × L × W',
            self::NosL => 'Nos × L',
            self::Nos => 'Nos',
            self::Manual => 'Manual quantity',
        };
    }

    /**
     * @return list<string>
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::NosLWH => ['nos', 'length', 'width', 'height'],
            self::NosLW => ['nos', 'length', 'width'],
            self::NosL => ['nos', 'length'],
            self::Nos => ['nos'],
            self::Manual => [],
        };
    }

    /**
     * The case for a stored value or a label, ignoring case and spaces.
     */
    public static function fromInput(string $value): ?self
    {
        $key = Str::lower(str_replace(' ', '', $value));

        foreach (self::cases() as $case) {
            if ($key === $case->value || $key === Str::lower(str_replace(' ', '', $case->label()))) {
                return $case;
            }
        }

        return null;
    }
}
