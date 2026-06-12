<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Peso;

/**
 * PesoRealService — função PURA de cálculo do Peso Real (ADR 0232).
 *
 * "Classificar memórias, decisões e iniciativas por contribuição à meta R$ [redacted Tier 0]M/ano"
 * (ADR 0022). Uma fórmula-mãe, três sabores — cada tipo tem natureza temporal
 * diferente, mas tudo aponta pra mesma meta:
 *
 *   PESO_REAL = relevancia_meta(0-100) × modulador_do_tipo
 *
 * IMPORTANTE — este service é PURO:
 *   - SEM I/O, SEM DB, SEM query business_id (é só aritmética).
 *   - Multi-tenant Tier 0 (ADR 0093) NÃO se aplica: não toca tabela alguma.
 *     Quem plugar este cálculo em retrieval/prod é que carrega o business_id
 *     scope — esta classe só recebe números já resolvidos.
 *   - Determinístico: mesma entrada → mesma saída. Testável sem fixtures de DB.
 *
 * Área A da Etapa 5 do IAOS — plugada no MeilisearchDriver::applyPesoReal SOB
 * feature-flag `copiloto.peso_real.retrieval_enabled` (kill-switch, OFF por
 * default: prod intocada). Ver ADR 0232 + ADR 0270 D-4 (decaimento no recall).
 *
 * Estado-da-arte que sustenta as fórmulas:
 *   - Iniciativas: WSJF / Cost of Delay (SAFe / Reinertsen) — value÷effort com
 *     time_criticality como componente de Cost of Delay.
 *   - Memórias: Generative Agents (recency exponencial × importance) +
 *     ADR 0195 (decay adaptativo) — generalizado pra qualquer memória.
 *   - Decisões: fitness-function / evergreen — perde peso por supersede, nunca
 *     por idade.
 *
 * @see memory/decisions/0232-modelo-peso-real-classificacao-por-meta.md
 */
class PesoRealService
{
    /**
     * Defaults de fallback hardcoded — usados quando config('copiloto.peso_real.*')
     * está ausente. Garante que o service NUNCA quebra por config faltando.
     *
     * KL-C1 (2026-06-12): espelho da tabela `copiloto.peso_real.lifecycle_mult`,
     * alinhada ao vocabulário REAL — status EN normalizado (accepted/proposed/
     * historical/superseded) + frontmatter canônico PT (aceito/proposto; lifecycle
     * ativo/arquivado/substituido/historical). Antes, só o vocabulário ADR 0232
     * existia e todo doc real caía no fallback 0.1 (ADR 0270 D-4 violada).
     */
    private const FALLBACK_LIFECYCLE_MULT = [
        // vigente — peso cheio
        'accepted'            => 1.0,
        'aceito'              => 1.0,
        'ativo'               => 1.0,
        'proposed'            => 1.0,
        'proposto'            => 1.0,
        // morto — decai por lifecycle (nunca por idade)
        'historical'          => 0.5,
        'superseded'          => 0.3,
        'substituido'         => 0.3,
        'arquivado'           => 0.3,
        // legacy ADR 0232 (back-compat com fatos antigos)
        'accepted-historical' => 0.8,
        'sunsetting'          => 0.4,
        'deprecated'          => 0.1,
    ];

    private const FALLBACK_HALF_LIFE = 60; // dias (ADR 0195)

    /**
     * Piso crítico (fração de relevancia_meta) aplicado a memórias que "protegem
     * cliente" — lição que evita erro que custa cliente NÃO decai abaixo deste
     * piso, por mais antiga que seja. Default 0.5 = nível HOT.
     */
    private const FALLBACK_PISO_CRITICO = 0.5;

    private const FALLBACK_SINAL = [
        'paga_reporta' => 1.0,
        'qualificado'  => 0.5,
        'hipotese'     => 0.2,
    ];

    private const FALLBACK_TIME_CRITICALITY = [
        'normal'     => 1.0,
        'compliance' => 1.5,
    ];

    /**
     * (a) DECISÃO / ADR — NÃO decai por tempo.
     *
     *   PESO_ADR = relevancia_meta × lifecycle_mult
     *
     * Decisão é evergreen: perde peso por supersede, nunca por idade. Lifecycle
     * desconhecido cai no fallback 'deprecated' (0.1) — conservador: item de
     * lifecycle não-mapeado não infla ranking.
     *
     * @param int    $relevanciaMeta 0-100 (clampado).
     * @param string $lifecycle      vigente (accepted|aceito|ativo|proposed|proposto) |
     *                               historical | superseded|substituido|arquivado |
     *                               legacy ADR 0232 (accepted-historical|sunsetting|deprecated)
     */
    public function pesoDecisao(int $relevanciaMeta, string $lifecycle): float
    {
        $rel = $this->clampRelevancia($relevanciaMeta);
        $tabela = $this->config('lifecycle_mult', self::FALLBACK_LIFECYCLE_MULT);

        // Lifecycle desconhecido → trata como deprecated (peso mínimo).
        $mult = $tabela[$lifecycle] ?? ($tabela['deprecated'] ?? 0.1);

        return $rel * (float) $mult;
    }

