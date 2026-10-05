<?php

use App\Models\User;
use App\Modules\Foundation\Actions\IncreaseSequenceNumber;
use App\Modules\Foundation\Actions\UpdateSequenceFormat;
use App\Modules\Foundation\Livewire\Admin\Sequences;
use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->definition = NumberSequenceFormat::query()->create(['document_type' => 'invoice', 'format' => 'INV-{yy}-{seq:5}', 'reset_policy' => 'fiscal_year']);
    $this->counter = NumberSequence::query()->create(['document_type' => 'invoice', 'scope_key' => 'fy:27', 'format' => 'INV-{yy}-{seq:5}', 'next_number' => 10, 'reset_policy' => 'fiscal_year']);
});

test('a format must contain a seq token and only known tokens', function () {
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($this->definition, 'INV-{yy}'), 'format');
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($this->definition, 'INV-{month}-{seq:5}'), 'format');
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($this->definition, 'INV-{seq:0}'), 'format');
});

test('a new format is applied to the definition and its counters', function () {
    app(UpdateSequenceFormat::class)->handle($this->definition, 'SI-{yyyy}-{seq:6}');

    expect($this->definition->fresh()->format)->toBe('SI-{yyyy}-{seq:6}')
        ->and($this->counter->fresh()->format)->toBe('SI-{yyyy}-{seq:6}');
});

test('the next number can only increase (FD-BR-07)', function () {
    expectValidationError(fn () => app(IncreaseSequenceNumber::class)->handle($this->counter, 10), 'next_number');
    expectValidationError(fn () => app(IncreaseSequenceNumber::class)->handle($this->counter, 3), 'next_number');

    app(IncreaseSequenceNumber::class)->handle($this->counter, 25);
    expect($this->counter->fresh()->next_number)->toBe(25);
});

test('the screen needs admin.sequences.view and shows a sample number', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.sequences.index'))->assertForbidden();

    $this->actingAs(userWithPermissions('admin.sequences.view'))
        ->get(route('admin.sequences.index'))
        ->assertOk()
        ->assertSee('INV-{yy}-{seq:5}')
        ->assertSee('00010');
});

test('editing from the screen needs admin.sequences.update', function () {
    Livewire::actingAs(userWithPermissions('admin.sequences.view'))
        ->test(Sequences::class)
        ->call('editFormat', $this->definition->id)
        ->assertForbidden();

    Livewire::actingAs(userWithPermissions('admin.sequences.view', 'admin.sequences.update'))
        ->test(Sequences::class)
        ->call('editCounter', $this->counter->id)
        ->set('nextNumber', 5)
        ->call('saveCounter')
        ->assertHasErrors(['nextNumber']);
});

test('the counter being edited cannot be chosen from the client', function () {
    Livewire::actingAs(userWithPermissions('admin.sequences.view', 'admin.sequences.update'))
        ->test(Sequences::class)
        ->set('counterId', $this->counter->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('a format must keep the tokens its reset and scope rely on', function () {
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($this->definition, 'INV-{seq:5}'), 'format');

    $perLine = NumberSequenceFormat::query()->create(['document_type' => 'quotation', 'format' => 'Q-{bl_prefix}-{seq:4}', 'reset_policy' => 'never', 'scope_by' => 'business_line']);
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($perLine, 'Q-{seq:4}'), 'format');

    $perBranch = NumberSequenceFormat::query()->create(['document_type' => 'receipt', 'format' => 'R-{branch}-{seq:4}', 'reset_policy' => 'never', 'scope_by' => 'branch']);
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($perBranch, 'R-{seq:4}'), 'format');

    $plain = NumberSequenceFormat::query()->create(['document_type' => 'customer', 'format' => 'C-{yy}-{seq:6}', 'reset_policy' => 'never']);
    app(UpdateSequenceFormat::class)->handle($plain, 'C-{seq:6}');
    expect($plain->fresh()->format)->toBe('C-{seq:6}');
});

test('a format must contain exactly one seq token', function () {
    expectValidationError(fn () => app(UpdateSequenceFormat::class)->handle($this->definition, 'INV-{yy}-{seq:3}{seq:2}'), 'format');
});

test('the next number cannot exceed the column maximum', function () {
    Livewire::actingAs(userWithPermissions('admin.sequences.view', 'admin.sequences.update'))
        ->test(Sequences::class)
        ->call('editCounter', $this->counter->id)
        ->set('nextNumber', 4294967296)
        ->call('saveCounter')
        ->assertHasErrors(['nextNumber']);
});

test('saving without admin.sequences.update is forbidden', function (string $method, array $arguments) {
    Livewire::actingAs(userWithPermissions('admin.sequences.view'))
        ->test(Sequences::class)
        ->call($method, ...$arguments)
        ->assertForbidden();
})->with([
    'editCounter' => ['editCounter', [1]],
    'saveFormat' => ['saveFormat', []],
    'saveCounter' => ['saveCounter', []],
]);
