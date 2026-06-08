<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Reconcile\Reconcilers;

use Illuminate\Support\Facades\File;
use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;

/**
 * EvalReconciler — faceta 'eval' do loop `jana:reconcile` (ADR 0237).
 *
 * Reconcilia a QUALIDADE do retrieval Jana medida pela eval golden-set RAGAS
 * (observed: pass-rate / score da última run) contra o threshold mínimo aceitável
 * (desired: `config('ragas.thresholds.faithfulness')`). Fecha o buraco descrito na
 * task: a eval golden-set já EXISTE (100 questões em
 * `Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json`, gate bloqueante em
 * `jana:ragas-ci-eval` + canary diário) mas o RESULTADO dela não era tratado como
 * estado observável do índice — quando o retrieval degrada e o score cai, o sinal
 * se perde. Esta faceta torna esse drift VISÍVEL todo dia: trata o score da eval
 * como observação e alerta se ele caiu abaixo do threshold OU se a eval nunca
 * produziu um resultado real (gate cego).
 *
 * ── O que é "observed" (a peça mais fina) ────────────────────────────────────
 * A fonte da verdade do "último score" é o artefato canary
 * `governance/jana-ragas-baseline.json` (mesmo shape gerado/atualizado pelo
 * workflow `jana-ragas-canary.yml` via `update_baseline`). Lê
 * `metrics.faithfulness.value` (a métrica primária do gate RAGAS — faithfulness =
 * resposta ancorada no contexto recuperado; é o proxy mais direto de "o índice
 * ainda recupera o que importa"). O baseline nasce ZERADO (placeholder
 * `mode: "placeholder"`, `value: 0`, `last_updated: null`) — enquanto ninguém
 * rodou a eval pra valer, NÃO existe score: isso é tratado como `null` (gate cego),
 * NÃO como score 0 (que falsamente diria "índice 100% quebrado"). Honestidade
 * brutal: esta faceta só vale o que a golden-set + o baseline populado valem;
 * baseline placeholder = a faceta grita "gate cego" até alguém calibrar.
 *
 * ── healable = FALSE (alerta-only, R10) ──────────────────────────────────────
 * Curar uma eval degradada NÃO é um ato append-only seguro: significa RE-RODAR a
 * eval (`gh workflow run jana-ragas-canary.yml -f mode=real` / `php artisan
 * jana:ragas-ci-eval --mode=real`) e INVESTIGAR o índice (re-ingestão, settings do
 * embedder, reranker) — trabalho de humano/pipeline. Disparar isso de dentro de um
 * reconciler idempotente violaria a invariante do contrato ("drift com
 * fonte-de-verdade clara cura; ambíguo/perigoso alerta humano", ADR 0237) e a
 * separação de runtime (ADR 0062). Por isso TODO ReconcileDrift daqui nasce
 * `healable=false`; com `heal=true` este Reconciler NÃO re-roda eval nenhuma —
 * apenas RE-REPORTA o alerta. `healedCount` é sempre 0.
 *
 * ── Testabilidade (sem rede / sem rodar eval / sem ler artefato real) ─────────
 * A leitura do resultado da eval é INJETÁVEL via closure no construtor
 * (`observarEval(): ?array`). Default lê o artefato real
 * (`governance/jana-ragas-baseline.json`) se existir; teste injeta um array fixo
 * (ou `null` pra simular artefato ausente). O núcleo `analisar(?float $score,
 * float $threshold)` é PURO + determinístico — exercitado direto, espelhando o
 * padrão já provado em `DeployReconciler::analisar` /
 * `DesignDocsFreshnessChecker::analisarDoc`.
 *
 * Refs:
 * - ADR 0237 (jana:reconcile loop único — contrato Reconciler)
 * - ADR 0037 §GAP-2 (RAGAS gate em CI — origem da eval golden-set)
 * - ADR 0062 (separação runtime Hostinger × CT 100 — por que não re-roda eval aqui)
 * - config/ragas.php (thresholds canônicos — fonte do "desired")
 * - governance/jana-ragas-baseline.json (artefato canary — fonte do "observed")
 * - .github/workflows/jana-ragas-canary.yml (quem popula/atualiza o baseline)
 * - Modules/Jana/Console/Commands/JanaRagasCiCommand.php (o gate que produz o score)
 */
