<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Peso;

/**
 * RelevanciaMetaInferer — Área B do Modelo de Peso Real (ADR 0232).
 *
 * FONTE de `relevancia_meta` (0-100): quanto um item move/protege a meta
 * R$5M/ano (ADR 0022). É o sinal cross-tipo que o {@see PesoRealService}
 * CONSOME pra calcular o Peso Real (relevancia_meta × modulador_do_tipo).
 *
 * Régua canônica (ADR 0232):
 *   0-25   indireto/abstrato (governança que não evita erro caro)
 *   26-50  habilitador (infra/meio, não fim)
 *   51-75  alavanca (módulo com diferencial, sinal médio)
 *   76-100 receita direta / proteção Tier 0 (meta, multi-tenant, cliente pagante)
 *
 * HIERARQUIA DE DECISÃO (estado-da-arte 2026 "Front-Matter Standard" + DRICE:
 * sinal explícito sempre vence estimativa subjetiva/heurística):
 *   1. Override explícito no frontmatter (`relevancia_meta: NN` ou
 *      `meta_contribution: alta|media|baixa`) → usa direto.
 *   2. Heurística por tags Tier 0 (multi-tenant/meta/segurança) → topo.
 *   3. Heurística por módulo (mapa NORTE-ROI: Tier 1 vende > Tier 2 > Tier 3).
 *   4. Heurística por tipo de documento.
 *   5. Default desconhecido → 50 (meio da régua).
 *
 * Service PURO: heurística determinística, SEM DB, SEM business_id, SEM LLM.
 * Não custa nada e não toca multi-tenant (não há query). NÃO está plugado em
 * retrieval/prod — é a fonte que a Área A (PesoRealService) virá a consumir.
 *
 * Toda faixa/mapa vem de config `jana.peso_real.relevancia` (valores diretos,
 * sem env() — Larastan barra env fora de config/ raiz, ADR 0232 / Constituição §4).
 *
 * @see memory/decisions/0232-modelo-peso-real-classificacao-por-meta.md (Área B)
 * @see memory/NORTE-ROI.md (ranking Tier 1/2/3)
 * @see Modules/Jana/Services/Peso/PesoRealService.php (consumidor — Área A)
 */
final class RelevanciaMetaInferer
{
    /**
     * Faixa default da régua (ADR 0232) quando nada casa.
     */
    private const DEFAULT_SCORE = 50;

    private const MIN = 0;

    private const MAX = 100;

    /**
     * Mapa `meta_contribution` (rótulo humano no frontmatter) → score canônico.
     * Usa o meio de cada faixa da régua ADR 0232.
     *
     * @var array<string, int>
     */
    private const CONTRIBUTION_LABELS = [
        'alta'  => 90, // receita direta / proteção Tier 0
        'media' => 60, // alavanca
        'baixa' => 30, // habilitador/indireto
    ];

    /**
     * Config resolvida do módulo Jana (`jana.peso_real.relevancia`).
     *
     * @var array<string, mixed>
     */
    private readonly array $config;

    /**
     * @param array<string, mixed>|null $config Override pra teste; senão lê config() do módulo.
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('jana.peso_real.relevancia', []);
    }

    /**
     * Infere `relevancia_meta` (0-100) de um documento.
     *
     * @param string                    $type        adr|spec|session|handoff|reference|...
     * @param string|null               $module      Nome do módulo (ex.: 'Vestuario') ou null.
     * @param array<int, string>        $tags        Tags do frontmatter.
     * @param array<string, mixed>      $frontmatter Frontmatter completo (pode trazer override).
     *
     * @return int Score clampeado em [0, 100].
     */
    public function inferir(
        string $type,
        ?string $module = null,
        array $tags = [],
        array $frontmatter = [],
    ): int {
        // 1. Override explícito vence tudo (Front-Matter Standard 2026).
        $override = $this->fromFrontmatter($frontmatter);
        if ($override !== null) {
            return $this->clamp($override);
        }

        // 2. Tags Tier 0 (meta / multi-tenant / segurança) → topo da régua.
        $byTag = $this->fromTags($tags);
        if ($byTag !== null) {
            return $this->clamp($byTag);
        }

        // 3. Módulo (mapa NORTE-ROI Tier 1/2/3).
        $byModule = $this->fromModule($module);
        if ($byModule !== null) {
            return $this->clamp($byModule);
        }

        // 4. Tipo de documento.
        $byType = $this->fromType($type);
        if ($byType !== null) {
            return $this->clamp($byType);
        }

        // 5. Desconhecido → meio da régua.
        return self::DEFAULT_SCORE;
    }

    /**
     * Override explícito no frontmatter.
     *
     * @param array<string, mixed> $frontmatter
     */
    private function fromFrontmatter(array $frontmatter): ?int
    {
        // `relevancia_meta: NN` numérico tem prioridade máxima.
        if (isset($frontmatter['relevancia_meta']) && is_numeric($frontmatter['relevancia_meta'])) {
            return (int) $frontmatter['relevancia_meta'];
        }

        // `meta_contribution: alta|media|baixa` (rótulo humano).
        if (isset($frontmatter['meta_contribution']) && is_string($frontmatter['meta_contribution'])) {
            $label = strtolower(trim($frontmatter['meta_contribution']));

            return self::CONTRIBUTION_LABELS[$label] ?? null;
        }

        return null;
    }

    /**
     * Heurística por tags Tier 0. Retorna o MAIOR peso dentre tags que casam.
     *
     * @param array<int, string> $tags
     */
    private function fromTags(array $tags): ?int
    {
        /** @var array<string, int> $tagMap */
        $tagMap = (array) ($this->config['tags'] ?? []);
        if ($tagMap === []) {
            return null;
        }

        $best = null;
        foreach ($tags as $tag) {
            $key = strtolower(trim((string) $tag));
            if (isset($tagMap[$key])) {
                $weight = (int) $tagMap[$key];
                $best = $best === null ? $weight : max($best, $weight);
            }
        }

        return $best;
    }

    /**
     * Heurística por módulo (mapa NORTE-ROI). Case-insensitive.
     */
    private function fromModule(?string $module): ?int
    {
        if ($module === null || trim($module) === '') {
            return null;
        }

        /** @var array<string, int> $moduleMap */
        $moduleMap = (array) ($this->config['modules'] ?? []);
        $key = strtolower(trim($module));

        foreach ($moduleMap as $name => $weight) {
            if (strtolower((string) $name) === $key) {
                return (int) $weight;
            }
        }

        return null;
    }

    /**
     * Heurística por tipo de documento (fallback fraco).
     */
    private function fromType(string $type): ?int
    {
        /** @var array<string, int> $typeMap */
        $typeMap = (array) ($this->config['types'] ?? []);
        $key = strtolower(trim($type));

        return isset($typeMap[$key]) ? (int) $typeMap[$key] : null;
    }

    /**
     * Clampa em [0, 100] (régua ADR 0232).
     */
    private function clamp(int $score): int
    {
        return max(self::MIN, min(self::MAX, $score));
    }
}
