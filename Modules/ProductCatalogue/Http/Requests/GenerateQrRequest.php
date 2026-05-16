<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8 Security — Wave 17 saturação (97% module-grade).
 *
 * FormRequest pra GET /product-catalogue/catalogue-qr (admin QR generator).
 * Endpoint protegido por:
 *   - auth (login obrigatório)
 *   - Subscription `productcatalogue_module` OU superadmin (CatalogueQrService::authorizeAccess)
 *
 * Esta camada FormRequest valida QUE há sessão UltimatePOS válida com
 * `user.business_id` (pré-requisito pro Service operar Tier 0).
 *
 * Sem payload — endpoint GET sem inputs. Mas FormRequest serve como:
 *   - Anchor de auditoria (Laravel registra validation pass em mcp_audit_log futuro)
 *   - Defense-in-depth: aborta cedo se sessão corrompida
 *
 * @see Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController@generateQr
 * @see Modules\ProductCatalogue\Services\CatalogueQrService::authorizeAccess
 */
class GenerateQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Middleware `auth` upstream + CatalogueQrService::authorizeAccess.
        // Aqui apenas garante sessão UltimatePOS coerente.
        $bizId = $this->session()->get('user.business_id');

        return is_int($bizId) || (is_string($bizId) && ctype_digit($bizId));
    }

    public function rules(): array
    {
        // Endpoint GET sem payload — rules vazias mas permite que Laravel
        // execute authorize() e bloqueie sessão inválida com 403.
        return [];
    }

    public function messages(): array
    {
        return [];
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'Sessão UltimatePOS inválida — faça login novamente.');
    }
}
