<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AcceptDelphiTokenHandshakeRequest — Wave 23 D8 SECURITY.
 *
 * FormRequest pro endpoint Delphi de handshake inicial de token API
 * (`POST /connector/api/handshake/{business_id}`). Hoje Delphi legacy
 * (UltimatePOS v6.7 cliente Windows) envia HD + CNPJ + serial em payload
 * pipe-format; controller faz validate inline. Esta classe formaliza:
 *
 *   1. Validação canon do payload mínimo (HD + serial + version)
 *   2. Mensagens PT-BR (cliente Delphi pode exibir traduzido)
 *   3. Anti-spoofing: NÃO aceita business_id no body (vem na URL)
 *
 * **Cross-tenant intencional Delphi** (ADR 0093 §exceções Connector):
 *   - business_id explícito na URL (rota path param) — Delphi legacy não tem
 *     session Laravel
 *   - HD lookup é cross-business permitido (notebook de suporte pode acessar
 *     várias filiais)
 *
 * Latência: ~30ms validation + HD resolve via DelphiSyncService.
 *
 * @see Modules\Connector\Services\DelphiSyncService::extractHd
 * @see Modules\Connector\Http\Requests\StoreLicencaComputadorRequest (sibling)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/Connector/SPEC.md (US-CONN-001..012)
 */
class AcceptDelphiTokenHandshakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint Delphi opera sem session Laravel (cliente Windows legacy).
        // Autorização real é HD + CNPJ match em DelphiSyncService — esta camada
        // só valida payload. Defesa: nenhum body field "business_id" permitido.
        return true;
    }

    public function rules(): array
    {
        return [
            // hd: serial do disco rígido (UPPER hex 8-32 chars típico)
            'hd' => ['required', 'string', 'min:6', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],

            // serial: serial software (random per-install)
            'serial' => ['required', 'string', 'min:8', 'max:120'],

            // versao: versão Delphi cliente (semver-like ou X.Y.Z)
            'versao' => ['required', 'string', 'max:32'],

            // ip: IP origem (opcional — Connector pode capturar via Request)
            'ip' => ['nullable', 'string', 'max:45'],

            // cnpj: CNPJ do business (validação leve — DelphiSyncService normaliza)
            'cnpj' => ['nullable', 'string', 'max:20'],

            // razao_social: nome empresarial (audit only)
            'razao_social' => ['nullable', 'string', 'max:255'],

            // host: hostname máquina (audit/debug)
            'host' => ['nullable', 'string', 'max:120'],

            // ANTI-SPOOFING: NUNCA aceitar business_id no body — vem na URL.
            // (Validation rule prohibits adiciona defesa em profundidade.)
            'business_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'hd.required'         => 'HD (serial do disco) obrigatório.',
            'hd.regex'            => 'HD deve conter apenas alfanuméricos, hífen e underscore.',
            'serial.required'     => 'Serial do software obrigatório.',
            'versao.required'     => 'Versão Delphi obrigatória.',
            'business_id.prohibited' => 'business_id não pode ser enviado no body — use a URL.',
        ];
    }

    /**
     * Coerce HD pra UPPER (convenção DelphiSyncService).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('hd')) {
            $this->merge(['hd' => strtoupper((string) $this->input('hd'))]);
        }
    }
}
