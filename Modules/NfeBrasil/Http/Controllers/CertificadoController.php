<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\NfeBrasil\Http\Requests\UploadCertificadoRequest;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\CertificadoService;

/**
 * US-NFE-041 — gerenciamento do certificado A1 do business.
 *
 * Permissão: `nfe.configuracao.manage` (validada no FormRequest::authorize).
 */
class CertificadoController extends Controller
{
    public function __construct(private readonly CertificadoService $service) {}

    /**
     * GET /nfe-brasil/configuracao/certificado
     *
     * Status atual: existe? CNPJ? dias até vencimento?
     */
    public function status(Request $request): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        $cert = NfeCertificado::where('business_id', $businessId)
            ->where('ativo', true)
            ->first();

        if (! $cert) {
            return response()->json([
                'tem_certificado' => false,
                'message' => 'Nenhum certificado ativo. Faça upload pra começar a emitir.',
            ]);
        }

        $dias = $cert->diasAteVencimento();
        $alerta = $dias < 0 ? 'vencido' : ($dias <= 30 ? 'proximo_vencimento' : 'ok');

        return response()->json([
            'tem_certificado'   => true,
            'cnpj_titular'      => $cert->cnpj_titular,
            'valido_ate'        => $cert->valido_ate->format('Y-m-d'),
            'dias_ate_vencimento' => $dias,
            'alerta'            => $alerta,
        ]);
    }

    /**
     * POST /nfe-brasil/configuracao/certificado
     *
     * Upload + validação + storage encrypted-at-rest.
     * Senha NUNCA loga (FormRequest a separa do payload do audit).
     */
    public function upload(UploadCertificadoRequest $request): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id');
        $cnpjBusiness = (string) $request->session()->get('business.tax_number_1', '');

        $pfxBase64 = base64_encode(file_get_contents($request->file('certificado')->getRealPath()));
        $senha = (string) $request->input('senha');

        try {
            $cert = $this->service->salvar(
                $businessId,
                $pfxBase64,
                $senha,
                array_filter(['cnpj_titular' => $cnpjBusiness ?: null]),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        // Audit log SEM senha e SEM path do arquivo
        activity('nfe.certificado')
            ->causedBy($request->user())
            ->withProperties([
                'business_id'  => $businessId,
                'cnpj_titular' => $cert->cnpj_titular,
                'valido_ate'   => $cert->valido_ate->format('Y-m-d'),
            ])
            ->log('certificado.uploaded');

        return response()->json([
            'ok' => true,
            'cnpj_titular'      => $cert->cnpj_titular,
            'valido_ate'        => $cert->valido_ate->format('Y-m-d'),
            'dias_ate_vencimento' => $cert->diasAteVencimento(),
        ]);
    }
}
