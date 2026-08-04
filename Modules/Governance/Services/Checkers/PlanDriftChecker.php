<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * PlanDriftChecker — adapter ao `jana:plan-drift` existente (ADR 0294 Onda 2).
 *
 * POR QUE EXISTE: o comando `jana:plan-drift` estava ESCRITO e TESTADO desde a Onda 2 e
 * **nunca foi invocado**. Medido em 2026-08-04, pelas duas pontas:
 *   - repo (`git grep "plan-drift"` em `origin/main`): o único invocador não-teste era a
 *     linha 45 de `scripts/governance/governance-audit.mjs` — bateria que, ela própria, não
 *     tinha invocador nenhum (8 menções não-.md — 7 comentário/docblock + 1 dentro da
 *     string `$signature` —, zero em workflow/cron/package).
 *   - runtime (`php artisan schedule:list` em PROD): 98 comandos agendados (contagem por
 *     `grep -c "php artisan"`), `plan-drift` em **0** deles.
 * É a classe que `memory/proibicoes.md` §"Sempre fazer" item 2 nomeia — *"máquina que existe
 * e NINGUÉM invoca é bug, não neutralidade"*. Este checker é o conserto: dá ao comando um
 * invocador de 1ª classe DENTRO do `governance:audit --all --notify` que **já roda** (cron
 * diário 06:35 BRT, `environments(['live'])`, confirmado no `schedule:list` de prod) — sem
 * criar cadência nova nem 2º medidor.
 *
 * PRECEDENTE DIRETO: {@see IngestLivenessChecker} (2026-07-18) — mesmo formato de conserto
 * (sinal que existia, nada alarmava → vira alerta dentro do governance:audit).
 *
 * Adapter pattern (ADR 0216 §D2), igual ao {@see ChartersFreshnessChecker}: NÃO duplica a
 * regra de drift. O dono da regra continua sendo `PlanDriftCommand::classifyDrift()` — aqui
 * só se chama `--json` e converte o relatório em DriftFinding[]. Um medidor, um dono.
 *
 * O QUE O SINAL DIZ HOJE (medido em PROD 2026-08-04, `php artisan jana:plan-drift`):
 *   `16 planos · 27 com tasks · 0 🔴 · 26 🟡` — e as 26 são TODAS de uma categoria só,
 *   "órfão reverso": tasks declaram `parent_plan=<slug>` que nenhum plano registra no
 *   PLANS-INDEX. Não é degenerado (as outras 4 regras existem e pontuam 0 = estão limpas);
 *   a uniformidade É o achado — o Índice de Planos Vivos, que se declara *"registro
 *   fonte-única de todos os PLANOs"*, está divorciado da execução. Exatamente o "meus planos
 *   estão se perdendo" que ele foi criado pra resolver, sem ninguém alarmando.
 *
 * SEVERITY POR CATEGORIA (calibração honesta — o baseline `medium` é sobrescrito por finding):
 *   - `fail`  em-execução com 0 tasks (ligação fantasma)          → high
 *   - `warn`  em plano DECLARADO (parou? concluído com aberta?)   → medium
 *   - `warn`  órfão reverso (slug que nenhum plano registra)      → low  ← as 26 de hoje
 *
 * Enforcement `warn` de propósito, NÃO `block`: o required `ADR 0216 PR scan` roda
 * `governance:audit --diff-only --fail-on=block`, e `finalize()` só derruba quando
 * `$checker->enforcement() === 'block'`. Promover a `block` exigiria mordida provada
 * (ADR 0336) — nada aqui vira required.
 *
 * SKIP GRACIOSO: o comando já pula sozinho quando `mcp_tasks` está ausente/vazia ou o
 * PLANS-INDEX não existe (`skipped: true`). Aqui isso vira `clean` COM `metadata.reason`
 * — ⊘ honesto, nunca lobo e nunca verde mudo. Qualquer exceção também degrada pra clean
 * (o checker nunca lança), como no precedente.
 *
 * Refs:
 * - ADR 0294 Onda 2 (mãe do comando) · ADR 0216 (framework DriftChecker) · ADR 0070 (tasks no MCP)
 * - memory/decisions/proposals/2026-08-04-ciclo-completo-responsabilidade-por-maquina.md
 * - memory/proibicoes.md §"Sempre fazer" item 2 (máquina sem invocador é bug)
 */
final class PlanDriftChecker implements DriftChecker
{
    public function name(): string
    {
        return 'plan_drift';
    }

    public function description(): string
    {
        return 'Drift entre o status declarado de um plano e a realidade das tasks MCP (parent_plan)';
    }

