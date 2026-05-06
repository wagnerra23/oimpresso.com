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
 * US-NFE-041 fase 2 — página Inertia de gerenciamento do certificado A1.
 *
 * Separado de CertificadoController (JSON API) para não misturar contratos.
 */
class ConfiguracaoController extends Controller
{
    public function __construct(private readonly CertificadoService $service) {}

    /**
     * GET /nfe-brasil/configuracao
     *
     * Renderiza a página React com o status atual do certificado.
     */
    public function index(Request $request): Response
    {
        $businessId = (int) $request->session()->get('business.id');

        $cert = NfeCertificado::where('business_id', $businessId)
            ->where('ativo', true)
            ->first();

        $certData = null;
        if ($cert) {
            $dias = $cert->diasAteVencimento();
            $certData = [
                'cnpj_titular'        => $cert->cnpj_titular,
                'valido_ate'          => $cert->valido_ate->format('Y-m-d'),
                'dias_ate_vencimento' => $dias,
                'alerta'              => $dias < 0 ? 'vencido' : ($dias <= 30 ? 'proximo_vencimento' : 'ok'),
            ];
        }

        return Inertia::render('NfeBrasil/Configuracao/Certificado', [
            'cert'       => $certData,
            'upload_url' => route('nfe-brasil.configuracao.certificado.store'),
        ]);
    }

    /**
     * POST /nfe-brasil/configuracao
     *
     * Upload + validação + storage. Retorna redirect com flash pra Inertia.
     * Senha NUNCA loga (UploadCertificadoRequest a separa do audit).
     */
    public function store(UploadCertificadoRequest $request): RedirectResponse
    {
        $businessId   = (int) $request->session()->get('business.id');
        $cnpjBusiness = (string) $request->session()->get('business.tax_number_1', '');

        $pfxBase64 = base64_encode(
            file_get_contents($request->file('certificado')->getRealPath())
        );
        $senha = (string) $request->input('senha');

        try {
            $cert = $this->service->salvar(
                $businessId,
                $pfxBase64,
                $senha,
                array_filter(['cnpj_titular' => $cnpjBusiness ?: null]),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['certificado' => $e->getMessage()]);
        }

        activity('nfe.certificado')
            ->causedBy($request->user())
            ->withProperties([
                'business_id'  => $businessId,
                'cnpj_titular' => $cert->cnpj_titular,
                'valido_ate'   => $cert->valido_ate->format('Y-m-d'),
            ])
            ->log('certificado.uploaded');

        return back()->with(
            'flash',
            ['status' => 'Certificado A1 salvo — válido até ' . $cert->valido_ate->format('d/m/Y') . '.']
        );
    }
}
