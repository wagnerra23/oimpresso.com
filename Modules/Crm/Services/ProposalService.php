<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\Proposal;
use Modules\Crm\Entities\ProposalTemplate;

/**
 * ProposalService — thin de criação/atualização de Proposal.
 *
 * Service extraído de `ProposalController::store/update` (Wave 18 D4.b). Mantém
 * back-compat 100% (Controller continua chamando `Proposal::create` direto se
 * preferir — Service é opt-in). A vantagem está em:
 *   - reuso (Connector API Crm/ProposalController pode chamar mesmo Service)
 *   - teste isolado (Pest mocka 1 Service vs Controller cheio)
 *   - OTel span unificado `crm.proposal.create` / `crm.proposal.update`
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): caller passa `$businessId`
 * resolvido. Service NUNCA toca session diretamente — facilita Job assíncrono.
 *
 * @see Modules\Crm\Http\Controllers\ProposalController
 * @see Modules\Crm\Entities\Proposal
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ProposalService
{
    /**
     * Campos aceitos no payload (subset whitelist back-compat com Controller).
     *
     * @var array<int, string>
     */
    private const PROPOSAL_INPUT_FIELDS = [
        'subject', 'body', 'contact_id', 'cc', 'bcc',
    ];

    /**
     * Cria Proposal scopado por business com payload pré-validado.
     *
     * @param  array<string, mixed>  $input  Payload já filtrado pelo Controller
     * @param  int  $businessId  Tier 0 obrigatório
     * @param  int  $sentBy  user_id (session('user.id'))
     */
    public function createProposal(array $input, int $businessId, int $sentBy): ?Proposal
    {
        return OtelHelper::spanBiz('crm.proposal.create', function () use ($input, $businessId, $sentBy) {
            $payload = array_intersect_key($input, array_flip(self::PROPOSAL_INPUT_FIELDS));
            $payload['business_id'] = $businessId;
            $payload['sent_by'] = $sentBy;

            return DB::transaction(fn () => Proposal::create($payload));
        }, [
            'business_id' => $businessId,
            'contact_id'  => $input['contact_id'] ?? null,
        ]);
    }

    /**
     * Atualiza Proposal existente. Controller garante isolamento via
     * `where('business_id', $businessId)` ANTES de invocar.
     */
    public function updateProposal(int $id, array $input, int $businessId): ?Proposal
    {
        return OtelHelper::spanBiz('crm.proposal.update', function () use ($id, $input, $businessId) {
            $payload = array_intersect_key($input, array_flip(self::PROPOSAL_INPUT_FIELDS));

            return DB::transaction(function () use ($id, $payload, $businessId) {
                $proposal = Proposal::where('business_id', $businessId)
                    ->where('id', $id)
                    ->firstOrFail();
                $proposal->fill($payload)->save();

                return $proposal->fresh();
            });
        }, [
            'business_id' => $businessId,
            'proposal_id' => $id,
        ]);
    }

    /**
     * Resolve template ativo de Proposal pra o business (1 por business).
     *
     * Wave 18: extraído de `ProposalController::store()` linhas 121-123 — agora
     * reusável pra preview e API external (Connector).
     */
    public function defaultTemplate(int $businessId): ?ProposalTemplate
    {
        return OtelHelper::spanBiz('crm.proposal.default_template', function () use ($businessId) {
            return ProposalTemplate::with(['media'])
                ->where('business_id', $businessId)
                ->first();
        }, ['business_id' => $businessId]);
    }

    /**
     * Lista de campos aceitos (fonte única — Controller usa em $request->only()).
     *
     * @return array<int, string>
     */
    public function acceptedFields(): array
    {
        return self::PROPOSAL_INPUT_FIELDS;
    }
}
