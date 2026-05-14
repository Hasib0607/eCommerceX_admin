<?php

namespace App\Services\WhatsAppAutomation;

use App\Models\User;
use App\Support\WhatsAppAutomation\Permissions;

class PermissionService
{
    public function canAccess(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->hasExplicitPermission($user, Permissions::ACCESS);
    }

    public function permissionsFor(User $user): array
    {
        if ($this->isSuperAdmin($user)) {
            return Permissions::all();
        }

        $granted = [];

        foreach (Permissions::all() as $permission) {
            if ($this->hasExplicitPermission($user, $permission)) {
                $granted[] = $permission;
            }
        }

        return $granted;
    }

    protected function isSuperAdmin(User $user): bool
    {
        // Adjust this to your real role logic
        return in_array($user->type ?? '', ['super_admin', 'superadmin'], true)
            || (int) ($user->is_super_admin ?? 0) === 1;
    }

    protected function hasExplicitPermission(User $user, string $permission): bool
    {
        // Replace this with your own existing permission system.
        // Example ideas:
        // - $user->can($permission)
        // - helper canAccess(...)
        // - staff role table lookup
        if (method_exists($user, 'can')) {
            return (bool) $user->can($permission);
        }

        return false;
    }
}