<?php

namespace Modules\Financeiro\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Services\ExportacaoContabilService;

/**
 * GET /financeiro/exportacao-contabil/{layout}?competencia=AAAA-MM (US-FIN-077).
 *
 * Arquivo de lançamentos da competência no leiaute do sistema do contador. Com pendência de
 * de-para responde 422 com a lista: arquivo incompleto não sai.
 */
class ExportacaoContabilController extends Controller
{
    public function __construct(private ExportacaoContabilService $service)
    {
        $this->middleware('can:financeiro.relatorios.view');
    }

    public function download(Request $request, string $layout)
    {
        abort_unless(in_array($layout, ExportacaoContabilService::LAYOUTS, true), 404);
        $competencia = (string) $request->query('competencia', '');
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competencia) === 1, 422, 'Competência inválida (AAAA-MM).');

        $businessId = (int) session('user.business_id');
        $r = $this->service->lancamentos($businessId, $competencia);
        if ($r['pendencias'] !== []) {
            return response()->json(['message' => 'De-para contábil incompleto.', 'pendencias' => $r['pendencias']], 422);
        }

        $cnpj = (string) DB::table('business')->where('id', $businessId)->value('tax_number_1');
        $conteudo = $this->service->gerar($layout, $cnpj, $r['lancamentos']);
        $nome = 'lancamentos_'.$layout.'_'.str_replace('-', '', $competencia).'.txt';

        return response($conteudo, 200, [
            'Content-Type' => 'text/plain; charset='.($layout === 'dominio' ? 'ISO-8859-1' : 'UTF-8'),
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
    }
}
