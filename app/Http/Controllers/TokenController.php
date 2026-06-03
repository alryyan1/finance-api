<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->select(['id', 'name', 'created_at', 'last_used_at'])->get();
        return response()->json($tokens);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:100']]);

        $token = $request->user()->createToken($request->input('name'));

        return response()->json([
            'id'           => $token->accessToken->id,
            'name'         => $token->accessToken->name,
            'plain_token'  => $token->plainTextToken,
            'created_at'   => $token->accessToken->created_at,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->tokens()->where('id', $id)->delete();
        return response()->json(['message' => 'تم حذف الرمز.']);
    }
}
