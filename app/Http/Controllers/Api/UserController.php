<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminNewUserNotificationMail;
use App\Mail\UserCreatedMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'name' => ['required', 'string', 'min:3', 'max:50'],
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'name' => $validated['name'],
            'role' => 'user',
            'active' => true,
        ]);

        Mail::to($user->email)->send(new UserCreatedMail($user));

        $adminEmail = (string) config('mail.admin_address', 'admin@example.com');
        Mail::to($adminEmail)->send(new AdminNewUserNotificationMail($user));

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'created_at' => $user->created_at?->toISOString(),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sortBy' => ['nullable', 'string', Rule::in(['name', 'email', 'created_at'])],
        ]);

        $sortBy = $validated['sortBy'] ?? 'created_at';
        $sortDirection = $sortBy === 'created_at' ? 'desc' : 'asc';
        $search = $validated['search'] ?? null;
        $authUser = $request->user();

        $users = User::query()
            ->where('active', true)
            ->when($search, function ($query, $searchValue) {
                $query->where(function ($searchQuery) use ($searchValue) {
                    $searchQuery
                        ->where('name', 'like', '%'.$searchValue.'%')
                        ->orWhere('email', 'like', '%'.$searchValue.'%');
                });
            })
            ->withCount('orders')
            ->orderBy($sortBy, $sortDirection)
            ->paginate(perPage: 15);

        return response()->json([
            'page' => $users->currentPage(),
            'users' => collect($users->items())->map(function (User $user) use ($authUser) {
                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->role,
                    'created_at' => $user->created_at?->toISOString(),
                    'orders_count' => $user->orders_count,
                    'can_edit' => $this->canEditUser($authUser, $user),
                ];
            })->values()->all(),
        ]);
    }

    private function canEditUser(?User $authUser, User $targetUser): bool
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
