<?php

namespace App\Support\Imports;

final readonly class ImportOutcome
{
    /**
     * @param  list<ImportRow>  $failed
     */
    public function __construct(public int $created, public int $updated, public array $failed) {}
}
