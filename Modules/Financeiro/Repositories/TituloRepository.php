<?php

declare(strict_types=1);

namespace Modules\Financeiro\Repositories;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Financeiro\Models\Titulo;

/**
 * Repository — agrega queries comuns de Titulo (fin_titulos) num único ponto
 * testável. Controllers passam a injetar TituloRepository em vez de chamar
 * Titulo::where(...) ad-hoc no body do método.
 *
 * Wave 18 D4 saturação Financeiro (68→95) — SoC brutal (Constituição v2 §5):
 *   - Antes: Controllers misturavam query Eloquent + render Inertia
 *   - Depois: Repository agrupa intent ("vencidos do mês", "abertos por categoria",
 *     "total a receber até data X")
 *
 * Multi-tenant Tier 0 (ADR 0093): todas queries recebem $businessId explícito
 * E confiam no BusinessScope global como defesa em profundidade. Tests biz=1
 * (ADR 0101) — nunca biz=4 (ROTA LIVRE prod).
 *
 * Observability D9.a: métodos críticos wrap em OtelHelper::spanBiz pra rastreio
 * por business_id sem PII.
 *
 * @see Modules\Financeiro\Models\Titulo
 * @see Modules\Financeiro\Services\FluxoCaixaService (consumer)
 * @see Modules\Financeiro\Services\TituloService     (consumer — emissão boleto)
 */
class TituloRepository
{
    /**
     * Lista paginada de títulos com filtros densos pro cockpit Unificado.
     *
     * Filtros aceitos (todos opcionais):
     *   tipo: 'receber'|'pagar'
     *   status: 'aberto'|'parcial'|'quitado'|'cancelado'
     *   vencimento_de / vencimento_ate (Y-m-d)
     *   cliente_id
     *   plano_conta_id
     *   busca (numero LIKE OR cliente_descricao LIKE)
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarPaginado(int $businessId, array $filtros = [], int $perPage = 50): LengthAwarePaginator
    {
        return OtelHelper::spanBiz('financeiro.titulo.repo.listar', function () use ($businessId, $filtros, $perPage) {
            return $this->aplicarFiltros($this->base($businessId), $filtros)
                ->orderBy('vencimento', 'asc')
                ->orderBy('id', 'asc')
                ->paginate($perPage);
        }, [
            'module'      => 'Financeiro',
            'op'          => 'titulo.repo.listar',
            'business_id' => $businessId,
            'has_filtros' => ! empty($filtros),
        ]);
    }

    /**
     * Total agregado de valor_aberto + count por tipo/status. KPI cockpit.
     *
     * @return array{count: int, total: float}
     */
    public function totaisAbertos(int $businessId, string $tipo, ?string $vencimentoAte = null): array
    {
        $q = $this->base($businessId)
            ->whereIn('status', ['aberto', 'parcial'])
            ->where('tipo', $tipo);

        if ($vencimentoAte) {
            $q->whereDate('vencimento', '<=', $vencimentoAte);
        }

        return [
            'count' => (int) (clone $q)->count(),
            'total' => (float) (clone $q)->sum('valor_aberto'),
        ];
    }

    /**
     * Lista vencidos > N dias sem baixa — usado em FinanceiroHealthCommand
     * + alerta dashboard cockpit.
     *
     * @return Collection<int, Titulo>
     */
    public function vencidosAntigos(int $businessId, string $tipo, int $diasMin = 30): Collection
    {
        $cutoff = now()->subDays($diasMin)->toDateString();

        return $this->base($businessId)
            ->where('tipo', $tipo)
            ->whereIn('status', ['aberto', 'parcial'])
            ->where('vencimento', '<', $cutoff)
            ->orderBy('vencimento', 'asc')
            ->get();
    }

