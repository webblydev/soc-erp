<?php

namespace App\Support\Collaboration;

use App\Models\User;

/**
 * A record that carries attachments or notes. Reading, downloading and adding them all require
 * the user to be able to see the record itself (FD-BR-09).
 */
interface Collaborative
{
    public function isViewableBy(User $user): bool;
}
