<?php

namespace Modules\ComunicacaoVisual\Services;

use InvalidArgumentException;
use Modules\ComunicacaoVisual\Entities\Acabamento;
use Modules\ComunicacaoVisual\Entities\InstalacaoCatalogo;
use Modules\ComunicacaoVisual\Entities\Material;
use Modules\ComunicacaoVisual\Entities\Substrato;

/**
 * OrcamentoCalculator — cálculo authoritative server-side de orçamentos de comunicação visual.
 *
 * US-COMVIS-001: backend é a fonte de verdade. Valores calculados pelo frontend
 * (area_m2, subtotal, total) são SEMPRE descartados e recalculados aqui.
 *
 * Camadas de cálculo (composáveis — input minimal, output completo):
 *
 * Item-level:
 *   area_m2  = largura_m × altura_m × quantidade               (round 3 PHP_ROUND_HALF_UP)
 *   emenda   = (n_tiras - 1) × lado_menor × qtd × custo_solda  (se largura ou altura > plotter)
 *              n_tiras = CEILING(lado_maior / largura_plotter_m default 1,60)
 *   area_cobrar = MAX(area_m2, substrato.minimo_m2)            (regra Calcgraf/Mubisys)
 *   subtotal_substrato = area_cobrar × preco_unitario_m2       (round 2)
 *   custo_acabamentos = SUM por tipo (m_linear×perímetro, m2×área, unitario×qtd, fixo)
 *   subtotal_item = subtotal_substrato + emenda + acabamentos
 *
 * Header-level:
 *   subtotal       = SUM(item.subtotal)
 *   custo_instal   = catalogo? (preco_base + área×preco_m2 + km×preco_km) : custo_instalacao flat
 *   alertas[]      = NR-35 (instalação >2m sem ART/ASO/treinamento) + outros
 *   total          = subtotal - desconto + extras + custo_instalacao + custo_entrega
 *
 * Resolução de preço m² (ordem de prioridade):
 *   1. input.preco_unitario_m2 (override operador — wins always)
 *   2. substrato.preco_venda_m2 (substrato_id passado — canon novo cv_substratos)
 *   3. material.preco_venda_m2  (material_id passado — legacy comvis_materiais)
 *   4. throw InvalidArgumentException
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * lookup de Substrato/Material/Acabamento/InstalacaoCatalogo via Model com global scope ativo
 * (filtra business_id da sessão automaticamente).
 *
 * Backward-compat: payload v1 (sem substrato_id/acabamentos/instalacao_catalogo_id) continua
 * funcionando idêntico ao comportamento Sprint 1 — os campos novos são opcionais.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 * @see memory/requisitos/ComunicacaoVisual/ROADMAP.md Fase 2
 * @see .claude/agents/comunicacao-visual-expert.md §4 fórmula canônica
 */
class OrcamentoCalculator
{
    /** Largura útil padrão da maioria dos plotters eco-solvente/latex (Roland VG, Mimaki JV) */
    public const LARGURA_PLOTTER_DEFAULT_M = 1.60;

    /** Custo padrão de solda térmica m linear (média mercado 2026 — fallback se sem catálogo) */
    public const CUSTO_SOLDA_M_DEFAULT = 12.00;

    /** Altura limiar NR-35 (Anexo I Portaria 3.214/78 c/ NR-35 atualizada 2022) */
    public const NR35_ALTURA_LIMIAR_M = 2.00;

