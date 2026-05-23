<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Services\BrLookupService;
use Modules\NfeBrasil\Services\SefazConsultaCadastroService;

/**
 * ClienteLookupController -- proxy ViaCEP + BrasilAPI Wave C (ADR 0179).
 *
 * 2 endpoints GET pra auto-preencher cadastro do drawer 760:
 *   - GET /cliente/lookup/cep/{cep}  -> Tab Endereco
 *   - GET /cliente/lookup/cnpj/{cnpj} -> Tab Identificacao
 *
 * Middleware Auth obrigatorio (stack pai grupo /cliente em
 * Modules/Crm/Routes/web.php) -- impede anonimo furar rate limit federal.
 * NAO precisa permission especifica: lookup e read-only, nao toca Contact,
 * dados sao publicos.
 *
 * Multi-tenant: lookup NAO filtra business_id (dados publicos CEP/CNPJ),
 * mas o Auth middleware garante que apenas usuarios logados consultam ->
 * impede DoS anonimo do oimpresso contra ViaCEP/BrasilAPI.
 *
 * Response shape (200):
 *   CEP:  {logradouro, bairro, cidade, uf}
 *   CNPJ: {razao_social, fantasia, ie, situacao}
 *
 * Response 404:
 *   {message: "CEP nao encontrado"} ou {message: "CNPJ nao encontrado"}
 *
 * Pre-flight LICOES F3 (Wave Financeiro rejeitado):
 *   - T-AP-7: Service real, NAO inventado
 *   - T-AP-8: session('user.business_id') NAO chamado aqui (lookup nao toca DB)
 *   - T-AP-9: middleware auth herdado do grupo da route -- nao precisa __construct
 *   - Multi-tenant Tier 0 (ADR 0093) preservado: cache compartilhado e dado publico
 *
 * @see Modules\Crm\Services\BrLookupService
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md
 */
class ClienteLookupController extends Controller
{
    public function __construct(
        private readonly BrLookupService $lookupService,
        private readonly SefazConsultaCadastroService $sefazService,
    ) {
    }

    /**
     * GET /cliente/lookup/cep/{cep}
     *
     * Resposta:
     *   200 {logradouro, bairro, cidade, uf}
     *   404 {message: "CEP nao encontrado"}
     */
    public function cep(string $cep): JsonResponse
    {
        $result = $this->lookupService->lookupCep($cep);

        if ($result === null) {
            return response()->json([
                'message' => 'CEP nao encontrado',
            ], 404);
        }

        return response()->json($result, 200);
    }

    /**
     * GET /cliente/lookup/cnpj/{cnpj}
     *
     * Resposta:
     *   200 {razao_social, fantasia, ie, situacao}
     *   404 {message: "CNPJ nao encontrado"}
     */
    public function cnpj(string $cnpj): JsonResponse
    {
        $result = $this->lookupService->lookupCnpj($cnpj);

        if ($result === null) {
            return response()->json([
                'message' => 'CNPJ nao encontrado',
            ], 404);
        }

        return response()->json($result, 200);
    }

    /**
     * GET /cliente/lookup/cnpj/{cnpj}/sefaz?uf=RS
     *
     * Consulta IE + situação cadastral via SEFAZ ConsultaCadastro (ADR 0186).
     * Usa chain de cert A1: business consumidor → legado → institucional oimpresso.
     *
     * Multi-tenant Tier 0: business_id vem de session('user.business_id'). Cache
     * Redis 30d compartilhado entre tenants (dado público SEFAZ).
     *
     * Resposta shape:
     *   200 {ie, situacao, nome, uf, fonte, cert_source, cert_business_id}
     *        - fonte: "sefaz_rs" (qual SEFAZ respondeu)
     *        - cert_source: "nfe_brasil" | "business_legado" | "institutional_fallback"
     *        - cert_business_id: qual business teve cert usado (∈{consumidor, fallback})
     *   404 {message: "...", reason: "uf_unsupported"|"no_cert"|"sefaz_error"|"cnpj_not_found"}
     *
     * Frontend (`IdentificacaoTab.handleCnpjLookup`) usa `cert_source` + `reason`
     * pra renderizar badge contextual UI (4 estados ADR 0186).
     */
    public function cnpjSefaz(Request $request, string $cnpj): JsonResponse
    {
        $uf = (string) $request->query('uf', '');
        $uf = strtoupper(trim($uf));

        if (strlen($uf) !== 2) {
            return response()->json([
                'message' => 'Parametro uf obrigatorio (2 letras)',
                'reason' => 'invalid_request',
            ], 422);
        }

        // Validação UF supported — devolve 404 com reason específico pra UI badge.
        $ufsSupported = config('fiscal.sefaz_consulta_cadastro_ufs_supported', []);
        if (! isset($ufsSupported[$uf])) {
            return response()->json([
                'message' => "SEFAZ-{$uf} nao disponivel — preencha IE manualmente",
                'reason' => 'uf_unsupported',
                'uf' => $uf,
            ], 404);
        }

        $businessId = (int) $request->session()->get('user.business_id');
        if ($businessId <= 0) {
            return response()->json([
                'message' => 'Sessao sem business_id',
                'reason' => 'no_session',
            ], 403);
        }

        $result = $this->sefazService->consultar($cnpj, $uf, $businessId);

        if ($result === null) {
            return response()->json([
                'message' => 'IE indisponivel — preencha manualmente',
                'reason' => 'sefaz_or_cert_error',
                'uf' => $uf,
            ], 404);
        }

        return response()->json($result, 200);
    }
}
