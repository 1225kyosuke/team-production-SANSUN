<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordLinkService
{
    public function sendSetup(User $user, bool $reset = false): void
    {
        $plainToken = Str::random(64);
        $user->forceFill([
            'must_set_password' => true,
            'password_setup_token_hash' => hash('sha256', $plainToken),
            'password_setup_expires_at' => now()->addHours($reset ? 1 : 24),
        ])->save();

        $url = rtrim((string) (env('FRONTEND_URL') ?: config('app.url')), '/') . '/password/setup?token=' . urlencode($plainToken) . '&email=' . urlencode($user->email);
        $title = $reset ? 'パスワード再設定のご案内' : '初期パスワード設定のご案内';
        Mail::raw("{$user->name} 様\n\n{$title}です。\n以下のリンクからパスワードを設定してください。\n\n{$url}\n\nこのリンクの有効期限は" . ($reset ? '1時間' : '24時間') . "です。", function ($message) use ($user, $title) {
            $message->to($user->email)->subject('[SANSUN学園] ' . $title);
        });
    }
}
