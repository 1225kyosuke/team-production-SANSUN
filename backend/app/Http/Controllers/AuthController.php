<?php

namespace App\Http\Controllers;

use App\Models\ApiSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $credentials['email'])->first();
        if (!$user || !$user->is_active || !Hash::check($credentials['password'], $user->password)) return response()->json(['message' => 'メールアドレスまたはパスワードが正しくありません。'], 422);
        ApiSession::where('user_id', $user->id)->delete();
        $token = Str::random(64);
        ApiSession::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'user_agent' => $request->userAgent(), 'ip_address' => $request->ip(), 'last_activity_at' => now()]);
        return response()->json(['token' => $token, 'user' => $user, 'expires_in' => 600]);
    }
    public function me(Request $request): JsonResponse { return response()->json($request->user()); }
    public function logout(Request $request): JsonResponse { $request->attributes->get('api_session')?->delete(); return response()->json(['message' => 'ログアウトしました。']); }
}
