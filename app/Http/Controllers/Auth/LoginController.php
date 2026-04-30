<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        if (Auth::check()) {
            return response()->json(['user' => Auth::user()]);
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
            'user' => Auth::user(),
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
        return response()->json($request->user());
    }
}
