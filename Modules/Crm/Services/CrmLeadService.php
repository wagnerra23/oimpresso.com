<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\Leaduser;
use Modules\Crm\Repositories\CrmLeadRepository;

/**
 * CrmLeadService — thin de criação / conversão de Lead (CrmContact type=lead).
 *
 * Service extraído de `LeadController::store/convertToContact` (Wave 18 RETRY D4.a).
 * Mantém back-compat 100% (Controller continua chamando `Contact::create` direto se
 * preferir — Service é opt-in). Vantagem:
 *   - Repository `CrmLeadRepository` resolve queries; Service cuida do write side
 *   - reuso (Connector API Crm/LeadController + Jana auto-import compartilham)
 *   - teste isolado (Pest mocka 1 Service vs Controller cheio)
 *   - OTel span unificado `crm.lead.create` / `crm.lead.convert`
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): caller passa `$businessId`
 * resolvido. Service NUNCA toca session diretamente.
 *
 * @see Modules\Crm\Http\Controllers\LeadController
 * @see Modules\Crm\Repositories\CrmLeadRepository
 * @see Modules\Crm\Entities\CrmContact
 */
class CrmLeadService
{
    /**
     * Campos aceitos no payload (subset whitelist).
     *
     * @var array<int, string>
     */
    private const LEAD_INPUT_FIELDS = [
        'first_name', 'middle_name', 'last_name', 'prefix', 'email', 'mobile',
        'landline', 'alternate_number', 'address_line_1', 'address_line_2',
        'city', 'state', 'country', 'zip_code', 'crm_source', 'crm_life_stage',
        'business_name', 'tax_number', 'dob', 'position',
    ];

    public function __construct(
        private readonly ?CrmLeadRepository $repo = null,
    ) {
    }

    /**
     * Cria Lead (CrmContact type=lead) scopado por business com payload validado.
     *
     * @param  array<string, mixed>  $input  Payload já filtrado pelo Controller/FormRequest
     * @param  int  $businessId  Tier 0 obrigatório
     * @param  int  $createdBy  user_id (session('user.id'))
     */
    public function createLead(array $input, int $businessId, int $createdBy): CrmContact
    {
        return OtelHelper::span('crm.lead.create', [
            'business_id' => $businessId,
            'created_by'  => $createdBy,
        ], function () use ($input, $businessId, $createdBy) {
            $payload = array_intersect_key($input, array_flip(self::LEAD_INPUT_FIELDS));
            $payload['business_id'] = $businessId;
            $payload['created_by']  = $createdBy;
            $payload['type']        = 'lead';

            return DB::transaction(function () use ($payload, $input) {
                $lead = CrmContact::create($payload);

                if (! empty($input['lead_users']) && is_array($input['lead_users'])) {
                    foreach ($input['lead_users'] as $userId) {
                        Leaduser::create([
                            'lead_id' => $lead->id,
                            'user_id' => (int) $userId,
                        ]);
                    }
                }

                return $lead;
            });
        });
    }

    /**
     * Converte Lead em Customer (muda type=lead → type=customer).
     *
     * Mantém audit trail (LogsActivity registra mudança). Caller (Controller)
     * verifica permissão antes de invocar.
     */
    public function convertToCustomer(int $leadId, int $businessId): CrmContact
    {
        return OtelHelper::span('crm.lead.convert', [
            'business_id' => $businessId,
            'lead_id'     => $leadId,
        ], function () use ($leadId, $businessId) {
            return DB::transaction(function () use ($leadId, $businessId) {
                $lead = CrmContact::where('business_id', $businessId)
                    ->where('id', $leadId)
                    ->where('type', 'lead')
                    ->firstOrFail();
                $lead->type = 'customer';
                $lead->converted_by = auth()->id();
                $lead->save();

                return $lead->fresh();
            });
        });
    }

    /**
     * Lista de campos aceitos (fonte única — Controller usa em $request->only()).
     *
     * @return array<int, string>
     */
    public function acceptedFields(): array
    {
        return self::LEAD_INPUT_FIELDS;
    }

    /**
     * Atalho ao repository pra dashboards / KPIs.
     */
    public function repository(): CrmLeadRepository
    {
        return $this->repo ?? app(CrmLeadRepository::class);
    }
}
