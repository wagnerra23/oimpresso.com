<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncrypter;

class EncryptCookies extends BaseEncrypter
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        // ADR 0191 — consent banner LGPD. Payload é 2 booleans + timestamp
        // (zero PII). Manter unencrypted permite leitura via
        // `request()->cookie()` no PHP sem decrypt (fonte de verdade compartilhada
        // entre Blade legacy e share Inertia). Risco: zero — nada sensível.
        'oimpresso_consent_v1',
    ];
}