    public function tags(): array
    {
        return ['tier_2', 'processo', 'plano', 'execucao'];
    }

    public function severity(): string
    {
        return 'medium';
    }

    public function enforcement(): string
    {
        return 'warn';
    }

    public function cadence(): string
    {
        return 'daily';
    }

    public function check(array $opts = []): DriftCheckResult
    {
        $start = microtime(true);

        try {
            Artisan::call('jana:plan-drift', ['--json' => true]);
            $output = Artisan::output();
            $report = json_decode($output, true);

            if (! is_array($report)) {
                Log::channel('single')->warning('plan_drift — jana:plan-drift JSON inválido', [
                    'output_preview' => mb_substr($output, 0, 200),
                ]);

                return DriftCheckResult::clean($this->name(), $this->ms($start), ['error' => 'invalid_json']);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('plan_drift — jana:plan-drift threw', [
                'exception' => $e->getMessage(),
            ]);

            return DriftCheckResult::clean($this->name(), $this->ms($start), ['error' => $e->getMessage()]);
        }

        // ⊘ honesto: MCP offline / índice ausente / nenhuma task com parent_plan ainda.
        if (($report['skipped'] ?? false) === true) {
            return DriftCheckResult::clean($this->name(), $this->ms($start), [
                'skipped' => true,
                'reason' => (string) ($report['reason'] ?? 'sem razão declarada'),
            ]);
        }

        $findings = $this->findingsFromReport($report);
        $meta = [
            'planos' => $report['planos'] ?? 0,
            'linked' => $report['linked'] ?? 0,
            'category_counts' => $this->categoryCounts($report),
            'scanned_at' => now()->toIso8601String(),
        ];

        if (count($findings) === 0) {
            return DriftCheckResult::clean($this->name(), $this->ms($start), $meta);
        }

        return DriftCheckResult::drifted(
            name: $this->name(),
            findings: $findings,
            duration_ms: $this->ms($start),
            metadata: $meta,
        );
    }

    /**
     * Converte o relatório de `jana:plan-drift --json` em DriftFinding[].
     *
     * PURA (sem Artisan/DB/filesystem) — é o que o teste exercita direto.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, DriftFinding>
     */
    public function findingsFromReport(array $report): array
    {
        $out = [];

        foreach ((array) ($report['findings'] ?? []) as $f) {
            if (! is_array($f)) {
                continue;
            }
            $slug = (string) ($f['slug'] ?? 'desconhecido');
            $categoria = self::categoria($f);

            $out[] = new DriftFinding(
                target: $slug,
                target_type: 'plano',
                severity: self::severityFor($categoria),
                message: sprintf(
                    'Plano "%s" [%s]: %s Ação: %s',
                    (string) ($f['plan'] ?? '(sem plano registrado)'),
                    $slug,
                    (string) ($f['issue'] ?? 'drift sem descrição'),
                    $categoria === 'orfao_reverso'
                        ? "registrar o plano no Índice de Planos Vivos (memory/requisitos/_processo/PLANS-INDEX.md) OU corrigir o parent_plan das tasks."
                        : 'reconciliar o bloco `## Status vivo` do plano com o estado real das tasks MCP.',
                ),
                evidence: [
                    'category' => $categoria,
                    'level' => (string) ($f['level'] ?? 'warn'),
                    'plan_status' => $f['status'] ?? null,
                    'counts' => $f['counts'] ?? [],
                ],
            );
        }

        return $out;
    }

    /**
     * Categoria do finding. `status === null` é a assinatura do órfão reverso emitido pelo
     * comando (task aponta pra slug que nenhum plano registrado declara).
     *
     * @param  array<string, mixed>  $f
     */
    private static function categoria(array $f): string
    {
        if (($f['level'] ?? 'warn') === 'fail') {
            return 'ligacao_fantasma';
        }

        return ($f['status'] ?? null) === null ? 'orfao_reverso' : 'status_divergente';
    }

    private static function severityFor(string $categoria): string
    {
        return match ($categoria) {
            'ligacao_fantasma' => 'high',
            'status_divergente' => 'medium',
            default => 'low',
        };
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, int>
     */
    private function categoryCounts(array $report): array
    {
        $counts = ['ligacao_fantasma' => 0, 'status_divergente' => 0, 'orfao_reverso' => 0];
        foreach ((array) ($report['findings'] ?? []) as $f) {
            if (is_array($f)) {
                $counts[self::categoria($f)]++;
            }
        }

        return $counts;
    }

    private function ms(float $start): int
    {
        return (int) ((microtime(true) - $start) * 1000);
    }
}
