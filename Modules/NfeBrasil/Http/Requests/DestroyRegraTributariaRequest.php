<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DELETE /nfe-brasil/tributacao/regras/{id} — mesma permissão do store/update
 * (`UpsertRegraTributariaRequest`). Apagar uma regra NCM faz o item cair na
 * tributação default da cascade (ADR arq/0006), então quem não pode criar a
 * regra também não pode apagá-la. UC-NFRF-04 (RegraForm.casos.md).
 */
class DestroyRegraTributariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfe.tributacao.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
