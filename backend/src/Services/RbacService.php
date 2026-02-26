<?php

declare(strict_types=1);

namespace App\Services;

final class RbacService
{
    /**
     * @param string[] $permissions
     */
    public function can(array $permissions, string $required): bool
    {
        return in_array('*', $permissions, true) || in_array($required, $permissions, true);
    }
}
