<?php

namespace App\Support\Exports;

use App\Models\User;
use App\Support\AuditTrail\AuditTrail;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ListingExport
{
    /**
     * Download the rows as Excel and record who exported what (docs/01 §10).
     *
     * @param  Builder<covariant Model>  $query
     * @param  array<string, string|Closure>  $columns
     */
    public static function download(string $name, Builder $query, array $columns): BinaryFileResponse
    {
        /** @var User $actor */
        $actor = Auth::user();

        AuditTrail::record($actor, 'exported', null, ['export' => $name, 'rows' => (clone $query)->toBase()->getCountForPagination()]);

        return Excel::download(new QueryExport($query, $columns), $name.'-'.now()->format('Ymd-His').'.xlsx');
    }
}
