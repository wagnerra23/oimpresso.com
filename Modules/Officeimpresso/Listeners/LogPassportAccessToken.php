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
            $ip = $request?->ip();
            $userId = $event->userId;

            // Dedup por (user + ip + event) no mesmo minuto — passwor grant pode
            // emitir multiplos AccessTokenCreated por login (1 access + 1
            // personal_access). Tratamos como 1 evento de UX.
            $exists = LicencaLog::where('user_id', $userId)
                ->where('ip', $ip)
                ->where('event', 'login_success')
                ->where('created_at', '>=', now()->subMinute())
                ->exists();
            if ($exists) return;

            // Enriquece com business_id via user (seguro — 1:1)
            $businessId = null;
            if ($userId) {
                $businessId = \DB::table('users')->where('id', $userId)->value('business_id');
            }

            // Match exato de licenca_computador pelo `hd` (serial do disco)
            // que o Delphi POR ENQUARTO nao envia em /oauth/token.
            // Quando Delphi for atualizado pra enviar hd como custom param,
            // este match vai funcionar automaticamente. Fallback: null.
            $licencaId = null;
            $hdFromRequest = $request?->input('hd') ?: $request?->header('X-OI-HD');
            if ($hdFromRequest && $businessId) {
                $licencaId = \DB::table('licenca_computador')
                    ->where('business_id', $businessId)
                    ->where('hd', $hdFromRequest)
                    ->value('id');
            }
            // metadata guarda o hd recebido pra debug futuro
            $metadata = $hdFromRequest ? ['hd' => $hdFromRequest] : null;

            LicencaLog::create([
                'event'       => 'login_success',
                'licenca_id'  => $licencaId,
                'business_id' => $businessId,
                'user_id'     => $userId,
                'client_id'   => (string) $event->clientId,
                'token_hint'  => $this->tokenHint($event->tokenId),
                'ip'          => $ip,
                'user_agent'  => Str::limit($request?->userAgent() ?? '', 500, ''),
                'endpoint'    => '/oauth/token',
                'http_method' => 'POST',
                'http_status' => 200,
                'metadata'    => $metadata,
                'source'      => 'passport_event',
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('LogPassportAccessToken falhou: ' . $e->getMessage());
        }
    }

    private function tokenHint(?string $tokenId): ?string
    {
        if (! $tokenId) return null;
        return substr($tokenId, 0, 8) . '…' . substr($tokenId, -4);
    }
}