    /**
     * Aging buckets (em_dia / <30 / 30-60 / 60-90 / >90) com count + valor.
     *
     * @return array<string, array{count: int, total: float}>
     */
    public function aging(int $businessId, string $tipo = 'receber'): array
    {
        return OtelHelper::spanBiz('financeiro.titulo.repo.aging', function () use ($businessId, $tipo) {
            $hoje = now()->toDateString();
            $base = $this->base($businessId)
                ->where('tipo', $tipo)
                ->whereIn('status', ['aberto', 'parcial']);

            $emDia = (clone $base)->where('vencimento', '>=', $hoje);

            $venc30 = (clone $base)
                ->whereDate('vencimento', '<', $hoje)
                ->whereDate('vencimento', '>=', now()->subDays(30)->toDateString());

            $venc60 = (clone $base)
                ->whereDate('vencimento', '<', now()->subDays(30)->toDateString())
                ->whereDate('vencimento', '>=', now()->subDays(60)->toDateString());

            $venc90 = (clone $base)
                ->whereDate('vencimento', '<', now()->subDays(60)->toDateString())
                ->whereDate('vencimento', '>=', now()->subDays(90)->toDateString());

            $vencAntigo = (clone $base)->whereDate('vencimento', '<', now()->subDays(90)->toDateString());

            return [
                'em_dia' => ['count' => (int) (clone $emDia)->count(), 'total' => (float) (clone $emDia)->sum('valor_aberto')],
                '<30'    => ['count' => (int) (clone $venc30)->count(), 'total' => (float) (clone $venc30)->sum('valor_aberto')],
                '30-60'  => ['count' => (int) (clone $venc60)->count(), 'total' => (float) (clone $venc60)->sum('valor_aberto')],
                '60-90'  => ['count' => (int) (clone $venc90)->count(), 'total' => (float) (clone $venc90)->sum('valor_aberto')],
                '>90'    => ['count' => (int) (clone $vencAntigo)->count(), 'total' => (float) (clone $vencAntigo)->sum('valor_aberto')],
            ];
        }, [
            'module'      => 'Financeiro',
            'op'          => 'titulo.repo.aging',
            'business_id' => $businessId,
            'tipo'        => $tipo,
        ]);
    }

    /**
     * Busca title por (origem, origem_id, parcela_numero) — UNIQUE idempotency
     * key explorada por TransactionObserver + CriarTituloDeVendaJob pra evitar
     * duplicação na sincronização vendas.
     */
    public function acharPorOrigem(int $businessId, string $origem, int $origemId, int $parcela = 1): ?Titulo
    {
        return $this->base($businessId)
            ->where('origem', $origem)
            ->where('origem_id', $origemId)
            ->where('parcela_numero', $parcela)
            ->first();
    }

    /**
     * Builder base — sempre escopado por business_id como defesa em profundidade.
     * BusinessScope global já filtra, mas explicitar evita bug se scope falhar.
     */
    private function base(int $businessId): Builder
    {
        return Titulo::query()->where('business_id', $businessId);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $q, array $filtros): Builder
    {
        if (! empty($filtros['tipo']) && in_array($filtros['tipo'], ['receber', 'pagar'], true)) {
            $q->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['status'])) {
            $q->where('status', $filtros['status']);
        }

        if (! empty($filtros['vencimento_de'])) {
            $q->whereDate('vencimento', '>=', $filtros['vencimento_de']);
        }

        if (! empty($filtros['vencimento_ate'])) {
            $q->whereDate('vencimento', '<=', $filtros['vencimento_ate']);
        }

        if (! empty($filtros['cliente_id'])) {
            $q->where('cliente_id', (int) $filtros['cliente_id']);
        }

        if (! empty($filtros['plano_conta_id'])) {
            $q->where('plano_conta_id', (int) $filtros['plano_conta_id']);
        }

        if (! empty($filtros['busca'])) {
            $busca = '%' . trim((string) $filtros['busca']) . '%';
            $q->where(function ($sub) use ($busca) {
                $sub->where('numero', 'like', $busca)
                    ->orWhere('cliente_descricao', 'like', $busca);
            });
        }

        return $q;
    }
}
