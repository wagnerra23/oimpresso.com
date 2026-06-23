<?php

declare(strict_types=1);

namespace Modules\Jana\Ai;

/**
 * UiJudgeConsensus — robustez self-consistency do PR UI Judge.
 *
 * Camada nova sobre o PrUiJudgeAgent (juiz de UI, Onda 4.1). Origem: dossiê
 * estado-da-arte 2026-06-23 (memory/sessions/2026-06-23-arte-validacao-L3-humano-judge.md,
 * §3b "LLM-judge robusto"). PROBLEMA que resolve: o juiz single-shot ALUCINA "ok"
 * — uma única amostra com sorte crava nota alta e o PR passa com "approve" falso.
 *
 * Pesquisa (VLM-as-judge surveys 2025-2026 + G-Eval): NÃO confie em 1 amostra.
 * Amostrar N vezes (temp~0.7) e agregar ganha 5-8% de alinhamento com humano sobre
 * o greedy single-shot, E — de graça — a VARIÂNCIA entre as N amostras vira o sinal
 * de CONFIANÇA: dimensão onde os N juízes concordam = alta confiança; onde divergem
 * = baixa confiança → abstenção → zona cinza (defer humano).
 *
 * Esta classe faz DUAS coisas, separadas de propósito:
 *  - collect()   — efeito colateral: roda o juiz N vezes via closure injetada.
 *  - aggregate() — PURA, estática, sem LLM: a matemática de mediana + spread→confiança.
 *                  É o alvo do unit test (UiJudgeConsensusTest) — sem chamada de API.
 *
 * Agregação das 3 dimensões SEMÂNTICAS (as 6 determinísticas vêm do
 * UiDeterministicScorer, fora daqui):
 *  - score agregado = MEDIANA dos N scores (robusta ao outlier — exatamente o
 *    "single-shot com sorte 9/10" que deveria ser 6).
 *  - confiança por dim = 1 - (spread/10), onde spread = max-min dos N scores.
 *  - confiança geral = MENOR confiança entre as dims (o juiz é tão confiável
 *    quanto sua dimensão mais discordante — uma dim onde 2 juízes deram 2 e um deu
 *    9 derruba a confiança geral, mesmo que as outras concordem).
 *  - abstém = N>=2 E confiança geral < limiar (Wagner calibra o limiar).
 *
 * Pré-req de diversidade: o PrUiJudgeAgent declara #[Temperature(0.7)] — sem isso
 * as N amostras seriam idênticas (greedy) e a confiança seria FALSA (sempre 1.0).
 *
 * NÃO troca de modelo: roda no gpt-4o-mini atual (decisão Wagner 2026-06-23). O
 * golden-visual (comparar screenshot vs ouro) é peça separada que entra depois.
 *
 * @see Modules/Jana/Ai/Agents/PrUiJudgeAgent.php (o juiz · #[Temperature])
 * @see Modules/Jana/Ai/UiDeterministicScorer.php (SEMANTIC_DIMENSIONS · as 6 det.)
 * @see memory/sessions/2026-06-23-arte-validacao-L3-humano-judge.md (§3b/§3d)
 */
final class UiJudgeConsensus
{
    public function __construct(
        private readonly int $samples = 3,
        private readonly float $abstainBelow = 0.6,
    ) {}

    /**
     * Roda o juiz $samples vezes via $runOnce e agrega as amostras válidas.
     *
     * $runOnce: callable(): ?array — uma execução do juiz já parseada (com a chave
     * `dimensoes`) ou null (falha de chamada/parse). Amostras null são descartadas;
     * a agregação trabalha só com as válidas (degrada graciosamente: 1 válida =
     * comportamento single-shot, sem abstenção por falta de sinal de discordância).
     *
     * @param  callable():(?array<string, mixed>)  $runOnce
     * @return array<string, mixed>
     */
    public function collect(callable $runOnce): array
    {
        $n = max(1, $this->samples);
        $parsed = [];

        for ($i = 0; $i < $n; $i++) {
            $one = $runOnce();
            if (is_array($one) && isset($one['dimensoes']) && is_array($one['dimensoes'])) {
                $parsed[] = $one;
            }
        }

        return self::aggregate($parsed, $this->abstainBelow);
    }

