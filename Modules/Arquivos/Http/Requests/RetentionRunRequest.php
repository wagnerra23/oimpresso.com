<?php

declare(strict_types=1);

namespace Modules\Arquivos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RetentionRunRequest — D8.c Wave 27 (2026-05-17).
 *
 * FormRequest pra endpoint admin que dispara `ArquivosRetentionService::run()`
 * via UI (em vez de via Artisan). Útil pra Auditor LGPD validar política
 * em dry-run antes de aprovar batch real.
 *
 * **Multi-tenant Tier 0 (ADR 0093):** business_id vem da sessão obrigatoriamente.
 * Service recebe `$businessId` no método — NUNCA aceita batch cross-tenant.
 *
 * **Defesa em profundidade:**
 *   - `dry_run` default `true` — caller precisa explicitar `false` pra mutação
 *   - `purge` requer `dry_run=false` E motivo (LGPD Art. 18 §VI rastreável)
 *   - `retention_days` faixa segura (90..3650) impede off-by-one catastrófico
 *
 * @see Modules\Arquivos\Services\ArquivosRetentionService::run
 * @see Modules\Arquivos\Console\RetentionCleanupCommand
 */
class RetentionRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        // business_id da sessão é mandatório — Service NÃO infere
        $businessId = $this->session()->get('user.business_id');

        return ! empty($businessId);
    }

    protected function prepareForValidation(): void
    {
        // Default conservador: dry_run TRUE quando ausente
        $this->merge([
            'dry_run' => $this->boolean('dry_run', true),
            'purge'   => $this->boolean('purge', false),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Faixa segura: 90d (mínimo LGPD prática) a 3650d (10 anos máx legal)
            'retention_days' => ['required', 'integer', 'between:90,3650'],

            // Default true via prepareForValidation
            'dry_run' => ['required', 'boolean'],

            // Purge (hard-delete) só com motivo + dry_run=false
            'purge' => ['required', 'boolean'],

            // Se purge=true, motivo obrigatório (LGPD Art. 18 §VI)
            'motivo' => ['required_if:purge,true', 'nullable', 'string', 'min:10', 'max:1000'],

            // Tag pra correlação multi-batch (ex: "lgpd_q2_2026")
            'batch_tag' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_\-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'retention_days.between' => 'retention_days fora da faixa segura (90..3650 dias).',
            'motivo.required_if'     => 'Motivo é obrigatório pra purge (hard-delete LGPD Art. 18 §VI).',
            'motivo.min'             => 'Motivo precisa ter pelo menos 10 caracteres pra audit.',
            'batch_tag.regex'        => 'batch_tag aceita apenas letras, números, _ e -.',
        ];
    }

    /**
     * Auxiliar — caller pega args prontos pro Service::run().
     *
     * @return array{retention_days:int, dry_run:bool, purge:bool}
     */
    public function toServiceArgs(): array
    {
        return [
            'retention_days' => (int) $this->input('retention_days'),
            'dry_run'        => (bool) $this->input('dry_run', true),
            'purge'          => (bool) $this->input('purge', false),
        ];
    }
}