    /**
     * Calcula orçamento completo a partir do payload validado.
     *
     * @param  array{
     *   data_emissao: string,
     *   data_validade?: string|null,
     *   contato_id?: int|null,
     *   vendedor_id?: int|null,
     *   desconto?: float,
     *   extras?: float,
     *   custo_instalacao?: float,
     *   custo_entrega?: float,
     *   observacoes?: string|null,
     *   largura_plotter_m?: float,
     *   custo_solda_m?: float,
     *   instalacao_catalogo_id?: int|null,
     *   altura_instalacao_m?: float|null,
     *   distancia_km?: float|null,
     *   art_id?: int|null,
     *   nr35_validade_instalador?: string|null,
     *   aso_validade_instalador?: string|null,
     *   itens: array<int, array{
     *     material_id?: int|null,
     *     substrato_id?: int|null,
     *     descricao: string,
     *     largura_m: float,
     *     altura_m: float,
     *     quantidade: int,
     *     preco_unitario_m2?: float|null,
     *     acabamentos?: array<int, array{catalogo_id?: int|null, descricao?: string, tipo?: string, preco?: float, quantidade?: int}>,
     *     observacoes?: string|null,
     *   }>
     * } $payload
     * @return array Resultado authoritative com todos os campos calculados + alertas
     * @throws InvalidArgumentException Se validação de negócio falhar
     */
    public function calcular(array $payload): array
    {
        $desconto         = $this->round2($payload['desconto']          ?? 0);
        $extras           = $this->round2($payload['extras']            ?? 0);
        $custoInstFlat    = $this->round2($payload['custo_instalacao']  ?? 0);
        $custoEntrega     = $this->round2($payload['custo_entrega']     ?? 0);
        $larguraPlotter   = (float) ($payload['largura_plotter_m']      ?? self::LARGURA_PLOTTER_DEFAULT_M);
        $custoSoldaM      = $this->round2($payload['custo_solda_m']     ?? self::CUSTO_SOLDA_M_DEFAULT);
        // Emenda é OPT-IN — backward compat com Sprint 1 (testes legacy assumem 0 emenda).
        // Setar calcular_emenda=true ativa cálculo automático banner large-format >1,60m.
        $calcularEmenda   = (bool) ($payload['calcular_emenda']         ?? false);

        $itensCalculados = [];
        $subtotalGeral   = 0.0;
        $areaTotalM2     = 0.0;

        foreach ($payload['itens'] as $indice => $item) {
            $itensCalculados[] = $calculado = $this->calcularItem(
                $item,
                $indice,
                $larguraPlotter,
                $custoSoldaM,
                $calcularEmenda
            );
            $subtotalGeral += $calculado['subtotal'];
            $areaTotalM2   += $calculado['area_m2'];
        }

        $subtotalGeral = $this->round2($subtotalGeral);
        $areaTotalM2   = round($areaTotalM2, 3, PHP_ROUND_HALF_UP);

        // Instalação: catálogo se passado, senão valor flat legacy
        $instalacaoCalculo = $this->calcularInstalacao($payload, $areaTotalM2, $custoInstFlat);
        $custoInstalacao   = $instalacaoCalculo['custo'];
        $alertas           = $instalacaoCalculo['alertas'];

        $total = $this->round2(
            $subtotalGeral - $desconto + $extras + $custoInstalacao + $custoEntrega
        );

        return [
            'data_emissao'           => $payload['data_emissao'],
            'data_validade'          => $payload['data_validade']  ?? null,
            'contato_id'             => $payload['contato_id']     ?? null,
            'vendedor_id'            => $payload['vendedor_id']    ?? null,
            'observacoes'            => $payload['observacoes']    ?? null,
            'subtotal'               => $subtotalGeral,
            'desconto'               => $desconto,
            'extras'                 => $extras,
            'custo_instalacao'       => $custoInstalacao,
            'custo_entrega'          => $custoEntrega,
            'area_total_m2'          => $areaTotalM2,
            'instalacao_breakdown'   => $instalacaoCalculo['breakdown'],
            'alertas'                => $alertas,
            'total'                  => $total,
            'itens'                  => $itensCalculados,
        ];
    }

