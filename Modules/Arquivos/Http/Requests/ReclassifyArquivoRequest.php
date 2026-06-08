<?php

declare(strict_types=1);

namespace Modules\Arquivos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Arquivos\Entities\Arquivo;

/**
 * ReclassifyArquivoRequest — D8.c Wave 27 (2026-05-17).
 *
 * FormRequest dedicada pra rota admin que dispara `ArquivosService::classify`
 * sobre um arquivo já existente (reclassificação manual). Útil quando o
 * CuradorEngine recebeu rule nova e precisamos re-rotular acervo legacy.
 *
 * **Multi-tenant Tier 0 (ADR 0093):** authorize() valida que o arquivo
 * pertence ao business_id da sessão — defesa em profundidade ANTES do
 * Service tocar DB. Atacante com signed admin URL forjado é bloqueado aqui.
 *
 * **Auditor LGPD persona (D5 README Wave 27):** este endpoint registra audit
 * log com o motivo da reclassificação — facilita exportar relatório de
 * mudanças de bucket (especialmente `sensitive` → `internal` que afeta
 * encryption-at-rest).
 *
 * @see Modules\Arquivos\Services\ArquivosService::classify
 * @see Modules\Arquivos\Services\Curador\CuradorEngine
 * @see memory/decisions/0123-modules-arquivos-backbone.md §classify
 */
class ReclassifyArquivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $businessId = $this->session()->get('user.business_id');
        if (empty($businessId)) {
            return false;
        }

        $arquivoId = (int) ($this->route('arquivo') ?? $this->input('arquivo_id', 0));
        if ($arquivoId <= 0) {
            return false;
        }

        $arquivo = Arquivo::find($arquivoId);

        return $arquivo !== null && (int) $arquivo->business_id === (int) $businessId;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            // Override opcional — caller pode forçar bucket específico ao invés
            // de re-executar regras. Whitelist espelha config/arquivos.php buckets.
            'force_bucket' => ['nullable', 'string', Rule::in(['public', 'internal', 'sensitive', 'vault'])],

            // Motivo é OBRIGATÓRIO pra audit trail LGPD (reclassify altera
            // visibilidade/encryption — Auditor precisa rastrear "por que").
            'motivo' => ['required', 'string', 'min:5', 'max:500'],

            // Tag opcional pra categorizar (ex: "bulk_legacy_curador_v3").
            'batch_tag' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_\-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required'  => 'Motivo da reclassificação é obrigatório pra audit LGPD.',
            'motivo.min'       => 'Motivo precisa ter pelo menos 5 caracteres (rastreabilidade).',
            'force_bucket.in'  => 'Bucket inválido. Use: public, internal, sensitive ou vault.',
            'batch_tag.regex'  => 'batch_tag aceita apenas letras, números, _ e -.',
        ];
    }
}
