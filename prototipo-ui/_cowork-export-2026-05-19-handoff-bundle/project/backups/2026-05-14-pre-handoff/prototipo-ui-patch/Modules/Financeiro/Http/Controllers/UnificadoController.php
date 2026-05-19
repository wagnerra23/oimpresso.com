<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\BankAccount;
use Modules\Financeiro\Models\FinancialEntry;     // TODO: confirmar nome real do modelo
use Modules\Financeiro\Models\Categoria;          // TODO: ou ChartOfAccount
use Modules\Financeiro\Services\BaixaService;     // TODO: criar se não existe

/**
 * Controller da Tela "Visão Unificada" (US-FIN-013/020).
 * Origem: prototipo Cowork aprovado por [W] em 2026-05-09.
 *
 * Persona-foco: Eliana [E] — financeiro de escritório.
 * Padrao Cockpit V2 — 1 tela mistura Pagar/Pagas/Receber/Recebidas + KPIs + drawer.
 *
 * @memcofre tela=/financeiro/unificado status=em-implementacao
 */
class UnificadoController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->business_id; // padrao multi-tenant do projeto

        $filters = [
            'tab'       => $request->string('tab', 'all')->toString(),
            'busca'     => $request->string('busca', '')->toString(),
            'conta'     => $request->string('conta', '')->toString(),
            'categoria' => $request->string('categoria', '')->toString(),
            'periodo'   => $request->string('periodo', 'mes_atual')->toString(),
            'densidade' => $request->string('densidade', 'comfortable')->toString(),
        ];

        // ---------- Query principal ----------
        // TODO: trocar pelo Service real (FinancialEntryQuery::forTenant($tenantId)).
        $q = FinancialEntry::query()
            ->where('business_id', $tenantId)
            ->with(['contraparte', 'categoria', 'contaBancaria']);

        // Periodo (default: mes corrente)
        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();
        $q->whereBetween('vencimento', [$start, $end]);

        // Tabs
        match ($filters['tab']) {
            'open'     => $q->whereIn('status', ['aberto', 'vencendo', 'atrasado']),
            'rec'      => $q->where('kind', 'receivable')->whereIn('status', ['aberto', 'vencendo', 'atrasado']),
            'pay'      => $q->where('kind', 'payable')->whereIn('status', ['aberto', 'vencendo', 'atrasado']),
            'received' => $q->where('kind', 'receivable')->where('status', 'recebido'),
            'paid'     => $q->where('kind', 'payable')->where('status', 'pago'),
            'late'     => $q->where('status', 'atrasado'),
            default    => null,
        };

        // Filtros adicionais
        if ($filters['conta'])     $q->where('conta_bancaria_id', $filters['conta']);
        if ($filters['categoria']) $q->where('categoria_id', $filters['categoria']);
        if ($filters['busca']) {
            $q->where(function ($qq) use ($filters) {
                $qq->where('descricao', 'like', "%{$filters['busca']}%")
                   ->orWhereHas('contraparte', fn($c) => $c->where('nome', 'like', "%{$filters['busca']}%"));
            });
        }

        $rows = $q->orderBy('vencimento')->limit(500)->get()->map(fn($e) => $this->presentEntry($e));

        // ---------- KPIs do periodo ----------
        $kpis = $this->kpis($tenantId, $start, $end);

        // ---------- Listas pra filtros ----------
        $contas = BankAccount::where('business_id', $tenantId)
            ->orderBy('nome')->get(['id', 'nome']);

        $categorias = Categoria::where('business_id', $tenantId)
            ->where('ativo', true)
            ->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Financeiro/Unificado/Index', [
            'kpis'        => $kpis,
            'lancamentos' => $rows,
            'filters'     => $filters,
            'contas'      => $contas,
            'categorias'  => $categorias,
        ]);
    }

    /**
     * POST /financeiro/unificado/{id}/baixar
     * 1-clique: marca como recebido/pago com data=hoje, conta=conta_padrao do tenant.
     */
    public function baixar(Request $request, int $id, BaixaService $service)
    {
        $tenantId = auth()->user()->business_id;
        $entry = FinancialEntry::where('business_id', $tenantId)->findOrFail($id);

        $service->baixarRapido($entry, [
            'data'  => now(),
            'conta' => $entry->conta_bancaria_id, // mesma conta do título
            'user'  => auth()->id(),
        ]);

        return back()->with('flash', [
            'kind'    => 'success',
            'message' => $entry->kind === 'receivable' ? 'Recebimento confirmado' : 'Pagamento confirmado',
        ]);
    }

    // ---------- Helpers privados ----------

    private function presentEntry(FinancialEntry $e): array
    {
        return [
            'id'                => $e->id,
            'kind'              => $e->kind, // receivable | payable
            'status'            => $e->status,
            'descricao'         => $e->descricao,
            'contraparte'       => $e->contraparte?->nome ?? '—',
            'contraparte_doc'   => $e->contraparte?->documento,
            'categoria'         => $e->categoria?->nome ?? 'Sem categoria',
            'conta_bancaria'    => $e->contaBancaria?->nome ?? '—',
            'vencimento'        => $e->vencimento?->toDateString(),
            'vencimento_label'  => $e->vencimento?->locale('pt_BR')->isoFormat('ddd, DD MMM'),
            'liquidacao'        => $e->liquidacao_at?->locale('pt_BR')->isoFormat('DD MMM'),
            'valor'             => (float) $e->valor,
            'nfe_numero'        => $e->nfe_numero,
            'canal'             => $e->canal,
            'observacao'        => $e->observacao,
        ];
    }

    private function kpis(int $tenantId, Carbon $start, Carbon $end): array
    {
        $base = FinancialEntry::where('business_id', $tenantId)
            ->whereBetween('vencimento', [$start, $end]);

        $rec  = (clone $base)->where('kind', 'receivable');
        $pay  = (clone $base)->where('kind', 'payable');

        $recebido  = (clone $rec)->where('status', 'recebido');
        $aReceber  = (clone $rec)->whereIn('status', ['aberto', 'vencendo', 'atrasado']);
        $pago      = (clone $pay)->where('status', 'pago');
        $aPagar    = (clone $pay)->whereIn('status', ['aberto', 'vencendo', 'atrasado']);

        // Saldo previsto = saldo atual + a receber - a pagar
        $saldoAtual = BankAccount::where('business_id', $tenantId)->sum('saldo_cached') ?? 0;
        $saldoPrevisto = $saldoAtual + $aReceber->sum('valor') - $aPagar->sum('valor');

        return [
            'saldo_previsto' => (float) $saldoPrevisto,
            'recebido'       => ['valor' => (float) $recebido->sum('valor'), 'qtd' => $recebido->count()],
            'a_receber'      => ['valor' => (float) $aReceber->sum('valor'), 'qtd' => $aReceber->count()],
            'pago'           => ['valor' => (float) $pago->sum('valor'),     'qtd' => $pago->count()],
            'a_pagar'        => ['valor' => (float) $aPagar->sum('valor'),   'qtd' => $aPagar->count()],
        ];
    }
}
