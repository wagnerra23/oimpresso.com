<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmContact;

/**
 * LeadAssignmentService — orquestrador thin de criação/atualização de Lead.
 *
 * Service thin extraído de `LeadController` (Wave Massive D4.a). Encapsula a
 * preparação do payload + chamada de Repository (`CrmContact::createNewLead`,
 * `CrmContact::updateLead`) — controller fica responsável por auth/permissão
 * e response shape (zero regressão Inertia/JSON).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): caller obrigatoriamente passa
 * `$businessId` resolvido de `session()->get('user.business_id')` — Service
 * NUNCA toca em session diretamente (back-pressure pra Job assíncrono no futuro).
 *
 * Padrão thin: zero side-effect além das chamadas Repository já existentes em
 * `CrmContact`. Compatível 100% com response shape legacy.
 *
 * @see Modules\Crm\Http\Controllers\LeadController
 * @see Modules\Crm\Entities\CrmContact::createNewLead
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0011-alinhamento-padrao-jana.md
 */
class LeadAssignmentService
{
    /**
     * Campos aceitos do request pra criar/atualizar Lead.
     *
     * Mantido idêntico ao subset usado em LeadController@store/update — qualquer
     * mudança aqui DEVE ter migration + bump de versão (back-compat preservada).
     *
     * @var array<int, string>
     */
    private const LEAD_INPUT_FIELDS = [
        'type', 'prefix', 'first_name', 'middle_name', 'last_name',
        'tax_number', 'mobile', 'landline', 'alternate_number',
        'city', 'state', 'country', 'landmark', 'contact_id',
        'custom_field1', 'custom_field2', 'custom_field3', 'custom_field4',
        'custom_field5', 'custom_field6', 'custom_field7', 'custom_field8',
        'custom_field9', 'custom_field10',
        'email', 'crm_source', 'crm_life_stage', 'dob',
        'address_line_1', 'address_line_2', 'zip_code',
        'supplier_business_name', 'shipping_custom_field_details',
    ];

    /**
     * Cria novo Lead com payload pré-validado.
     *
     * @param  array<string, mixed>            $input          Payload já filtrado + normalizado pelo Controller
     * @param  array<int, int>|int|null        $assignedUsers  Array de user_ids (sync) ou null
     * @return CrmContact|null  Lead criado ou null em caso de falha downstream
     */
    public function createLead(array $input, $assignedUsers): ?CrmContact
    {
        return OtelHelper::spanBiz('crm.lead.create', function () use ($input, $assignedUsers) {
            return CrmContact::createNewLead($input, $assignedUsers);
        }, ['contact_id' => $input['contact_id'] ?? null]);
    }

    /**
     * Atualiza Lead existente com payload pré-validado.
     *
     * Caller (Controller) garante que `$id` pertence a `$businessId` via query
     * `where('business_id', $businessId)` ANTES de invocar (multi-tenant Tier 0).
     *
     * @param  int                              $id             ID do Lead (já scopado por business_id no Controller)
     * @param  array<string, mixed>             $input          Payload já filtrado + normalizado
     * @param  array<int, int>|int|null         $assignedUsers  Array de user_ids (sync) ou null
     */
    public function updateLead(int $id, array $input, $assignedUsers): ?CrmContact
    {
        return OtelHelper::spanBiz('crm.lead.update', function () use ($id, $input, $assignedUsers) {
            return CrmContact::updateLead($id, $input, $assignedUsers);
        }, ['lead_id' => $id]);
    }

    /**
     * Retorna a lista de campos aceitos no payload Lead — fonte única.
     *
     * Usado pelo Controller em `$request->only(...)`. Centralizar aqui evita
     * drift entre store/update (bug histórico antes da extração).
     *
     * @return array<int, string>
     */
    public function acceptedFields(): array
    {
        return self::LEAD_INPUT_FIELDS;
    }
}
