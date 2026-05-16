<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Business;
use App\Transaction;
use App\Util\OtelHelper;
use App\Utils\NotificationUtil;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Crm\Entities\Campaign;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Notifications\SendCampaignNotification;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * CampaignService — orquestrador thin de campanhas SMS/Email (crm_campaigns).
 *
 * Service thin extraído de `CampaignController` (Wave J D4.a boost — 2026-05-16).
 * Encapsula a montagem do payload (merge contact_ids + additional_info) + envio
 * de notificação (SMS via NotificationUtil ou Email via Notification facade).
 * Controller fica responsável por auth/permissão (subscription + RBAC) + response
 * shape (redirect com flash status — zero regressão).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * caller obrigatoriamente passa `$businessId` resolvido de `session()->get('user.business_id')`.
 *
 * Padrão thin: zero side-effect além das chamadas Repository/Notification já
 * existentes em Campaign + NotificationUtil. Compatível 100% com response shape legacy.
 *
 * @see Modules\Crm\Http\Controllers\CampaignController
 * @see Modules\Crm\Entities\Campaign
 * @see Modules\Crm\Notifications\SendCampaignNotification
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class CampaignService
{
    public function __construct(
        private readonly NotificationUtil $notificationUtil,
    ) {
    }

    /**
     * Cria campanha nova mesclando contact_ids (customers + leads + birthday wishes)
     * e expandindo additional_info (to / trans_activity / in_days).
     *
     * Caller (Controller) garante autorização superadmin+subscription antes de chamar.
     *
     * @param  array<string, mixed>  $base            `$request->only('name', 'campaign_type', 'subject', 'email_body', 'sms_body')`
     * @param  array<int, int>       $customers       IDs vindos de `contact_id` input
     * @param  array<int, int>       $leads           IDs vindos de `lead_id` input
     * @param  array<int, int>       $birthdayWishes  IDs vindos de `contact` input
     * @param  array<string, mixed>  $additionalInfo  ['to' => ?, 'trans_activity' => ?, 'in_days' => ?]
     */
    public function createCampaign(
        array $base,
        int $businessId,
        int $createdBy,
        array $customers,
        array $leads,
        array $birthdayWishes,
        array $additionalInfo,
    ): Campaign {
        $base['business_id']     = $businessId;
        $base['created_by']      = $createdBy;
        $base['contact_ids']     = array_merge($customers, $leads, $birthdayWishes);
        $base['additional_info'] = $additionalInfo;

        DB::beginTransaction();
        try {
            $campaign = Campaign::create($base);
            DB::commit();

            return $campaign;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza campanha existente — só payload, ID já resolvido pelo Controller
     * via query scopada por business + permissão (`->findOrFail($id)`).
     *
     * @param  Campaign              $campaign        Já carregada e validada
     * @param  array<string, mixed>  $base
     * @param  array<int, int>       $customers
     * @param  array<int, int>       $leads
     * @param  array<int, int>       $birthdayWishes
     * @param  array<string, mixed>  $additionalInfo
     */
    public function updateCampaign(
        Campaign $campaign,
        array $base,
        array $customers,
        array $leads,
        array $birthdayWishes,
        array $additionalInfo,
    ): Campaign {
        $base['contact_ids']     = array_merge($customers, $leads, $birthdayWishes);
        $base['additional_info'] = $additionalInfo;

        DB::beginTransaction();
        try {
            $campaign->update($base);
            DB::commit();

            return $campaign->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Envia notificação SMS/Email pra contatos resolvidos da campanha.
     *
     * Resolve `contact_ids` de 2 fontes possíveis:
     *   - explícita (additional_info.to ∈ ['lead','customer','contact'] OU legacy)
     *   - dinâmica (additional_info.to = 'transaction_activity' — query Transaction)
     *
     * Marca `sent_on = now()` no sucesso.
     *
     * Multi-tenant: campanha JÁ deve estar scopada por `$businessId` antes de chegar aqui.
     *
     * @return array{success: bool, msg: string}
     */
    public function sendCampaignNotification(int $campaignId, int $businessId): array
    {
        return OtelHelper::spanBiz('crm.campaign.send', function () use ($campaignId, $businessId) {
            try {
                $campaign = Campaign::where('business_id', $businessId)->findOrFail($campaignId);
                $business = Business::findOrFail($businessId);

                $contactIds = $this->resolveContactIds($campaign, $businessId);
                $notifiableUsers = CrmContact::find($contactIds);

                if (! empty($notifiableUsers) && $campaign->campaign_type === 'sms') {
                    $notificationData = [
                        'sms_settings' => request()->session()->get('business.sms_settings'),
                    ];
                    foreach ($notifiableUsers as $user) {
                        $notificationData['mobile_number'] = $user->mobile;
                        $notificationData['sms_body'] = preg_replace(
                            ['/{contact_name}/', '/{campaign_name}/', '/{business_name}/'],
                            [$user->name, $campaign->name, $business->name],
                            $campaign->sms_body,
                        );

                        $this->notificationUtil->sendSms($notificationData);
                    }
                } elseif (! empty($notifiableUsers) && $campaign->campaign_type === 'email') {
                    Notification::send($notifiableUsers, new SendCampaignNotification($campaign, $business));
                }

                DB::beginTransaction();
                $campaign->sent_on = Carbon::now();
                $campaign->save();
                DB::commit();

                return ['success' => true, 'msg' => __('lang_v1.success')];
            } catch (\Throwable $e) {
                DB::rollBack();
                // D7 LGPD: redaciona PII (telefone/email de contato pode vazar via $e->getMessage()).
                $safeMessage = app(PiiRedactor::class)->redact($e->getMessage());
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$safeMessage);

                return ['success' => false, 'msg' => __('messages.something_went_wrong')];
            }
        }, ['campaign_id' => $campaignId]);
    }

    /**
     * Resolve qual conjunto de contact_ids a campanha vai notificar.
     *
     * @return array<int, int>
     */
    private function resolveContactIds(Campaign $campaign, int $businessId): array
    {
        $to = $campaign->additional_info['to'] ?? null;

        if (
            (! empty($to) && in_array($to, ['lead', 'customer', 'contact'], true))
            || (! isset($to) && ! empty($campaign->contact_ids))
        ) {
            return (array) $campaign->contact_ids;
        }

        if (! empty($to) && $to === 'transaction_activity') {
            $day = Carbon::now()->subDays((int) ($campaign->additional_info['in_days'] ?? 0))->toDateTimeString();
            $query = Transaction::where('business_id', $businessId)
                ->select('contact_id', DB::raw('MAX(transaction_date) as last_shoped'));

            $activity = $campaign->additional_info['trans_activity'] ?? null;
            if ($activity === 'has_transactions') {
                $query->having('last_shoped', '>=', $day);
            } elseif ($activity === 'has_no_transactions') {
                $query->having('last_shoped', '<=', $day);
            }

            return $query->groupBy('contact_id')->get()->pluck('contact_id')->toArray();
        }

        return [];
    }
}
