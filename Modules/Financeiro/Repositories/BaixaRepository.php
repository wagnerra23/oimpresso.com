<?php

declare(strict_types=1);

namespace Modules\Financeiro\Repositories;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Repository — baixas (fin_titulo_baixas) append-only.
 *
 * Wave 18 RETRY D4 saturação Financeiro — granular além de TituloRepository.
 *
 * Consumers:
 *   - FluxoCaixaService (histórico 2d baixas realizadas)
 *   - FinanceiroHealthCommand (KPIs recebidos/pagos mês)
 *   - UnificadoService (KPI cockpit recebidos_mes/pagos_mes)
 *   - DashboardController.calcularKpis (centralizar para reuso)
 *
 * Multi-tenant Tier 0 (ADR 0093): businessId explícito + BusinessScope global
 * (defesa em profundidade). Tests biz=1 (ADR 0101) — NUNCA biz=4 ROTA LIVRE.
 *
 * Observability D9.a: spans `financeiro.baixa.repo.*`.
 *
 * @see Modules\Financeiro\Models\TituloBaixa
 * @see Modules\Financeiro\Repositories\TituloRepository (peer — títulos)
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §5 SoC
 */
class BaixaRepository
{
    /**
     * Lista paginada de baixas (recebimentos + pagamentos) com filtros canônicos.
     *
     * Filtros aceitos:
     *   tipo_titulo: 'receber'|'pagar' (filtra via relação titulo)
     *   data_de / data_ate: Y-m-d (data_baixa)
     *   conta_bancaria_id: int
     *   meio_pagamento: 'dinheiro'|'pix'|'cartao_credito'|'cheque'|'transferencia'|'outro'
     *   ignorar_estornos: bool (default true — estornos viram linha negativa, polui leitura)
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarPaginado(int $businessId, array $filtros = [], int $perPage = 50): LengthAwarePaginator
    {
        return OtelHelper::spanBiz('financeiro.baixa.repo.listar', function () use ($businessId, $filtros, $perPage) {
            return $this->aplicarFiltros($this->base($businessId), $filtros)
                ->with(['titulo:id,business_id,tipo,numero,cliente_descricao,categoria_id', 'titulo.categoria:id,nome'])
                ->orderBy('data_baixa', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($perPage);
        }, [
            'module'      => 'Financeiro',
            'op'          => 'baixa.repo.listar',
            'business_id' => $businessId,
        ]);
    }

    /**
     * Totais agregados por tipo + período (KPI cockpit "Recebido mês" / "Pago mês").
     *
     * @return array{count: int, total: float}
     */
    public function totaisPorTipoPeriodo(
        int $businessId,
        string $tipoTitulo,
        string $dataDe,
        string $dataAte,
    ): array {
        return OtelHelper::spanBiz('financeiro.baixa.repo.totais_periodo', function () use ($businessId, $tipoTitulo, $dataDe, $dataAte) {
            $q = $this->base($businessId)
                ->whereBetween('data_baixa', [$dataDe, $dataAte])
                ->whereNull('estorno_de_id') // ignora estornos no total
                ->whereHas('titulo', fn ($sub) => $sub->where('tipo', $tipoTitulo));

            return [
                'count' => (int) (clone $q)->count(),
                'total' => (float) (clone $q)->sum('valor_baixa'),
            ];
        }, [
            'module'      => 'Financeiro',
            'op'          => 'baixa.repo.totais_periodo',
            'business_id' => $businessId,
            'tipo_titulo' => $tipoTitulo,
        ]);
    }

    /**
     * Histórico recente (últimos N dias) — usado em FluxoCaixaService pra render
     * coluna "passado" da projeção 35d.
     *
     * @return Collection<int, TituloBaixa>
     */
    public function historicoRecente(int $businessId, int $diasAtras = 2): Collection
    {
        $de = now()->subDays($diasAtras)->toDateString();
        $ate = now()->subDay()->toDateString(); // exclui hoje (ainda em curso)

        return $this->base($businessId)
            ->whereBetween('data_baixa', [$de, $ate])
            ->whereNull('estorno_de_id')
            ->with([
                'titulo:id,business_id,tipo,numero,cliente_descricao,categoria_id',
                'titulo.categoria:id,nome',
            ])
            ->orderBy('data_baixa')
            ->get();
    }

    /**
     * Acha baixa por idempotency_key (ex: "tp_{transaction_payment_id}") — usado
     * por TituloAutoService::registrarPagamento pra evitar dupla baixa.
     */
    public function acharPorIdempotencyKey(int $businessId, string $key): ?TituloBaixa
    {
        return $this->base($businessId)
            ->where('idempotency_key', $key)
            ->first();
    }

    /**
     * Builder base — escopo explícito por business_id (defesa em profundidade).
     */
    private function base(int $businessId): Builder
    {
        return TituloBaixa::query()->where('business_id', $businessId);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $q, array $filtros): Builder
    {
        if (! empty($filtros['data_de'])) {
            $q->whereDate('data_baixa', '>=', $filtros['data_de']);
        }

        if (! empty($filtros['data_ate'])) {
            $q->whereDate('data_baixa', '<=', $filtros['data_ate']);
        }

        if (! empty($filtros['conta_bancaria_id'])) {
            $q->where('conta_bancaria_id', (int) $filtros['conta_bancaria_id']);
        }

        if (! empty($filtros['meio_pagamento'])) {
            $validos = ['dinheiro', 'pix', 'cartao_credito', 'cheque', 'transferencia', 'outro'];
            if (in_array($filtros['meio_pagamento'], $validos, true)) {
                $q->where('meio_pagamento', $filtros['meio_pagamento']);
            }
        }

        if (! empty($filtros['tipo_titulo']) && in_array($filtros['tipo_titulo'], ['receber', 'pagar'], true)) {
            $q->whereHas('titulo', fn ($sub) => $sub->where('tipo', $filtros['tipo_titulo']));
        }

        $ignorarEstornos = $filtros['ignorar_estornos'] ?? true;
        if ($ignorarEstornos) {
            $q->whereNull('estorno_de_id');
        }

        return $q;
    }
}
