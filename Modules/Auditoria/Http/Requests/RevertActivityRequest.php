<?php

declare(strict_types=1);

namespace Modules\Auditoria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra POST /auditoria/{activityId}/revert.
 *
 * D8.c Security — Wave S Batch 2. Extraido de AuditoriaController@revert.
 *
 * Em 2026-11-09 o controller respondia 501 depois desta validacao e nao chamava
 * o RevertService. A autorizacao descrita em RevertService::canRevert() so vale
 * quando a rota for ligada (decisao D1 de [W], ver SPEC §Estado real).
 *
 * Aqui validamos APENAS o input HTTP: revert_reason >= 10 chars, a mesma regra
 * de RevertService::revert().
 *
 * @see Modules/Auditoria/Http/Controllers/AuditoriaController.php
 * @see Modules/Auditoria/Services/RevertService.php
 * @see memory/requisitos/Auditoria/SPEC.md
 * @see memory/decisions/0127-modules-auditoria-undo-activity-log.md
 */
class RevertActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth basica: so garante que ha user autenticado (middleware 'auth' ja
        // bloqueia, duplicado pro caso de FormRequest fora da rota web).
        // RBAC fino fica em RevertService::canRevert(), fora do caminho HTTP
        // enquanto o controller responder 501.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // RevertService::revert() exige min 10 chars — replicado aqui pra
            // retornar 422 antes de tocar o Service.
            'revert_reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'revert_reason.required' => 'Razao do revert e obrigatoria (audit trail).',
            'revert_reason.min'      => 'Razao precisa ter no minimo 10 caracteres.',
            'revert_reason.max'      => 'Razao limitada a 500 caracteres.',
        ];
    }
}