    /**
     * PURA — agrega N amostras parseadas nas 3 dims semânticas. Sem LLM.
     *
     * @param  list<array<string, mixed>>  $samples  cada item: review parseado com `dimensoes`
     * @return array{
     *     dimensoes: array<string, array<string, mixed>>,
     *     violacoes_estruturais: list<array<string, mixed>>,
     *     sugestoes: list<string>,
     *     lembretes: list<string>,
     *     confianca: float,
     *     confianca_por_dim: array<string, float>,
     *     samples: int,
     *     abstem: bool
     * }
     */
    public static function aggregate(array $samples, float $abstainBelow = 0.6): array
    {
        $samples = array_values(array_filter(
            $samples,
            static fn ($s): bool => is_array($s) && isset($s['dimensoes']) && is_array($s['dimensoes']),
        ));
        $n = count($samples);

        if ($n === 0) {
            return [
                'dimensoes' => [],
                'violacoes_estruturais' => [],
                'sugestoes' => [],
                'lembretes' => [],
                'confianca' => 0.0,
                'confianca_por_dim' => [],
                'samples' => 0,
                'abstem' => true,
            ];
        }

        $aggDims = [];
        $confPorDim = [];

        foreach (UiDeterministicScorer::SEMANTIC_DIMENSIONS as $dim) {
            $scores = [];
            $rationales = [];

            foreach ($samples as $s) {
                $d = $s['dimensoes'][$dim] ?? null;
                if (is_array($d) && isset($d['score'])) {
                    $scores[] = (int) $d['score'];
                    $rationales[] = (string) ($d['rationale'] ?? $d['nota'] ?? '');
                }
            }

            if ($scores === []) {
                continue; // dim ausente em TODAS as amostras (raro) — não agrega
            }

            $median = self::median($scores);
            $spread = max($scores) - min($scores); // 0..10
            $conf = round(1.0 - ($spread / 10.0), 2); // 1.0 = acordo total
            $repIdx = self::closestIndex($scores, $median); // rationale da amostra mais perto da mediana

            $aggDims[$dim] = [
                'score' => (int) round($median),
                'rationale' => $rationales[$repIdx] ?? '',
                'confianca' => $conf,
                'spread' => $spread,
                'scores' => $scores,
            ];
            $confPorDim[$dim] = $conf;
        }

        // Confiança geral = a MENOR entre as dims. Com 1 amostra não há sinal de
        // discordância → 1.0 (degrada pro single-shot antigo, sem abstenção falsa).
        $confianca = match (true) {
            $confPorDim === [] => 0.0,
            $n === 1 => 1.0,
            default => round(min($confPorDim), 2),
        };
        $abstem = $n >= 2 && $confianca < $abstainBelow;

        return [
            'dimensoes' => $aggDims,
            'violacoes_estruturais' => self::mergeViolacoes($samples, $n),
            'sugestoes' => self::mergeSugestoes($samples),
            'lembretes' => self::firstLembretes($samples),
            'confianca' => $confianca,
            'confianca_por_dim' => $confPorDim,
            'samples' => $n,
            'abstem' => $abstem,
        ];
    }

    /**
     * @param  list<int>  $nums
     */
    private static function median(array $nums): float
    {
        sort($nums);
        $c = count($nums);
        $mid = intdiv($c, 2);

        return $c % 2 === 1
            ? (float) $nums[$mid]
            : ($nums[$mid - 1] + $nums[$mid]) / 2.0;
    }

    /**
     * Índice (no array ORIGINAL, não ordenado) do score mais perto do alvo.
     *
     * @param  list<int>  $scores
     */
    private static function closestIndex(array $scores, float $target): int
    {
        $best = 0;
        $bestDist = INF;

        foreach ($scores as $i => $s) {
            $dist = abs($s - $target);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $i;
            }
        }

        return $best;
    }

    /**
     * União das violações estruturais das N amostras, deduplicada por
     * (tipo|arquivo|linha), com `consenso` = "k/N" (quantas amostras a viram).
     * Uma violação k/N baixo é achado pouco-consensual; k/N alto é robusto.
     *
     * @param  list<array<string, mixed>>  $samples
     * @return list<array<string, mixed>>
     */
    private static function mergeViolacoes(array $samples, int $n): array
    {
        $bucket = [];

        foreach ($samples as $s) {
            $vs = is_array($s['violacoes_estruturais'] ?? null) ? $s['violacoes_estruturais'] : [];
            foreach ($vs as $v) {
                if (! is_array($v)) {
                    continue;
                }
                $key = ($v['tipo'] ?? '').'|'.($v['arquivo'] ?? '').'|'.($v['linha'] ?? '');
                if (! isset($bucket[$key])) {
                    $v['_hits'] = 1;
                    $bucket[$key] = $v;
                } else {
                    $bucket[$key]['_hits']++;
                }
            }
        }

        $out = [];
        foreach ($bucket as $v) {
            $hits = (int) $v['_hits'];
            unset($v['_hits']);
            $v['consenso'] = $hits.'/'.$n;
            $out[] = $v;
        }

        // mais-consensuais primeiro
        usort($out, static fn ($a, $b): int => ((int) explode('/', (string) $b['consenso'])[0])
            <=> ((int) explode('/', (string) $a['consenso'])[0]));

        return $out;
    }

    /**
     * União deduplicada (case-insensitive) das sugestões das N amostras.
     *
     * @param  list<array<string, mixed>>  $samples
     * @return list<string>
     */
    private static function mergeSugestoes(array $samples): array
    {
        $seen = [];
        $out = [];

        foreach ($samples as $s) {
            $sugs = is_array($s['sugestoes'] ?? null) ? $s['sugestoes'] : [];
            foreach ($sugs as $sug) {
                $str = trim((string) $sug);
                $k = mb_strtolower($str);
                if ($str === '' || isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $out[] = $str;
            }
        }

        return $out;
    }

    /**
     * Lembretes da primeira amostra que os trouxer (são estáticos — iguais em todas).
     *
     * @param  list<array<string, mixed>>  $samples
     * @return list<string>
     */
    private static function firstLembretes(array $samples): array
    {
        foreach ($samples as $s) {
            if (! empty($s['lembretes']) && is_array($s['lembretes'])) {
                return array_values(array_map(static fn ($l): string => (string) $l, $s['lembretes']));
            }
        }

        return [];
    }
}
