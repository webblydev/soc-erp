<?php

namespace App\Modules\Foundation\Services;

use Illuminate\Support\Str;

/**
 * Reads app/Modules/<Module>/permissions.php files. Each returns:
 *   ['permissions' => [module => [resource => [action, ...]]], 'grants' => [role_code => [pattern, ...]]]
 * An empty resource key ('') yields a two-part name such as `notes.create`.
 */
final class PermissionManifest
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(private array $paths) {}

    public static function discover(): self
    {
        return new self(glob(app_path('Modules/*/permissions.php')) ?: []);
    }

    /**
     * @return list<array{name: string, module: string, resource: string, action: string, sort_order: int}>
     */
    public function permissions(): array
    {
        $rows = [];

        foreach ($this->manifests() as $manifest) {
            foreach ($manifest['permissions'] as $module => $resources) {
                foreach ($resources as $resource => $actions) {
                    foreach ($actions as $action) {
                        $rows[] = [
                            'name' => implode('.', array_filter([$module, (string) $resource, $action], fn (string $part): bool => $part !== '')),
                            'module' => $module,
                            'resource' => (string) $resource,
                            'action' => $action,
                            'sort_order' => count($rows) + 1,
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<string, list<string>>
     */
    public function grants(): array
    {
        $grants = [];

        foreach ($this->manifests() as $manifest) {
            foreach ($manifest['grants'] ?? [] as $role => $patterns) {
                $grants[$role] = [...($grants[$role] ?? []), ...$patterns];
            }
        }

        return $grants;
    }

    /**
     * Resolve each role's grant patterns (wildcards allowed) against the given permission names.
     *
     * @param  list<string>  $names
     * @return array<string, list<string>>
     */
    public function expandGrants(array $names): array
    {
        return array_map(
            fn (array $patterns): array => array_values(array_filter($names, fn (string $name): bool => Str::is($patterns, $name))),
            $this->grants(),
        );
    }

    /**
     * @return list<array{permissions: array<string, array<string, list<string>>>, grants?: array<string, list<string>>}>
     */
    private function manifests(): array
    {
        return array_map(fn (string $path): array => require $path, $this->paths);
    }
}
