<?php

namespace App\Support\Settings;

use App\Modules\Foundation\Models\Setting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class SettingsRepository
{
    public const CACHE_KEY = 'settings';

    public function __construct(private CacheRepository $cache) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->get()
            ->mapWithKeys(fn (Setting $setting): array => [
                $setting->group.'.'.$setting->key => $this->cast($setting->type, $setting->value),
            ])
            ->all());
    }

    public function set(string $key, mixed $value): void
    {
        if (! str_contains($key, '.')) {
            throw new InvalidArgumentException("Setting key [{$key}] must be in group.key form.");
        }

        [$group, $name] = explode('.', $key, 2);

        $setting = Setting::query()->where('group', $group)->where('key', $name)->firstOrFail();
        $setting->value = $this->cast($setting->type, $value);
        $setting->updated_by = Auth::id();
        $setting->save();

        $this->flush();
    }

    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    private function cast(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            $type === 'int', str_starts_with($type, 'fk:') => (int) $value,
            $type === 'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            $type === 'decimal', $type === 'string' => (string) $value,
            $type === 'json' => (array) $value,
            default => $value,
        };
    }
}
