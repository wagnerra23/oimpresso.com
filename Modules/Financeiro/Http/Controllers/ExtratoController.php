<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Http\Controllers\Concerns\RendersMockCowork;
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
    use RendersMockCowork;

    /**
     * Ponto de entrada SEM id: /financeiro/extrato.
     *
     * O sidebar / topnav apontam pra /financeiro/extrato (sem contaBancariaId),
     * mas a tela de detalhe exige um id numérico (rota extrato.index com
     * whereNumber). Sem este resolver o link dava 404 (bug B4).
     *
     * Resolve pra primeira conta bancária do business (orderBy id) e redireciona
     * pra /financeiro/extrato/{id}. Se o business não tem nenhuma conta, manda
     * cadastrar em /financeiro/contas-bancarias (com flash) — SEM fallback
     * silencioso (Log::warning antes do redirect).
     *
     * Session key: usa `user.business_id` (canon UPOS — lições F3 T-AP-8 + os
     * demais controllers do módulo). Padronizado no fix B5 (antes era `business.id`,
     * inconsistente com o resto). Ambas as chaves carregam o mesmo valor na stack
     * (SetSessionData + FinanceiroTestCase), mas `user.business_id` é a canônica.
     */
    public function selecionar(Request $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $conta = ContaBancaria::where('business_id', $businessId)
            ->orderBy('id')
            ->first();

        if ($conta === null) {
            Log::warning('Financeiro/Extrato: business sem conta bancária ao abrir /financeiro/extrato', [
                'business_id' => $businessId,
            ]);

            return redirect('/financeiro/contas-bancarias')
                ->with('warning', 'Cadastre uma conta bancária para visualizar o extrato.');
        }

        return redirect('/financeiro/extrato/' . $conta->id);
    }

    public function index(Request $request, int $contaBancariaId): Response|\Illuminate\Http\Response
    {
        if ($mock = $this->tryRenderMockCowork()) {
            return $mock;
        }

        $businessId = (int) $request->session()->get('user.business_id');

        // Conta header é cheap (firstOrFail single row) — fica eager.
        $conta = ContaBancaria::where('business_id', $businessId)
            ->where('id', $contaBancariaId)
            ->with('account:id,name,account_number')
            ->firstOrFail();

        $from = $request->date('from') ?? Carbon::now()->subDays(30)->startOfDay();
        $to   = $request->date('to')   ?? Carbon::now()->endOfDay();

        // Wave 17 D6+D9 — lancamentos LIMIT 500 + totais agregados são pesados (range scan).
        // Inertia::defer + OtelHelper::spanBiz pra observabilidade per-business.
        $loadLancamentos = function () use ($businessId, $contaBancariaId, $from, $to) {
            return \App\Util\OtelHelper::spanBiz('financeiro.extrato.lancamentos', function () use ($businessId, $contaBancariaId, $from, $to) {
                return ExtratoLancamento::where('business_id', $businessId)
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
            }, ['op' => 'lancamentos_range', 'conta_id' => $contaBancariaId]);
        };

        return Inertia::render('Financeiro/Extrato/Index', [
            'conta' => [
                'id'                  => $conta->id,
                'nome'                => $conta->nome,
                'banco_nome'          => $conta->banco_nome,
                'numero_conta'        => $conta->numero_conta,
                'saldo_cached'        => $conta->saldo_cached,
                'saldo_atualizado_em' => $conta->saldo_atualizado_em?->toIso8601String(),
            ],
            'filtros' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],

            // Lazy: deferred — frontend wrap em <Deferred data="lancamentos" fallback={skeleton}>.
            'lancamentos' => Inertia::defer($loadLancamentos),

            // Totais derivam dos lancamentos — também defer (independente).
            'totais' => Inertia::defer(function () use ($loadLancamentos) {
                $lancs = $loadLancamentos();
                return [
                    'creditos' => $lancs->where('tipo', 'C')->sum('valor'),
                    'debitos'  => $lancs->where('tipo', 'D')->sum('valor'),
                    'count'    => $lancs->count(),
                ];
            }),
        ]);
    }
}
