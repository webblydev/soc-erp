<?php

use App\Models\User;
use App\Modules\Catalog\Actions\SaveService;
use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\PricingBasis;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceCategory;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Foundation\Models\AuditLog;

function serviceInput(array $overrides = []): array
{
    return [
        'code' => 'bd',
        'name' => 'Building Design Work',
        'service_category_id' => ServiceCategory::factory()->create()->id,
        'business_line_id' => BusinessLine::factory()->create()->id,
        'default_unit_id' => '',
        'pricing_basis_id' => PricingBasis::query()->firstOrCreate(['code' => PricingBasis::FIXED], ['name' => 'Fixed'])->id,
        'default_rate' => '1,250.50',
        'requires_approval_tracking' => false,
        'description' => '',
        'is_active' => true,
        ...$overrides,
    ];
}

test('a service is created with its code upper-cased and blanks stored as null', function () {
    $this->actingAs(User::factory()->create());

    $service = app(SaveService::class)->handle(serviceInput());

    expect($service->code)->toBe('BD')
        ->and($service->default_rate)->toBe('1250.5000')
        ->and($service->default_unit_id)->toBeNull()
        ->and($service->description)->toBeNull()
        ->and($service->created_by)->not->toBeNull()
        ->and(AuditLog::query()->where('auditable_type', 'service')->where('event', 'created')->exists())->toBeTrue();
});

test('codes are unique case-insensitively and limited to A-Z 0-9 . _ - (CT-BR-05, C4)', function () {
    Service::factory()->create(['code' => 'BD-01']);

    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['code' => 'bd-01'])), 'code');
    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['code' => 'BD/01'])), 'code');
    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['code' => 'BD 01'])), 'code');
});

test('the default rate cannot be negative and has at most 4 decimals (CT-BR-03)', function () {
    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['default_rate' => '-1'])), 'default_rate');
    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['default_rate' => '1.23456'])), 'default_rate');
    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['default_rate' => 'abc'])), 'default_rate');
});

test('an internal business line is refused (C9)', function () {
    $internal = BusinessLine::factory()->internal()->create();

    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['business_line_id' => $internal->id])), 'business_line_id');
});

test('category and pricing basis are required and must be active', function () {
    $inactive = ServiceCategory::factory()->create(['is_active' => false]);

    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['service_category_id' => ''])), 'service_category_id');
    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['service_category_id' => $inactive->id])), 'service_category_id');
    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['pricing_basis_id' => ''])), 'pricing_basis_id');
    expectValidationError(fn () => app(SaveService::class)->handle(serviceInput(['default_unit_id' => Unit::factory()->create(['is_active' => false])->id])), 'default_unit_id');
});

test('an existing service keeps a category that was deactivated later (CM-BR-03)', function () {
    $service = Service::factory()->create();
    $service->category->update(['is_active' => false]);

    app(SaveService::class)->handle([...$service->only(['code', 'service_category_id', 'business_line_id', 'pricing_basis_id', 'is_active']), 'name' => 'Renamed'], $service);

    expect($service->fresh()->name)->toBe('Renamed');
});

test('updating a service ignores its own code in the unique check', function () {
    $service = Service::factory()->create(['code' => 'SOIL']);

    app(SaveService::class)->handle(serviceInput(['code' => 'soil', 'name' => 'Soil Test Work']), $service);

    expect($service->fresh()->name)->toBe('Soil Test Work')->and(Service::query()->count())->toBe(1);
});
