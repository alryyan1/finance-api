<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'created_at'])
            ->map(fn ($u) => array_merge($u->toArray(), [
                'roles' => $u->roles->pluck('name'),
            ]));

        return response()->json($users);
    }

    public function roles(): JsonResponse
    {
        return response()->json(Role::orderBy('name')->get(['id', 'name']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'roles'    => ['nullable', 'array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ]);

        $user = User::create($data);

        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return response()->json($this->userWithRoles($user), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username,' . $user->id],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', Password::min(8)],
            'roles'    => ['nullable', 'array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        $user->syncRoles($data['roles'] ?? []);

        return response()->json($this->userWithRoles($user->fresh()));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'لا يمكنك حذف حسابك الخاص.');

        $user->delete();

        return response()->json(['message' => 'تم حذف المستخدم بنجاح.']);
    }

    private function userWithRoles(User $user): array
    {
        return array_merge(
            $user->only(['id', 'name', 'username', 'email', 'created_at']),
            ['roles' => $user->getRoleNames()]
        );
    }
}