    /**
     * (b) MEMÓRIA / lição / fato / session — DECAI por tempo.
     *
     *   PESO_MEM = max(piso, relevancia_meta × exp(-dias/half_life)
     *                        × (1 + log10(recorrencia+1)/log10(6)))
     *
     * - Decay exponencial (Generative Agents / ADR 0195): quanto mais antiga,
     *   menor o peso.
     * - Bônus de recorrência: memória que reaparece N vezes ganha boost
     *   logarítmico (saturante — não explode).
     * - Floor crítico ($critica=true): memória que protege cliente pagante NUNCA
     *   cai abaixo do piso (fração de relevancia_meta), mesmo muito velha.
     *
     * @param int  $relevanciaMeta 0-100 (clampado).
     * @param int  $diasDesde      dias desde o evento (negativo tratado como 0).
     * @param bool $critica        true = aplica floor anti-decay (protege cliente).
     * @param int  $recorrencia    nº de vezes que a memória reapareceu (>=0).
     * @param int  $halfLife       meia-vida em dias (default config / 60).
     */
    public function pesoMemoria(
        int $relevanciaMeta,
        int $diasDesde,
        bool $critica = false,
        int $recorrencia = 0,
        int $halfLife = 60,
    ): float {
        $rel = $this->clampRelevancia($relevanciaMeta);
        $dias = max(0, $diasDesde);
        $rec = max(0, $recorrencia);

        // half_life: usa o argumento se != default explícito; senão config; senão 60.
        $hl = $halfLife > 0 ? $halfLife : (int) $this->config('half_life', self::FALLBACK_HALF_LIFE);
        if ($hl <= 0) {
            $hl = self::FALLBACK_HALF_LIFE;
        }

        $decay = exp(-$dias / $hl);
        // Boost de recorrência: log10(rec+1)/log10(6). Normalizado pra que
        // recorrencia=5 dê ~+1.0 (dobra). Saturante.
        $boost = 1.0 + (log10($rec + 1) / log10(6));

        $peso = $rel * $decay * $boost;

        if ($critica) {
            $fracao = (float) $this->config('piso_critico', self::FALLBACK_PISO_CRITICO);
            $piso = $rel * $fracao;
            $peso = max($piso, $peso);
        }

        return $peso;
    }

    /**
     * (c) INICIATIVA / módulo — ROI (WSJF / Cost of Delay).
     *
     *   PESO_INI = (receita_anual × sinal_cliente × time_criticality) ÷ esforço
     *
     * É o NORTE-ROI com time_criticality (Cost of Delay / WSJF) somado: NFe com
     * prazo SEFAZ pontua acima de módulo de igual valor sem urgência.
     *
     * Edge: esforço <= 0 → divisor protegido por 1.0 (evita divisão por zero;
     * trata como "esforço mínimo / desconhecido", retornando o numerador puro).
     *
     * Os fatores sinal_cliente e time_criticality são passados já resolvidos
     * (use sinalCliente()/timeCriticality() pra mapear rótulos → fator).
     *
     * @param float $receitaAnual   receita anual estimada (R$).
     * @param float $sinalCliente   1.0 paga+reporta · 0.5 qualificado · 0.2 hipótese (ADR 0105).
     * @param float $timeCriticality 1.0 normal · 1.5 prazo legal/compliance.
     * @param float $esforco        esforço estimado (dev-days ou pontos). >0.
     */
    public function pesoIniciativa(
        float $receitaAnual,
        float $sinalCliente,
        float $timeCriticality,
        float $esforco,
    ): float {
        $numerador = $receitaAnual * $sinalCliente * $timeCriticality;

        // Proteção divisão por zero (e esforço negativo, que não faz sentido).
        $divisor = $esforco > 0 ? $esforco : 1.0;

        return $numerador / $divisor;
    }

    /**
     * Mapeia rótulo de sinal de cliente → fator (ADR 0105).
     * Rótulo desconhecido → 'hipotese' (0.2, conservador).
     */
    public function sinalCliente(string $rotulo): float
    {
        $tabela = $this->config('sinal', self::FALLBACK_SINAL);

        return (float) ($tabela[$rotulo] ?? ($tabela['hipotese'] ?? 0.2));
    }

    /**
     * Mapeia rótulo de time_criticality → fator (Cost of Delay / WSJF).
     * Rótulo desconhecido → 'normal' (1.0).
     */
    public function timeCriticality(string $rotulo): float
    {
        $tabela = $this->config('time_criticality', self::FALLBACK_TIME_CRITICALITY);

        return (float) ($tabela[$rotulo] ?? ($tabela['normal'] ?? 1.0));
    }

    /**
     * relevancia_meta é 0-100 por definição (ADR 0232). Clampa pra blindar
     * entradas fora de faixa.
     */
    private function clampRelevancia(int $valor): float
    {
        return (float) max(0, min(100, $valor));
    }

    /**
     * Lê config('copiloto.peso_real.<chave>') com fallback hardcoded. Usa helper
     * config() do Laravel quando disponível; senão (service puro chamado fora do
     * framework) cai no default sem quebrar.
     */
    private function config(string $chave, mixed $fallback): mixed
    {
        if (! function_exists('config')) {
            return $fallback;
        }

        $valor = config("copiloto.peso_real.{$chave}");

        return $valor ?? $fallback;
    }
}
