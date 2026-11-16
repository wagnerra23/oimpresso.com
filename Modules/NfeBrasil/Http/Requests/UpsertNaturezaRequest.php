<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formato da natureza enviada pela tela (protótipo aprovado, função `vazia()`). Valida só FORMA;
 * as regras fiscais são da conferência (`ConferenciaNaturezaService`), que devolve erro, aviso e
 * informação com o texto da tela.
 */
class UpsertNaturezaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfe.tributacao.manage') ?? false;
    }

    public function rules(): array
    {
        $pct = ['nullable', 'numeric', 'min:0', 'max:100'];
        $cod = fn (int $max) => ['nullable', 'string', "max:{$max}", 'regex:/^[0-9]*$/'];

        return [
            'id'    => ['nullable', 'integer'],
            'nome'  => ['present', 'nullable', 'string', 'max:80'],
            'tipo'  => ['required', Rule::in(['S', 'E'])],
            'fin'   => ['required', Rule::in(['1', '2', '3', '4'])],
            'ativo' => ['required', 'boolean'],
            'ref'   => ['required', 'boolean'],

            'cfop'     => ['required', 'array'],
            'cfop.in'  => ['nullable', 'string', 'max:4'],
            'cfop.out' => ['nullable', 'string', 'max:4'],
            'cfop.ext' => ['nullable', 'string', 'max:4'],

            'icms'            => ['required', 'array'],
            'icms.cst'        => $cod(2),
            'icms.csosn'      => $cod(3),
            'icms.origem'     => ['required', Rule::in(['uf', 'fixa', 'origem'])],
            'icms.p'          => $pct,
            'icms.modbc'      => ['nullable', Rule::in(['0', '1', '2', '3'])],
            'icms.red'        => $pct,
            'icms.pDif'       => $pct,
            'icms.pCredSN'    => $pct,
            'icms.bFrete'     => ['nullable', 'boolean'],
            'icms.bIPI'       => ['nullable', 'boolean'],
            'icms.bDesc'      => ['nullable', 'boolean'],
            'icms.difal'      => ['nullable', 'boolean'],
            'icms.fcp'        => ['nullable', 'boolean'],
            'icms.st'         => ['nullable', 'array'],
            'icms.st.modbcst' => ['nullable', Rule::in(['0', '1', '2', '3', '4', '5', '6'])],
            'icms.st.mvaOrig' => ['nullable', Rule::in(['produto', 'fixa'])],
            'icms.st.mva'     => ['nullable', 'numeric', 'min:0', 'max:500'],
            'icms.st.redB'    => $pct,

            'ipi'        => ['required', 'array'],
            'ipi.on'     => ['required', 'boolean'],
            'ipi.cst'    => $cod(2),
            'ipi.cEnq'   => $cod(3),
            'ipi.origem' => ['required', Rule::in(['produto', 'fixa', 'origem'])],
            'ipi.p'      => $pct,

            'pis'             => ['required', 'array'],
            'pis.cst'         => $cod(2),
            'pis.cstSN'       => $cod(2),
            'pis.origem'      => ['required', Rule::in(['regime', 'fixa', 'origem'])],
            'pis.p'           => $pct,
            'pis.exclICMS'    => ['nullable', 'boolean'],
            'cofins'          => ['required', 'array'],
            'cofins.cst'      => $cod(2),
            'cofins.cstSN'    => $cod(2),
            'cofins.origem'   => ['required', Rule::in(['regime', 'fixa', 'origem'])],
            'cofins.p'        => $pct,
            'cofins.exclICMS' => ['nullable', 'boolean'],

            'ibs'        => ['required', 'array'],
            'ibs.on'     => ['required', 'boolean'],
            'ibs.cst'    => $cod(3),
            'ibs.cClass' => ['nullable', 'string', 'max:6'],
            'ibs.origem' => ['required', Rule::in(['padrao', 'fixa'])],
            'ibs.pUF'    => $pct,
            'ibs.pMun'   => $pct,
            'ibs.pCBS'   => $pct,
            'ibs.pRed'   => $pct,
        ];
    }
}
