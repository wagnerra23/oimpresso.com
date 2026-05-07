<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\ExtratoLancamento;

/**
 * Tela /financeiro/extrato/{contaBancariaId}.
 *
 * Lista lançamentos de extrato bancário sincronizados via Banking API
 * (hoje só Inter; futuro Sicoob/BTG/Cora). Filtro período padrão últimos
 * 30 dias. Job `SyncBankStatementsJob` roda diário 07:00 BRT.
 *
 * Pattern: ADR 0029 (Inertia + React + UPos).
 *
 * @see US-RB-046
 */
class ExtratoController extends Controller
{
    public function index(Request $request, int $contaBancariaId): Response
    {
        $businessId = (int) $request->session()->get('business.id');

        $conta = ContaBancaria::where('business_id', $businessId)
            ->where('id', $contaBancariaId)
            ->with('account:id,name,account_number')
            ->firstOrFail();

        $from = $request->date('from') ?? Carbon::now()->subDays(30)->startOfDay();
        $to   = $request->date('to')   ?? Carbon::now()->endOfDay();

        $lancamentos = ExtratoLancamento::where('business_id', $businessId)
            ->where('conta_bancaria_id', $contaBancariaId)
            ->whereBetween('data', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (ExtratoLancamento $e) => [
                'id'                    => $e->id,
                'data'                  => $e->data->toDateString(),
                'valor'                 => (float) $e->valor,
                'tipo'                  => $e->tipo,
                'descricao'             => $e->descricao,
                'contraparte_documento' => $e->contraparte_documento,
                'contraparte_nome'      => $e->contraparte_nome,
            ]);

        return Inertia::render('Financeiro/Extrato/Index', [
            'conta' => [
                'id'                  => $conta->id,
                'nome'                => $conta->nome,
                'banco_nome'          => $conta->banco_nome,
                'numero_conta'        => $conta->numero_conta,
                'saldo_cached'        => $conta->saldo_cached,
                'saldo_atualizado_em' => $conta->saldo_atualizado_em?->toIso8601String(),
            ],
            'lancamentos' => $lancamentos,
            'filtros'     => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'totais' => [
                'creditos' => $lancamentos->where('tipo', 'C')->sum('valor'),
                'debitos'  => $lancamentos->where('tipo', 'D')->sum('valor'),
                'count'    => $lancamentos->count(),
            ],
        ]);
    }
}
