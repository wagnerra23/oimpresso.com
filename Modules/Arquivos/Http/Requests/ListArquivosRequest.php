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
