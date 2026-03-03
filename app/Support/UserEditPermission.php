<?php

namespace App\Support;

use App\Models\User;

class UserEditPermission
{
    public static function canEdit(?User $authUser, User $targetUser): bool
    {
        if (! $authUser) {
            return false;
        }

        return match ($authUser->role) {
            'admin' => true,
            'manager' => $targetUser->role === 'user',
            'user' => $authUser->id === $targetUser->id,
            default => false,
        };
    }
}