    /**
     * Calcula um item individual: area_m2 + emenda + acabamento + subtotal.
     *
     * @throws InvalidArgumentException
     */
    private function calcularItem(
        array $item,
        int $indice,
        float $larguraPlotter,
        float $custoSoldaM,
        bool $calcularEmenda
    ): array {
        $largura = (float) ($item['largura_m']  ?? 0);
        $altura  = (float) ($item['altura_m']   ?? 0);
        $qtd     = (int)   ($item['quantidade'] ?? 1);

        $this->validarDimensoesItem($largura, $altura, $qtd, $indice);

        // area_m2 base
        $areaUnitaria = round($largura * $altura, 3, PHP_ROUND_HALF_UP);
        $areaTotal    = round($areaUnitaria * $qtd, 3, PHP_ROUND_HALF_UP);

        // Resolve substrato (se passado) — usado pra preço default + minimo_m2 + NCM/CFOP/CSOSN
        $substrato = $this->resolverSubstrato($item, $indice);

        // Preço m² (override > substrato > material > throw)
        $precoUnitario = $this->resolverPreco($item, $substrato, $indice);

        // Regra área mínima do substrato (Calcgraf/Mubisys padrão setor)
        $minimoM2  = $substrato !== null && $substrato->minimo_m2 !== null
            ? (float) $substrato->minimo_m2
            : 0.0;
        $areaCobrar = $minimoM2 > 0 && $areaUnitaria < $minimoM2
            ? round($minimoM2 * $qtd, 3, PHP_ROUND_HALF_UP)
            : $areaTotal;
        $aplicouMinimo = $areaCobrar > $areaTotal;

        $subtotalSubstrato = $this->round2($areaCobrar * $precoUnitario);

        // Emenda banner large-format (opt-in via payload.calcular_emenda)
        $emenda = $calcularEmenda
            ? $this->calcularEmenda($largura, $altura, $qtd, $larguraPlotter, $custoSoldaM)
            : ['n_tiras' => 1, 'lado_maior_m' => max($largura, $altura), 'lado_menor_m' => min($largura, $altura), 'custo' => 0.0];

        // Acabamentos catalogados (perímetro × m_linear, área × m2, qtd × unitario, fixo)
        $acabamentos = $this->calcularAcabamentos(
            $item['acabamentos'] ?? [],
            $areaCobrar,
            $largura,
            $altura,
            $qtd,
            $indice
        );

        $subtotalItem = $this->round2(
            $subtotalSubstrato + $emenda['custo'] + $acabamentos['custo_total']
        );

        return [
            'material_id'         => $item['material_id']  ?? null,
            'substrato_id'        => $item['substrato_id'] ?? null,
            'descricao'           => $item['descricao'],
            'largura_m'           => $largura,
            'altura_m'            => $altura,
            'quantidade'          => $qtd,
            'observacoes'         => $item['observacoes']  ?? null,
            'area_m2'             => $areaTotal,
            'area_cobrar_m2'      => $areaCobrar,
            'aplicou_minimo_m2'   => $aplicouMinimo,
            'preco_unitario_m2'   => $precoUnitario,
            'subtotal_substrato'  => $subtotalSubstrato,
            'emenda'              => $emenda,
            'acabamentos'         => $acabamentos['itens'],
            'custo_acabamentos'   => $acabamentos['custo_total'],
            'subtotal'            => $subtotalItem,
            // Tributação inferida do substrato (snapshot momento orçamento)
            'ncm'                 => $substrato?->ncm,
            'cfop_padrao'         => $substrato?->cfop_padrao,
            'csosn_padrao'        => $substrato?->csosn_padrao,
        ];
    }

