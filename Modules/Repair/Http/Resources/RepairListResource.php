<?php

namespace Modules\Repair\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelha cada linha exibida em `Pages/Repair/Index.tsx`.
 *
 * O controller já fez o select com aliases (contact_name, repair_status_name,
 * service_staff_first/last, etc.) — esta classe só formata.
 *
 * Sprint 2 / MWART-0001 — port 1:1 da listagem Blade. Não inventa colunas.
 */
class RepairListResource extends JsonResource
{
    public function toArray($request): array
    {
        // As duas datas chegam como STRING: App\Transaction não tem cast de data. Antes,
        // `optional($string)?->toIso8601String()` devolvia null sem erro (o Optional só chama
        // método em objeto) e a fila mostrava as colunas de data vazias. Ver UC-RIDX-06.
        $transactionDate = $this->asCarbon($this->transaction_date);
        $repairDueDate = $this->asCarbon($this->repair_due_date);

        return [
            'id'                    => (int) $this->id,
            'invoice_no'            => $this->invoice_no,
            'transaction_date'      => $transactionDate?->toIso8601String(),
            'repair_due_date'       => $repairDueDate?->toIso8601String(),
            'repair_due_human'      => $repairDueDate?->diffForHumans(),
            'is_overdue'            => $this->is_overdue ?? false,
            'serial_no'             => $this->repair_serial_no,
            'defects'               => $this->repair_defects,
            'final_total'           => (float) ($this->final_total ?? 0),
            'final_total_formatted' => $this->final_total_formatted ?? null,
            'payment_status'        => $this->payment_status,
            'contact'               => [
                'id'   => $this->contact_id ? (int) $this->contact_id : null,
                'name' => $this->contact_name ?? null,
            ],
            'service_staff'         => $this->res_waiter_id
                ? [
                    'id'   => (int) $this->res_waiter_id,
                    'name' => trim(($this->service_staff_first ?? '').' '.($this->service_staff_last ?? '')),
                ]
                : null,
            'location'              => [
                'id'   => $this->location_id ? (int) $this->location_id : null,
                'name' => $this->location_name ?? null,
            ],
            'status'                => [
                'id'    => $this->repair_status_id ? (int) $this->repair_status_id : null,
                'name'  => $this->repair_status_name ?? null,
                'color' => $this->repair_status_color ?? null,
            ],
            'warranty_name'         => $this->warranty_name ?? null,
            'device_model_name'     => $this->device_model_name ?? null,
            'created_by'            => $this->created_by ? (int) $this->created_by : null,
        ];
    }

    /** Aceita string do banco, Carbon ou vazio; devolve Carbon ou null. */
    private function asCarbon($valor): ?\Carbon\Carbon
    {
        if (empty($valor)) {
            return null;
        }

        return $valor instanceof \Carbon\Carbon ? $valor : \Carbon\Carbon::parse($valor);
    }
}
