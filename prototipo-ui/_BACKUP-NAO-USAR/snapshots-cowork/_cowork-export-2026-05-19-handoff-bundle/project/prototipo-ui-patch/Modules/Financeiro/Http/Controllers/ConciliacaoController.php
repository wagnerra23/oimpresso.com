<?php

// @memcofre
//   controller: ConciliacaoController
//   tela: /financeiro/conciliacao
//   module: Financeiro
//   status: stub-mock-data
//   tests: Modules/Financeiro/Tests/Feature/ConciliacaoControllerTest

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConciliacaoController extends Controller
{
    public function index(Request $request): Response
    {
        // TODO[CL]: substituir por ConciliacaoService->listar(tenantId, contaId, periodo)
        $linhas = [
            ['id' => 1, 'ofx_id' => 'OFX-04388', 'data' => '2026-05-02', 'data_label' => '02/05', 'descricao' => 'TED IMOBILIARIA CENTRO LTDA', 'valor' => -4500.00, 'status' => 'matched', 'match_entry_id' => 1884, 'match_descricao' => 'Aluguel maio · Imobiliária Centro', 'match_ref' => 'P-1884 · NF 4521', 'match_confidence' => 1.0],
            ['id' => 2, 'ofx_id' => 'OFX-04389', 'data' => '2026-05-03', 'data_label' => '03/05', 'descricao' => 'DEB AUT VIVO EMPRESAS', 'valor' => -320.00, 'status' => 'matched', 'match_entry_id' => 1885, 'match_descricao' => 'Telefone Vivo Empresas', 'match_ref' => 'P-1885 · DA', 'match_confidence' => 1.0],
            ['id' => 3, 'ofx_id' => 'OFX-04395', 'data' => '2026-05-08', 'data_label' => '08/05', 'descricao' => 'PIX PADARIA PAO QUENTE', 'valor' => 480.00, 'status' => 'suggest', 'match_entry_id' => 2641, 'match_descricao' => 'Cartão fidelidade Padaria Pão Quente', 'match_ref' => 'R-2641 · NF 8810', 'match_confidence' => 0.95],
            ['id' => 4, 'ofx_id' => 'OFX-04396', 'data' => '2026-05-08', 'data_label' => '08/05', 'descricao' => 'TARIFA CESTA CONTA PJ', 'valor' => -89.90, 'status' => 'none', 'match_entry_id' => null, 'match_descricao' => null, 'match_ref' => null, 'match_confidence' => null],
        ];

        $totalIn = collect($linhas)->where('valor', '>', 0)->sum('valor');
        $totalOut = collect($linhas)->where('valor', '<', 0)->sum('valor');

        return Inertia::render('Financeiro/Conciliacao/Index', [
            'periodo_label' => '02 → 09 mai 2026',
            'conta' => 'Itaú PJ',
            'importado_em' => '09/05 14:32',
            'total_linhas' => count($linhas),
            'conciliados' => collect($linhas)->where('status', 'matched')->count(),
            'pendentes' => collect($linhas)->where('status', '!=', 'matched')->count(),
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'linhas' => $linhas,
        ]);
    }

    public function aceitar(Request $request, int $id): RedirectResponse
    {
        // TODO[CL]: ConciliacaoService->aceitarSugestao(linhaId=$id, user=auth()->id())
        // grava match_entry_id em bank_statement_lines, atualiza FinancialEntry.paid_at
        return back();
    }

    public function desfazer(Request $request, int $id): RedirectResponse
    {
        // TODO[CL]: ConciliacaoService->desfazerMatch(linhaId=$id)
        return back();
    }
}