    /**
     * Emenda banner large-format: peça > largura útil plotter exige solda térmica.
     *
     * Regra (state-of-art Calcgraf/Mubisys 2026):
     *   - Plotter padrão 1,60m largura útil (eco-solvente Roland/Mimaki/HP Latex)
     *   - Lado maior > 1,60m → divide em N tiras (CEILING(lado_maior/1,60))
     *   - Emenda costura/solda térmica = (N-1) × lado_menor × custo_solda_m
     *   - Multiplica por qtd peças (cada peça da quantidade leva emenda)
     *
     * @return array{n_tiras: int, lado_maior_m: float, lado_menor_m: float, custo: float}
     */
    private function calcularEmenda(
        float $largura,
        float $altura,
        int $qtd,
        float $larguraPlotter,
        float $custoSoldaM
    ): array {
        $ladoMaior = max($largura, $altura);
        $ladoMenor = min($largura, $altura);

        if ($ladoMaior <= $larguraPlotter || $larguraPlotter <= 0) {
            return [
                'n_tiras'      => 1,
                'lado_maior_m' => $ladoMaior,
                'lado_menor_m' => $ladoMenor,
                'custo'        => 0.0,
            ];
        }

        // CEILING via intval+ceil — PHP_INT_MAX é mais que suficiente
        $nTiras = (int) ceil($ladoMaior / $larguraPlotter);
        // (N-1) emendas, cada emenda atravessa lado_menor
        $custo = $this->round2(($nTiras - 1) * $ladoMenor * $qtd * $custoSoldaM);

        return [
            'n_tiras'      => $nTiras,
            'lado_maior_m' => $ladoMaior,
            'lado_menor_m' => $ladoMenor,
            'custo'        => $custo,
        ];
    }

    /**
     * Acabamentos: resolve cada item via catalog (cv_acabamentos) e calcula por tipo.
     *
     * Tipos:
     *   - m_linear: preço × perímetro (bainha, costura, reforço borda)
     *   - unitario: preço × quantidade especificada (ilhós, perfuração)
     *               Se quantidade não especificada, default = CEILING(perímetro / 0,5m)
     *   - m2:       preço × área cobrada (laminação, verniz, hot-stamp)
     *   - fixo:     preço (uma vez, independente de dimensão — taxa setup arte)
     *
     * Multi-tenant Tier 0: Acabamento::find usa global scope filtrando business_id.
     *
     * @param  array<int, array> $acabamentos
     * @return array{itens: array, custo_total: float}
     * @throws InvalidArgumentException
     */
    private function calcularAcabamentos(
        array $acabamentos,
        float $areaCobrar,
        float $largura,
        float $altura,
        int $qtd,
        int $indiceItem
    ): array {
        if (empty($acabamentos)) {
            return ['itens' => [], 'custo_total' => 0.0];
        }

        $perimetro = 2 * ($largura + $altura);
        $resultados = [];
        $totalAcab = 0.0;

        foreach ($acabamentos as $idxAcab => $acab) {
            // Resolve catalog ou usa inline (catalogo_id wins)
            $catalogo = null;
            if (! empty($acab['catalogo_id'])) {
                $catalogo = Acabamento::find((int) $acab['catalogo_id']);
                if ($catalogo === null) {
                    throw new InvalidArgumentException(
                        "Item #{$indiceItem} acabamento #{$idxAcab}: catalogo_id {$acab['catalogo_id']} não encontrado neste business."
                    );
                }
                $nome  = $catalogo->nome;
                $tipo  = $catalogo->tipo;
                $preco = (float) $catalogo->preco;
            } else {
                $nome  = (string) ($acab['descricao'] ?? '');
                $tipo  = (string) ($acab['tipo'] ?? '');
                $preco = (float) ($acab['preco'] ?? 0);
                if ($nome === '' || ! in_array($tipo, ['m_linear', 'unitario', 'm2', 'fixo'], true) || $preco <= 0) {
                    throw new InvalidArgumentException(
                        "Item #{$indiceItem} acabamento #{$idxAcab}: passar catalogo_id OU (descricao + tipo m_linear|unitario|m2|fixo + preco>0)."
                    );
                }
            }

            $qtdAcab = isset($acab['quantidade']) ? (int) $acab['quantidade'] : null;

            switch ($tipo) {
                case 'm_linear':
                    // Perímetro × preço × qtd peças
                    $custo = $this->round2($perimetro * $preco * $qtd);
                    $qtdAplicada = round($perimetro * $qtd, 3, PHP_ROUND_HALF_UP);
                    break;

                case 'unitario':
                    // qtd_acabamento × preço × qtd peças
                    // Default qtd_acabamento = perímetro / 0,5m (ilhós a cada 50cm) ceil
                    $qtdUnit = $qtdAcab ?? (int) ceil($perimetro / 0.5);
                    $custo = $this->round2($qtdUnit * $preco * $qtd);
                    $qtdAplicada = $qtdUnit * $qtd;
                    break;

                case 'm2':
                    // Área cobrar × preço (área já inclui qtd)
                    $custo = $this->round2($areaCobrar * $preco);
                    $qtdAplicada = $areaCobrar;
                    break;

                case 'fixo':
                    // Taxa one-shot
                    $custo = $this->round2($preco);
                    $qtdAplicada = 1;
                    break;

                default:
                    throw new InvalidArgumentException(
                        "Item #{$indiceItem} acabamento #{$idxAcab}: tipo '{$tipo}' inválido (use m_linear|unitario|m2|fixo)."
                    );
            }

            $totalAcab += $custo;
            $resultados[] = [
                'catalogo_id'    => $catalogo?->id,
                'nome'           => $nome,
                'tipo'           => $tipo,
                'preco_unitario' => $this->round2($preco),
                'qtd_aplicada'   => $qtdAplicada,
                'custo'          => $custo,
            ];
        }

        return ['itens' => $resultados, 'custo_total' => $this->round2($totalAcab)];
    }

