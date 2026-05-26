<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeCertificado;

/**
 * Cert/Cfg fiscal — UNIFICADO (sub-página 6 do design KB-9.75).
 *
 * Tela ÚNICA pra gestão de certificado A1 + ambiente SEFAZ + tributação.
 * Embute funcionalidade do Modules/NfeBrasil/Configuracao/Certificado (upload,
 * testar conexão, trocar ambiente) — forms apontam pros endpoints existentes
 * `/nfe-brasil/configuracao/certificado/*` (FormRequest valida permissão +
 * HasBusinessScope ADR 0093, ZERO duplicação de lógica).
 *
 * Permissão: superadmin OU fiscal.config.edit (+ nfe.configuracao.manage no
 * endpoint NfeBrasil pra mutações).
 */
class ConfigController extends Controller
{
    public function index(Request $request): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.config.edit')) {
            abort(403, 'Sem permissão fiscal.config.edit');
        }

        $businessId = (int) $request->session()->get('business.id');
        $cnpjBusinessSession = (string) $request->session()->get('business.tax_number_1', '');

        // Cert ativo do business (escopo via HasBusinessScope global)
        $cert = NfeCertificado::query()
            ->where('ativo', true)
            ->orderByDesc('valido_ate')
            ->first();

        $config = NfeBusinessConfig::query()->first();

        $painel = $this->montarPainelFiscal($businessId, $cnpjBusinessSession);

        $certPayload = null;
        if ($cert) {
            $dias = $cert->valido_ate
                ? (int) now()->startOfDay()->diffInDays($cert->valido_ate, false)
                : null;

            $alerta = $dias === null
                ? null
                : ($dias < 0 ? 'vencido' : ($dias <= 30 ? 'proximo_vencimento' : 'ok'));

            // Fallback CNPJ titular (cert antigo pode ter vazio)
            $cnpjTitularRaw = $cert->cnpj_titular ?: '';
            $cnpjTitularFallback = $cnpjTitularRaw === '' ? $painel['cnpj_business'] : null;

            $certPayload = [
                'uuid'                  => $cert->uuid,
                'cnpjTitular'           => $cert->cnpj_titular,
                'cnpjTitularFallback'   => $cnpjTitularFallback,
                'validoAteIso'          => $cert->valido_ate?->toIso8601String(),
                'validoAteBr'           => $cert->valido_ate?->format('d/m/Y'),
                'diasRestantes'         => $dias,
                'alerta'                => $alerta,
                'ativo'                 => (bool) $cert->ativo,
            ];
        }

        return Inertia::render('Fiscal/Config', [
            'certificado' => $certPayload,
            'config' => $config ? [
                'regime'              => $config->regime ?? 'lucro_presumido',
                'autoEmissionEnabled' => (bool) ($config->auto_emission_enabled ?? false),
                'tributacaoDefault'   => $config->tributacao_default ?? [],
            ] : null,
            // Painel fiscal — espelha CertificadoController::status() do NfeBrasil
            'painel' => $painel,
        ]);
    }

    /**
     * Coleta dados consolidados pra painel fiscal — espelha
     * Modules\NfeBrasil\Http\Controllers\CertificadoController::montarPainelFiscal.
     *
     * Inclui: CNPJ + razão social + regime + numeração NFe + tributação default
     * + UF/cidade + ambiente SEFAZ (1=produção, 2=homologação).
     *
     * Defensivo — colunas opcionais não quebram se ausentes.
     *
     * @return array<string, mixed>
     */
    private function montarPainelFiscal(int $businessId, string $cnpjBusinessSession): array
    {
        $business = DB::table('business')->where('id', $businessId)->first();
        $config   = DB::table('nfe_business_configs')->where('business_id', $businessId)->first();
        $location = DB::table('business_locations')
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->first();

        $tributacao = $config?->tributacao_default
            ? json_decode($config->tributacao_default, true)
            : null;

        $serie  = (string) ($business->numero_serie_nfe ?? '1');
        $ultimo = (int) ($business->ultimo_numero_nfe ?? 0);

        return [
            'cnpjBusiness'   => $cnpjBusinessSession ?: ($business->cnpj ?? null),
            'razaoSocial'    => $business->name ?? null,
            'regime'         => $config?->regime,
            'ncmPadrao'      => $business->ncm_padrao ?? null,
            'serieNfe'       => $serie,
            'ultimoNumero'   => $ultimo,
            'proximoNumero'  => $ultimo + 1,
            'cfopDefault'    => $tributacao['cfop'] ?? null,
            'csosnDefault'   => $tributacao['csosn'] ?? null,
            'cstDefault'     => $tributacao['cst'] ?? null,
            'uf'             => $location?->state ?? null,
            'cidade'         => $location?->city ?? null,
            'ambiente'       => (int) ($business->ambiente ?? 2),
        ];
    }
}
