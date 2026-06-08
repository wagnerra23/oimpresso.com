<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\LidPhoneMap;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Contacts\LidPhoneResolver;

/**
 * LidBackfillCommand — varre `messages.payload` histórico e popula
 * `whatsapp_lid_pn_map` retroativamente (US-WA-093 P1 #697).
 *
 * Contexto: webhook canônico (controller `ChannelBaileysWebhookController`)
 * persiste o par `(remoteJid@lid, senderPn)` na hora — mas conversas
 * pré-PR #696 nunca passaram por essa lógica. Este command resgata o que
 * tem em `payload` JSON pra popular o cache LID retroativamente.
 *
 * Heurística: pra cada `messages.payload`, busca:
 *   - `key.remoteJid` que termina em `@lid` (formato Multi-Device)
 *   - `key.senderPn` (phone real anexado pelo WhatsApp esporadicamente)
 *
 * Quando encontra par válido, chama `LidPhoneResolver::record()` que faz
 * UPSERT idempotente (firstOrCreate na UNIQUE business+lid).
 *
 * Uso:
 *   php artisan whatsapp:lid-backfill --business=1            # smoke biz=1
 *   php artisan whatsapp:lid-backfill --dry-run               # preview todos
 *   php artisan whatsapp:lid-backfill --limit=10000           # cap scan
 *   php artisan whatsapp:lid-backfill                         # all biz, no cap
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 *  - `business_id` scope explícito em todas queries (withoutGlobalScope
 *    porque rodando CLI sem session user).
 *  - Resolver `record()` injeta business_id no UPSERT.
 *  - Logs sem PII (só ids + counts).
 *  - Idempotente — rodar 2× não duplica (UNIQUE constraint + last_seen bump).
 *
 * @see Modules\Whatsapp\Services\Contacts\LidPhoneResolver
 * @see Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class LidBackfillCommand extends Command
{
    protected $signature = 'whatsapp:lid-backfill
                            {--business=all : business_id alvo (default: all)}
                            {--limit=0 : Máximo de msgs a varrer (0 = sem limite)}
                            {--dry-run : Só conta, não persiste}';

    protected $description = 'Backfill whatsapp_lid_pn_map a partir de messages.payload histórico (idempotente)';

    public function handle(LidPhoneResolver $resolver): int
    {
        $businessOpt = (string) $this->option('business');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma row será persistida em whatsapp_lid_pn_map.');
        }

        // SUPERADMIN: CLI cross-business — scope explícito por --business.
        $query = Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->whereNotNull('payload');

        if ($businessOpt !== 'all') {
            $businessId = (int) $businessOpt;
            if ($businessId <= 0) {
                $this->error("--business={$businessOpt} inválido (esperado inteiro > 0 ou 'all').");
                return self::FAILURE;
            }
            $query->where('business_id', $businessId);
        }

        // Por business — agrupamos pra relatar tabela por biz no final.
        $bucketByBiz = [];
        $start = microtime(true);

        $query->orderBy('id')->chunk(1000, function ($chunk) use (
            $resolver,
            $dryRun,
            $limit,
            &$bucketByBiz,
        ) {
            foreach ($chunk as $msg) {
                /** @var Message $msg */
                $biz = (int) $msg->business_id;
                if (! isset($bucketByBiz[$biz])) {
                    $bucketByBiz[$biz] = [
                        'business_id' => $biz,
                        'rows_scanned' => 0,
                        'pairs_recorded' => 0,
                        'pairs_skipped' => 0,
                    ];
                }

                $bucketByBiz[$biz]['rows_scanned']++;

                // Limit global — para iteração quando atingir
                $totalScanned = array_sum(array_column($bucketByBiz, 'rows_scanned'));
                if ($limit > 0 && $totalScanned >= $limit) {
                    return false; // breaks chunk loop
                }

                $pair = $this->extractLidPhonePair($msg->payload ?? []);
                if ($pair === null) {
                    continue;
                }

                [$lid, $phone] = $pair;

                if ($dryRun) {
                    // Em dry-run conta como "would record" mas sem distinguir
                    // novo vs existente (resolver decide via firstOrCreate).
                    $bucketByBiz[$biz]['pairs_recorded']++;
                    continue;
                }

                // Detecta idempotência: row já existia com mesmo phone?
                $existed = LidPhoneMap::query()
                    ->withoutGlobalScope(ScopeByBusiness::class)
                    ->where('business_id', $biz)
                    ->where('lid', preg_replace('/\D+/', '', preg_replace('/@.+$/', '', $lid)))
                    ->where('phone_e164', '+' . preg_replace('/\D+/', '', preg_replace('/@.+$/', '', $phone)))
                    ->exists();

                $resolver->record($biz, $lid, $phone, LidPhoneMap::SOURCE_MANUAL);

                if ($existed) {
                    $bucketByBiz[$biz]['pairs_skipped']++;
                } else {
                    $bucketByBiz[$biz]['pairs_recorded']++;
                }
            }
        });

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        // Relatório CLI por business
        $rows = [];
        foreach ($bucketByBiz as $b) {
            $rows[] = [
                $b['business_id'],
                $b['rows_scanned'],
                $b['pairs_recorded'],
                $b['pairs_skipped'],
                $durationMs,
            ];
        }

        if (empty($rows)) {
            $this->info('Nenhuma mensagem com payload pra varrer.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['business_id', 'rows_scanned', 'pairs_recorded', 'pairs_skipped', 'duration_ms'],
            $rows,
        );

        Log::info('[whatsapp.lid_backfill.completed]', [
            'business_filter' => $businessOpt,
            'dry_run' => $dryRun,
            'limit' => $limit,
            'buckets' => array_values($bucketByBiz),
            'duration_ms' => $durationMs,
        ]);

        return self::SUCCESS;
    }

    /**
     * Extrai par (lid, phone) de um payload Baileys webhook.
     *
     * Aceita variantes:
     *  - $payload['key']['remoteJid'] → "X@lid"
     *  - $payload['key']['senderPn']  → "5548999...@s.whatsapp.net" (canônico)
     *  - $payload['key']['participantPn'] → fallback v7.x preview
     *
     * Retorna [lid, phone] OU null quando par não está completo.
     */
    private function extractLidPhonePair(array $payload): ?array
    {
        $key = $payload['key'] ?? null;
        if (! is_array($key)) {
            return null;
        }

        $remoteJid = $key['remoteJid'] ?? null;
        if (! is_string($remoteJid) || ! str_contains($remoteJid, '@lid')) {
            return null;
        }

        // senderPn é o nome canônico (PR #696 + ChannelBaileysWebhookController L131)
        // participantPn / senderPN são variantes vistas em Baileys 7.x preview.
        $candidates = [
            $key['senderPn'] ?? null,
            $key['participantPn'] ?? null,
            $key['senderPN'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && str_contains($candidate, '@s.whatsapp.net')) {
                return [$remoteJid, $candidate];
            }
        }

        return null;
    }
}
