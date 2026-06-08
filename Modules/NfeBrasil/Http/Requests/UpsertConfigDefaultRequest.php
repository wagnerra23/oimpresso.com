<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertConfigDefaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfe.tributacao.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'regime' => ['required', Rule::in(['mei', 'simples', 'lucro_presumido', 'lucro_real'])],

            'ncm_default'      => ['required', 'string', 'size:8', 'regex:/^[0-9]{8}$/'],
            'cfop_default'     => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],

            'csosn'     => ['nullable', 'string', 'size:3', 'regex:/^[0-9]{3}$/', 'required_without:cst'],
            'cst'       => ['nullable', 'string', 'size:3', 'regex:/^[0-9]{3}$/', 'required_without:csosn'],

            'aliquota_icms'   => ['required', 'numeric', 'min:0', 'max:1'],
            'aliquota_pis'    => ['required', 'numeric', 'min:0', 'max:1'],
            'aliquota_cofins' => ['required', 'numeric', 'min:0', 'max:1'],
            'aliquota_ipi'    => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ncm_default.regex'  => 'NCM padrão deve ter 8 dígitos (ex: 49019900).',
            'cfop_default.regex' => 'CFOP padrão deve ter 4 dígitos (ex: 5102).',
            'csosn.required_without' => 'Informe CSOSN (Simples) ou CST (Regime Normal).',
            'cst.required_without'   => 'Informe CSOSN (Simples) ou CST (Regime Normal).',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->filled('csosn') && $this->filled('cst')) {
                $v->errors()->add(
                    'csosn',
                    'CSOSN e CST não podem ser preenchidos juntos.'
                );
            }
        });
    }
}
