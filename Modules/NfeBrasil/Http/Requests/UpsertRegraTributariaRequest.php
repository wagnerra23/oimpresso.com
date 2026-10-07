<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertRegraTributariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfe.tributacao.manage') ?? false;
    }

    /** UFs brasileiras pra valida UF origem/destino. */
    private const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
        'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
        'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public function rules(): array
    {
        return [
            'ncm'       => ['required', 'string', 'size:8', 'regex:/^[0-9]{8}$/'],
            'uf_origem' => ['required', 'string', 'size:2', Rule::in(self::UFS)],
            'uf_destino' => ['nullable', 'string', 'size:2', Rule::in(self::UFS)],
            'cfop'      => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],

            // CSOSN OU CST — exclusivo por regime (CRT 1 vs CRT 3)
            'csosn'     => ['nullable', 'string', 'size:3', 'regex:/^[0-9]{3}$/', 'required_without:cst'],
            'cst'       => ['nullable', 'string', 'size:3', 'regex:/^[0-9]{3}$/', 'required_without:csosn'],

            'aliquota_icms'   => ['required', 'numeric', 'min:0', 'max:1'],
            'aliquota_pis'    => ['required', 'numeric', 'min:0', 'max:1'],
            'aliquota_cofins' => ['required', 'numeric', 'min:0', 'max:1'],
            'aliquota_ipi'    => ['required', 'numeric', 'min:0', 'max:1'],

            'mva' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'fcp' => ['nullable', 'numeric', 'min:0', 'max:1'],

            // Reforma Tributária — NT 2025.002 / US-FISCAL-021. As 5 colunas existem desde a
            // migration 2026_05_26_000001 e o motor já as lê; sem declarar aqui, o `validated()`
            // do store/update as descartava em silêncio. Só FORMATO: obrigatoriedade por regime
            // é regra fiscal com data (D-OPERACAO), fora desta validação.
            'c_class_trib' => ['nullable', 'string', 'size:6', 'regex:/^[0-9]{6}$/', 'required_with:cst_ibs,cst_cbs'],
            'cst_ibs'      => ['nullable', 'string', 'size:3', 'regex:/^[0-9]{3}$/', 'required_with:c_class_trib'],
            'cst_cbs'      => ['nullable', 'string', 'size:3', 'regex:/^[0-9]{3}$/', 'required_with:c_class_trib'],
            'aliquota_ibs' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'aliquota_cbs' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ncm.regex'       => 'NCM deve ter exatamente 8 dígitos.',
            'cfop.regex'      => 'CFOP deve ter exatamente 4 dígitos.',
            'csosn.required_without' => 'Informe CSOSN (Simples) ou CST (Regime Normal).',
            'cst.required_without'   => 'Informe CSOSN (Simples) ou CST (Regime Normal).',
            'aliquota_icms.max'      => 'Alíquota é decimal (0.18 = 18%) — máximo 1 (100%).',
            'aliquota_pis.max'       => 'Alíquota é decimal (0.0065 = 0,65%) — máximo 1.',
            'aliquota_cofins.max'    => 'Alíquota é decimal (0.03 = 3%) — máximo 1.',
            'aliquota_ipi.max'       => 'Alíquota é decimal — máximo 1.',
            'c_class_trib.regex'     => 'cClassTrib deve ter exatamente 6 dígitos.',
            'c_class_trib.size'      => 'cClassTrib deve ter exatamente 6 dígitos.',
            'c_class_trib.required_with' => 'Informe o cClassTrib junto com o CST de IBS/CBS.',
            'cst_ibs.regex'          => 'CST do IBS deve ter exatamente 3 dígitos.',
            'cst_ibs.size'           => 'CST do IBS deve ter exatamente 3 dígitos.',
            'cst_ibs.required_with'  => 'Informe o CST do IBS junto com o cClassTrib.',
            'cst_cbs.regex'          => 'CST da CBS deve ter exatamente 3 dígitos.',
            'cst_cbs.size'           => 'CST da CBS deve ter exatamente 3 dígitos.',
            'cst_cbs.required_with'  => 'Informe o CST da CBS junto com o cClassTrib.',
            'aliquota_ibs.max'       => 'Alíquota é decimal (0.001 = 0,1%) — máximo 1.',
            'aliquota_cbs.max'       => 'Alíquota é decimal (0.009 = 0,9%) — máximo 1.',
        ];
    }

    /** CSOSN e CST são mutualmente exclusivos. */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->filled('csosn') && $this->filled('cst')) {
                $v->errors()->add(
                    'csosn',
                    'CSOSN e CST não podem ser preenchidos juntos — escolha um conforme o regime.'
                );
            }
        });
    }
}
