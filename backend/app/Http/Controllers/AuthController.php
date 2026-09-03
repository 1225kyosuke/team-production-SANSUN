<?php

namespace App\Http\Controllers;

use App\Models\ApiSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Services\PasswordLinkService;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $credentials['email'])->first();
        if (!$user || !$user->is_active || !Hash::check($credentials['password'], $user->password)) return response()->json(['message' => 'メールアドレスまたはパスワードが正しくありません。'], 422);
        if ($user->must_set_password) return response()->json(['message' => 'メールに記載されたリンクから初期パスワードを設定してください。','must_set_password'=>true], 403);
        ApiSession::where('user_id', $user->id)->delete();
        $token = Str::random(64);
        ApiSession::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'user_agent' => $request->userAgent(), 'ip_address' => $request->ip(), 'last_activity_at' => now()]);
        return response()->json(['token' => $token, 'user' => $user, 'expires_in' => 600]);
    }
    public function me(Request $request): JsonResponse { return response()->json($request->user()); }
    public function logout(Request $request): JsonResponse { $request->attributes->get('api_session')?->delete(); return response()->json(['message' => 'ログアウトしました。']); }

    public function setupPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'token' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = User::where('email', $data['email'])->where('password_setup_token_hash', hash('sha256', $data['token']))->first();
        if (!$user || !$user->password_setup_expires_at || $user->password_setup_expires_at->isPast()) return response()->json(['message' => 'リンクが無効または期限切れです。'], 422);
        $user->forceFill(['password' => Hash::make($data['password']), 'must_set_password' => false, 'password_setup_token_hash' => null, 'password_setup_expires_at' => null, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        return response()->json(['message' => 'パスワードを設定しました。ログインできます。']);
    }

    public function requestReset(Request $request, PasswordLinkService $links): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        if ($user = User::where('email', $data['email'])->where('is_active', true)->first()) $links->sendSetup($user, true);
        return response()->json(['message' => '登録されている場合は、パスワード再設定用リンクをメールで送信しました。']);
    }
}
