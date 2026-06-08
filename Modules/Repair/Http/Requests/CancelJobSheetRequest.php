<?php

declare(strict_types=1);

namespace Modules\Repair\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

/**
 * D8 Security — Wave 25 saturação Repair (2026-05-16).
 *
 * FormRequest pra cancelar JobSheet via FSM action `cancelar_os` (ADR 0143).
 * Garante:
 *  - jobsheet pertence ao business da sessao (anti-IDOR)
 *  - motivo obrigatório (audit trail LGPD + customer service)
 *  - jobsheet NÃO está em stage terminal (entregue/cancelado/recusado/descartado)
 *
 * Pattern espelha StartFsmActionRequest mas pra cancelamento crítico —
 * `is_critical=true` na sale_stage_actions seed exige role + motivo.
 *
 * @see Modules\Repair\Http\Requests\StartFsmActionRequest
 * @see app/Domain/Fsm/Services/ExecuteStageActionService::cancelarVendaCascade
 */
class CancelJobSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'jobsheet_id' => ['required', 'integer', 'min:1'],
            'motivo'      => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $sessionBiz  = session('user.business_id') ?? session('business.id');
            $jobsheetId  = (int) $this->input('jobsheet_id');

            if ($sessionBiz === null || $jobsheetId <= 0) {
                return;
            }

            $row = DB::table('repair_job_sheets')
                ->where('id', $jobsheetId)
                ->where('business_id', $sessionBiz)
                ->first(['id', 'current_stage_id']);

            if (! $row) {
                $v->errors()->add('jobsheet_id', 'JobSheet nao pertence ao business da sessao.');
                return;
            }

            // Bloqueio extra: se já está em terminal, não pode cancelar
            if ($row->current_stage_id !== null) {
                $terminal = DB::table('sale_process_stages')
                    ->where('id', $row->current_stage_id)
                    ->where('is_terminal', true)
                    ->exists();

                if ($terminal) {
                    $v->errors()->add('jobsheet_id', 'JobSheet ja esta em stage terminal — cancelamento bloqueado.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo do cancelamento (audit trail LGPD).',
            'motivo.min'      => 'Motivo precisa ter pelo menos 5 caracteres.',
            'motivo.max'      => 'Motivo nao pode passar de 500 caracteres.',
        ];
    }
}
