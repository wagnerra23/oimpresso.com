<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Drivers\WhatsmeowDriver;
use Modules\Whatsapp\Services\Drivers\WhatsmeowState;

/**
 * WhatsmeowReconciler — State Machine canon WuzAPI user lifecycle (ADR 0206).
 *
 * Resolve débito raiz catalogado 2026-05-27: ChannelsController chamava
 * `POST /session/connect` direto sem verificar estado, daemon retornava
 * 500 "already connected" se sessão já ativa (WuzAPI issue #131).
 *
 * Estratégia: **SEMPRE** consulta `GET /admin/users` + `GET /session/status`
 * ANTES de qualquer mutação. Decide caminho baseado no estado real. Single
 * source of truth do estado é o daemon — não DB local.
 *
 * Reconciler é o **único ponto** que muta sessão WuzAPI. Controller +
 * webhook chamam Reconciler.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): channel.business_id global
 * scope sempre respeitado — Reconciler NUNCA atravessa fronteira business.
 *
 * Idempotente: re-rodar reconcile é seguro (operação convergente, não
 * acumulativa). Pareamento ja completo → retorna PAIRED sem mutar nada.
 *
 * Logs estruturados Pino-compat (chave `event` + `channel_id` + `business_id`
 * em cada entry).
 *
 * @see memory/decisions/0206-state-machine-whatsmeow-reconciliacao.md
 * @see memory/sessions/2026-05-27-dossier-profissionalizacao-whatsmeow.md
 */
final class WhatsmeowReconciler
{
    public function __construct(
        private readonly WhatsmeowDriver $driver,
    ) {}

    /**
     * Reconcilia o estado do channel com o daemon. Idempotente.
     *
     * Retorna o estado canon observado. Não muta nada — read-only.
     * Pra mutações use ensureProvisioned() / getQrCode() / markPairedInDb().
     */
    public function reconcile(Channel $channel): WhatsmeowState
    {
        if ($channel->type !== Channel::TYPE_WHATSAPP_WHATSMEOW) {
            // Defensive — controller já gateia, mas Reconciler nunca confia
            return WhatsmeowState::ERROR;
        }

        // 1. Daemon vivo?
        if (! $this->daemonHealthy()) {
            return WhatsmeowState::DAEMON_UNREACHABLE;
        }

        // 2. User existe no daemon?
        $userName = $channel->whatsmeowUserName();
        if ($userName === null) {
            return WhatsmeowState::ERROR;
        }

        $remoteUsers = $this->listRemoteUsers();
        $remoteUser = $this->findUserByName($remoteUsers, $userName);

        if ($remoteUser === null) {
            return WhatsmeowState::NOT_EXISTS;
        }

        // 3. Token user disponível pra consultar status?
        $cfg = $channel->config_json ?? [];
        $userToken = $cfg['whatsmeow_user_token'] ?? null;
        if (empty($userToken)) {
            // User existe no daemon mas token foi perdido no DB
            Log::warning('whatsmeow.reconcile.token_missing', [
                'event' => 'whatsmeow.reconcile.token_missing',
                'channel_id' => $channel->id,
                'business_id' => $channel->business_id,
                'remote_user' => $userName,
            ]);
            return WhatsmeowState::PROVISION_PENDING;
        }

        // 4. Consulta /session/status pra distinguir QR_PENDING / PAIRED / LOGGED_OUT
        $status = $this->fetchSessionStatus($userToken);

        $connected = (bool) ($status['Connected'] ?? $status['connected'] ?? false);
        $loggedIn = (bool) ($status['LoggedIn'] ?? $status['loggedIn'] ?? false);

        if ($connected && $loggedIn) {
            return WhatsmeowState::PAIRED;
        }

        if ($connected && ! $loggedIn) {
            // Daemon detecta JID anterior? Distinguir QR_PENDING (primeira vez)
            // de LOGGED_OUT (perdeu sessão). Pragmatismo: se channel.status já
            // foi 'active' antes E tem channel_health=disconnected, marca como
            // LOGGED_OUT pra UI mostrar "Re-conecte com novo QR".
            if ($channel->status === 'active' || $channel->channel_health === 'disconnected') {
                return WhatsmeowState::LOGGED_OUT;
            }
            return WhatsmeowState::QR_PENDING;
        }

        return WhatsmeowState::PROVISION_PENDING;
    }