    /**
     * Instalação: catálogo (preco_base + área×preco_m2 + km×preco_km) ou flat legacy.
     *
     * NR-35 enforcement: se catálogo.exige_nr35 + altura > 2m, valida documentos:
     *   - art_id (Anotação Responsabilidade Técnica CREA)
     *   - nr35_validade_instalador (treinamento NR-35 vigente)
     *   - aso_validade_instalador (ASO Trabalho em Altura)
     * Documentos ausentes => alerta no array (soft-warn, não bloqueia cálculo).
     *
     * @return array{custo: float, breakdown: array|null, alertas: array<string>}
     */
    private function calcularInstalacao(array $payload, float $areaTotal, float $custoFlat): array
    {
        $alertas = [];

        if (empty($payload['instalacao_catalogo_id'])) {
            // Sem catálogo: usa valor flat legacy
            return [
                'custo'     => $custoFlat,
                'breakdown' => null,
                'alertas'   => $alertas,
            ];
        }

        $catalogo = InstalacaoCatalogo::find((int) $payload['instalacao_catalogo_id']);
        if ($catalogo === null) {
            throw new InvalidArgumentException(
                "instalacao_catalogo_id {$payload['instalacao_catalogo_id']} não encontrado neste business."
            );
        }

        $distKm  = (float) ($payload['distancia_km'] ?? 0);
        $altura  = isset($payload['altura_instalacao_m'])
            ? (float) $payload['altura_instalacao_m']
            : null;

        $base   = (float) $catalogo->preco_base;
        $perM2  = (float) $catalogo->preco_m2;
        $perKm  = (float) $catalogo->preco_km;

        $custoBase    = $this->round2($base);
        $custoArea    = $this->round2($areaTotal * $perM2);
        $custoDeslo   = $this->round2($distKm * $perKm);
        $custoTotal   = $this->round2($custoBase + $custoArea + $custoDeslo);

        // NR-35 enforcement (alertas soft)
        if ($catalogo->exige_nr35 || ($altura !== null && $altura > self::NR35_ALTURA_LIMIAR_M)) {
            if ($altura !== null && $altura > self::NR35_ALTURA_LIMIAR_M) {
                if (empty($payload['art_id'])) {
                    $alertas[] = "NR-35: instalação a {$altura}m exige ART (Anotação Responsabilidade Técnica) — campo art_id não preenchido.";
                }
                if (empty($payload['nr35_validade_instalador'])) {
                    $alertas[] = "NR-35: instalação >2m exige treinamento NR-35 vigente — campo nr35_validade_instalador não preenchido.";
                }
                if (empty($payload['aso_validade_instalador'])) {
                    $alertas[] = "NR-35: instalação >2m exige ASO Trabalho em Altura vigente — campo aso_validade_instalador não preenchido.";
                }
            } elseif ($altura === null) {
                $alertas[] = "NR-35: instalação '{$catalogo->nome}' marcada exige_nr35 — informe altura_instalacao_m pra validar documentos.";
            }
        }

        return [
            'custo'     => $custoTotal,
            'breakdown' => [
                'catalogo_id'        => $catalogo->id,
                'nome'               => $catalogo->nome,
                'preco_base'         => $custoBase,
                'preco_m2_aplicado'  => $custoArea,
                'preco_km_aplicado'  => $custoDeslo,
                'area_total_m2'      => $areaTotal,
                'distancia_km'       => $distKm,
                'altura_m'           => $altura,
                'exige_nr35'         => (bool) $catalogo->exige_nr35,
            ],
            'alertas' => $alertas,
        ];
    }

