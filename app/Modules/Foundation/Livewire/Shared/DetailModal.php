<?php

namespace App\Modules\Foundation\Livewire\Shared;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;
use Livewire\Component;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Shows a detail page inside a modal at md and up. Links marked `data-detail-modal` dispatch
 * `open-detail-modal` with their URL (resources/js/app.js); only routes listed in
 * `navigation.detail_modal` open here, and the page component authorizes itself on mount.
 */
class DetailModal extends Component
{
    public ?string $url = null;

    public function show(string $url): void
    {
        $this->url = $url;
    }

    public function close(): void
    {
        $this->url = null;
    }

    /**
     * Match the URL to an allowed detail route and build the page component's mount parameters.
     *
     * @return array{component: class-string<Component>, parameters: array<string, mixed>}
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

        foreach ((new ReflectionMethod($component, 'mount'))->getParameters() as $parameter) {
            $name = $parameter->getName();
            $value = $route->parameter($name);
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Model::class)) {
                /** @var Model $model */
                $model = new ($type->getName());
                $value = $model->resolveRouteBinding($value, $route->bindingFieldFor($name))
                    ?? throw (new ModelNotFoundException)->setModel($type->getName(), [$value]);
            }

            $parameters[$name] = $value;
        }

        foreach ((new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if ($property->getAttributes(Url::class) !== [] && is_string($request->query($name)) && ! array_key_exists($name, $parameters)) {
                $parameters[$name] = $request->query($name);
            }
        }

        return ['component' => $component, 'parameters' => $parameters];
    }

    public function render(): View
    {
        return view('livewire.shared.detail-modal', [
            'detail' => $this->url !== null ? $this->resolve($this->url) : null,
        ]);
    }
}
