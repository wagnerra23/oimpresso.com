<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

/**
 * Listener — quando OS Repair muda pra `ready`/`waiting_parts`, dispara Whatsapp.
 *
 * Cumpre [ADR Repair tech/0001](../../requisitos/Repair/adr/tech/0001-auto-sms-em-mudanca-de-status-critico.md)
 * que decidiu auto-notificar cliente, mas SMS é caro. Whatsapp template
 * = R$ 0,07 (Meta Cloud) ou freeform Z-API incluído (driver default).
 *
 * **Multi-números (ADR 0117 — US-WA-040):**
 * Resolve número via `WhatsappBusinessPhone::resolveForEvent($bizId, 'repair_status')`.
 * Se nenhum phone tem `handles_repair_status=true`, fallback pra phone com
 * `handles_outbound_default=true`. Se nenhum atende, falha silenciosa (log info).
 *
 * **Plugagem no Repair:**
 * Lote 2c entregou o listener. Plug do Repair (criar evento próprio
 * `Modules\Repair\Events\RepairStatusChanged` + dispatch dele em
 * `RepairStatusController` quando OS muda) fica pra Lote 2d (depende de
 * coordenação com Felipe/Maiara que mantêm o Repair).
 *
 * Enquanto isso, este listener pode ser invocado manualmente via:
 *   `app(NotifyRepairCustomer::class)->handle($repair, $newStatus);`
 * (ex: pra teste manual ou comando artisan).
 *
 * **Falha silenciosa:**
 * Se nenhum WhatsappBusinessPhone resolve pra `repair_status` OU template
 * não cadastrado OU cliente não tem mobile, log info e retorna sem
 * disparar Job.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-004, US-WA-040
 * @see memory/decisions/0117-multiplos-numeros-whatsapp-por-business.md
 */
class NotifyRepairCustomer
{
    /**
     * Wrapper para Laravel Event listener (recebe instância do RepairStatusChanged).
     *
     * Conexão com `Modules\Repair\Events\RepairStatusChanged` registrada em
     * `WhatsappServiceProvider::boot()` via `Event::listen()`.
     */
    public function handleEvent(\Modules\Repair\Events\RepairStatusChanged $event): void
    {
        $this->handle($event->repair, $event->newStatus);
    }

    /**
     * @param  object  $repair  Esperado: ->business_id, ->contact_id, ->id
     * @param  string  $newStatus  Esperado: 'ready'|'waiting_parts'|outros (ignora)
     */
    public function handle(object $repair, string $newStatus): void
    {
        if (! in_array($newStatus, ['ready', 'waiting_parts'], true)) {
            return;
        }

        $businessId = (int) ($repair->business_id ?? 0);
        if ($businessId === 0) {
            return;
        }

        // Resolve qual número Whatsapp atende repair_status (ADR 0117 §Q2)
        $phone = WhatsappBusinessPhone::resolveForEvent($businessId, 'repair_status');

        if ($phone === null) {
            \Log::info('[whatsapp.notify_repair] business sem phone configurado pra repair_status — skip', [
                'business_id' => $businessId,
                'repair_id' => $repair->id ?? null,
            ]);
            return;
        }

        $templateName = $newStatus === 'ready'
            ? $phone->template_repair_ready_name
            : $phone->template_repair_waiting_parts_name;

        if (empty($templateName)) {
            \Log::info('[whatsapp.notify_repair] template não cadastrado no phone — skip', [
                'business_id' => $businessId,
                'phone_id' => $phone->id,
                'phone_label' => $phone->label,
                'status' => $newStatus,
            ]);
            return;
        }

        $contact = $this->resolveContact($repair);
        if ($contact === null || empty($contact->mobile)) {
            \Log::info('[whatsapp.notify_repair] cliente sem mobile — skip', [
                'business_id' => $businessId,
                'phone_id' => $phone->id,
                'repair_id' => $repair->id ?? null,
            ]);
            return;
        }

        SendWhatsappMessageJob::dispatch(
            $businessId,
            $phone->id,
            $contact->mobile,
            'template',
            [
                'name' => $templateName,
                'params' => [
                    'customer_name' => $contact->name ?? 'Cliente',
                    'repair_id' => (string) ($repair->id ?? ''),
                    'ready_at' => now()->format('d/m/Y H:i'),
                ],
                'locale' => 'pt_BR',
            ],
        );
    }

    private function resolveContact(object $repair): ?object
    {
        $contactId = $repair->contact_id ?? null;
        if ($contactId === null) {
            return null;
        }

        // Eloquent Contact é Model core UltimatePOS (não escopa via Scope)
        return \App\Contact::find($contactId);
    }
}
