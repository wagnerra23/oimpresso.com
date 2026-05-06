<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfe.configuracao.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'certificado' => [
                'required',
                'file',
                'mimes:pfx,p12',
                'max:100', // KB
            ],
            // Senha em separado pra não passar em URL/log; max 80 segue padrão SEFAZ
            'senha' => ['required', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'certificado.required' => 'Selecione o arquivo .pfx do certificado A1.',
            'certificado.file'     => 'Arquivo de certificado inválido.',
            'certificado.mimes'    => 'O certificado deve ser .pfx ou .p12.',
            'certificado.max'      => 'Certificado muito grande (máx 100 KB — verifique se é um cert A1 e não A3).',
            'senha.required'       => 'Informe a senha do certificado.',
            'senha.max'            => 'Senha do certificado muito longa.',
        ];
    }
}
