<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * POST /api/consent — registra decisão LGPD do user (ADR 0191, pré-req Clarity).
 * Sem auth: banner aparece pra anônimo na landing. Cookie é unencrypted
 * (excluído em EncryptCookies — 2 bools + ts, zero PII) com HttpOnly+SameSite=Lax.
 * `necessary` é implícito (cookies session/CSRF Laravel são não-opcionais).
 */
class ConsentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'analytics' => ['required', 'boolean'],
            'marketing' => ['required', 'boolean'],
        ]);

        $cookieName = (string) config('services.consent.cookie_name', 'oimpresso_consent_v1');
        $ttlMinutes = (int) config('services.consent.cookie_ttl_days', 365) * 24 * 60;

        Cookie::queue(
            $cookieName,
            json_encode([
                'analytics' => (bool) $validated['analytics'],
                'marketing' => (bool) $validated['marketing'],
                'ts'        => now()->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES),
            $ttlMinutes,
            '/',
            null,
            (bool) config('session.secure'), // secure = igual sessão Laravel
            true,                            // HttpOnly
            false,                           // raw
            'lax'                            // SameSite
        );

        return response()->noContent();
    }
}
