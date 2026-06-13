<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * GT-G8 — linha SDD do Daily Brief (plano SDD 2026-06-12 §2 GARANTIDA:
 * "leitura sem esforço — linha SDD no brief diário, só quando muda ou tem vermelho").
 *
 * Lê os 2 últimos snapshots de `mcp_sdd_scorecard_history` (GT-G7) e decide:
 *  - composta MUDOU vs snapshot anterior (1º snapshot conta como mudança) → linha
 *  - métrica armada regrediu OU fonte vermelha (alerts não-vazio)        → linha
 *  - nada mudou e zero alertas → null (brief não ganha ruído diário)
 *
 * Formato: `SDD: composta NN (ΔN) · X/10 vivas · alerta: <métrica>` — emoji
 * 🔴 (tem alerta) / 🟡 (só mudança) segue a convenção da seção FLAGS.
 * Determinística (pós-LLM): `inject()` é chamado por GenerateBriefCommand
 * DEPOIS do Brain B gerar o markdown — o modelo nunca inventa esse número.
 * Degrada graciosamente: tabela ausente/sem rows/erro → brief intacto.
 *
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point)
 * @see memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md §4
 */
final class SddBriefLineService
{
    /**
     * Injeta a linha SDD como 1º bullet da seção `## FLAGS`. Best-effort:
     * qualquer falha (ou linha null) devolve o conteúdo intacto — o brief
     * NUNCA quebra por causa da linha SDD.
     * Kill-switch: `governance.sdd_brief_line` false → no-op (default ON).
     */
    public function inject(string $content): string
    {
        if (! (bool) config('governance.sdd_brief_line', true)) {
            return $content;
        }

        try {
            $line = $this->line();
        } catch (Throwable) {
            return $content;
        }

        if ($line === null) {
            return $content;
        }

        $injected = preg_replace('/^## FLAGS$/m', "## FLAGS\n- {$line}", $content, 1, $count);

        return ($count === 1 && is_string($injected)) ? $injected : $content;
    }

    /**
     * Linha SDD ou null quando não há nada digno de nota (sem mudança de
     * composta E sem alerta) — ver regra no docblock da classe.
     */
    public function line(): ?string
    {
        if (! Schema::hasTable('mcp_sdd_scorecard_history')) {
            return null;
        }

        $rows = DB::table('mcp_sdd_scorecard_history')
            ->orderByDesc('snapshot_date')
            ->limit(2)
            ->get();

        $latest = $rows->first();
        if ($latest === null) {
            return null;
        }

        $previous = $rows->get(1);
        $payload = json_decode((string) $latest->payload, true) ?: [];
        $alerts = array_values((array) ($payload['alerts'] ?? []));

        $atual = $this->toFloat($latest->composta);
        $anterior = $previous !== null ? $this->toFloat($previous->composta) : null;
        $changed = $previous === null || $atual !== $anterior;

        if (! $changed && $alerts === []) {
            return null;
        }

        return sprintf(
            '%s SDD: composta %s (%s) · %d/%d vivas%s',
            $alerts !== [] ? '🔴' : '🟡',
            $atual === null ? '—' : number_format($atual, 1, ',', '.'),
            $this->formatDelta($atual, $previous !== null, $anterior),
            (int) ($payload['vivas'] ?? 0),
            (int) ($payload['metrics_total'] ?? 10),
            $alerts !== [] ? ' · alerta: '.$this->alertSummary($alerts) : '',
        );
    }

    /** Δ assinado pt-BR; 'Δ—' quando não há par comparável (1º snapshot / composta nula). */
    private function formatDelta(?float $atual, bool $hasPrevious, ?float $anterior): string
    {
        if (! $hasPrevious || $atual === null || $anterior === null) {
            return 'Δ—';
        }

        $delta = round($atual - $anterior, 1);

        return 'Δ'.($delta > 0 ? '+' : '').number_format($delta, 1, ',', '.');
    }

    /** Nome da 1ª métrica em alerta + contagem das demais ("ghost_count +2"). */
    private function alertSummary(array $alerts): string
    {
        $metric = trim(explode(':', (string) $alerts[0], 2)[0]);
        $rest = count($alerts) - 1;

        return $rest > 0 ? "{$metric} +{$rest}" : $metric;
    }

    private function toFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
