<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRegrasCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfe.tributacao.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5 MB max
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.required' => 'Selecione um arquivo CSV pra importar.',
            'arquivo.file'     => 'Arquivo inválido.',
            'arquivo.mimes'    => 'O arquivo deve ser .csv ou .txt.',
            'arquivo.max'      => 'Arquivo muito grande (máx 5 MB).',
        ];
    }
}
