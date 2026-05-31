<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * InsightsService — camada de CONSULTA read-only do histórico financeiro.
 *
 * Camada de dados do recurso "perguntar ao histórico financeiro" (ADR ARQ-0006).
 * Decisão: NÃO é RAG free-text — dado financeiro é estruturado, exato e Tier 0
 * crítico. Em vez disso, expomos TOOLS determinísticas read-only que um LLM
 * (Jana, em PR futuro) vai chamar via tool-calling pra traduzir pergunta em
 * linguagem natural → consulta escopada por business_id → resposta CITANDO os
 * registros-fonte (títulos/baixas). Esta PR entrega SÓ as tools (o data layer);
 * o wrapper Jana + UI vêm na PR2.
 *
 * Princípio P4 (ADR ARQ-0006): copiloto PROPÕE — esta camada só LÊ. Zero
 * mutação (sem create/update/delete). Toda agregação devolve, além da resposta,
 * um array `fontes` com os IDs de origem (título/baixa) pra a resposta futura
 * conseguir CITAR a fonte (fundação anti-alucinação — número sempre rastreável
 * a registros reais).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): `business_id` SEMPRE no 1º
 * argumento, fornecido pelo SERVIDOR — NUNCA por LLM/usuário. Todas as queries
 * forçam `->where('business_id', $businessId)` explícito (defesa em
 * profundidade), mesmo com o BusinessScope global já filtrando. Tests biz=1
 * (ADR 0101) — nunca biz=4 (ROTA LIVRE prod).
 *
 * Observability D9.a: cada método wrap em `OtelHelper::spanBiz('financeiro.
 * insights.<x>', ...)` (espelha UnificadoService/FluxoCaixaService/DreService).
 *
 * @see Modules\Financeiro\Models\Titulo                  (agingBucket / isVencido)
 * @see Modules\Financeiro\Models\TituloBaixa             (data_baixa / estorno_de_id)
 * @see Modules\Financeiro\Services\UnificadoService      (estilo de query KPI)
 * @see Modules\Financeiro\Repositories\BaixaRepository   (totaisPorTipoPeriodo)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class InsightsService
{
    /**
     * Buckets de aging — MESMOS thresholds (em dias) de Titulo::agingBucket().
     * Mantidos como fonte única pra a resposta casar com o que a UI mostra.
     */
    private const AGING_BUCKETS = ['em_dia', '<30', '30-60', '60-90', '90-180', '>180'];

    /**
     * "Quanto recebi entre X e Y?" — soma das baixas REAIS (não estornadas) de
     * títulos a-receber cuja data_baixa cai no intervalo [de, ate].
     *
     * Read-only. Devolve a resposta (valor + qtd) + `fontes` (IDs das baixas)
     * pra citação. Datas no formato 'Y-m-d' (inclusive nas duas pontas).
     *
     * @param  int     $businessId  Tier 0 (ADR 0093) — sempre via argumento, servidor.
     * @param  string  $de          Data inicial 'Y-m-d' (inclusive).
     * @param  string  $ate         Data final 'Y-m-d' (inclusive).
     *
     * @return array{
     *   valor: float,
     *   qtd: int,
     *   periodo: array{de: string, ate: string},
     *   fontes: array<int, int>
     * }
     */
    public function recebidoPorPeriodo(int $businessId, string $de, string $ate): array
    {
        return OtelHelper::spanBiz('financeiro.insights.recebido_por_periodo', function () use ($businessId, $de, $ate): array {
            return $this->recebidoPorPeriodoInternal($businessId, $de, $ate);
        }, [
            'module'      => 'Financeiro',
            'op'          => 'insights.recebido_por_periodo',
            'business_id' => $businessId,
        ]);
    }

    /**
     * @return array{valor: float, qtd: int, periodo: array{de: string, ate: string}, fontes: array<int, int>}
     */
    private function recebidoPorPeriodoInternal(int $businessId, string $de, string $ate): array
    {
        // Baixas de títulos a-receber, no período, ignorando estornos.
        // whereHas('titulo', tipo='receber') espelha BaixaRepository::totaisPorTipoPeriodo.
        $baixas = TituloBaixa::query()
            ->where('business_id', $businessId)                         // Tier 0 defesa em profundidade
            ->whereBetween('data_baixa', [$de, $ate])
            ->whereNull('estorno_de_id')                                // baixa estornada não conta
            ->whereHas('titulo', function ($sub) use ($businessId): void {
                $sub->where('business_id', $businessId)                 // Tier 0 também na relação
                    ->where('tipo', 'receber');
            })
            ->orderBy('data_baixa')
            ->get(['id', 'valor_baixa', 'data_baixa']);

        $valor = (float) $baixas->sum(fn (TituloBaixa $b): float => (float) $b->valor_baixa);
        $fontes = $baixas->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        return [
            'valor'   => round($valor, 2),
            'qtd'     => $baixas->count(),
            'periodo' => ['de' => $de, 'ate' => $ate],
            'fontes'  => $fontes,
        ];
    }

    /**
     * "Como está o aging dos recebíveis em aberto?" — distribui os títulos
     * abertos/parciais nos buckets de Titulo::agingBucket() (em_dia / <30 /
     * 30-60 / 60-90 / 90-180 / >180), com qtd + valor (valor_aberto) por bucket.
     *
     * Bucketing feito em PHP iterando os títulos pra reusar EXATAMENTE a regra
     * de Titulo::agingBucket()/isVencido() — garante que a resposta case com o
     * bucket que a UI atribui a cada título (sem divergência SQL vs model).
     *
     * Read-only. Devolve `fontes` (IDs dos títulos considerados) pra citação.
     *
     * @param  int          $businessId  Tier 0 (ADR 0093) — sempre via argumento.
     * @param  string       $tipo        'receber' (default) | 'pagar'.
     * @param  string|null  $hojeIso     'Y-m-d' p/ referência (testes determinísticos).
     *
     * @return array{
     *   buckets: array<string, array{qtd: int, valor: float}>,
     *   total: array{qtd: int, valor: float},
     *   fontes: array<int, int>
     * }
     */
    public function agingResumo(int $businessId, string $tipo = 'receber', ?string $hojeIso = null): array
    {
        return OtelHelper::spanBiz('financeiro.insights.aging_resumo', function () use ($businessId, $tipo, $hojeIso): array {
            return $this->agingResumoInternal($businessId, $tipo, $hojeIso);
        }, [
            'module'      => 'Financeiro',
            'op'          => 'insights.aging_resumo',
            'business_id' => $businessId,
            'tipo'        => $tipo,
        ]);
    }

    /**
     * @return array{buckets: array<string, array{qtd: int, valor: float}>, total: array{qtd: int, valor: float}, fontes: array<int, int>}
     */
    private function agingResumoInternal(int $businessId, string $tipo, ?string $hojeIso): array
    {
        $hoje = $hojeIso !== null
            ? CarbonImmutable::parse($hojeIso)->startOfDay()
            : CarbonImmutable::today();

        // Inicializa todos os buckets zerados (resposta estável p/ o LLM citar).
        $buckets = [];
        foreach (self::AGING_BUCKETS as $b) {
            $buckets[$b] = ['qtd' => 0, 'valor' => 0.0];
        }

        $titulos = Titulo::query()
            ->where('business_id', $businessId)                 // Tier 0 defesa em profundidade
            ->where('tipo', $tipo)
            ->whereIn('status', ['aberto', 'parcial'])          // só em aberto / parcial
            ->whereNull('deleted_at')
            ->orderBy('vencimento')
            ->get(['id', 'vencimento', 'valor_aberto']);

        $fontes = [];
        $totalQtd = 0;
        $totalValor = 0.0;

        foreach ($titulos as $titulo) {
            $bucket = $this->bucketDeAging($titulo->vencimento, $hoje);
            $valor = (float) $titulo->valor_aberto;

            $buckets[$bucket]['qtd']++;
            $buckets[$bucket]['valor'] = round($buckets[$bucket]['valor'] + $valor, 2);

            $totalQtd++;
            $totalValor += $valor;
            $fontes[] = (int) $titulo->id;
        }

        return [
            'buckets' => $buckets,
            'total'   => ['qtd' => $totalQtd, 'valor' => round($totalValor, 2)],
            'fontes'  => $fontes,
        ];
    }

    /**
     * Replica a regra de Titulo::agingBucket() (sem depender de now() global,
     * pra ser determinístico em teste). vencimento futuro/hoje = 'em_dia';
     * senão, classifica pelos MESMOS thresholds em dias de atraso.
     *
     * Nota Carbon 3: diffInDays() é SINALIZADO por default (negativo p/ data
     * passada). Passamos o flag absoluto (2º arg true) — convenção do codebase
     * (ex: NfeHealthCommand, ReopenJobSheetRequest) — pra obter a contagem
     * POSITIVA de dias de atraso que os thresholds esperam.
     */
    private function bucketDeAging(?\DateTimeInterface $vencimento, CarbonImmutable $hoje): string
    {
        if ($vencimento === null) {
            return 'em_dia';
        }

        $venc = CarbonImmutable::parse($vencimento->format('Y-m-d'))->startOfDay();

        if ($venc->greaterThanOrEqualTo($hoje)) {
            return 'em_dia';
        }

        // Dias de atraso (positivo) — thresholds idênticos a Titulo::agingBucket().
        $dias = (int) $hoje->diffInDays($venc, true);

        return match (true) {
            $dias < 30  => '<30',
            $dias < 60  => '30-60',
            $dias < 90  => '60-90',
            $dias < 180 => '90-180',
            default     => '>180',
        };
    }

    /**
     * "Esse cliente costuma atrasar?" — perfil de pontualidade de UMA
     * contraparte: quantos títulos tem, quantos atrasaram, % de atraso e atraso
     * médio (em dias). "Atrasado" = título quitado com data_baixa > vencimento
     * OU título ainda em aberto/parcial já vencido (Titulo::isVencido()).
     *
     * Match por `cliente_descricao LIKE %contraparte%` (mesma estratégia de
     * busca textual do TituloRepository::aplicarFiltros).
     *
     * Read-only. Devolve `fontes` (IDs dos títulos da contraparte) pra citação.
     *
     * @param  int          $businessId   Tier 0 (ADR 0093) — sempre via argumento.
     * @param  string       $contraparte  Trecho do nome (cliente_descricao LIKE).
     * @param  string|null  $hojeIso      'Y-m-d' p/ referência (testes determinísticos).
     *
     * @return array{
     *   contraparte: string,
     *   qtd_titulos: int,
     *   qtd_atrasados: int,
     *   pct_atraso: float,
     *   dias_medio_atraso: float,
     *   fontes: array<int, int>
     * }
     */
    public function historicoAtrasoContraparte(int $businessId, string $contraparte, ?string $hojeIso = null): array
    {
        return OtelHelper::spanBiz('financeiro.insights.historico_atraso_contraparte', function () use ($businessId, $contraparte, $hojeIso): array {
            return $this->historicoAtrasoContraparteInternal($businessId, $contraparte, $hojeIso);
        }, [
            'module'      => 'Financeiro',
            'op'          => 'insights.historico_atraso_contraparte',
            'business_id' => $businessId,
        ]);
    }

    /**
     * @return array{contraparte: string, qtd_titulos: int, qtd_atrasados: int, pct_atraso: float, dias_medio_atraso: float, fontes: array<int, int>}
     */
    private function historicoAtrasoContraparteInternal(int $businessId, string $contraparte, ?string $hojeIso): array
    {
        $hoje = $hojeIso !== null
            ? CarbonImmutable::parse($hojeIso)->startOfDay()
            : CarbonImmutable::today();

        $termo = '%' . trim($contraparte) . '%';

        // Títulos da contraparte + última baixa NÃO estornada (pra medir atraso
        // de quitação). eager-load escopado por business_id (Tier 0 na relação).
        $titulos = Titulo::query()
            ->where('business_id', $businessId)                 // Tier 0 defesa em profundidade
            ->where('cliente_descricao', 'like', $termo)
            ->whereIn('status', ['aberto', 'parcial', 'quitado'])   // ignora cancelado
            ->whereNull('deleted_at')
            ->with(['baixas' => function ($sub) use ($businessId): void {
                $sub->where('business_id', $businessId)         // Tier 0 também na relação
                    ->whereNull('estorno_de_id')
                    ->orderByDesc('data_baixa');
            }])
            ->orderBy('id')
            ->get(['id', 'status', 'vencimento', 'cliente_descricao']);

        $qtdTitulos = $titulos->count();
        $qtdAtrasados = 0;
        $somaDiasAtraso = 0;
        $fontes = [];

        foreach ($titulos as $titulo) {
            $fontes[] = (int) $titulo->id;

            $diasAtraso = $this->diasAtrasoTitulo($titulo, $hoje);
            if ($diasAtraso > 0) {
                $qtdAtrasados++;
                $somaDiasAtraso += $diasAtraso;
            }
        }

        $pctAtraso = $qtdTitulos > 0
            ? round(($qtdAtrasados / $qtdTitulos) * 100.0, 2)
            : 0.0;

        $diasMedioAtraso = $qtdAtrasados > 0
            ? round($somaDiasAtraso / $qtdAtrasados, 2)
            : 0.0;

        return [
            'contraparte'       => $contraparte,
            'qtd_titulos'       => $qtdTitulos,
            'qtd_atrasados'     => $qtdAtrasados,
            'pct_atraso'        => $pctAtraso,
            'dias_medio_atraso' => $diasMedioAtraso,
            'fontes'            => $fontes,
        ];
    }

    /**
     * Dias de atraso de UM título:
     *  - quitado  → data_baixa (última não estornada) − vencimento, se positivo
     *  - aberto/parcial vencido → hoje − vencimento (Titulo::isVencido())
     *  - caso contrário → 0 (em dia)
     */
    private function diasAtrasoTitulo(Titulo $titulo, CarbonImmutable $hoje): int
    {
        if ($titulo->vencimento === null) {
            return 0;
        }

        $venc = CarbonImmutable::parse($titulo->vencimento->format('Y-m-d'))->startOfDay();

        if ($titulo->status === 'quitado') {
            $ultimaBaixa = $titulo->baixas->first();   // já ordenado data_baixa desc
            if ($ultimaBaixa === null || $ultimaBaixa->data_baixa === null) {
                return 0;
            }
            $pago = CarbonImmutable::parse($ultimaBaixa->data_baixa->format('Y-m-d'))->startOfDay();

            // 2º arg true = absoluto (Carbon 3 é sinalizado por default).
            return $pago->greaterThan($venc) ? (int) $venc->diffInDays($pago, true) : 0;
        }

        // aberto / parcial: atrasado se vencimento já passou.
        return $venc->lessThan($hoje) ? (int) $venc->diffInDays($hoje, true) : 0;
    }

    /**
     * "Quanto recebi por canal/origem no período?" — recebido (baixas reais de
     * títulos a-receber) agrupado pela `origem` do título (manual / venda /
     * compra / despesa / recurring / folha — o "canal" comercial).
     *
     * Read-only. Devolve `por_canal` ordenado por valor desc + `fontes` (IDs das
     * baixas somadas) pra citação. Datas 'Y-m-d' inclusivas.
     *
     * @param  int     $businessId  Tier 0 (ADR 0093) — sempre via argumento.
     * @param  string  $de          Data inicial 'Y-m-d' (inclusive).
     * @param  string  $ate         Data final 'Y-m-d' (inclusive).
     *
     * @return array{
     *   periodo: array{de: string, ate: string},
     *   total: float,
     *   por_canal: array<int, array{canal: string, valor: float, qtd: int}>,
     *   fontes: array<int, int>
     * }
     */
    public function totaisPorCanal(int $businessId, string $de, string $ate): array
    {
        return OtelHelper::spanBiz('financeiro.insights.totais_por_canal', function () use ($businessId, $de, $ate): array {
            return $this->totaisPorCanalInternal($businessId, $de, $ate);
        }, [
            'module'      => 'Financeiro',
            'op'          => 'insights.totais_por_canal',
            'business_id' => $businessId,
        ]);
    }

    /**
     * @return array{periodo: array{de: string, ate: string}, total: float, por_canal: array<int, array{canal: string, valor: float, qtd: int}>, fontes: array<int, int>}
     */
    private function totaisPorCanalInternal(int $businessId, string $de, string $ate): array
    {
        // Baixas a-receber no período (não estornadas) + origem do título.
        // eager-load título escopado por business_id (Tier 0 na relação).
        $baixas = TituloBaixa::query()
            ->where('business_id', $businessId)                 // Tier 0 defesa em profundidade
            ->whereBetween('data_baixa', [$de, $ate])
            ->whereNull('estorno_de_id')
            ->whereHas('titulo', function ($sub) use ($businessId): void {
                $sub->where('business_id', $businessId)         // Tier 0 também na relação
                    ->where('tipo', 'receber');
            })
            ->with(['titulo' => function ($sub) use ($businessId): void {
                $sub->where('business_id', $businessId)
                    ->select('id', 'business_id', 'origem');
            }])
            ->orderBy('data_baixa')
            ->get(['id', 'titulo_id', 'valor_baixa', 'data_baixa']);

        $agrupado = []; // canal => ['valor' => float, 'qtd' => int]
        $total = 0.0;
        $fontes = [];

        foreach ($baixas as $baixa) {
            $canal = (string) ($baixa->titulo?->origem ?? 'desconhecido');
            $valor = (float) $baixa->valor_baixa;

            if (! isset($agrupado[$canal])) {
                $agrupado[$canal] = ['valor' => 0.0, 'qtd' => 0];
            }
            $agrupado[$canal]['valor'] += $valor;
            $agrupado[$canal]['qtd']++;

            $total += $valor;
            $fontes[] = (int) $baixa->id;
        }

        // Materializa lista ordenada por valor desc (mais relevante primeiro).
        $porCanal = [];
        foreach ($agrupado as $canal => $dados) {
            $porCanal[] = [
                'canal' => $canal,
                'valor' => round($dados['valor'], 2),
                'qtd'   => $dados['qtd'],
            ];
        }
        usort($porCanal, fn (array $a, array $b): int => $b['valor'] <=> $a['valor']);

        return [
            'periodo'   => ['de' => $de, 'ate' => $ate],
            'total'     => round($total, 2),
            'por_canal' => $porCanal,
            'fontes'    => $fontes,
        ];
    }
}
