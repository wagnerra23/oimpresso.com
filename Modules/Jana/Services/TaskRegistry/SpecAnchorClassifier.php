<?php

declare(strict_types=1);

namespace Modules\Jana\Services\TaskRegistry;

/**
 * SpecAnchorClassifier — porta PHP (pura) da gramática de âncora spec↔código
 * do ADR 0273, restrita ao que o forward-close por âncora precisa (ADR 0337,
 * emenda à 0144).
 *
 * ── POR QUE EXISTE ──────────────────────────────────────────────────────────
 * A âncora `**Implementado em:** ... verificado@<sha> (data)` é a FONTE ÚNICA de
 * done-ness (ADR 0302). Os lints de governança (`anchor-lint.mjs` / `doneness-lint.mjs`)
 * a checam no git, mas PARAM na fronteira do git — o status do card no `mcp_tasks`
 * ficava órfão (SPEC/anchor dizendo done, card `todo` por dias — incidente US-FIN-031,
 * fechada 8d atrasada). Este classificador dá ao `TaskParserService` a capacidade de
 * LER o veredito da âncora e carregá-lo pro DB (forward-close, ADR 0337).
 *
 * ── NÚCLEO PURO ─────────────────────────────────────────────────────────────
 * {@see classify()} é determinístico: recebe o bloco da US + um callback de
 * existência-de-path (injetado) e devolve o estado SEM tocar disco/DB. Espelha o
 * padrão {@see \Modules\Jana\Services\Reconcile\Reconcilers\TasksReconciler::analisar()}
 * (núcleo puro testável sem I/O).
 *
 * ── FIDELIDADE À GRAMÁTICA (anchor-lint.mjs) ────────────────────────────────
 * Só o estado `anchored_ok` (com SHA) habilita o forward-close, e ele é MAIS
 * estrito que o `classify()` do anchor-lint: exige a forma canônica COMPLETA
 * (`\`path\`(  · \`path\`)* · verificado@<7hex> (YYYY-MM-DD)`) E todos os paths
 * existentes no disco. `_pendente_`/`_parcial_`/placeholder/path-morto NUNCA
 * habilitam (fail-closed). Zumbi (Page desligada — ADR 0273 SA-A2-bis) NÃO é
 * reavaliado aqui: o gatilho do forward-close exige TAMBÉM o `status: done`
 * declarado à mão no SPEC, que é a decisão humana que cobre esse resíduo.
 *
 * Refs: ADR 0273 (gramática anchor) · ADR 0302 (âncora = fonte de done-ness) ·
 *       ADR 0337 (emenda 0144 — forward-close por âncora) · ADR 0144 (DB canon).
 */
final class SpecAnchorClassifier
{
    /** Campo leniente (legados usam `> ` blockquote). Espelha FIELD_RE do anchor-lint. */
    private const FIELD_RE = '/^(?:>\s*)?\*\*Implementado em:\*\*\s*(.*)$/mu';

    /**
     * Forma canônica de uma âncora VERIFICADA (ADR 0273 §1), no `rest` (o que vem
     * depois de `**Implementado em:** `). Captura o SHA (grupo 1) e a data (grupo 2).
     * NÃO casa `_parcial_` (começa com backtick) nem `_pendente_`.
     */
    private const GRAMMAR_OK_RE = '/^`[^`]+`(?: · `[^`]+`)* · verificado@([0-9a-f]{7}) \((\d{4}-\d{2}-\d{2})\)(?: — .+)?$/u';

    /** Placeholder legado (ADR 0273 "Contexto"). Espelha PLACEHOLDER_RE do anchor-lint. */
    private const PLACEHOLDER_RE = '/TODO|_\[path\]_|\ba criar\b|_xx_/i';

    /**
     * Classifica a âncora de um bloco de US.
     *
     * @param  string  $block       Corpo da US (do heading até a próxima US).
     * @param  callable(string): bool  $pathExists  path repo-relativo → existe no disco?
     * @return array{state: string, sha: ?string, paths: list<string>}
     *         state ∈ sem_campo | pendente | parcial | placeholder | anchored_dead | anchored_ok
     */
    public function classify(string $block, callable $pathExists): array
    {
        $rest = $this->extractRest($block);
        if ($rest === null) {
            return ['state' => 'sem_campo', 'sha' => null, 'paths' => []];
        }

        if (str_starts_with($rest, '_pendente_')) {
            return ['state' => 'pendente', 'sha' => null, 'paths' => []];
        }
        if (str_starts_with($rest, '_parcial_')) {
            // Coberta mas NÃO done — não habilita forward-close (fail-closed).
            return ['state' => 'parcial', 'sha' => null, 'paths' => []];
        }
        if (preg_match(self::PLACEHOLDER_RE, $rest) === 1) {
            return ['state' => 'placeholder', 'sha' => null, 'paths' => []];
        }

        if (preg_match(self::GRAMMAR_OK_RE, $rest, $m) !== 1) {
            // Preenchido mas fora da forma canônica verificada → não confiável.
            return ['state' => 'anchored_dead', 'sha' => null, 'paths' => []];
        }

        $sha = $m[1];
        // Paths canônicos ficam ANTES do ` · verificado@` (a nota livre depois pode
        // conter backticks tipo `bulk_*` que NÃO são paths).
        $pathPart = preg_split('/ · verificado@/u', $rest, 2)[0];
        $paths = $this->backtickPaths($pathPart);

        if ($paths === []) {
            // Gramática exige ≥1 segmento-path (ADR 0273 §1).
            return ['state' => 'anchored_dead', 'sha' => null, 'paths' => []];
        }

        $mortos = array_values(array_filter($paths, static fn (string $p): bool => ! $pathExists($p)));
        if ($mortos !== []) {
            return ['state' => 'anchored_dead', 'sha' => null, 'paths' => $mortos];
        }

        return ['state' => 'anchored_ok', 'sha' => $sha, 'paths' => $paths];
    }

    /** Extrai o `rest` do campo `**Implementado em:**` (primeira ocorrência) ou null. */
    public function extractRest(string $block): ?string
    {
        if (preg_match(self::FIELD_RE, $block, $m) !== 1) {
            return null;
        }

        return trim($m[1]);
    }

    /**
     * Segmentos-path em backtick (ADR 0273 §1): contêm '/', relativos à raiz do
     * repo — `/rota` (URL) e `~/...` (home) NÃO são verificáveis → ignorados.
     *
     * @return list<string>
     */
    private function backtickPaths(string $rest): array
    {
        if (preg_match_all('/`([^`]+)`/u', $rest, $mm) === false || empty($mm[1])) {
            return [];
        }

        $out = [];
        foreach ($mm[1] as $seg) {
            $seg = preg_replace('/[.,;:]+$/', '', trim($seg));
            if ($seg === '' || $seg === null) {
                continue;
            }
            if (str_contains($seg, '/') && ! str_starts_with($seg, '/') && ! str_starts_with($seg, '~')) {
                $out[] = $seg;
            }
        }

        return array_values(array_unique($out));
    }
}
