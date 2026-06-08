<?php

declare(strict_types=1);

namespace Modules\Cms\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Log;
use Modules\Cms\Entities\CmsSiteDetail;
use Modules\Cms\Notifications\NewLeadGeneratedNotification;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Notification;

/**
 * CmsLeadService — captura + notificação de leads via formulário público de contato.
 *
 * Extraído de `CmsController@postContactForm` (Wave 18 D4 saturação).
 *
 * SoC brutal (ADR 0094 §5):
 *  - Controller só faz HTTP (validate + return response).
 *  - Service orquestra notificação + log com PII redactada (D7.a LGPD).
 *
 * Multi-tenant: endpoint público (sem auth), notifiable_email vem de CmsSiteDetail
 * — quando cms_site_details ganhar `business_id` (US-CMS-003), Service recebe
 * `$businessId` no método e propaga sem mudar Controller.
 *
 * @see Modules\Cms\Http\Controllers\CmsController::postContactForm
 * @see Modules\Cms\Notifications\NewLeadGeneratedNotification
 * @see Modules\Cms\Tests\Feature\CmsLeadServiceTest
 */
class CmsLeadService
{
    public function __construct(protected PiiRedactor $piiRedactor) {}

    /**
     * Processa lead capturado: dispara notificação ao admin + loga (PII-safe).
     *
     * @param  array<string, mixed>  $leadDetails  payload validado (sem _gotcha)
     * @return bool true se notificação foi disparada, false se sem destinatário
     */
    public function capturar(array $leadDetails): bool
    {
        return OtelHelper::spanBiz('cms.service.lead.capturar', function () use ($leadDetails) {
            $recipient = CmsSiteDetail::getValue('notifiable_email');

            $sent = false;
            if (! empty($recipient) && ! empty($leadDetails['message'])) {
                OtelHelper::spanBiz('cms.lead.notify', function () use ($recipient, $leadDetails) {
                    Notification::route('mail', $recipient)
                        ->notify(new NewLeadGeneratedNotification($leadDetails));
                }, ['has_recipient' => true]);
                $sent = true;
            }

            // D7.a LGPD — log com PII redactada (CPF/CNPJ/email/telefone)
            Log::info('[cms.lead.captured] novo lead via formulario publico', [
                'lead' => $this->piiRedactor->redactArray($leadDetails),
                'sent' => $sent,
            ]);

            return $sent;
        }, ['has_message' => ! empty($leadDetails['message'])]);
    }
}
