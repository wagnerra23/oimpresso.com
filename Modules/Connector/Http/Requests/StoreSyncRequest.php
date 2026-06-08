<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra POST /api/connector/delphi-sync (lote Delphi → oimpresso).
 *
 * Wave 18 RETRY D8.a: substitui validação inline de `DelphiSyncController`.
 * Payload pode chegar em 3 formatos (`pipe|json_flat|json_nested` — detectados
 * por `DelphiSyncService::detectBodyFormat`); aqui validamos só metadados
 * comuns: `cnpj`, `host`, `version`, `payload` raw.
 *
 * Tier 0 (ADR 0093): business_id é RESOLVIDO via lookup `business.tax_number`
 * a partir do `cnpj` do payload (validado no Service), NUNCA do input direto.
 */
class StoreSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Sync API key + IP whitelist são validadas em middleware antes —
        // FormRequest assume já autenticado.
        return true;
    }

    public function rules(): array
    {
        return [
            'cnpj'         => ['required', 'string', 'min:11', 'max:18'],
            'host'         => ['nullable', 'string', 'max:100'],
            'version'      => ['nullable', 'string', 'max:25'],
            'ip'           => ['nullable', 'ip'],
            'payload_raw'  => ['nullable', 'string', 'max:5000000'], // 5MB lote
            'payload_json' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'CNPJ é obrigatório para identificar business destino.',
            'cnpj.min'      => 'CNPJ inválido (mínimo 11 dígitos CPF/14 CNPJ).',
        ];
    }
}
