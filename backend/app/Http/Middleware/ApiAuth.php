<?php

namespace App\Http\Middleware;

use App\Models\ApiSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $session = $token ? ApiSession::with('user')->where('token_hash', hash('sha256', $token))->first() : null;
        if (!$session || !$session->user?->is_active) return response()->json(['message' => '認証が必要です。'], 401);
        if ($session->last_activity_at->lt(now()->subMinutes(10))) { $session->delete(); return response()->json(['message' => '10分間操作がなかったため自動ログアウトしました。'], 401); }
        $session->update(['last_activity_at' => now()]);
        $request->setUserResolver(fn () => $session->user);
        $request->attributes->set('api_session', $session);
        return $next($request);
    }
}
