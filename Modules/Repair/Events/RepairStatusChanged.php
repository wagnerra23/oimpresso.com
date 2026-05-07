<?php

declare(strict_types=1);

namespace Modules\Repair\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando JobSheet (OS Repair) muda de status.
 *
 * Listeners externos podem reagir — ex: `Modules\Whatsapp\Listeners\NotifyRepairCustomer`
 * dispara mensagem Whatsapp ao cliente quando status vai pra `ready`/`waiting_parts`
 * (cumpre [ADR Repair tech/0001](../../../requisitos/Repair/adr/tech/0001-auto-sms-em-mudanca-de-status-critico.md)).
 *
 * **Como disparar (a fazer em PR coordenado com Felipe/Maiara):**
 *
 * No `JobSheetController::update` (linha ~724 onde `status_id` é atualizado):
 *
 * ```php
 * $oldStatusName = optional(RepairStatus::find($job_sheet->getOriginal('status_id')))->name;
 * $job_sheet->status_id = $input['status_id'];
 * $job_sheet->save();
 *
 * $newStatusName = optional(RepairStatus::find($job_sheet->status_id))->name;
 * if ($oldStatusName !== $newStatusName) {
 *     event(new \Modules\Repair\Events\RepairStatusChanged($job_sheet, $newStatusName));
 * }
 * ```
 *
 * Este evento é declarado neste PR (Lote 2d Whatsapp). O dispatch real
 * fica pra PR Repair separado (não conflitar com features ativas).
 *
 * @property object $repair  Instância JobSheet (id, business_id, contact_id, etc)
 * @property string $newStatus  Nome do novo status (ex: 'ready', 'waiting_parts', 'in_progress')
 */
class RepairStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly object $repair,
        public readonly string $newStatus,
    ) {
    }
}
