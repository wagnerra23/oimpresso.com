<?php

declare(strict_types=1);

namespace Modules\Auditoria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra POST /auditoria/{activityId}/revert.
 *
 * D8.c Security — Wave S Batch 2. Extraido de AuditoriaController@revert (MVP 501).
 *
 * Constituicao Art. 9 (audit trail preservado) — Tier 0 IRREVOGAVEL:
 *   - Authorization Spatie per-permission acontece no RevertService::canRevert()
 *     (3 niveis: auditoria.revert.own / .any / .unlimited)
 *   - 5 categorias UNREVERTIBLE bloqueadas pelo registry do RevertService
 *     (Marcacao, NFe SEFAZ, Asaas paid, OS+NFSe, Transaction com payment)
 *   - Cada revert gera NOVA entry activity_log event='reverted' linkada via
 *     batch_uuid (append-only conceitual — audit trail intocado)
 *
 * Aqui validamos APENAS o input HTTP: revert_reason >= 10 chars (regra explicita
 * no RevertService::revert() L163-164). Authorization fica DELEGADA pro Service
 * (multi-camada checks: unrevertibleRegistry + Spatie permission + time window).
 *
 * @see Modules/Auditoria/Http/Controllers/AuditoriaController.php
 * @see Modules/Auditoria/Services/RevertService.php
 * @see memory/decisions/0127-modulo-auditoria-ui-undo.md
 */
class RevertActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth basica — RBAC fino (auditoria.revert.own/.any/.unlimited) +
        // unrevertibleRegistry sao checados no RevertService (fail-secure).
        // Aqui so garantimos que ha user autenticado (middleware 'auth' ja
        // bloqueia, mas duplicamos pro caso de FormRequest fora da rota web).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // RevertService::revert() L163-164 exige min 10 chars — replicamos
            // aqui pra retornar 422 antes de tocar Service (UX melhor que 500).
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
