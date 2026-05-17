<?php

declare(strict_types=1);

namespace Modules\SRS\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\SRS\Entities\DocChatMessage;
use Modules\SRS\Entities\DocValidationRun;

/**
 * DocRetentionCleaner — aplica janelas LGPD declaradas em Config/retention.php.
 *
 * Wave 18 RETRY — D7 LGPD + D4 boost.
 *
 * Implementa princípio LGPD Art. 16 (minimização) declarado em
 * `Modules/SRS/Config/retention.php`:
 *   - chat_messages_days (default 365d)
 *   - generation_logs_days (default 365d)
 *   - draft_versions_days (default 90d)
 *   - generated_docs_days (default 1825d / 5 anos governance audit)
 *
 * NÃO é executado automaticamente ainda — service stub canônico pra futuro
 * comando `srs:retention-cleanup` (schedule mensal). Por ora:
 *   - `dryRun()` retorna contagem do que SERIA purgado (audit pré-flight)
 *   - `purge()` chamado explicitamente após Wagner sign-off
 *
 * Append-only por design (ADR 0093): só purga rows com `created_at` antes
 * da janela; NÃO toca rows ativos / dentro da janela.
 *
 * Cross-tenant: opera com global scope HasBusinessScope ativo — Wagner roda
 * cleanup per-tenant ou cross-tenant (`withoutGlobalScopes` em Console command).
 *
 * @see Modules\SRS\Config\retention.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §4
 */
class DocRetentionCleaner
{
    /**
     * Retorna contagens do que seria purgado (dry-run audit).
     *
     * @return array{chat_messages: int, validation_runs: int, cutoffs: array}
     */
    public function dryRun(): array
    {
        return OtelHelper::spanBiz('srs.retention.dry_run', function (): array {
            $cutoffs = $this->computeCutoffs();

            return [
                'chat_messages' => DocChatMessage::query()
                    ->where('created_at', '<', $cutoffs['chat'])
                    ->count(),
                'validation_runs' => DocValidationRun::query()
                    ->where('created_at', '<', $cutoffs['logs'])
                    ->count(),
                'cutoffs' => [
                    'chat'  => $cutoffs['chat']->toIso8601String(),
                    'logs'  => $cutoffs['logs']->toIso8601String(),
                    'draft' => $cutoffs['draft']->toIso8601String(),
                    'docs'  => $cutoffs['docs']->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * Aplica DELETE rows fora da janela. Wagner-only (rodado via artisan
     * command com `--confirm`).
     *
     * @return array{chat_messages: int, validation_runs: int}
     */
    public function purge(): array
    {
        return OtelHelper::spanBiz('srs.retention.purge', function (): array {
            $cutoffs = $this->computeCutoffs();
            $deleted = ['chat_messages' => 0, 'validation_runs' => 0];

            DB::transaction(function () use ($cutoffs, &$deleted) {
                $deleted['chat_messages'] = DocChatMessage::query()
                    ->where('created_at', '<', $cutoffs['chat'])
                    ->delete();

                $deleted['validation_runs'] = DocValidationRun::query()
                    ->where('created_at', '<', $cutoffs['logs'])
                    ->delete();
            });

            Log::info('[SRS][retention] purge applied', $deleted + [
                'cutoff_chat' => $cutoffs['chat']->toIso8601String(),
                'cutoff_logs' => $cutoffs['logs']->toIso8601String(),
            ]);

            return $deleted;
        });
    }

    /**
     * Calcula data-corte por categoria (now - retention_days).
     *
     * @return array<string, Carbon>
     */
    protected function computeCutoffs(): array
    {
        $cfg = config('srs.retention') ?? config('retention') ?? [
            'chat_messages_days'    => 365,
            'generation_logs_days'  => 365,
            'draft_versions_days'   => 90,
            'generated_docs_days'   => 1825,
        ];

        // Fallback robusto: read direto do arquivo se config não carregado.
        if (empty($cfg['chat_messages_days'])) {
            $cfg = require __DIR__ . '/../Config/retention.php';
        }

        $now = now();

        return [
            'chat'  => $now->copy()->subDays((int) $cfg['chat_messages_days']),
            'logs'  => $now->copy()->subDays((int) $cfg['generation_logs_days']),
            'draft' => $now->copy()->subDays((int) $cfg['draft_versions_days']),
            'docs'  => $now->copy()->subDays((int) $cfg['generated_docs_days']),
        ];
    }
}
