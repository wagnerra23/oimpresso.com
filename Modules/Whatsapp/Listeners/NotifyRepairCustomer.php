<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

/**
 * Listener — quando OS Repair muda pra `ready`/`waiting_parts`, dispara Whatsapp.
 *
 * Cumpre [ADR Repair tech/0001](../../requisitos/Repair/adr/tech/0001-auto-sms-em-mudanca-de-status-critico.md)
 * que decidiu auto-notificar cliente, mas SMS é caro. Whatsapp template
 * = R$ [redacted Tier 0] (Meta Cloud) ou freeform Z-API incluído (driver default).
 *
 * **Plugagem no Repair:**
 * Lote 2c (este) entrega o listener. Plug do Repair (criar evento próprio
 * `Modules\Repair\Events\RepairStatusChanged` + dispatch dele em
 * `RepairStatusController` quando OS muda) fica pra Lote 2d (depende de
 * coordenação com Felipe/Maiara que mantêm o Repair).
 *
 * Enquanto isso, este listener pode ser invocado manualmente via:
 *   `app(NotifyRepairCustomer::class)->handle($repair, $newStatus);`
 * (ex: pra teste manual ou comando artisan).
 *
 * **Falha silenciosa:**
 * Se WhatsappBusinessConfig não existe pra business OU cliente não tem
 * mobile cadastrado, log info e retorna sem disparar Job.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-004
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

        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->first();

        if ($config === null) {
            \Log::info('[whatsapp.notify_repair] business sem config Whatsapp — skip', [
                'business_id' => $businessId,
                'repair_id' => $repair->id ?? null,
            ]);
            return;
        }

        $templateName = $newStatus === 'ready'
            ? $config->template_repair_ready_name
            : $config->template_repair_waiting_parts_name;

        if (empty($templateName)) {
            \Log::info('[whatsapp.notify_repair] template não cadastrado — skip', [
                'business_id' => $businessId,
                'status' => $newStatus,
            ]);
            return;
        }

        $contact = $this->resolveContact($repair);
        if ($contact === null || empty($contact->mobile)) {
            \Log::info('[whatsapp.notify_repair] cliente sem mobile — skip', [
                'business_id' => $businessId,
                'repair_id' => $repair->id ?? null,
            ]);
            return;
        }

        SendWhatsappMessageJob::dispatch(
            $businessId,
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
