<?php

namespace Modules\Officeimpresso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/officeimpresso/audit — o que o desktop pode gravar em licenca_log.
 *
 * Até 2026-11-18 o endpoint aceitava qualquer `event` do corpo (auditoria 2026-11-05,
 * Lote 3): um desktop autenticado gravava no log append-only eventos com rótulo do
 * servidor (`admin_action`, `login_success`, `block`...). Agora:
 *
 * - `event` só da lista EVENTOS_DESKTOP, todos com prefixo `desktop_`, que não colide
 *   com evento que o servidor grava. As três categorias além do genérico são as que o
 *   docblock do AuditController sempre descreveu (conexão local, hardware, timeout).
 * - `licenca_id`, quando vem, tem de ser uma licença da empresa do usuário do token.
 *   Antes o desktop podia pendurar o registro numa licença de outra empresa.
 * - Os demais campos conhecidos têm tipo e tamanho da coluna. Campos não conhecidos
 *   continuam indo para metadata, como antes (hd, hostname, versão do executável).
 */
class StoreDesktopAuditRequest extends FormRequest
{
    public const EVENTOS_DESKTOP = [
        'desktop_audit',
        'desktop_erro_conexao',
        'desktop_hardware',
        'desktop_timeout',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $businessId = $this->user()?->business_id;

        return [
            'event'         => ['nullable', 'string', Rule::in(self::EVENTOS_DESKTOP)],
            'licenca_id'    => [
                'nullable', 'integer',
                Rule::exists('licenca_computador', 'id')->where('business_id', $businessId),
            ],
            'error_code'    => ['nullable', 'string', 'max:100'],
            'error_message' => ['nullable', 'string', 'max:5000'],
            'endpoint'      => ['nullable', 'string', 'max:255'],
            'http_method'   => ['nullable', 'string', 'max:10'],
            'http_status'   => ['nullable', 'integer', 'between:100,599'],
            'duration_ms'   => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'event.in'          => 'Evento não permitido ao desktop. Use: '.implode(', ', self::EVENTOS_DESKTOP).'.',
            'licenca_id.exists' => 'A licença informada não pertence à sua empresa.',
        ];
    }
}
