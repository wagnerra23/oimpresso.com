<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Crm\Services\BrLookupService;

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
}
