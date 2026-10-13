<?php

declare(strict_types=1);

namespace Modules\NFSe\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave S — FormRequest extraído de NfseController::store (US-NFSE-006).
 *
 * Regras fiscais ABRASF/Município (NFSe Nacional) preservadas integralmente:
 *   - competencia: Y-m (mês/ano fiscal).
 *   - lc116_codigo: código LC 116/2003 (até 5 chars, ex "1.04").
 *   - aliquota_iss: fração 0..1 (ex 0.05 = 5%).
 *   - tomador: nome obrigatório; CNPJ/CPF/email opcionais (depende prefeitura).
 *
 * NÃO relaxar — compliance fiscal. authorize via gate `nfse.emit` (espelhado do
 * `$this->authorize('nfse.emit')` original).
 */
class StoreNfseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfse.emit') ?? false;
    }

    public function rules(): array
    {
        return [
            'competencia'     => ['required', 'date_format:Y-m'],
            'tomador_nome'    => ['required', 'string', 'max:150'],
            'tomador_cnpj'    => ['nullable', 'string'],
            'tomador_cpf'     => ['nullable', 'string'],
            'tomador_email'   => ['nullable', 'email'],
            'descricao'       => ['required', 'string', 'max:2000'],
            'lc116_codigo'    => ['required', 'string', 'max:5'],
            'valor_servicos'  => ['required', 'numeric', 'min:0.01'],
            'aliquota_iss'    => ['required', 'numeric', 'min:0', 'max:1'],
            'iss_retido'      => ['boolean'],
            // R-NFSE-003: quem emite declara que o serviço é do art. 30 da Lei 10.833/2003.
            'retencao_federal_sujeito' => ['boolean'],
            'transaction_id'  => ['nullable', 'integer'],
            // R-NFSE-006: desdobro nacional escolhido quando o subitem tem mais de um.
            'ctrib_nac'       => ['nullable', 'digits:6'],
        ];
    }

    /**
     * R-NFSE-006 — o código LC 116 tem de estar na lista de serviços nacional e, se o
     * subitem tem mais de um desdobro, a escolha é obrigatória e precisa ser dele.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            if ($v->errors()->hasAny(['lc116_codigo', 'ctrib_nac'])) {
                return;
            }
            $lista = app(\Modules\NfeBrasil\Services\Nfse\ListaServicoNacional::class);
            $lc116 = (string) $this->input('lc116_codigo');
            if ($lista->desdobros($lc116) === []) {
                $v->errors()->add('lc116_codigo', "Código LC 116 {$lc116} não está na lista de serviços nacional.");

                return;
            }
            try {
                $lista->resolver($lc116, $this->input('ctrib_nac'));
            } catch (\InvalidArgumentException $e) {
                $v->errors()->add('ctrib_nac', $e->getMessage());
            }
        });
    }
}
