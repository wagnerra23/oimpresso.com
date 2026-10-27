<?php

namespace Modules\Financeiro\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Financeiro\Services\ImportacaoTitulosService;

/**
 * Importação de títulos por planilha (US-FIN-034). A prévia não grava; gravar exige o token da
 * prévia, que só nasce depois de o usuário ver as linhas e os totais.
 *
 *  GET  /financeiro/importacao-titulos/modelo  → CSV com o cabeçalho
 *  POST /financeiro/importacao-titulos/previa  → {token, linhas, resumo}
 *  POST /financeiro/importacao-titulos/gravar  → {gravados, pulados, receber_centavos, pagar_centavos}
 */
class ImportacaoTitulosController extends Controller
{
    public function __construct(private ImportacaoTitulosService $service)
    {
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function modelo()
    {
        $csv = implode(';', ImportacaoTitulosService::COLUNAS)."\r\n";

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo-titulos.csv"',
        ]);
    }

    public function previa(Request $request): JsonResponse
    {
        $request->validate(['arquivo' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx,xls']]);
        $rows = Excel::toArray([], $request->file('arquivo'))[0] ?? [];

        try {
            return response()->json($this->service->previa((int) session('user.business_id'), $rows));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function gravar(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:64']]);

        try {
            return response()->json($this->service->gravar(
                (int) session('user.business_id'), (int) $request->user()->id, (string) $request->input('token')
            ));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
