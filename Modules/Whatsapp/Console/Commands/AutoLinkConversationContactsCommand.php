<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Services\Contacts\ConversationContactLinker;

/**
 * Backfill — Auto-link Conversation → Contact CRM por phone (US-WA-078).
 *
 * Contexto: webhook hoje cria Conversation com `contact_id=null` SEMPRE
 * (US-WA-064 vincula só manual via UI). Em prod biz=1: 32 conversations
 * com contact_id=null e MUITAS delas seriam vinculáveis automaticamente
 * (Wagner sabe que Contacts existem com phones que batem).
 *
 * Webhook auto-link (Parte A) cuida das NOVAS conversations. Este command
 * faz o BACKFILL retroativo: itera todas convs órfãs aplicando a MESMA
 * heurística do `ConversationContactLinker::tryLink()`.
 *
 * Uso:
 *   php artisan whatsapp:auto-link-contacts --business=1            # smoke biz=1
 *   php artisan whatsapp:auto-link-contacts --dry-run               # preview todos
 *   php artisan whatsapp:auto-link-contacts --limit=500             # limita por execução (schedule weekly)
 *   php artisan whatsapp:auto-link-contacts                         # roda todos
 *
 * Output tabela CLI por business:
 *   biz | total_unlinked | linked | still_unlinked | duration_ms
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 *  - `business_id` scope explícito em todas queries.
 *  - Linker reusa lógica do webhook (single source of truth).
 *  - Logs sem PII (só IDs).
 *  - Idempotente — convs já linkadas são puladas no SELECT.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-078
 */
class AutoLinkConversationContactsCommand extends Command
{
    protected $signature = 'whatsapp:auto-link-contacts
                            {--business=all : business_id alvo (default: all)}
                            {--limit=1000 : Máximo de conversations órfãs processadas por business (default: 1000)}
                            {--dry-run : Só conta, não persiste}';

    protected $description = 'Backfill auto-link Conversation→Contact CRM por phone (idempotente, schedule weekly)';

    public function handle(ConversationContactLinker $linker): int
    {
        $businessOpt = (string) $this->option('business');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if ($limit <= 0) {
            $this->error("--limit={$limit} inválido (esperado inteiro > 0).");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma row será persistida.');
        }

        // Resolve lista de businesses alvo. Quando 'all', faz GROUP BY direto
        // pra evitar full-scan dispatch de Conversation::all() na app-layer.
        $businessIds = $this->resolveBusinessIds($businessOpt);
        if ($businessIds === null) {
            return self::FAILURE;
        }
        if (empty($businessIds)) {
            $this->info('Nenhum business com conversation órfã (contact_id=null).');
            return self::SUCCESS;
        }

        $rows = [];
        $grandTotal = 0;
        $grandLinked = 0;
        $grandAmbiguous = 0;

        foreach ($businessIds as $bizId) {
            $startedAt = microtime(true);
            $stats = $this->processBusiness($bizId, $limit, $dryRun, $linker);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $rows[] = [
                'biz' => $bizId,
                'total_unlinked' => $stats['total_unlinked'],
                'linked' => ($dryRun ? '(' . $stats['linked'] . ')' : (string) $stats['linked']),
                'still_unlinked' => $stats['still_unlinked'],
                'duration_ms' => $durationMs,
            ];

            $grandTotal += $stats['total_unlinked'];
            $grandLinked += $stats['linked'];
            $grandAmbiguous += $stats['ambiguous'];
        }

        $this->table(
            ['biz', 'total_unlinked', 'linked', 'still_unlinked', 'duration_ms'],
            $rows,
        );

        $this->info(sprintf(
            '✓ Resumo: %d total · %s %d linkadas · %d ambíguas (linkadas primeiro)',
            $grandTotal,
            $dryRun ? 'WOULD link' : 'linked',
            $grandLinked,
            $grandAmbiguous,
        ));

        Log::info('[whatsapp.auto_link_contacts.completed]', [
            'business_filter' => $businessOpt,
            'dry_run' => $dryRun,
            'limit' => $limit,
            'total_processed' => $grandTotal,
            'linked' => $grandLinked,
            'ambiguous' => $grandAmbiguous,
            'businesses_count' => count($businessIds),
        ]);

        return self::SUCCESS;
    }

    /**
     * Retorna lista de business_ids a processar:
     *  - businessOpt='all' → GROUP BY business em conversations órfãs
     *  - businessOpt=int   → [int] (sem validar existência)
     *  - businessOpt inválido → null (caller aborta)
     *
     * @return array<int>|null
     */
    private function resolveBusinessIds(string $businessOpt): ?array
    {
        if ($businessOpt === 'all') {
            return Conversation::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->whereNull('contact_id')
                ->select('business_id')
                ->distinct()
                ->orderBy('business_id')
                ->pluck('business_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $businessId = (int) $businessOpt;
        if ($businessId <= 0) {
            $this->error("--business={$businessOpt} inválido (esperado inteiro > 0 ou 'all').");
            return null;
        }

        return [$businessId];
    }

    /**
     * Processa convs órfãs de um business até `$limit`. Retorna stats.
     *
     * @return array{total_unlinked: int, linked: int, still_unlinked: int, ambiguous: int}
     */
    private function processBusiness(int $bizId, int $limit, bool $dryRun, ConversationContactLinker $linker): array
    {
        $query = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $bizId)
            ->whereNull('contact_id');

        $total = (clone $query)->count();
        $linked = 0;
        $ambiguous = 0;

        if ($total === 0) {
            return [
                'total_unlinked' => 0,
                'linked' => 0,
                'still_unlinked' => 0,
                'ambiguous' => 0,
            ];
        }

        $query->orderBy('id')
            ->limit($limit)
            ->chunk(200, function ($chunk) use (
                $linker,
                $dryRun,
                &$linked,
                &$ambiguous,
            ) {
                foreach ($chunk as $conv) {
                    /** @var Conversation $conv */
                    if ($dryRun) {
                        $matches = $linker->findMatches($conv);
                        if ($matches->isNotEmpty()) {
                            $linked++;
                            if ($matches->count() > 1) {
                                $ambiguous++;
                            }
                        }
                        continue;
                    }

                    $matchesPre = $linker->findMatches($conv);
                    if ($matchesPre->count() > 1) {
                        $ambiguous++;
                    }

                    $contact = $linker->tryLink($conv);
                    if ($contact) {
                        $linked++;
                    }
                }
            });

        $stillUnlinked = max(0, $total - $linked);

        return [
            'total_unlinked' => $total,
            'linked' => $linked,
            'still_unlinked' => $stillUnlinked,
            'ambiguous' => $ambiguous,
        ];
    }
}
