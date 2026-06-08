<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Http\Requests;

use App\BusinessLocation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Wave 14 D8 Security — FormRequest pra rota publica /catalogue/{business_id}/{location_id}.
 *
 * Endpoint NAO tem auth (catalogo acessado via QR code do cliente). Atacante pode
 * tentar enumerar tenants via varredura sequencial de business_id. Defenses:
 *
 *  - authorize() sempre true (rota publica), mas rules() VALIDA consistencia
 *  - business_id e location_id devem ser positivos
 *  - location_id deve EXISTIR no business correspondente (anti-enumeration cross-tenant)
 *  - throttle:30,1 no route group ja limita abuso por IP
 *
 * Defense-in-depth alem do PublicCatalogueSecurityTest (multi-tenant scope ja cobre
 * isolamento de Product::where(business_id), mas este Request faz fail-fast antes
 * de executar queries caras de catalogo).
 *
 * @see Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController@index
 * @see Modules\ProductCatalogue\Tests\Feature\PublicCatalogueSecurityTest
 */
class ShowPublicCatalogueRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Rota publica — qualquer um com URL valida pode ver catalogo.
        return true;
    }

    public function rules(): array
    {
        return [
            'business_id' => ['required', 'integer', 'min:1', 'exists:business,id'],
            'location_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Validacao cross-field: location_id deve pertencer ao business_id da URL.
     * Bloqueia enumeration onde atacante cruza business=1 com location=99 de outro tenant.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $businessId = (int) $this->route('business_id');
            $locationId = (int) $this->route('location_id');

            if ($businessId <= 0 || $locationId <= 0) {
                return;
            }

            $exists = BusinessLocation::where('business_id', $businessId)
                ->where('id', $locationId)
                ->exists();

            if (! $exists) {
                $v->errors()->add('location_id', 'Location nao pertence ao business informado.');
            }
        });
    }

    /**
     * URL params nao vem em $request->input por padrao — Laravel usa route()->parameters().
     * Sobrescreve all() pra que rules() consiga validar business_id/location_id da URL.
     */
    public function all($keys = null): array
    {
        $data = parent::all($keys);
        $data['business_id'] = $this->route('business_id');
        $data['location_id'] = $this->route('location_id');

        return $data;
    }
}
