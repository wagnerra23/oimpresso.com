<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * US-GOV-052 — seção OUTCOME DO AGENTE (7d) no Daily Brief (evals de outcome).
 *
 * Fecha o "medidor existe mas ninguém vê": o DORA dos PRs do agente (#4024 —
 * change-failure-rate, accept-rate, time-to-merge) só aparecia no job summary
 * semanal do workflow agent-pr-outcomes.yml. Aqui os 3 números chegam ao Wagner
 * 6x/dia, com tendência vs a janela 30d como baseline (a régua é o próprio
 * histórico, não valor absoluto — gap G3 do script).
 *
 * Transporte (o brief é PHP, o medidor é Node): shell-out de
 * `scripts/governance/agent-pr-outcomes.mjs --json` via Process + json_decode —
 * mesmo idioma dos irmãos (PlanHealth/ShippedLog/AdrReview). Fonte-única: NÃO
 * reimplementa as métricas em PHP — quem mede é o script (funções puras com
 * selftest próprio), aqui só se formata. Duas janelas = duas execuções
 * (--days 7 e --days 30); a de 30d é best-effort (sem ela, sai só a linha 7d).
 *
 * Determinística (pós-LLM): `inject()` roda DEPOIS do Brain B gerar o markdown —
 * o modelo nunca inventa esses números. Injeta a seção `## OUTCOME DO AGENTE (7d)`
 * imediatamente ANTES de `## FLAGS` (BriefValidator só exige os 7 headers
 * canônicos em ordem; header extra entre eles é permitido).
 *
 * Degrada graciosamente (brief NUNCA quebra por causa dela):
 *  - `node`/`gh` ausentes no host do cron (ex.: shared hosting) / timeout → null
 *  - JSON inválido / script sem PRs terminais na janela 7d → null
 *  - janela 30d falhou → seção sai sem o bullet de tendência
 * Kill-switch: `governance.agent_outcome_brief_section` false → no-op (default ON).
 *
 * @see scripts/governance/agent-pr-outcomes.mjs (medidor · shape {ok,agent,metrics,gaps})
 * @see .github/workflows/agent-pr-outcomes.yml (relatório semanal irmão, job summary)
 * @see Modules\Governance\Services\AdrReviewBriefLineService (pattern irmão)
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */
final class AgentOutcomeBriefSectionService
{
    /** Teto do shell-out (s) — este medidor chama `gh pr list` (rede), mais folga que os irmãos locais. */
    private const TIMEOUT_SECONDS = 60;

    /** Janela principal (o "(7d)" do header) e baseline de tendência. */
    private const WINDOW_DAYS = 7;

    private const BASELINE_DAYS = 30;

    /**
     * Injeta a seção `## OUTCOME DO AGENTE (7d)` antes de `## FLAGS`. Best-effort:
     * qualquer falha (ou seção null) devolve o conteúdo intacto.
     * Kill-switch: `governance.agent_outcome_brief_section` false → no-op (default ON).
     */
    public function inject(string $content): string
    {
        if (! (bool) config('governance.agent_outcome_brief_section', true)) {
            return $content;
        }

        try {
            $section = $this->section();
        } catch (Throwable) {
            return $content;
        }

        if ($section === null) {
            return $content;
        }

        $injected = preg_replace('/^## FLAGS$/m', "{$section}\n\n## FLAGS", $content, 1, $count);

        return ($count === 1 && is_string($injected)) ? $injected : $content;
    }

