<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Webhook\MessagePersister;

/**
 * Import histórico Baileys 90 dias (US-WA-080).
 *
 * Wagner pediu (2026-05-12): biz=1 só tem msgs desde 11/05 11:37 (~24h)
 * porque webhook só captura inbound/outbound real-time. Baileys 6.7.9
 * expõe `socket.fetchMessageHistory(count, oldestKey, oldestTs)` que
 * pede HISTORY_SYNC_ON_DEMAND ao WhatsApp — chip precisa estar
 * conectado E ter histórico no device.
 *
 * Fluxo:
 *   1. Pra cada Conversation existente no business, pega msg mais antiga
 *      no DB como cursor inicial (se houver) OU msg mais recente (pra
 *      conversas novas sem histórico DB)
 *   2. Loop: POST /instances/{instance_id}/history → daemon → WhatsApp
 *      → batch de até 100 msgs → MessagePersister persiste idempotente
 *   3. Atualiza cursor pra oldest_id/oldest_ts do batch retornado
 *   4. Sleep 1.5s entre chamadas (anti-ban — Wagner regra empírica)
 *   5. Para quando: cutoff `--since` atingido OR has_more=false OR
 *      empty=true OR `--max` cap OR daemon erro repetido
 *
 * Uso:
 *   php artisan whatsapp:import-history --channel=3 --since=90d
 *   php artisan whatsapp:import-history --channel=3 --since=2026-04-01 --dry-run
 *   php artisan whatsapp:import-history --channel=3 --max=500
 *   php artisan whatsapp:import-history --channel=3 --conversation=42  (1 conv só)
 *
 * Multi-tenant Tier 0 (ADR 0093):
 * - Channel resolvido por --channel (FK explícita), business_id derivado
 * - Todas queries com withoutGlobalScope + filtro business_id explícito
 * - SUPERADMIN comment justifica bypass
 *
 * Limitação WhatsApp:
 * - Só puxa o que o CHIP tem no device storage. Se o WhatsApp do chip
 *   teve "clear chat" ou foi recém-pareado, daemon recebe vazio.
 * - 90 dias é cap prático — WhatsApp não garante histórico ilimitado.
 *
 * Anti-ban:
 * - Sleep 1.5s entre chamadas (configurável via --sleep)
 * - WhatsApp pode flagear chip fetcheando muito rápido (ban_detected)
 * - `--max` cap obrigatório (default 2000) impede acidente exponencial
 *
 * Deploy:
 * - Daemon CT 100 precisa ter PR mergeado + build + restart container
 *   ANTES desse comando funcionar (daemon expor /history)
 * - Hostinger só roda PHP — sem deps novas no composer
 *
 * @see Modules/Whatsapp/Services/Webhook/MessagePersister.php
 * @see Modules/Whatsapp/daemon-node/src/http/routes/messages.ts
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-080
 */
class ImportHistoryCommand extends Command
{
    protected $signature = 'whatsapp:import-history
                            {--channel= : Channel ID (required)}
                            {--since=90d : Tempo retroativo (90d, 30d, ISO date 2026-04-01)}
                            {--max=2000 : Cap total mensagens importadas (anti-acidente)}
                            {--conversation= : Filtra 1 conversation_id só (debug)}
                            {--batch-size=50 : Mensagens por chamada (1-100)}
                            {--sleep=1500 : Sleep ms entre chamadas (anti-ban)}
                            {--max-empty-rounds=2 : Para após N respostas vazias da mesma conv}
                            {--dry-run : Preview sem persistir}';

    protected $description = 'Importa histórico Baileys de uma Channel (até 90d retroativo).';

    public function handle(): int
    {
        $channelId = (int) $this->option('channel');
        if ($channelId <= 0) {
            $this->error('--channel=<ID> obrigatório.');
            return self::FAILURE;
        }

        $sinceCutoff = $this->parseSince((string) $this->option('since'));
        $max = (int) $this->option('max');
        $batchSize = max(1, min(100, (int) $this->option('batch-size')));
        $sleepMs = max(0, (int) $this->option('sleep'));
        $maxEmptyRounds = max(1, (int) $this->option('max-empty-rounds'));
        $convFilter = $this->option('conversation') ? (int) $this->option('conversation') : null;
        $dryRun = (bool) $this->option('dry-run');

        // SUPERADMIN: CLI cross-business — bypass scope explícito (ADR 0093)
        $channel = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->find($channelId);

        if (! $channel) {
            $this->error("Channel #{$channelId} não encontrado.");
            return self::FAILURE;
        }

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            $this->error("Channel #{$channelId} tipo '{$channel->type}' — só Baileys suporta import histórico (ZAPI/Meta usam Graph API outros endpoints).");
            return self::FAILURE;
        }

