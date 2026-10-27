<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Http\Controllers;

use App\BusinessLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Services\MarketplacesAcesso;

/**
 * Tela "Pedidos dos marketplaces" (US-MKT-008 · PT-01 Lista).
 *
 * Mostra as contas conectadas da empresa (com o local onde a venda entra — decisão [W]
 * 2026-10-26: "escolhido ao conectar a conta") e os pedidos recebidos, com a venda gerada.
 * Escopo por empresa: os models usam HasBusinessScope e a empresa vem da sessão. Props caras
 * são deferidas (RUNBOOK-inertia-defer-pattern).
 */
class PedidosController extends Controller
{
    private const LIMITE = 200;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $situacao = in_array($request->query('situacao'), ['sem_venda', 'com_venda'], true) ? (string) $request->query('situacao') : '';
        $biz = (int) $request->session()->get('user.business_id');

        return Inertia::render('Marketplaces/Pedidos', [
            'filtros' => ['q' => $q, 'situacao' => $situacao],
            'pode_conectar' => app(MarketplacesAcesso::class)->liberado($request->user(), $biz, MarketplacesAcesso::PERMISSAO_CONECTAR),
            'contas' => Inertia::defer(fn () => $this->contas($biz)),
            'locais' => Inertia::defer(fn () => BusinessLocation::query()->where('business_id', $biz)->orderBy('name')->get(['id', 'name'])->map(fn ($l) => ['id' => (int) $l->id, 'nome' => $l->name])->all()),
            'pedidos' => Inertia::defer(fn () => $this->pedidos($q, $situacao)),
        ]);
    }

    /** Escolhe o local de venda de uma conta. Só local e conta da própria empresa. */
    public function definirLocal(Request $request, int $conta): RedirectResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $registro = ContaVendedor::query()->findOrFail($conta);
        $dados = $request->validate([
            'location_id' => ['required', 'integer', function ($attr, $valor, $falha) use ($biz) {
                if (! BusinessLocation::query()->where('business_id', $biz)->whereKey($valor)->exists()) {
                    $falha('Escolha um local de venda desta empresa.');
                }
            }],
        ]);

        $registro->forceFill(['location_id' => (int) $dados['location_id']])->save();

        return back()->with('status', ['success' => 1, 'msg' => 'Local de venda da conta atualizado.']);
    }

    /** @return list<array<string, mixed>> */
    private function contas(int $biz): array
    {
        return ContaVendedor::query()->with('marketplace:id,nome')->orderBy('id')->get()
            ->map(fn (ContaVendedor $c) => [
                'id' => (int) $c->id,
                'marketplace' => $c->marketplace?->nome,
                'vendedor' => $c->seller_nickname ?: $c->seller_id_external,
                'situacao' => $c->status,
                'local_id' => $c->location_id !== null ? (int) $c->location_id : null,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function pedidos(string $q, string $situacao): array
    {
        return PedidoMarketplace::query()
            ->with('conta.marketplace:id,nome')
            ->when($q !== '', fn ($b) => $b->where('order_id_external', 'like', '%'.$q.'%'))
            ->when($situacao === 'sem_venda', fn ($b) => $b->whereNull('transaction_id'))
            ->when($situacao === 'com_venda', fn ($b) => $b->whereNotNull('transaction_id'))
            ->orderByDesc('date_created_external')->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (PedidoMarketplace $p) => [
                'id' => (int) $p->id,
                'numero' => $p->order_id_external,
                'marketplace' => $p->conta?->marketplace?->nome,
                'data' => $p->date_created_external?->toIso8601String(),
                'situacao_ml' => $p->status_external,
                'total' => $p->total_amount !== null ? (float) $p->total_amount : null,
                'venda_id' => $p->transaction_id !== null ? (int) $p->transaction_id : null,
            ])->all();
    }
}
