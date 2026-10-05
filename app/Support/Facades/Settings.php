<?php

namespace App\Support\Facades;

use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static array<string, mixed> all()
 * @method static void flush()
 *
 * @see SettingsRepository
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsRepository::class;
    }
}
