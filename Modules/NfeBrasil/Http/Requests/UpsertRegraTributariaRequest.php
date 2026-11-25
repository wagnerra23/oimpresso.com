<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;

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

            // R-NFE-025b: CST de saída do PIS/COFINS. Vazio = a nota sai com 07, como antes.
            'cst_pis'    => ['nullable', 'string', Rule::in(ImportRegrasCsvService::CST_PIS_COFINS_SAIDA)],
            'cst_cofins' => ['nullable', 'string', Rule::in(ImportRegrasCsvService::CST_PIS_COFINS_SAIDA)],

            'mva' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'fcp' => ['nullable', 'numeric', 'min:0', 'max:1'],

            // Benefício fiscal (thread 25 · R-NFE-030). Redução em decimal (0.3333 = 33,33%),
            // menor que 1 (redução de 100% é isenção, outro CST). Lei 4 do módulo: benefício
            // só com o ato legal citado — o fundamento é obrigatório junto da redução ou do código.
            'reducao_base'         => ['nullable', 'numeric', 'min:0', 'lt:1'],
            'c_benef'              => ['nullable', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'],
            'beneficio_fundamento' => ['nullable', 'string', 'max:255', 'required_with:reducao_base,c_benef'],

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

            if ($v->errors()->isEmpty() && ($existente = $this->regraConcorrente()) !== null) {
                $destino = $existente->uf_destino ?? 'todas as UFs';
                $v->errors()->add(
                    'ncm',
                    "Já existe uma regra para o NCM {$existente->ncm} de {$existente->uf_origem} para {$destino}. "
                    . 'Edite a regra existente em vez de criar outra: duas regras com a mesma chave competem na emissão.'
                );
            }
        });
    }

    /**
     * UC-NFRF-10 · outra regra vigente com a mesma chave do motor (NCM + UF origem + UF destino,
     * no mesmo regime e na mesma operação). Com duas, o `MotorTributarioService::buscarRegra`
     * pega a primeira pelo `id`, e a que vale dependeria da ordem de cadastro.
     *
     * Na edição, regime e operação são os da regra editada (o form não muda os dois). Regra que
     * não é do tenant devolve nulo aqui, e o controller responde 404 como antes.
     */
    private function regraConcorrente(): ?NfeFiscalRule
    {
        $businessId = (int) $this->session()->get('business.id');
        $editandoId = $this->route('id') !== null ? (int) $this->route('id') : null;
        $versionado = NfeFiscalRule::temVersionamento();
        $porRegime = NfeFiscalRule::temRegraPorRegime();

        $crt = null;
        $operacaoId = null;
        if ($editandoId !== null) {
            $editada = NfeFiscalRule::where('business_id', $businessId)->where('id', $editandoId)->first();
            if ($editada === null) {
                return null;
            }
            $crt = $porRegime ? $editada->crt : null;
            $operacaoId = $versionado ? $editada->operacao_id : null;
        }

        $ufDestino = $this->input('uf_destino') ?: null;
        $hoje = now()->toDateString();

        return NfeFiscalRule::where('business_id', $businessId)
            ->where('ncm', (string) $this->input('ncm'))
            ->where('uf_origem', (string) $this->input('uf_origem'))
            ->when(
                $ufDestino === null,
                fn ($q) => $q->whereNull('uf_destino'),
                fn ($q) => $q->where('uf_destino', $ufDestino),
            )
            ->when($editandoId !== null, fn ($q) => $q->where('id', '!=', $editandoId))
            ->when($porRegime, fn ($q) => $crt === null ? $q->whereNull('crt') : $q->where('crt', $crt))
            ->when($versionado, fn ($q) => $q
                ->where(fn ($w) => $operacaoId === null ? $w->whereNull('operacao_id') : $w->where('operacao_id', $operacaoId))
                ->where(fn ($w) => $w->whereNull('valida_ate')->orWhere('valida_ate', '>=', $hoje)))
            ->orderBy('id')
            ->first();
    }
}
