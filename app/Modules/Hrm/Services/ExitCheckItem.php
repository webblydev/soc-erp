<?php

namespace App\Modules\Hrm\Services;

/**
 * One line of the exit checklist. A blocking item stops the exit until it is resolved.
 */
final readonly class ExitCheckItem
{
    public function __construct(public string $label, public ?string $url = null, public bool $blocking = false) {}
}
