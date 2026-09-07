<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        if (Auth::check()) {
            return response()->json(['user' => $this->withAbilities(Auth::user())]);
        }

        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['username' => $request->input('username'), 'password' => $request->input('password')], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => ['بيانات الاعتماد المدخلة غير صحيحة.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $this->withAbilities(Auth::user()),
        ]);
    }

    /**
     * Token-based login for native clients (mobile app) that can't use the
     * cookie/session SPA flow. Returns a Sanctum personal access token the
     * client sends as `Authorization: Bearer <token>` on subsequent requests.
     */
    public function mobileLogin(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string'],
        ]);

        $user = User::where('username', $request->input('username'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['بيانات الاعتماد المدخلة غير صحيحة.'],
            ]);
        }

        $token = $user->createToken($request->input('device_name'));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->withAbilities($user),
        ]);
    }

    /**
     * Revoke only the token used for the current request (single device),
     * leaving other devices' tokens and the web session untouched.
     */
    public function mobileLogout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح.']);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح.']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($this->withAbilities($request->user()));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $user->password = $request->input('password');
        $user->save();

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح.']);
    }

    /**
     * Serialize the user with their role names and effective permission names,
     * so the frontend can gate UI without a round-trip per permission check.
     */
    private function withAbilities(User $user): array
    {
        return [
            ...$user->toArray(),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
