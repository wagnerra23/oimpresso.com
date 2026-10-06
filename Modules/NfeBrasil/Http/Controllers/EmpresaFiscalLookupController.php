<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\NfeBrasil\Services\EmpresaFiscalLookupService;
use Modules\NfeBrasil\Services\Tributacao\TributacaoTemplateService;

/**
 * GET /nfe-brasil/tributacao/empresa-fiscal — "Configurar pelo certificado" (leitura).
 *
 * Playbook Fiscal thread 21 · UC-NFTR-14/15/16 · R-NFE-028. **Read-only**: lê os dados
 * fiscais da própria empresa + sugere templates; nada é gravado. A aplicação continua
 * sendo o `POST templates/{slug}/aplicar`, por clique.
 *
 * Multi-tenant Tier 0 (ADR 0093): o business vem SÓ da sessão — nenhum parâmetro da
 * request escolhe o tenant.
 */
class EmpresaFiscalLookupController extends Controller
{
    private const REGIMES = ['mei', 'simples', 'normal', 'lucro_presumido', 'lucro_real'];

    public function show(
        Request $request,
        EmpresaFiscalLookupService $lookup,
        TributacaoTemplateService $templates,
    ): JsonResponse {
        abort_unless($request->user()?->can('nfe.tributacao.manage'), 403);

        $businessId = (int) $request->session()->get('business.id');
        $lido = $lookup->ler($businessId);
        $campos = $lido['campos'];

        // Regime divergente não é resolvido aqui (UC-NFTR-16): só sugere quando o operador
        // escolheu (`?regime=`) ou quando as fontes concordam.
        $regime = $request->query('regime');
        if (! is_string($regime) || ! in_array($regime, self::REGIMES, true)) {
            $regime = $campos['regime']['valor'] ?? null;
        }

        return response()->json([
            'campos'     => $campos,
            'sugestoes'  => $templates->sugerir(
                $regime,
                $campos['uf']['valor'] ?? null,
                (array) ($campos['cnaes']['valor'] ?? []),
            ),
        ]);
    }
}