    /**
     * Garante user provisionado no daemon. Idempotente.
     *
     * Se NOT_EXISTS → cria via POST /admin/users + grava token em config_json.
     * Se já existe → retorna config atual sem mutar.
     *
     * @return array{token: string, name: string, webhook: string}
     */
    public function ensureProvisioned(Channel $channel): array
    {
        $cfg = $channel->config_json ?? [];
        $userToken = $cfg['whatsmeow_user_token'] ?? null;

        // Já provisionado? Retorna config existente
        if (! empty($userToken)) {
            return [
                'token' => (string) $userToken,
                'name' => (string) ($cfg['whatsmeow_user_name'] ?? $channel->whatsmeowUserName() ?? ''),
                'webhook' => (string) ($cfg['whatsmeow_webhook_url'] ?? ''),
            ];
        }

        // Resolve business UUID pra webhook multi-tenant
        $business = DB::table('business')->where('id', $channel->business_id)->first();
        if ($business === null || empty($business->uuid)) {
            throw new \RuntimeException(
                'Business #'.$channel->business_id.' sem uuid — execute migration add_uuid_to_business_table.'
            );
        }

        $provision = $this->driver->provisionSession($channel, (string) $business->uuid);

        $cfg['whatsmeow_user_token'] = $provision['token'];
        $cfg['whatsmeow_user_name'] = $provision['name'];
        $cfg['whatsmeow_webhook_url'] = $provision['webhook'];
        $channel->config_json = $cfg;
        $channel->save();

        Log::info('whatsmeow.reconcile.provisioned', [
            'event' => 'whatsmeow.reconcile.provisioned',
            'channel_id' => $channel->id,
            'business_id' => $channel->business_id,
            'user_name' => $provision['name'],
        ]);

        return $provision;
    }

    /**
     * Retorna QR code base64 (sem prefix data:image) ou null se PAIRED.
     *
     * Dispara connect se necessário (estado PROVISION_PENDING). Idempotente
     * em estados QR_PENDING / PAIRED.
     */
    public function getQrCode(Channel $channel): ?string
    {
        $cfg = $channel->config_json ?? [];
        $userToken = $cfg['whatsmeow_user_token'] ?? null;

        if (empty($userToken)) {
            throw new \RuntimeException('Channel não provisionado — chame ensureProvisioned() antes.');
        }

        $state = $this->reconcile($channel);

        if ($state === WhatsmeowState::PAIRED) {
            return null;
        }

        // Dispara connect se ainda não conectou no daemon-side
        $result = $this->driver->connect($channel);
        return $result['qr_base64'] ?? null;
    }

    /**
     * Atualiza channel no DB quando webhook Connected/PairSuccess chega.
     *
     * Marca channel.status=active + channel_health=healthy. Idempotente —
     * re-chamar com mesmo JID é no-op (apenas atualiza last_health_check_at).
     */
    public function markPairedInDb(Channel $channel, ?string $jid = null): void
    {
        $cfg = $channel->config_json ?? [];
        if ($jid !== null) {
            $cfg['whatsmeow_jid'] = $jid;
        }
        $channel->config_json = $cfg;

        $channel->forceFill([
            'status' => 'active',
            'channel_health' => 'healthy',
            'channel_health_consecutive_failures' => 0,
            'last_health_check_at' => now(),
            'last_health_message' => 'whatsmeow paired'.($jid ? " (jid={$jid})" : ''),
        ])->save();

        Log::info('whatsmeow.reconcile.marked_paired', [
            'event' => 'whatsmeow.reconcile.marked_paired',
            'channel_id' => $channel->id,
            'business_id' => $channel->business_id,
            'jid' => $jid,
        ]);
    }

    /**
     * Atualiza channel quando webhook Disconnected/LoggedOut chega.
     *
     * banDetected=true marca como banned (estado P0 — alerta humano).
     */
    public function markDisconnectedInDb(Channel $channel, string $reason, bool $banDetected = false): void
    {
        $newHealth = $banDetected ? 'banned' : 'disconnected';

        $channel->forceFill([
            'channel_health' => $newHealth,
            'last_health_check_at' => now(),
            'last_health_message' => "whatsmeow disconnected: {$reason}",
        ])->save();

        Log::warning('whatsmeow.reconcile.marked_disconnected', [
            'event' => 'whatsmeow.reconcile.marked_disconnected',
            'channel_id' => $channel->id,
            'business_id' => $channel->business_id,
            'reason' => $reason,
            'ban_detected' => $banDetected,
        ]);
    }

