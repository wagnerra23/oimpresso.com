<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Governance\Services\Concerns\PersistsDriftAlert;
use Modules\Governance\Services\DriftFinding;

/**
 * governance:ragas-eval-alert — persiste em mcp_alertas_eventos o veredito do eval REAL
 * da Jana (`jana:ragas-real-eval`) medido FORA desta app, no container de staging do CT 100.
 *
 * ── O BURACO QUE ISTO FECHA (medido 2026-07-27) ──────────────────────────────
 * O eval mede, publica e o vermelho MORRE. Cadeia real do domingo 2026-07-26:
 *
 *   06:06  jana:ragas-real-eval roda no CT 100 → gate_status=fail
 *          (context_recall 0.3461 < piso 0.36 — abaixo da faixa observada, não ruído)
 *   06:06  o invocador sai exit 1 e escreve em /opt/oimpresso-ragas/evals.log
 *   08:30  o transporte publica o trend na órfã governance/ragas-real-trend
 *   →      NINGUÉM É AVISADO.
 *
 * Duas razões independentes, e as duas foram verificadas:
 *   (a) o `->onFailure()` do `app/Console/Kernel.php` NÃO dispara — quem invoca é o
 *       cron do host (caminho A da US-COPI-140), não o scheduler do Laravel. O
 *       onFailure é do scheduler; sem ele, o hook de falha não existe.
 *   (b) no read-side, `sdd-scorecard.mjs::measureRagasRealUptime` conta semana
 *       `fail` como VÁLIDA de propósito — a métrica é UPTIME ("rodou?"), não
 *       qualidade. O `gate_status` viaja em `detail.latest`, mas nada fica
 *       vermelho onde alguém olha.
 *
 * Ou seja: o alarme existia no desenho e não existia no caminho. Este comando é o
 * elo que faltava — e NÃO cria régua nova: reusa o mesmo `PersistsDriftAlert`
 * (ADR 0216) que o `staging_freshness` e o `webhook_canary` já usam, com
 * idempotência DIÁRIA (não vira spam) + escalonamento >3d (warn→high→critical) +
 * insert em `mcp_alertas_eventos`, que o brief/inbox do time LÊ.
 *
 * ── POR QUE UM COMANDO (e não um DriftChecker no governance:audit) ───────────
 * Mesmíssimo racional do `RecordStagingFreshnessAlertCommand`: quem MEDE é outro
 * runtime. O eval roda no container `oimpresso-staging`, cujo banco tem 15 tabelas e
 * NÃO tem `mcp_alertas_eventos`; o banco que tem é o do `oimpresso-mcp`. Então a
 * DETECÇÃO fica onde o corpus está e a PERSISTÊNCIA vem pra cá, chamada via
 * `docker exec oimpresso-mcp php artisan governance:ragas-eval-alert ...`.
 *
 * Só persiste quando `--gate-status=fail`. `pass` e `skipped` = no-op (convenção dos
 * demais checkers: não emitir quando limpo; o ack/resolve é MANUAL na UI Governance).
 *
 * @see scripts/tests/ct100-jana-evals.sh (o invocador — cron dom 06:00 no host CT 100)
 * @see Modules/Jana/Console/Commands/JanaRagasRealEvalCommand.php (quem mede)
 * @see governance/jana-ragas-real-baseline.json (dono único dos pisos — US-COPI-136)
 */
class RecordRagasEvalAlertCommand extends Command
{
    use PersistsDriftAlert;

    protected $signature = 'governance:ragas-eval-alert
                            {--gate-status= : Veredito do eval (pass | fail | skipped)}
                            {--week= : Semana ISO do run (YYYY-MM-DD do domingo)}
                            {--faithfulness= : faithfulness_avg medido}
                            {--relevancy= : relevancy_avg medido}
                            {--context-recall= : context_recall_avg medido}
                            {--n-evaluated= : Quantas perguntas do gold-set foram avaliadas}';

    protected $description = 'Persiste em mcp_alertas_eventos o gate_status do jana:ragas-real-eval medido no CT 100';

    public function handle(): int
    {
        $status = strtolower(trim((string) $this->option('gate-status')));

        // Só FAIL escala. pass/skipped = no-op (idem convenção dos checkers).
        if ($status !== 'fail') {
            $this->info("gate_status '{$status}' — nada a persistir (só 'fail' escala pra mcp_alertas).");

            return self::SUCCESS;
        }

        $week = trim((string) $this->option('week'));
        $faith = $this->numeroOuNull($this->option('faithfulness'));
        $relev = $this->numeroOuNull($this->option('relevancy'));
        $recall = $this->numeroOuNull($this->option('context-recall'));
        $n = $this->numeroOuNull($this->option('n-evaluated'));

        // Nomeia a métrica que caiu — alerta que só diz "falhou" obriga o humano a ir
        // cavar o log no CT 100, que é exatamente o que este elo veio evitar.
        $quaisCairam = [];
        if ($faith !== null && $faith < 0.65) {
            $quaisCairam[] = sprintf('faithfulness %.4f < 0.65', $faith);
        }
        if ($relev !== null && $relev < 0.75) {
            $quaisCairam[] = sprintf('answer_relevancy %.4f < 0.75', $relev);
        }
        if ($recall !== null && $recall < 0.36) {
            $quaisCairam[] = sprintf('context_recall %.4f < 0.36', $recall);
        }
        $resumo = $quaisCairam === []
            ? 'piso não identificado pelas flags recebidas — ver report'
            : implode(' · ', $quaisCairam);

        $finding = new DriftFinding(
            target: 'jana:ragas-real-eval',
            target_type: 'eval',
            severity: 'high',
            message: "Qualidade REAL da Jana abaixo do piso na semana {$week}: {$resumo}. "
                .'Pisos vêm de governance/jana-ragas-real-baseline.json (thresholds_regressao, '
                .'dono único — US-COPI-136). Report: /opt/oimpresso-ragas/evals.log no CT 100; '
                .'histórico: branch órfã governance/ragas-real-trend.',
            evidence: [
                'week' => $week,
                'gate_status' => $status,
                'faithfulness_avg' => $faith,
                'relevancy_avg' => $relev,
                'context_recall_avg' => $recall,
                'n_evaluated' => $n,
                'source' => 'ct100-jana-evals.sh (cron dom 06:00 host CT100)',
            ],
        );

        $id = $this->persistirDriftAlert('ragas_eval_quality', $finding);

        if ($id === null) {
            $this->error('Falha ao persistir alerta ragas_eval_quality (ver log channel single).');

            return self::FAILURE;
        }

        $this->info("Alerta ragas_eval_quality persistido/idempotente (mcp_alertas_eventos id={$id}).");

        return self::SUCCESS;
    }

    /** flag ausente/vazia/não-numérica → null (o alerta sai mesmo assim, sem inventar número). */
    private function numeroOuNull(mixed $v): ?float
    {
        $s = trim((string) $v);

        return ($s !== '' && is_numeric($s)) ? (float) $s : null;
    }
}
