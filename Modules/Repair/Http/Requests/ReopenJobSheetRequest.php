<?php

declare(strict_types=1);

namespace Modules\Repair\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

/**
 * D8 Security — Wave 27 saturação Repair (2026-05-17).
 *
 * FormRequest pra reabrir OS já entregue via FSM action `acionar_garantia`
 * (ADR 0143 §Repair pipeline — transição `entregue_completo` → `garantia_acionada`).
 *
 * Garante:
 *  - jobsheet pertence ao business da sessão (anti-IDOR)
 *  - motivo obrigatório de reabertura (CDC Art. 26 — garantia 90 dias)
 *  - jobsheet ESTÁ em stage terminal `entregue_completo` (única origem válida)
 *  - prazo CDC garantia 90 dias respeitado (sinal — soft validation, warn não block)
 *
 * Pattern espelha StartFsmActionRequest + CancelJobSheetRequest. Action
 * `acionar_garantia` é `is_critical=false` mas LGPD audit exige motivo.
 *
 * @see Modules\Repair\Http\Requests\CancelJobSheetRequest
 * @see Modules\Repair\Http\Requests\StartFsmActionRequest
 * @see app/Domain/Fsm/Services/ExecuteStageActionService
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
class ReopenJobSheetRequest extends FormRequest
{
    /**
     * CDC Art. 26 — garantia produto durável 90 dias após entrega.
     * Reabertura após esse prazo deve ser sinalizada (não bloqueada — Wagner aprovou
     * pós-90d como cortesia comercial Modules/Repair shared infra).
     */
    private const CDC_GARANTIA_DIAS = 90;

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'jobsheet_id'    => ['required', 'integer', 'min:1'],
            'motivo'         => ['required', 'string', 'min:5', 'max:500'],
            'defeito_novo'   => ['sometimes', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $sessionBiz = session('user.business_id') ?? session('business.id');
            $jobsheetId = (int) $this->input('jobsheet_id');

            if ($sessionBiz === null || $jobsheetId <= 0) {
                return;
            }

            $row = DB::table('repair_job_sheets')
                ->where('id', $jobsheetId)
                ->where('business_id', $sessionBiz)
                ->first(['id', 'current_stage_id', 'completed_on']);

            if (! $row) {
                $v->errors()->add('jobsheet_id', 'JobSheet nao pertence ao business da sessao.');
                return;
            }

            // Reabertura exige stage ATUAL ser `entregue_completo` (única transição
            // válida pra `acionar_garantia` no canon ADR 0143 §Repair pipeline).
            if ($row->current_stage_id === null) {
                $v->errors()->add('jobsheet_id', 'JobSheet ainda nao iniciou pipeline FSM — nao pode acionar garantia.');
                return;
            }

            $stageKey = DB::table('sale_process_stages')
                ->where('id', $row->current_stage_id)
                ->value('key');

            if ($stageKey !== 'entregue_completo') {
                $v->errors()->add(
                    'jobsheet_id',
                    "Garantia so pode ser acionada a partir de 'entregue_completo' (atual: {$stageKey})."
                );
            }

            // Soft warning: prazo CDC 90 dias. Não bloqueia — registra metadata pro audit.
            if ($row->completed_on !== null) {
                $diasDecorridos = (int) now()->diffInDays($row->completed_on, true);
                if ($diasDecorridos > self::CDC_GARANTIA_DIAS) {
                    // Inject metadata no request pra ExecuteStageActionService logar
                    $this->merge([
                        '_fora_prazo_cdc' => true,
                        '_dias_decorridos' => $diasDecorridos,
                    ]);
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo da reabertura por garantia (audit LGPD + CDC).',
            'motivo.min'      => 'Motivo precisa ter pelo menos 5 caracteres.',
            'motivo.max'      => 'Motivo nao pode passar de 500 caracteres.',
        ];
    }
}
