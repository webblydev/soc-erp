<?php

namespace App\Modules\Crm\Actions;

/**
 * A lead or customer that shares a phone or email with the record being saved (CRM-BR-03).
 */
final readonly class DuplicateMatch
{
    public function __construct(
        public string $type,
        public int $id,
        public string $number,
        public string $name,
        public string $status,
        public ?string $owner,
    ) {}
}
