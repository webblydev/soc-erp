<?php

use App\Models\User;
use App\Modules\Foundation\Models\AuditLog;
use App\Support\Exports\ListingExport;
use App\Support\Exports\QueryExport;
use Maatwebsite\Excel\Facades\Excel;

test('a listing export downloads the query rows and records an audit row', function () {
    Excel::fake();
    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(9, 30, 0));
    $actor = User::factory()->create(['username' => 'exporter']);
    $this->actingAs($actor);

    ListingExport::download('users', User::query(), ['Username' => 'username', 'Name' => fn (User $user): string => strtoupper($user->name)]);

    Excel::assertDownloaded('users-20261006-093000.xlsx', function (QueryExport $export) {
        return $export->headings() === ['Username', 'Name']
            && $export->map(User::query()->where('username', 'exporter')->first())[0] === 'exporter';
    });

    expect(AuditLog::query()->where('event', 'exported')->where('auditable_id', $actor->id)->first()?->new_values)
        ->toBe(['export' => 'users', 'rows' => 1]);
});
