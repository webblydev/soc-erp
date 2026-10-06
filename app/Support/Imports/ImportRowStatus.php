<?php

namespace App\Support\Imports;

enum ImportRowStatus: string
{
    case New = 'new';
    case Update = 'update';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Update => __('Update'),
            self::Error => __('Error'),
        };
    }

    /**
     * BlatUI badge tone.
     */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'success',
            self::Update => 'info',
            self::Error => 'danger',
        };
    }
}
