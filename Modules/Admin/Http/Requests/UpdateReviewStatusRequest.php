<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W30 Agent B (2026-05-17) — Atualizar status PDCA de uma tela via Screen Review.
 *
 * Append-only: cada chamada adiciona um round novo no `<Tela>.review.md`,
 * NUNCA edita rounds anteriores. Status `rejected` opcionalmente cria
 * Initiative governance via InitiativeService.
 *
 * Tier 0 IRREVOGÁVEL:
 *   - Middleware stack tailscale-only + auth + is-wagner restringe Wagner-only.
 *   - Notes pode conter PII (Wagner descrevendo bug com nome cliente);
 *     PiiRedactor aplicado no Controller via AdminAuditLogger.
 *
 * @see Modules\Admin\Http\Controllers\ScreenReviewController::updateStatus
 */
class UpdateReviewStatusRequest extends FormRequest
{
    /** Status válidos PDCA Wagner. */
    public const STATUSES = [
        'approved',
        'rejected',
        'iterate',
        'pending-wagner',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'desvios' => ['nullable', 'array', 'max:50'],
            'desvios.*' => ['string', 'max:500'],
            'create_initiative' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status inválido — use: '.implode(', ', self::STATUSES).'.',
            'notes.max' => 'Notes máximo 2000 chars (use desvios pra lista).',
            'desvios.max' => 'Máximo 50 desvios por round (use rounds adicionais).',
        ];
    }
}
