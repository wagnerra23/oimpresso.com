<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Contrato Delphi (Services.RegistroSistema.pas → VerificarAtualizacao):
 *
 *   POST /connector/api/check-update
 *   Body: text/plain  "CNPJ;VersaoAtual"
 *   Auth: Bearer token (auth:api)
 *
 *   Response: texto simples "VersaoNova;VersaoMinObrigatoria"
 *     - Res[0] = versão disponível para download, ou 'N' se não há
 *     - Res[1] = versão mínima obrigatória (vazio = sem obrigatoriedade)
 *
 *   Os campos business.versao_disponivel e business.versao_obrigatoria
 *   são gerenciados manualmente pelo superadmin.
 */
class CheckUpdateController extends Controller
{
    public function check(Request $request): Response
    {
        $raw  = trim($request->getContent());
        $parts = explode(';', $raw, 2);
        $cnpj  = $parts[0] ?? '';

        $business = $this->resolveBusiness($cnpj);

        $disponivel   = $business?->versao_disponivel   ?? '';
        $obrigatoria  = $business?->versao_obrigatoria  ?? '';

        $hasUpdate = $disponivel !== '' && $disponivel !== null;

        $first  = $hasUpdate ? $disponivel : 'N';
        $second = $obrigatoria ?? '';

        return response("$first;$second", 200)
            ->header('Content-Type', 'text/plain');
    }

    private function resolveBusiness(string $cnpj): ?object
    {
        if ($cnpj === '') {
            return null;
        }

        $loc = DB::table('business_locations')->where('cnpj', $cnpj)->first(['business_id']);
        $bid = $loc?->business_id
            ?? DB::table('business')->where('cnpj', $cnpj)->value('id');

        if (! $bid) {
            return null;
        }

        return DB::table('business')
            ->where('id', $bid)
            ->first(['versao_disponivel', 'versao_obrigatoria']);
    }
}
