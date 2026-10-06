<?php

namespace App\Modules\Foundation\Livewire\Shared;

use App\Modules\Foundation\Concerns\SavesFromDetailModal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Shows a detail page or a create/edit form inside a modal at md and up. Links marked
 * `data-detail-modal` dispatch `open-detail-modal` with their URL (resources/js/app.js); only
 * routes listed in `navigation.detail_modal` open here, and the page component authorizes
 * itself on mount. Forms using SavesFromDetailModal reload the page under the modal on save.
 */
class DetailModal extends Component
{
    public ?string $url = null;

    #[Locked]
    public ?string $returnUrl = null;

    public function show(string $url, ?string $returnUrl = null): void
    {
        $this->url = $url;
        $this->returnUrl = $returnUrl !== null ? $this->localUrl($returnUrl) : null;
    }

    public function close(): void
    {
        $this->url = null;
        $this->returnUrl = null;
    }

    /**
     * Match the URL to an allowed detail route and build the page component's mount parameters,
     * plus the modal header: the record's code and name (or "New …" on a create route) and its type.
     *
     * @return array{component: class-string<Component>, parameters: array<string, mixed>, heading: array{code: string|null, name: string, type: string}|null}
     */
    private function resolve(string $url): array
    {
        $request = Request::create($url);

        try {
            $route = Route::getRoutes()->match($request);
        } catch (HttpException) {
            abort(404);
        }

        abort_unless(in_array($route->getName(), config('navigation.detail_modal', []), true), 404);

        /** @var class-string<Component> $component */
        $component = $route->getAction('livewire_component');
        $parameters = [];
        $record = null;
        $recordClass = null;

        foreach ((new ReflectionMethod($component, 'mount'))->getParameters() as $parameter) {
            $name = $parameter->getName();
            $value = $route->parameter($name);
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Model::class)) {
                $recordClass ??= $type->getName();

                if ($value !== null) {
                    /** @var Model $model */
                    $model = new ($type->getName());
                    $value = $model->resolveRouteBinding($value, $route->bindingFieldFor($name))
                        ?? throw (new ModelNotFoundException)->setModel($type->getName(), [$value]);
                    $record ??= $value;
                }
            }

            $parameters[$name] = $value;
        }

        foreach ((new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if ($property->getAttributes(Url::class) !== [] && is_string($request->query($name)) && ! array_key_exists($name, $parameters)) {
                $parameters[$name] = $request->query($name);
            }
        }

        if ($this->returnUrl !== null && in_array(SavesFromDetailModal::class, class_uses_recursive($component), true)) {
            $parameters['returnUrl'] = $this->returnUrl;
        }

        $type = $recordClass !== null ? __(Str::headline(class_basename($recordClass))) : null;

        $heading = match (true) {
            $record !== null => [
                'code' => (string) $record->getRouteKey(),
                'name' => (string) ($record->getAttribute('full_name') ?? $record->getAttribute('name')),
                'type' => str_ends_with((string) $route->getName(), '.edit') ? __('Edit :type', ['type' => Str::lower($type)]) : $type,
            ],
            $type !== null => ['code' => null, 'name' => __('New :type', ['type' => Str::lower($type)]), 'type' => $type],
            default => null,
        };

        return ['component' => $component, 'parameters' => $parameters, 'heading' => $heading];
    }

    /**
     * Keep only the path and query of the page under the modal, so saving can never redirect off-site.
     */
    private function localUrl(string $url): string
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return url($path).(is_string($query) && $query !== '' ? '?'.$query : '');
    }

    public function render(): View
    {
        return view('livewire.shared.detail-modal', [
            'detail' => $this->url !== null ? $this->resolve($this->url) : null,
        ]);
    }
}
