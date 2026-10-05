<?php

use App\Models\User;
use App\Modules\Foundation\Actions\DeleteLookup;
use App\Modules\Foundation\Actions\ReorderLookup;
use App\Modules\Foundation\Actions\SaveLookup;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Currency;
use Illuminate\Database\Eloquent\ModelNotFoundException;

test('a new row is appended at the end of the sort order', function () {
    Branch::factory()->create(['sort_order' => 5]);

    $branch = app(SaveLookup::class)->handle('branches', ['code' => 'CTG', 'name' => 'Chattogram', 'is_active' => true, 'is_head_office' => false]);

    expect($branch->sort_order)->toBe(6)->and($branch->is_system)->toBeFalse();
});

test('system rows keep their code and stay active (FD-BR-05)', function () {
    $row = Branch::factory()->create(['code' => 'HO', 'is_system' => true]);

    expectValidationError(fn () => app(SaveLookup::class)->handle('branches', ['code' => 'HQ', 'name' => 'HQ', 'is_active' => true], $row), 'code');
    expectValidationError(fn () => app(SaveLookup::class)->handle('branches', ['code' => 'HO', 'name' => 'HQ', 'is_active' => false], $row), 'is_active');
    expectValidationError(fn () => app(DeleteLookup::class)->handle('branches', $row), 'row');
});

test('rows in use cannot be deleted (FD-BR-06)', function () {
    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    expectValidationError(fn () => app(DeleteLookup::class)->handle('branches', $branch), 'row');
    expect($branch->fresh())->not->toBeNull();
});

test('setting a single flag clears it on the other rows', function () {
    $old = Currency::factory()->create(['is_base' => true]);
    $new = Currency::factory()->create(['is_base' => false]);

    app(SaveLookup::class)->handle('currencies', ['code' => $new->code, 'name' => $new->name, 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'is_base' => true], $new);

    expect($old->fresh()->is_base)->toBeFalse()->and($new->fresh()->is_base)->toBeTrue();
});

test('reorder moves a row and ignores ids from other tables', function () {
    [$a, $b, $c] = collect(['A', 'B', 'C'])->map(fn (string $code, int $i) => Branch::factory()->create(['code' => $code, 'sort_order' => $i + 1]))->all();

    app(ReorderLookup::class)->handle('branches', $c->id, 0);
    expect(Branch::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['C', 'A', 'B']);

    app(ReorderLookup::class)->handle('branches', $a->id, 99);
    expect(Branch::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['C', 'B', 'A']);

    $currency = Currency::factory()->create();
    expect(fn () => app(ReorderLookup::class)->handle('branches', $currency->id + 1000, 0))
        ->toThrow(ModelNotFoundException::class);
});
