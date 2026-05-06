<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\NfeBrasil\Http\Requests\UploadCertificadoRequest;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\CertificadoService;

/**
 * US-NFE-041 — gerenciamento do certificado A1 do business.
 *
 * Permissão: `nfe.configuracao.manage` (validada no FormRequest::authorize +
 * declarada em Modules\NfeBrasil\Http\Controllers\DataController::user_permissions).
 *
 * Pattern: Inertia (status() = render; upload() = redirect+flash). ADR 0029.
 */
class CertificadoController extends Controller
{
    public function __construct(private readonly CertificadoService $service) {}

    /**
     * GET /nfe-brasil/configuracao/certificado
     *
     * Renderiza a Page Inertia com status atual do cert ativo do business.
     */
    public function status(Request $request): Response
    {
        $businessId = (int) $request->session()->get('business.id');
        $cnpjBusiness = (string) $request->session()->get('business.tax_number_1', '');

        $cert = NfeCertificado::where('business_id', $businessId)
            ->where('ativo', true)
            ->first();

        if (! $cert) {
            return Inertia::render('NfeBrasil/Configuracao/Certificado', [
                'tem_certificado' => false,
                'cnpj_business'   => $cnpjBusiness ?: null,
            ]);
        }

        $dias = $cert->diasAteVencimento();
        $alerta = $dias < 0 ? 'vencido' : ($dias <= 30 ? 'proximo_vencimento' : 'ok');

        return Inertia::render('NfeBrasil/Configuracao/Certificado', [
            'tem_certificado'     => true,
            'cnpj_business'       => $cnpjBusiness ?: null,
            'cnpj_titular'        => $cert->cnpj_titular,
            'valido_ate'          => $cert->valido_ate->format('Y-m-d'),
            'dias_ate_vencimento' => $dias,
            'alerta'              => $alerta,
        ]);
    }

    /**
     * POST /nfe-brasil/configuracao/certificado
     *
     * Upload + validação + storage encrypted-at-rest.
     * Senha NUNCA loga (FormRequest a separa do payload do audit).
     */
    public function upload(UploadCertificadoRequest $request): RedirectResponse
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
            return back()
                ->withErrors(['certificado' => $e->getMessage()])
                ->withInput($request->only('senha') ? [] : []); // nunca repõe senha
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

        return redirect()
            ->route('nfe-brasil.certificado.status')
            ->with('success', "Certificado A1 cadastrado. CNPJ {$cert->cnpj_titular} válido até {$cert->valido_ate->format('d/m/Y')}.");
    }
}