    /**
     * Resolve Substrato a partir do item (Tier 0 global scope automático).
     *
     * @throws InvalidArgumentException
     */
    private function resolverSubstrato(array $item, int $indice): ?Substrato
    {
        if (empty($item['substrato_id'])) {
            return null;
        }
        $sub = Substrato::find((int) $item['substrato_id']);
        if ($sub === null) {
            throw new InvalidArgumentException(
                "Item #{$indice}: substrato_id {$item['substrato_id']} não encontrado ou não pertence a este business."
            );
        }
        return $sub;
    }

    /**
     * Resolve preco_unitario_m2 com prioridade:
     *   1. input override (preco_unitario_m2 passado no payload)
     *   2. substrato.preco_venda_m2 (canon novo)
     *   3. material.preco_venda_m2 (legacy)
     *   4. throw — sem preço disponível
     *
     * @throws InvalidArgumentException
     */
    private function resolverPreco(array $item, ?Substrato $substrato, int $indice): float
    {
        // Prioridade 1: override explícito
        if (isset($item['preco_unitario_m2']) && $item['preco_unitario_m2'] !== null) {
            $preco = (float) $item['preco_unitario_m2'];
            if ($preco <= 0) {
                throw new InvalidArgumentException(
                    "Item #{$indice}: preco_unitario_m2 deve ser positivo (recebido: {$preco})."
                );
            }
            return $this->round2($preco);
        }

        // Prioridade 2: substrato (canon)
        if ($substrato !== null) {
            $preco = (float) $substrato->preco_venda_m2;
            if ($preco <= 0) {
                throw new InvalidArgumentException(
                    "Item #{$indice}: substrato '{$substrato->nome}' não possui preco_venda_m2 configurado."
                );
            }
            return $this->round2($preco);
        }

        // Prioridade 3: material legacy
        if (! empty($item['material_id'])) {
            $material = Material::find((int) $item['material_id']);
            if ($material === null) {
                throw new InvalidArgumentException(
                    "Item #{$indice}: material_id {$item['material_id']} não encontrado ou não pertence a este business."
                );
            }
            $preco = (float) $material->preco_venda_m2;
            if ($preco <= 0) {
                throw new InvalidArgumentException(
                    "Item #{$indice}: material '{$material->nome}' não possui preco_venda_m2 configurado."
                );
            }
            return $this->round2($preco);
        }

        // Prioridade 4: throw
        throw new InvalidArgumentException(
            "Item #{$indice}: preco_unitario_m2 é obrigatório quando substrato_id e material_id não são informados."
        );
    }

    private function validarDimensoesItem(float $largura, float $altura, int $qtd, int $indice): void
    {
        if ($largura <= 0) {
            throw new InvalidArgumentException(
                "Item #{$indice}: largura_m deve ser maior que zero (recebido: {$largura})."
            );
        }
        if ($altura <= 0) {
            throw new InvalidArgumentException(
                "Item #{$indice}: altura_m deve ser maior que zero (recebido: {$altura})."
            );
        }
        if ($qtd < 1) {
            throw new InvalidArgumentException(
                "Item #{$indice}: quantidade deve ser pelo menos 1 (recebido: {$qtd})."
            );
        }
    }

    private function round2(float $v): float
    {
        return round($v, 2, PHP_ROUND_HALF_UP);
    }
}