        $businessId = (int) $channel->business_id;
        $instanceId = $this->resolveInstanceId($channel);

        $this->info("Import histórico Baileys");
        $this->line("  channel  : #{$channel->id} '{$channel->label}' (biz={$businessId})");
        $this->line("  instance : {$instanceId}");
        $this->line("  since    : {$sinceCutoff->toIso8601String()}");
        $this->line("  max      : {$max} msgs");
        $this->line("  batch    : {$batchSize}");
        $this->line("  sleep    : {$sleepMs}ms");
        $this->line("  dry-run  : " . ($dryRun ? 'sim' : 'não'));
        $this->newLine();

        // Pega conversations do channel pra iterar
        $convQuery = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class) // SUPERADMIN ADR 0093
            ->where('business_id', $businessId)
            ->where('channel_id', $channel->id);

        if ($convFilter !== null) {
            $convQuery->where('id', $convFilter);
        }

        $conversations = $convQuery->orderBy('id')->get();
        $totalConvs = $conversations->count();

        if ($totalConvs === 0) {
            $this->warn('Nenhuma conversation encontrada — chip precisa receber/enviar ao menos 1 msg via webhook real-time primeiro pra criar conv. Sem conv não há jid pra puxar histórico.');
            return self::SUCCESS;
        }

        $this->info("{$totalConvs} conversation(s) pra processar.");

        $persister = new MessagePersister($channel);
        $stats = [
            'conversations_processed' => 0,
            'conversations_skipped' => 0,
            'imported' => 0,
            'duplicate' => 0,
            'skipped' => 0,
            'daemon_errors' => 0,
        ];

        foreach ($conversations as $conv) {
            $this->line("  Conv #{$conv->id} {$conv->customer_external_id}");
            $convStats = $this->importConversation(
                $persister,
                $conv,
                $instanceId,
                $sinceCutoff,
                $batchSize,
                $sleepMs,
                $maxEmptyRounds,
                $max - $stats['imported'],
                $dryRun,
            );

            if ($convStats === null) {
                $stats['conversations_skipped']++;
                continue;
            }

            $stats['conversations_processed']++;
            $stats['imported'] += $convStats['imported'];
            $stats['duplicate'] += $convStats['duplicate'];
            $stats['skipped'] += $convStats['skipped'];
            $stats['daemon_errors'] += $convStats['daemon_errors'];

            $this->line(sprintf(
                '    → imported=%d  duplicate=%d  skipped=%d  errors=%d',
                $convStats['imported'],
                $convStats['duplicate'],
                $convStats['skipped'],
                $convStats['daemon_errors'],
            ));

            if ($stats['imported'] >= $max) {
                $this->warn("Cap --max={$max} atingido — parando.");
                break;
            }
        }

        $this->newLine();
        $this->info('Resumo:');
        foreach ($stats as $k => $v) {
            $this->line("  {$k} : {$v}");
        }

        Log::info('[whatsapp.import_history.completed]', [
            'channel_id' => $channel->id,
            'business_id' => $businessId,
            'instance_id' => $instanceId,
            'dry_run' => $dryRun,
            'since' => $sinceCutoff->toIso8601String(),
        ] + $stats);

        return self::SUCCESS;
    }

    /**
     * Itera puxando batches do daemon até cutoff/has_more=false/cap.
     *
     * Retorna null se conv não tem jid resolvível ou cursor inicial inviável.
     *
     * @return array{imported:int,duplicate:int,skipped:int,daemon_errors:int}|null
     */
    private function importConversation(
        MessagePersister $persister,
        Conversation $conv,
        string $instanceId,
        Carbon $sinceCutoff,
        int $batchSize,
        int $sleepMs,
        int $maxEmptyRounds,
        int $remainingCap,
        bool $dryRun,
    ): ?array {
        if ($remainingCap <= 0) {
            return null;
        }

        // Resolve jid: tenta achar 1 msg pra extrair remoteJid do payload.
        // Se não houver msg E customer_external_id é E.164, monta jid
        // padrão `<digits>@s.whatsapp.net` (sem garantia que casa com
        // o que o chip armazena — alguns @lid em multi-device).
        $jidInfo = $this->resolveJidAndCursor($conv);
        if ($jidInfo === null) {
            $this->line('    skip — sem jid resolvível');
            return null;
        }

        [$jid, $cursorId, $cursorTs, $cursorFromMe] = $jidInfo;

        $stats = ['imported' => 0, 'duplicate' => 0, 'skipped' => 0, 'daemon_errors' => 0];
        $emptyRoundsInRow = 0;
        $consecutiveErrors = 0;

        while (true) {
            if ($stats['imported'] >= $remainingCap) {
                $this->line('    cap remaining atingido — parando conv');
                break;
            }

            $response = $this->callDaemonHistory(
                $instanceId,
                $jid,
                $batchSize,
                $cursorId,
                $cursorTs,
                $cursorFromMe,
            );

            if ($response === null) {
                $stats['daemon_errors']++;
                $consecutiveErrors++;
                if ($consecutiveErrors >= 3) {
                    $this->warn('    3 erros consecutivos — abortando conv');
                    break;
                }
                usleep($sleepMs * 1000);
                continue;
            }
            $consecutiveErrors = 0;

            $msgs = $response['messages'] ?? [];
            $hasMore = (bool) ($response['has_more'] ?? false);
            $empty = (bool) ($response['empty'] ?? (count($msgs) === 0));

            if ($empty || count($msgs) === 0) {
                $emptyRoundsInRow++;
                if ($emptyRoundsInRow >= $maxEmptyRounds) {
                    $this->line('    daemon empty — parando conv');
                    break;
                }
                usleep($sleepMs * 1000);
                continue;
            }
            $emptyRoundsInRow = 0;

            // Persiste cada msg
            foreach ($msgs as $msgPayload) {
                if (! is_array($msgPayload)) {
                    continue;
                }

                if ($dryRun) {
                    $stats['skipped']++;
                    continue;
                }

                $result = $persister->persist($msgPayload, bumpUnread: false);

                if ($result->wasCreated()) {
                    $stats['imported']++;
                } elseif ($result->wasDuplicate()) {
                    $stats['duplicate']++;
                } else {
                    $stats['skipped']++;
                }

                if ($stats['imported'] >= $remainingCap) {
                    break 2; // sai loop while também
                }
            }

            // Atualiza cursor pra oldest do batch
            $newCursorId = $response['oldest_id'] ?? null;
            $newCursorTs = isset($response['oldest_ts']) ? (int) $response['oldest_ts'] : null;

            if ($newCursorTs === null || $newCursorId === null) {
                $this->line('    cursor inválido — parando conv');
                break;
            }

            // Atingiu cutoff `--since`?
            $oldestCarbon = Carbon::createFromTimestamp($newCursorTs);
            if ($oldestCarbon->lessThan($sinceCutoff)) {
                $this->line("    cutoff --since atingido ({$oldestCarbon->toDateString()})");
                break;
            }

            // Mesmo cursor? bug daemon — para pra evitar loop infinito
            if ($newCursorId === $cursorId) {
                $this->warn('    cursor não avançou — abortando conv (anti-loop)');
                break;
            }

            $cursorId = $newCursorId;
            $cursorTs = $newCursorTs;

            if (! $hasMore) {
                $this->line('    has_more=false — parando conv');
                break;
            }

            // Anti-ban sleep
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return $stats;
    }

    /**
     * Resolve jid + cursor inicial pra uma conversation.
     *
     * @return array{0:string,1:string,2:int,3:bool}|null  [jid, cursorId, cursorTs, fromMe]
     */
    private function resolveJidAndCursor(Conversation $conv): ?array
    {
        // Msg mais antiga do DB — vira cursor (puxamos msgs MAIS ANTIGAS que essa)
        $oldestMsg = Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class) // SUPERADMIN ADR 0093
            ->where('business_id', $conv->business_id)
            ->where('conversation_id', $conv->id)
            ->whereNotNull('provider_message_id')
            ->orderBy('created_at', 'asc')
            ->first();

        if ($oldestMsg) {
            $jid = $this->extractJidFromPayload($oldestMsg);
            if ($jid === null) {
                $jid = $this->jidFromExternalId($conv->customer_external_id);
            }
            $cursorTs = $oldestMsg->created_at?->timestamp ?? time();
            $fromMe = $oldestMsg->direction === 'outbound';
            return [$jid, (string) $oldestMsg->provider_message_id, $cursorTs, $fromMe];
        }

        // Sem msg no DB — não temos cursor. Não dá pra puxar histórico
        // sem cursor (Baileys 6.7.9 exige oldestMsgKey+timestamp).
        return null;
    }

    /**
     * Extrai remoteJid do payload Baileys (preferindo proto raw).
     */
    private function extractJidFromPayload(Message $msg): ?string
    {
        $payload = $msg->payload ?? [];
        return $payload['key']['remoteJid'] ?? null;
    }

    /**
     * Monta jid `<digits>@s.whatsapp.net` a partir do customer_external_id E.164.
     * Fallback last-resort — pode não casar com chave real (multi-device @lid).
     */
    private function jidFromExternalId(string $externalId): string
    {
        $digits = preg_replace('/\D/', '', $externalId) ?? '';
        return $digits . '@s.whatsapp.net';
    }

    /**
     * Chama daemon /history. Retorna response JSON ou null em erro/timeout.
     *
     * @return array<string,mixed>|null
     */
    private function callDaemonHistory(
        string $instanceId,
        string $jid,
        int $count,
        string $beforeId,
        int $beforeTs,
        bool $fromMe,
    ): ?array {
        $daemonUrl = (string) config('whatsapp.baileys.daemon_url');
        $apiKey = (string) config('whatsapp.baileys.api_key', '');
        $timeout = (int) config('whatsapp.baileys.request_timeout', 15);

        if ($daemonUrl === '' || $apiKey === '') {
            $this->error('    WHATSAPP_BAILEYS_DAEMON_URL/_API_KEY ausentes no .env');
            return null;
        }

        try {
            // Timeout HTTP do PHP > timeout interno do daemon (60s default Zod)
            // pra deixar daemon resolver evento async sem timeout PHP cortar antes.
            $response = Http::withToken($apiKey)
                ->timeout(max($timeout, 75))
                ->acceptJson()
                ->asJson()
                ->post(rtrim($daemonUrl, '/') . "/instances/{$instanceId}/history", [
                    'jid' => $jid,
                    'count' => $count,
                    'before_id' => $beforeId,
                    'before_ts' => $beforeTs,
                    'from_me' => $fromMe,
                ]);

            if ($response->status() === 404) {
                $this->warn('    daemon 404 instance_not_found — chip pode estar desconectado');
                return null;
            }

            if ($response->status() === 409) {
                $this->warn('    daemon 409 instance_not_connected — chip offline');
                return null;
            }

            if (! $response->successful()) {
                Log::warning('[whatsapp.import_history.daemon_error]', [
                    'instance_id' => $instanceId,
                    'status' => $response->status(),
                    // Sem PII no log — body pode conter trecho msg
                    'body_length' => strlen($response->body()),
                ]);
                $this->warn("    daemon HTTP {$response->status()}");
                return null;
            }

            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }

            return $json;
        } catch (ConnectionException $e) {
            $this->warn('    daemon timeout/connection — ' . class_basename($e));
            return null;
        } catch (\Throwable $e) {
            Log::error('[whatsapp.import_history.exception]', [
                'instance_id' => $instanceId,
                'exception_class' => $e::class,
                // Sem PII — message pode ter jid
            ]);
            return null;
        }
    }

    /**
     * Parse `--since`: aceita `90d`, `30d`, ou ISO `2026-04-01`.
     */
    private function parseSince(string $since): Carbon
    {
        $since = trim($since);

        // Match `Nd` (dias)
        if (preg_match('/^(\d+)d$/', $since, $m)) {
            return now()->subDays((int) $m[1]);
        }

        // Match `Nh` (horas — útil debug)
        if (preg_match('/^(\d+)h$/', $since, $m)) {
            return now()->subHours((int) $m[1]);
        }

        try {
            return Carbon::parse($since);
        } catch (\Throwable $e) {
            $this->warn("--since='{$since}' inválido — fallback 90d");
            return now()->subDays(90);
        }
    }

    /**
     * Instance ID derivado de Channel — espelha ChannelsController::connect()
     * (linha 358): `'ch-' . str_replace('-', '', $channel->channel_uuid)`.
     */
    private function resolveInstanceId(Channel $channel): string
    {
        return 'ch-' . str_replace('-', '', (string) $channel->channel_uuid);
    }
}
