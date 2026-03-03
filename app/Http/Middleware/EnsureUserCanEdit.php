<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\UserEditPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanEdit
{
    public function handle(Request $request, Closure $next, string $parameter = 'user'): Response
    {
        $target = $request->route($parameter);
        $targetUser = $target instanceof User ? $target : User::find($target);

        if (! $targetUser) {
            abort(404, 'User not found.');
        }

        if (! UserEditPermission::canEdit($request->user(), $targetUser)) {
            abort(403, 'You are not allowed to edit this user.');
        }

        return $next($request);
    }
}