final class EvalReconciler implements Reconciler
{
    /**
     * Path (relativo a base_path) do artefato canary que guarda o último score da
     * eval golden-set. Mesmo arquivo que o workflow jana-ragas-canary.yml atualiza.
     */
    public const BASELINE_PATH = 'governance/jana-ragas-baseline.json';

    /**
     * Threshold default de faithfulness quando `config('ragas.thresholds.faithfulness')`
     * estiver ausente. Espelha o default canônico de config/ragas.php (0.70 — MVP
     * "realistic-strict" calibrado 2026-05-13). Mantido aqui só como rede de segurança
     * pra o reconciler nunca rodar com threshold 0 (que aceitaria qualquer degradação).
     */
    public const DEFAULT_THRESHOLD = 0.70;

    /**
     * @var \Closure(): ?array<string, mixed> Observa o resultado da última eval.
     *   Default: lê governance/jana-ragas-baseline.json (decodificado). Devolve null
     *   quando o artefato não existe / é ilegível / é malformado. Teste injeta um stub.
     */
    private \Closure $observarEval;

    /**
     * @param (\Closure(): ?array<string, mixed>)|null $observarEval Injeta o leitor da
     *   eval (teste passa um array fixo ou null). Default lê o artefato real.
     */
    public function __construct(?\Closure $observarEval = null)
    {
        $this->observarEval = $observarEval ?? fn (): ?array => $this->lerBaselineArtefato();
    }

    public function name(): string
    {
        return 'eval';
    }

    public function description(): string
    {
        return 'Score da eval golden-set RAGAS (faithfulness) vs threshold — gate de qualidade do retrieval (alerta-only)';
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tier_1', 'eval', 'quality', 'ia', 'alert_only'];
    }

    public function reconcile(array $opts = []): ReconcileResult
    {
        $start = microtime(true);

        $threshold = $this->resolveThreshold();

        $evalRaw = ($this->observarEval)();
        $score = $this->extrairScore($evalRaw);

        $drifts = $this->analisar($score, $threshold);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $metadata = [
            'desired_threshold' => $threshold,
            'observed_score' => $score,
            'metric' => 'faithfulness',
            'eval_present' => $score !== null,
            'baseline_path' => self::BASELINE_PATH,
            // Documenta no resultado que esta faceta NUNCA cura (eval é re-rodada por humano/pipeline).
            'heal_supported' => false,
        ];

        return ReconcileResult::from($this->name(), $drifts, $durationMs, $metadata);
    }

    /**
     * Núcleo PURO + determinístico (sem I/O, sem rede, sem rodar eval): decide o(s)
     * ReconcileDrift a partir do score observado + threshold desejado. Testável direto.
     *
     * Semântica:
     *  - score null            → drift NÃO-curável "gate cego": a eval nunca produziu
     *                            resultado real (baseline placeholder / artefato ausente).
     *                            É a peça mais incerta — sem score, o gate de qualidade
     *                            está DESLIGADO sem ninguém perceber. Alerta humano calibrar.
     *  - score < threshold     → drift NÃO-curável: retrieval regrediu abaixo do mínimo
     *                            aceitável — re-rodar eval + investigar o índice (humano).
     *  - score >= threshold    → [] (synced).
     *
     * TODO drift nasce healable=false: a "cura" é re-rodar a eval + investigar o índice (R10).
     *
     * @return array<int, ReconcileDrift>
     */
    public function analisar(?float $score, float $threshold): array
    {
        $thresholdStr = $this->fmt($threshold);

        // Gate cego: sem score real, a eval não está protegendo nada. Drift informativo
        // (honesto: depende da golden-set ter sido rodada pra valer + baseline populado).
        if ($score === null) {
            return [new ReconcileDrift(
                target: 'eval:faithfulness',
                detail: 'Eval golden-set sem resultado real (baseline placeholder ou artefato ausente em '
                    . self::BASELINE_PATH . '). O gate de qualidade do retrieval está CEGO — uma '
                    . 'degradação do índice passaria despercebida. Ação: rodar a eval pra valer e popular '
                    . 'o baseline (`gh workflow run jana-ragas-canary.yml -f mode=real -f update_baseline=true` '
                    . 'ou `php artisan jana:ragas-ci-eval --mode=real`). Este reconciler NÃO roda eval.',
                desired: "faithfulness >= {$thresholdStr}",
                observed: 'sem score (gate cego)',
                healable: false,
            )];
        }

        $scoreStr = $this->fmt($score);

        // Sincronizado: score na régua ou acima.
        if ($score >= $threshold) {
            return [];
        }

        // Drift real: retrieval regrediu abaixo do gate. NÃO-curável — alerta humano
        // re-rodar a eval + investigar o índice.
        return [new ReconcileDrift(
            target: 'eval:faithfulness',
            detail: "Eval golden-set abaixo do threshold: faithfulness={$scoreStr} < {$thresholdStr}. "
                . 'O retrieval Jana degradou — re-rodar a eval (`php artisan jana:ragas-ci-eval --mode=real`) '
                . 'e investigar o índice (re-ingestão, settings do embedder, reranker). '
                . 'Este reconciler NÃO re-roda eval nem mexe no índice.',
            desired: "faithfulness >= {$thresholdStr}",
            observed: "faithfulness = {$scoreStr}",
            healable: false,
        )];
    }

