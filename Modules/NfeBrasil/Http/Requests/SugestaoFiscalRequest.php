<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sugestões da Jana na tributação (playbook Fiscal thread 10 · D-IA). Listar, pedir, aceitar e
 * descartar exigem a mesma permissão de quem mexe nas regras — aceitar aplica na tributação.
 */
class SugestaoFiscalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfe.tributacao.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id'        => ['nullable', 'integer', 'min:1'],
            'confirmou_leitura' => ['nullable', 'boolean'],
        ];
    }
}
