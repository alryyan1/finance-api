<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
