<?php

namespace App\Modules\Foundation\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Filters the navigation registry for a user.
 *
 * An item is either a link (route, optional route params) or a branch (children) that renders
 * as a tree node in the sidebar. Branches with no visible children are dropped, like empty groups.
 * A link with params is active only on that route with those params (e.g. one master data table).
 *
 * @phpstan-type NavLink array{label: string, route: string, icon: string, url: string, active: bool}
 * @phpstan-type NavBranch array{label: string, icon: string, active: bool, children: list<NavLink>}
 * @phpstan-type NavGroup array{key: string, label: string, icon: string, items: list<NavLink|NavBranch>}
 * @phpstan-type LinkConfig array{label: string, route: string, params?: array<string, string>, icon: string, permission?: string|null, mobile_primary?: bool}
 * @phpstan-type BranchConfig array{label: string, icon: string, children: list<LinkConfig>}
 */
final class Navigation
{
    public const MOBILE_PRIMARY_LIMIT = 3;

    /**
     * @param  list<array{key: string, label: string, icon: string, items: list<LinkConfig|BranchConfig>}>  $groups
     */
    public function __construct(private array $groups) {}

    /**
     * @return list<NavGroup>
     */
    public function for(User $user): array
    {
        $groups = [];

        foreach ($this->groups as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if (isset($item['children'])) {
                    $children = $this->visibleLinks($item['children'], $user);

                    if ($children !== []) {
                        $items[] = [
                            'label' => $item['label'],
                            'icon' => $item['icon'],
                            'active' => in_array(true, array_column($children, 'active'), true),
                            'children' => $children,
                        ];
                    }
                } elseif ($this->isVisible($item, $user)) {
                    $items[] = $this->present($item);
                }
            }

            if ($items !== []) {
                $groups[] = ['key' => $group['key'], 'label' => $group['label'], 'icon' => $group['icon'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @return list<NavLink>
     */
    public function primaryMobile(User $user): array
    {
        $items = [];

        foreach ($this->groups as $group) {
            foreach ($this->links($group['items']) as $item) {
                if (($item['mobile_primary'] ?? false) && $this->isVisible($item, $user)) {
                    $items[] = $this->present($item);
                }
            }
        }

        return array_slice($items, 0, self::MOBILE_PRIMARY_LIMIT);
    }

    /**
     * @param  list<LinkConfig>  $links
     * @return list<NavLink>
     */
    private function visibleLinks(array $links, User $user): array
    {
        return array_map(
            fn (array $link): array => $this->present($link),
            array_values(array_filter($links, fn (array $link): bool => $this->isVisible($link, $user))),
        );
    }

    /**
     * Branches flattened into their links, in order.
     *
     * @param  list<LinkConfig|BranchConfig>  $items
     * @return list<LinkConfig>
     */
    private function links(array $items): array
    {
        $links = [];

        foreach ($items as $item) {
            array_push($links, ...(isset($item['children']) ? $item['children'] : [$item]));
        }

        return $links;
    }

    /**
     * @param  LinkConfig  $item
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
     * @param  LinkConfig  $item
     * @return NavLink
     */
    private function present(array $item): array
    {
        $params = $item['params'] ?? [];

        return [
            'label' => $item['label'],
            'route' => $item['route'],
            'icon' => $item['icon'],
            'url' => route($item['route'], $params),
            'active' => $params === []
                ? request()->routeIs(Str::beforeLast($item['route'], '.').'.*')
                : request()->routeIs($item['route']) && $this->routeHasParams($params),
        ];
    }

    /**
     * @param  array<string, string>  $params
     */
    private function routeHasParams(array $params): bool
    {
        foreach ($params as $key => $value) {
            if ((string) request()->route($key) !== $value) {
                return false;
            }
        }

        return true;
    }
}