    /**
     * Threshold desejado: `config('ragas.thresholds.faithfulness')`, com fallback
     * defensivo no default canônico (nunca 0 — threshold 0 aceitaria qualquer degradação).
     */
    private function resolveThreshold(): float
    {
        $raw = config('ragas.thresholds.faithfulness', self::DEFAULT_THRESHOLD);

        if (is_int($raw) || is_float($raw)) {
            $val = (float) $raw;

            return $val > 0.0 ? $val : self::DEFAULT_THRESHOLD;
        }

        if (is_string($raw) && is_numeric($raw)) {
            $val = (float) $raw;

            return $val > 0.0 ? $val : self::DEFAULT_THRESHOLD;
        }

        return self::DEFAULT_THRESHOLD;
    }

    /**
     * Extrai o score de faithfulness do array da eval, tolerando ausência/placeholder.
     *
     * Devolve null (= "sem score real", gate cego) quando:
     *   - o array é null / não tem `metrics.faithfulness`;
     *   - o registro está marcado como placeholder (`mode: "placeholder"`) ou nunca
     *     rodou (`last_updated: null` / `evaluated_questions: 0`);
     *   - o value não é numérico ou é <= 0 (baseline zerado = "nunca calibrado").
     *
     * value > 0 só é aceito como score real. Mantém a leitura defensiva contra
     * mixed (PHPStan lvl 5: o JSON decodificado é array<mixed>).
     *
     * @param array<string, mixed>|null $evalRaw
     */
    private function extrairScore(?array $evalRaw): ?float
    {
        if ($evalRaw === null) {
            return null;
        }

        $metrics = $evalRaw['metrics'] ?? null;
        if (! is_array($metrics)) {
            return null;
        }

        $faith = $metrics['faithfulness'] ?? null;
        if (! is_array($faith)) {
            return null;
        }

        // Marcadores explícitos de "ainda não calibrado" → gate cego.
        $mode = $faith['mode'] ?? null;
        if (is_string($mode) && strtolower($mode) === 'placeholder') {
            return null;
        }
        if (($faith['last_updated'] ?? null) === null) {
            return null;
        }
        $evaluated = $faith['evaluated_questions'] ?? null;
        if (is_int($evaluated) && $evaluated <= 0) {
            return null;
        }

        $value = $faith['value'] ?? null;
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }
        $score = (float) $value;

        // value <= 0 = baseline zerado / placeholder não-marcado → trata como gate cego,
        // NUNCA como "índice 100% quebrado" (seria alarme falso de severidade máxima).
        return $score > 0.0 ? $score : null;
    }

    /**
     * Leitor default do artefato canary (governance/jana-ragas-baseline.json).
     * Devolve null se ausente / ilegível / JSON inválido (→ gate cego, não erro fatal).
     *
     * @return array<string, mixed>|null
     */
    private function lerBaselineArtefato(): ?array
    {
        $path = base_path(self::BASELINE_PATH);

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Formata score/threshold (0..1) com 2 casas, estável pra mensagem + comparação visual.
     */
    private function fmt(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
