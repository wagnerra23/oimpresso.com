<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        // D9.a OTel — wrap check-update Delphi (chamado a cada boot WR Comercial).
        return OtelHelper::spanBiz('connector.delphi.check_update', function () use ($request) {
            $raw  = trim($request->getContent());
            $parts = explode(';', $raw, 2);
            $cnpj  = $parts[0] ?? '';
            $versaoAtual = $parts[1] ?? '';

            // D9.b log estruturado contexto biz — versão Delphi atual.
            Log::channel('stack')->info('connector.delphi.check_update.request', [
                'biz' => session('business.id'),
                'endpoint' => '/connector/api/check-update',
                'cnpj_hash' => $cnpj !== '' ? substr(hash('sha256', $cnpj), 0, 8) : null,
                'versao_atual_delphi' => $versaoAtual,
                'remote_ip' => $request->ip(),
            ]);

            $business = $this->resolveBusiness($cnpj);

            $disponivel   = $business?->versao_disponivel   ?? '';
            $obrigatoria  = $business?->versao_obrigatoria  ?? '';

            $hasUpdate = $disponivel !== '' && $disponivel !== null;

            $first  = $hasUpdate ? $disponivel : 'N';
            $second = $obrigatoria ?? '';

            return response("$first;$second", 200)
                ->header('Content-Type', 'text/plain');
        }, ['connector.endpoint' => 'check-update']);
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