    /**
     * Seção completa (header + 1-2 bullets), ou null quando não há nada confiável
     * a reportar (medidor indisponível / 0 PRs terminais na janela 7d).
     *
     * Formato (2 bullets, ~50 tokens — ADR 0226 ≤8k respeitado):
     *   ## OUTCOME DO AGENTE (7d)
     *   - 7d: 56 PRs terminais · aceitação 96,4% (54/56) · change-failure 3,7% (2/54 c/ hotfix ≤48h) · TTM med 2,3h · p90 18h
     *   - Tendência vs 30d: aceitação 94%→96,4% ▲ · CFR 6%→3,7% ▼ · TTM med 3,1h→2,3h ▼
     */
    public function section(): ?string
    {
        $r7 = $this->fetchReport(self::WINDOW_DAYS);

        if ($r7 === null || ($r7['ok'] ?? false) !== true) {
            return null;
        }

        $terminais = (int) ($r7['agent']['total_terminais'] ?? 0);
        if ($terminais === 0) {
            return null;
        }

        $lines = ['## OUTCOME DO AGENTE (7d)', '- '.$this->doraBullet($r7, $terminais)];

        // Baseline 30d é best-effort — sem ela a seção sai só com a linha 7d.
        $r30 = $this->fetchReport(self::BASELINE_DAYS);
        if ($r30 !== null && ($r30['ok'] ?? false) === true) {
            $lines[] = '- '.$this->trendBullet($r7, $r30);
        }

        return implode("\n", $lines);
    }

    /** Bullet 1 — os 3 números DORA da janela 7d. */
    private function doraBullet(array $r, int $terminais): string
    {
        $accept = (array) ($r['metrics']['accept'] ?? []);
        $cfr = (array) ($r['metrics']['change_failure'] ?? []);
        $ttm = (array) ($r['metrics']['time_to_merge'] ?? []);

        return sprintf(
            '7d: %d PRs terminais · aceitação %s (%d/%d) · change-failure %s (%d/%d c/ hotfix ≤48h) · TTM med %s · p90 %s',
            $terminais,
            $this->pct($accept['accept_rate'] ?? null),
            (int) ($accept['merged'] ?? 0),
            $terminais,
            $this->pct($cfr['cfr'] ?? null),
            (int) ($cfr['failures'] ?? 0),
            (int) ($cfr['merged_count'] ?? 0),
            $this->horas($ttm['median_h'] ?? null),
            $this->horas($ttm['p90_h'] ?? null),
        );
    }

    /** Bullet 2 — tendência 30d→7d por métrica (seta = direção crua, sem juízo). */
    private function trendBullet(array $r7, array $r30): string
    {
        $par = fn (array $r, string $grupo, string $campo) => $r['metrics'][$grupo][$campo] ?? null;

        return sprintf(
            'Tendência vs 30d: aceitação %s · CFR %s · TTM med %s',
            $this->trend($par($r30, 'accept', 'accept_rate'), $par($r7, 'accept', 'accept_rate'), 'pct'),
            $this->trend($par($r30, 'change_failure', 'cfr'), $par($r7, 'change_failure', 'cfr'), 'pct'),
            $this->trend($par($r30, 'time_to_merge', 'median_h'), $par($r7, 'time_to_merge', 'median_h'), 'horas'),
        );
    }

    /** `94%→96,4% ▲` (baseline→janela + seta de direção). Qualquer lado null → `n/d`. */
    private function trend(int|float|null $baseline, int|float|null $atual, string $unidade): string
    {
        if ($baseline === null || $atual === null) {
            return 'n/d';
        }

        $fmt = fn (int|float $v) => $unidade === 'pct' ? $this->pct($v) : $this->horas($v);
        $seta = $atual <=> $baseline;

        return sprintf('%s→%s %s', $fmt($baseline), $fmt($atual), $seta > 0 ? '▲' : ($seta < 0 ? '▼' : '='));
    }

    /** `96,4%` (decimal pt-BR) ou `n/d`. */
    private function pct(int|float|null $v): string
    {
        return $v === null ? 'n/d' : str_replace('.', ',', (string) $v).'%';
    }

    /** `2,3h` (decimal pt-BR) ou `n/d`. */
    private function horas(int|float|null $v): string
    {
        return $v === null ? 'n/d' : str_replace('.', ',', (string) $v).'h';
    }

    /**
     * Roda o medidor Node numa janela e devolve o JSON decodificado, ou null se o
     * shell-out falhar/não produzir JSON (binário ausente, gh sem auth, timeout).
     *
     * @return array<string, mixed>|null
     */
    private function fetchReport(int $days): ?array
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run(['node', 'scripts/governance/agent-pr-outcomes.mjs', '--json', '--days', (string) $days]);
        } catch (Throwable $e) {
            Log::debug('[agent-outcome brief section] shell-out falhou: '.$e->getMessage());

            return null;
        }

        $decoded = json_decode($result->output(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
