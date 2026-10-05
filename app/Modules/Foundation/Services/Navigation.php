<?php

namespace App\Modules\Foundation\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Filters the navigation registry for a user.
 *
 * @phpstan-type NavItem array{label: string, route: string, icon: string, url: string, active: bool}
 * @phpstan-type NavGroup array{key: string, label: string, icon: string, items: list<NavItem>}
 */
final class Navigation
{
    public const MOBILE_PRIMARY_LIMIT = 3;

    /**
     * @param  list<array{key: string, label: string, icon: string, items: list<array{label: string, route: string, icon: string, permission?: string|null, mobile_primary?: bool}>}>  $groups
     */
    public function __construct(private array $groups) {}

    /**
     * @return list<NavGroup>
     */
    public function for(User $user): array
    {
        $groups = [];

        foreach ($this->groups as $group) {
            $items = array_map(
                fn (array $item): array => $this->present($item),
                array_values(array_filter($group['items'], fn (array $item): bool => $this->isVisible($item, $user))),
            );

            if ($items !== []) {
                $groups[] = ['key' => $group['key'], 'label' => $group['label'], 'icon' => $group['icon'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @return list<NavItem>
     */
    public function primaryMobile(User $user): array
    {
        $items = [];

        foreach ($this->groups as $group) {
            foreach ($group['items'] as $item) {
                if (($item['mobile_primary'] ?? false) && $this->isVisible($item, $user)) {
                    $items[] = $this->present($item);
                }
            }
        }

        return array_slice($items, 0, self::MOBILE_PRIMARY_LIMIT);
    }

    /**
     * @param  array{label: string, route: string, icon: string, permission?: string|null, mobile_primary?: bool}  $item
     */
    private function isVisible(array $item, User $user): bool
    {
        if (! Route::has($item['route'])) {
            return false;
        }

        $permission = $item['permission'] ?? null;

        return $permission === null || $user->can($permission);
    }

    /**
     * @param  array{label: string, route: string, icon: string, permission?: string|null, mobile_primary?: bool}  $item
     * @return NavItem
     */
    private function present(array $item): array
    {
        return [
            'label' => $item['label'],
            'route' => $item['route'],
            'icon' => $item['icon'],
            'url' => route($item['route']),
            'active' => request()->routeIs(Str::beforeLast($item['route'], '.').'.*'),
        ];
    }
}
