<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Services\Notify\NotificadorClienteWhatsapp;

/**
 * Listener — quando o status da OS do Repair tem marca de aviso, avisa o cliente por WhatsApp.
 *
 * Cumpre [ADR Repair tech/0001](../../requisitos/Repair/adr/tech/0001-auto-sms-em-mudanca-de-status-critico.md)
 * (auto-notificar o cliente em mudança de status crítico).
 *
 * **Plugagem no Repair (medido 2026-10-20, UC-FLX-24):**
 * `JobSheetController::update` dispara `RepairStatusChanged` quando `status_id` muda. A transição
 * pela FSM (`RepairFsmActionController::execute`) não dispara.
 *
 * **Qual status avisa (decisão [W] 2026-10-21):** a marca `repair_statuses.whatsapp_template_aviso`
 * ('ready' | 'waiting_parts' | NULL), configurada em /repair/status, chega pelo evento. O nome do
 * status não decide. `is_completed_status` não entra, porque vale como "Pronto" no kanban e como
 * "Entregue" no startPipeline.
 *
 * **Envio (US-WA-319, 2026-10-26):** pelo `NotificadorClienteWhatsapp`, evento `repair_status` —
 * canal marcado para OS (`handles_repair_status`), senão o de saída padrão. Até 2026-10-26 este
 * listener resolvia número por `whatsapp_business_phones` e mandava template HSM; medido em
 * produção, essa tabela tem 0 linhas e o aviso nunca saía. O notificador aplica as travas
 * (cliente da mesma empresa, `Contact::canReceiveWhatsappNotification`, celular, canal no ar) e
 * falta de pré-condição sai em log, sem exceção.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-004, US-WA-319
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
        // A marca do status decide, não o nome: o nome é escrito por cada empresa.
        $this->handle($event->repair, (string) ($event->avisoTemplate ?? ''));
    }

    /**
     * @param  object  $repair  Esperado: ->business_id, ->contact_id, ->id (e ->job_sheet_no, se houver)
     * @param  string  $newStatus  Mensagem marcada no status: 'ready'|'waiting_parts'; vazio ou outro valor não avisa
     */
    public function handle(object $repair, string $newStatus): void
    {
        if (! in_array($newStatus, ['ready', 'waiting_parts'], true)) {
            return;
        }
        $businessId = (int) ($repair->business_id ?? 0);
        $contactId = (int) ($repair->contact_id ?? 0);
        if ($businessId === 0 || $contactId === 0) {
            return;
        }

        $nome = (string) (DB::table('contacts')->where('business_id', $businessId)->where('id', $contactId)->value('name') ?? '');
        $primeiro = trim(explode(' ', trim($nome))[0]);
        $saudacao = $primeiro !== '' ? "Olá, {$primeiro}!" : 'Olá!';
        $os = (string) ($repair->job_sheet_no ?? $repair->id ?? '');

        $texto = $newStatus === 'ready'
            ? "{$saudacao} Sua OS nº {$os} está pronta para retirada."
            : "{$saudacao} Sua OS nº {$os} está aguardando peça. Avisamos assim que chegar.";

        app(NotificadorClienteWhatsapp::class)->notificar($businessId, $contactId, 'repair_status', $texto);
    }
}
