<?php

namespace Modules\Officeimpresso\Listeners;

use Laravel\Passport\Events\AccessTokenCreated;
use Modules\Officeimpresso\Entities\LicencaLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Escuta AccessTokenCreated do Passport e grava login_success em licenca_log
 * com contexto rico (IP, user-agent, client_id).
 *
 * SEGURANCA: toda a operacao eh dentro de try/catch. Se qualquer coisa
 * falhar (DB down, migration desatualizada, etc.), o Passport continua
 * funcionando normalmente — Delphi nao e afetado.
 */
class LogPassportAccessToken
{
    public function handle(AccessTokenCreated $event): void
    {
        try {
            $request = request();

            LicencaLog::create([
                'event'       => 'login_success',
                'user_id'     => $event->userId,
                'client_id'   => (string) $event->clientId,
                'token_hint'  => $this->tokenHint($event->tokenId),
                'ip'          => $request?->ip(),
                'user_agent'  => Str::limit($request?->userAgent() ?? '', 500, ''),
                'endpoint'    => '/oauth/token',
                'http_method' => 'POST',
                'http_status' => 200,
                'source'      => 'passport_event',
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Nao propaga — Passport tem que continuar emitindo token mesmo se
            // o log falhar. Registra em laravel.log pra debug.
            Log::warning('LogPassportAccessToken falhou: ' . $e->getMessage());
        }
    }

    private function tokenHint(?string $tokenId): ?string
    {
        if (! $tokenId) return null;
        return substr($tokenId, 0, 8) . '…' . substr($tokenId, -4);
    }
}
