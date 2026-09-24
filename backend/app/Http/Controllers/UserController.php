<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin-only user management. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        return User::query()
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->query('search'), fn ($q, $s) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")
            ))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(User::ROLES)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        if (in_array($data['role'], [User::ROLE_DOCTOR, User::ROLE_PATIENT], true)) {
            return response()->json([
                'message' => 'Create doctor and patient accounts through their dedicated management screens so the required profile is created too.',
            ], 422);
        }

        return response()->json(User::create($data), 201);
    }

    public function show(User $user)
    {
        return $user->load(['doctor', 'patient']);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        if (array_key_exists('password', $data) && ! $data['password']) {
            unset($data['password']);
        }

        $roleChanged = array_key_exists('role', $data) && $data['role'] !== $user->role;
        $hasClinicalRole = in_array($data['role'] ?? $user->role, [User::ROLE_DOCTOR, User::ROLE_PATIENT], true)
            || in_array($user->role, [User::ROLE_DOCTOR, User::ROLE_PATIENT], true);

        if ($roleChanged && $hasClinicalRole) {
            return response()->json([
                'message' => 'Doctor and patient roles are managed with their linked profiles and cannot be changed here.',
            ], 422);
        }

        $user->update($data);

        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $user->tokens()->delete();
        }

        return $user;
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
