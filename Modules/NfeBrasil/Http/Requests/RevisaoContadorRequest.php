<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\NfeBrasil\Services\Tributacao\RevisaoContadorService;

/**
 * Revisão do contador (playbook Fiscal thread 15a). Só quem tem `nfe.tributacao.aceitar` — checado
 * direto no Spatie, sem `can()`: o motivo (o `Gate::before` liberaria o `Admin#`) está em
 * `RevisaoContadorService::podeAceitar`.
 */
class RevisaoContadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RevisaoContadorService::podeAceitar($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'comentario' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
