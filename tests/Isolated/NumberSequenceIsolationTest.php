<?php

use App\Modules\Foundation\Models\NumberSequenceFormat;
use App\Support\NumberSequenceService;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

beforeEach(fn () => NumberSequenceFormat::query()->create([
    'document_type' => 'invoice', 'format' => 'INV-{yy}-{seq:5}', 'reset_policy' => 'fiscal_year',
]));

test('numbers cannot be issued outside a transaction', function () {
    app(NumberSequenceService::class)->next('invoice');
})->throws(LogicException::class);

test('fifty parallel requests never receive the same number', function () {
    $code = 'echo Illuminate\Support\Facades\DB::transaction(fn () => app(App\Support\NumberSequenceService::class)->next("invoice"));';

    $results = Process::pool(function (Pool $pool) use ($code): void {
        foreach (range(1, 50) as $i) {
            $pool->path(base_path())->timeout(120)->command(['php', 'artisan', 'tinker', '--execute', $code]);
        }
    })->start()->wait();

    $numbers = collect($results)->map(fn ($result) => trim($result->output()));

    expect($numbers->filter(fn (string $n) => str_starts_with($n, 'INV-')))->toHaveCount(50)
        ->and($numbers->unique())->toHaveCount(50);
})->group('mysql')->skip(fn () => config('database.default') !== 'mysql', 'Requires MySQL (FD-AC-05).');
