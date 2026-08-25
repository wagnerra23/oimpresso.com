<?php

declare(strict_types=1);

namespace Modules\Arquivos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest pra LIST/SEARCH de Arquivos (admin DataController).
 *
 * Wave 18 D8 SATURATION — formaliza contrato de filtros aceitos
 * (bucket, owner_type, mime, range datas, paginação).
 *
 * **Tier 0**: business_id resolve via sessão automaticamente (HasBusinessScope
 * global scope na Model Arquivo).
 *
 * **Hardening**: bucket allow-list, mime regex, per_page cap.
 */
class ListArquivosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $businessId = $this->session()->get('user.business_id');
        return ! empty($businessId);
    }

    public function rules(): array
    {
        return [
            'bucket'       => ['nullable', 'string', Rule::in(['public', 'internal', 'sensitive', 'vault'])],
            'owner_type'   => ['nullable', 'string', 'max:120'],
            'mime'         => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9\.\-\/\+]+$/'],
            'from'         => ['nullable', 'date_format:Y-m-d'],
            'to'           => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page'     => ['nullable', 'integer', 'between:1,100'],
            'q'            => ['nullable', 'string', 'max:255'],
            'with_trashed' => ['nullable', 'boolean'],

            // ── Onda 1 · PR-2 (trilha) ───────────────────────────────────────
            // Qual vista da tela está aberta. Vocabulário de URL do projeto é
            // `?tab=` (Financeiro, Fiscal/Dfe, Cliente) — não se inventa um
            // segundo nome pra mesma coisa.
            'tab'          => ['nullable', 'string', Rule::in(['acervo', 'trilha'])],

            // Ação do `arquivos_audit_log`. Validado por FORMA, não por lista:
            // o dono do vocabulário é o ENUM da coluna, que já mudou 2× por
            // migration (`signed_url_consumed` em 2026-07-02, `exported` em
            // 2026-08-10). Restatear a lista aqui seria repetir um fato que
            // outro sistema sabe melhor — e ela ficaria defasada calada na 3ª
            // migration. As opções REAIS que a tela oferece saem de um DISTINCT
            // do próprio log (ver `ArquivosAdminController::buildTrilhaPayload`),
            // então valor fora do enum simplesmente não tem chip pra clicar e,
            // se digitado na URL, devolve lista vazia — nunca 500.
            'acao'         => ['nullable', 'string', 'max:32', 'regex:/^[a-z_]+$/'],
        ];
    }

    /**
     * Defaults idempotentes pra paginação.
     *
     * @return array{per_page:int}
     */
    public function pageDefaults(): array
    {
        return [
            'per_page' => (int) $this->input('per_page', 25),
        ];
    }
}
