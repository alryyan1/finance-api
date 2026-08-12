<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name')->orderBy('name')->get();
        $userCounts = $this->userCountsByRole($roles->pluck('id'));

        return response()->json(
            $roles->map(fn (Role $role) => $this->roleWithPermissions($role, $userCounts[$role->id] ?? 0))
        );
    }

    public function permissions(): JsonResponse
    {
        return response()->json(
            Permission::orderBy('name')->pluck('name')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return response()->json($this->roleWithPermissions($role->fresh(['permissions']), 0), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_if($role->name === 'admin', 422, 'لا يمكن تعديل دور "مدير النظام".');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name,'.$role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        $userCount = $this->userCountsByRole(collect([$role->id]))[$role->id] ?? 0;

        return response()->json($this->roleWithPermissions($role->fresh(['permissions']), $userCount));
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_if($role->name === 'admin', 422, 'لا يمكن حذف دور "مدير النظام".');

        $inUse = ($this->userCountsByRole(collect([$role->id]))[$role->id] ?? 0) > 0;
        abort_if($inUse, 422, 'لا يمكن حذف دور مُستخدَم من قبل مستخدمين. أزل الدور منهم أولاً.');

        $role->delete();

        return response()->json(['message' => 'تم حذف الدور بنجاح.']);
    }

    /**
     * Count users per role directly against the pivot table, bypassing Spatie's
     * Role::users() relation — it resolves its related model from the *current
     * default auth guard*, which Laravel's auth:sanctum middleware temporarily
     * switches to "sanctum" mid-request (a guard not present in config/auth.php),
     * breaking relation resolution for every request that reaches this controller.
     *
     * @return array<int, int> role id => user count
     */
    private function userCountsByRole($roleIds): array
    {
        return DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('role_id', $roleIds)
            ->select('role_id', DB::raw('count(*) as aggregate'))
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id')
            ->all();
    }

    private function roleWithPermissions(Role $role, int $usersCount): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => $usersCount,
        ];
    }
}