    /**
     * Resolve channel pelo user_name no daemon (Username payload webhook).
     *
     * Multi-tenant Tier 0: escopado por $businessId — nunca cross-tenant.
     * Usado pelo middleware quando payload Connected vem com Username.
     */
    public function resolveChannelByUserName(int $businessId, string $userName): ?Channel
    {
        // Fluxo de webhook do daemon WuzAPI, que chega sem session de tenant —
        // $businessId é resolvido pelo middleware e passado explícito; o
        // where('business_id') abaixo garante isolamento Tier 0.
        return Channel::query()
            ->withoutGlobalScopes() // SUPERADMIN: webhook sem session, escopado por business_id explícito (ADR 0093)
            ->where('business_id', $businessId)
            ->where('type', Channel::TYPE_WHATSAPP_WHATSMEOW)
            ->get()
            ->first(fn (Channel $ch) => $ch->whatsmeowUserName() === $userName);
    }

    /**
     * Resolve channel "em pareamento ativo" pra fallback quando payload
     * webhook Connected NÃO vem com Username (WuzAPI versão antiga).
     *
     * Heurística: primeiro channel whatsmeow do business em estado
     * setup/disconnected (não-pareado). Multi-tenant Tier 0 escopado.
     *
     * Edge case: se houver 2+ channels simultâneos pareando, atualiza só
     * o primeiro — outros vão atualizar quando próximo webhook chegar
     * (cada channel tem seu próprio user_token + webhook).
     */
    public function resolveChannelForPendingPair(int $businessId): ?Channel
    {
        // Fallback de webhook do daemon WuzAPI sem session de tenant — $businessId
        // vem do middleware; where('business_id') mantém isolamento Tier 0 (nunca
        // atravessa fronteira business).
        return Channel::query()
            ->withoutGlobalScopes() // SUPERADMIN: webhook sem session, escopado por business_id explícito (ADR 0093)
            ->where('business_id', $businessId)
            ->where('type', Channel::TYPE_WHATSAPP_WHATSMEOW)
            ->whereIn('status', ['setup', 'disconnected'])
            ->orderBy('updated_at', 'desc')
            ->first();
    }

    // ─── Helpers internos ─────────────────────────────────────────────

    private function daemonHealthy(): bool
    {
        $daemonUrl = (string) config('whatsapp.whatsmeow.daemon_url');
        if (empty($daemonUrl)) {
            return false;
        }

        try {
            // Health endpoint pode não existir em todas versões WuzAPI — usa
            // /admin/users como liveness proxy (sempre retorna 200 ou 401).
            $r = Http::baseUrl($daemonUrl)
                ->timeout(3)
                ->withoutVerifying()
                ->withHeaders(['Authorization' => (string) config('whatsapp.whatsmeow.api_key')])
                ->get('/admin/users');

            // 200 ou 401 = daemon vivo (401 só significa token errado, daemon respondeu)
            return $r->successful() || $r->status() === 401;
        } catch (\Throwable $e) {
            Log::warning('whatsmeow.reconcile.daemon_unreachable', [
                'event' => 'whatsmeow.reconcile.daemon_unreachable',
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function listRemoteUsers(): array
    {
        $daemonUrl = (string) config('whatsapp.whatsmeow.daemon_url');
        $adminToken = (string) config('whatsapp.whatsmeow.api_key');
        $timeout = (int) config('whatsapp.whatsmeow.request_timeout', 10);

        try {
            $r = Http::baseUrl($daemonUrl)
                ->timeout($timeout)
                ->withoutVerifying()
                ->withHeaders(['Authorization' => $adminToken])
                ->get('/admin/users');

            if (! $r->successful()) {
                return [];
            }

            $body = $r->json();
            // WuzAPI envelopa em {code, data: [...], success} OU retorna array direto
            return $body['data'] ?? (is_array($body) ? $body : []);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Procura user na lista por name. WuzAPI retorna shape variável
     * ({name, token, ...} ou {Name, Token, ...}).
     */
    private function findUserByName(array $users, string $userName): ?array
    {
        foreach ($users as $user) {
            if (! is_array($user)) {
                continue;
            }
            $name = $user['name'] ?? $user['Name'] ?? null;
            if ($name === $userName) {
                return $user;
            }
        }
        return null;
    }

    private function fetchSessionStatus(string $userToken): array
    {
        $daemonUrl = (string) config('whatsapp.whatsmeow.daemon_url');
        $timeout = (int) config('whatsapp.whatsmeow.request_timeout', 10);

        try {
            $r = Http::baseUrl($daemonUrl)
                ->timeout($timeout)
                ->withoutVerifying()
                ->withHeaders(['Token' => $userToken])
                ->get('/session/status');

            if (! $r->successful()) {
                return ['Connected' => false, 'LoggedIn' => false];
            }

            $body = $r->json();
            // WuzAPI envelope: {code, data: {Connected, LoggedIn, Jid}, success}
            return $body['data'] ?? $body ?? [];
        } catch (\Throwable) {
            return ['Connected' => false, 'LoggedIn' => false];
        }
    }
}
