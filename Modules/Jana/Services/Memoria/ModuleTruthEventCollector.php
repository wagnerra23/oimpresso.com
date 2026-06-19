<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria;

use Carbon\CarbonImmutable;

/**
 * PR-B do keystone distiller-módulo-verdade ([ADR 0291] D-A · emenda 0270 F3).
 *
 * A DECISÃO de "o que distilar" — pura, determinística, testável SEM LLM/FS/DB.
 * Dado o catálogo de eventos recentes de um módulo (sessions/handoffs/PRs/audits,
 * cada um já com a lista de módulos que cita/toca) + a porta (`lastDistilledAt`)
 * + a janela, devolve o subconjunto RELEVANTE e ORDENADO a alimentar a LLM.
 *
 * Espelha a separação ProfileDistiller × ContextSnapshotService: o cálculo
 * (barato, testável) fica separado da chamada cara. O DistillerModuloVerdade
 * (PR-C) lê o filesystem/git pra montar os eventos, chama este seletor, e só
 * então invoca o AnonymousAgent.
 *
 * Regra da janela ([ADR 0291] D-A): "desde o último `distilled_at` OU 30 dias,
 * o que for MAIOR". Janela maior = começa mais cedo = pega mais eventos — assim
 * nunca se perde o que aconteceu desde a última destilação, e há sempre um piso
 * de recência de 30d.
 *
 * Formato de evento (entrada — injetada, nunca lida aqui):
 *   ['type' => 'session'|'handoff'|'pr'|'audit',
 *    'ref'  => string,            // path do doc OU "#NNNN" do PR (proveniência)
 *    'date' => ?string,           // 'YYYY-MM-DD' ou null (undated)
 *    'modules' => string[],       // módulos citados/tocados pelo evento
 *    'title'=> string]
 */
final class ModuleTruthEventCollector
{
    public const DEFAULT_WINDOW_DAYS = 30;

    /** Sentinela de ordenação pra eventos sem data — sempre o "mais recente". */
    private const UNDATED_SENTINEL = '9999-12-31';

    /**
     * Data-limite inferior (inclusiva) da janela de destilação.
     *
     * start = min(now - windowDays, lastDistilledAt) — a MAIOR janela vence.
     */
    public static function windowStart(?string $lastDistilledAt, int $windowDays, string $now): string
    {
        $base = CarbonImmutable::parse($now)->subDays(max(0, $windowDays))->toDateString();

        return ($lastDistilledAt !== null && $lastDistilledAt !== '' && $lastDistilledAt < $base)
            ? $lastDistilledAt
            : $base;
    }

    /** Verdadeiro se o evento cita/toca o módulo (membership case-insensitive). */
    public static function isRelevant(array $event, string $module): bool
    {
        $needle = mb_strtolower(trim($module));
        if ($needle === '') {
            return false;
        }
        foreach (($event['modules'] ?? []) as $m) {
            if (mb_strtolower(trim((string) $m)) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Seleciona + ordena os eventos a destilar pra um módulo.
     *
     * Filtra por (relevante ao módulo) E (sem data OU dentro da janela). Ordena
     * do mais recente pro mais antigo, com os undated no topo (relevantes mas
     * sem data não são silenciosamente descartados). `maxEvents` (>0) limita aos
     * N mais recentes — prioridade é parte da decisão.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    public static function select(
        array $events,
        string $module,
        ?string $lastDistilledAt,
        int $windowDays = self::DEFAULT_WINDOW_DAYS,
        ?string $now = null,
        ?int $maxEvents = null,
    ): array {
        $now ??= CarbonImmutable::now()->toDateString();
        $start = self::windowStart($lastDistilledAt, $windowDays, $now);

        $selected = array_values(array_filter($events, static function (array $e) use ($module, $start): bool {
            if (! self::isRelevant($e, $module)) {
                return false;
            }
            $date = $e['date'] ?? null;

            return $date === null || $date === '' || (string) $date >= $start;
        }));

        // Ordena desc por data (undated = sentinela = topo). usort do PHP 8+ é
        // estável → empates preservam a ordem de inserção (determinístico).
        usort($selected, static function (array $a, array $b): int {
            $ka = ($a['date'] ?? null) ?: self::UNDATED_SENTINEL;
            $kb = ($b['date'] ?? null) ?: self::UNDATED_SENTINEL;

            return strcmp((string) $kb, (string) $ka);
        });

        if ($maxEvents !== null && $maxEvents > 0 && count($selected) > $maxEvents) {
            $selected = array_slice($selected, 0, $maxEvents);
        }

        return $selected;
    }
}
