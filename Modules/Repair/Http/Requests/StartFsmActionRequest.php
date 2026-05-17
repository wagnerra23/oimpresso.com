<?php

declare(strict_types=1);

namespace Modules\Repair\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D2 Code Quality + D8 Security — Wave 18 saturação Repair.
 *
 * FormRequest pra iniciar pipeline FSM ([ADR 0143]) em JobSheet. Garante:
 *  - jobsheet_id presente + inteiro positivo
 *  - jobsheet pertence ao business da sessao (anti-IDOR)
 *  - jobsheet ainda nao iniciou pipeline (current_stage_id == null)
 *
 * @see Modules\Repair\Http\Controllers\RepairFsmActionController
 * @see app/Domain/Fsm/Services/ExecuteStageActionService
 */
class StartFsmActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'jobsheet_id' => ['required', 'integer', 'min:1'],
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

            $row = \DB::table('repair_job_sheets')
                ->where('id', $jobsheetId)
                ->where('business_id', $sessionBiz)
                ->first(['id', 'current_stage_id']);

            if (! $row) {
                $v->errors()->add('jobsheet_id', 'JobSheet nao pertence ao business da sessao.');
                return;
            }

            if ($row->current_stage_id !== null) {
                $v->errors()->add('jobsheet_id', 'Pipeline FSM ja foi iniciado pra este JobSheet.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'jobsheet_id.required' => 'jobsheet_id obrigatorio pra iniciar pipeline.',
            'jobsheet_id.min'      => 'jobsheet_id deve ser positivo.',
        ];
    }
}
