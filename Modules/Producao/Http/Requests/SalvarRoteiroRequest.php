<?php

namespace Modules\Producao\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Regra do UC-PCV-13 (Producao.casos.md): o roteiro aceita até 2 trilhas em paralelo, e elas
 * sempre terminam numa etapa de junção nomeada. Uma 3ª trilha ou trilha sem junção é recusada
 * ao salvar. A junção é a etapa em sequência logo depois do bloco, com o nome que o bloco declara.
 *
 * Formato de `etapas` (o mesmo do protótipo `producao-cv-data.jsx`):
 *   { nome, posto_id?, horas?, opcional_por? }        etapa em sequência
 *   { paralelo: [[etapa…], [etapa…]], junta: "nome" } bloco de 2 trilhas
 */
class SalvarRoteiroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('producao.access');
    }

    public function rules(): array
    {
        $biz = (int) $this->session()->get('user.business_id');
        $posto = Rule::exists('pcp_postos', 'id')->where('business_id', $biz);

        return [
            'familia' => ['required', 'string', 'max:80'],
            'nome' => ['required', 'string', 'max:120'],
            'etapas' => ['required', 'array', 'min:1'],
            'etapas.*' => ['array'],
            'etapas.*.nome' => ['nullable', 'string', 'max:120'],
            'etapas.*.posto_id' => ['nullable', 'integer', $posto],
            'etapas.*.horas' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'etapas.*.opcional_por' => ['nullable', 'string', 'max:80'],
            'etapas.*.junta' => ['nullable', 'string', 'max:120'],
            'etapas.*.paralelo' => ['nullable', 'array'],
            'etapas.*.paralelo.*' => ['array', 'min:1'],
            'etapas.*.paralelo.*.*.nome' => ['required', 'string', 'max:120'],
            'etapas.*.paralelo.*.*.posto_id' => ['nullable', 'integer', $posto],
            'etapas.*.paralelo.*.*.horas' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'etapas.*.paralelo.*.*.opcional_por' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $etapas = array_values((array) $this->input('etapas', []));
            foreach ($etapas as $i => $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (! array_key_exists('paralelo', $item)) {
                    if (blank($item['nome'] ?? null)) {
                        $v->errors()->add("etapas.$i.nome", 'Toda etapa precisa de nome.');
                    }

                    continue;
                }
                $trilhas = (array) $item['paralelo'];
                if (count($trilhas) > 2) {
                    $v->errors()->add("etapas.$i.paralelo", 'No máximo 2 trilhas em paralelo.');
                } elseif (count($trilhas) < 2) {
                    $v->errors()->add("etapas.$i.paralelo", 'Um bloco em paralelo tem 2 trilhas.');
                }
                foreach ($trilhas as $t => $trilha) {
                    foreach ((array) $trilha as $passo) {
                        if (is_array($passo) && array_key_exists('paralelo', $passo)) {
                            $v->errors()->add("etapas.$i.paralelo.$t", 'Trilha não abre outro paralelo.');
                        }
                    }
                }
                $junta = trim((string) ($item['junta'] ?? ''));
                $seguinte = $etapas[$i + 1] ?? null;
                if ($junta === '') {
                    $v->errors()->add("etapas.$i.junta", 'As trilhas precisam se juntar numa etapa nomeada.');
                } elseif (! is_array($seguinte) || array_key_exists('paralelo', $seguinte)
                    || trim((string) ($seguinte['nome'] ?? '')) !== $junta) {
                    $v->errors()->add("etapas.$i.junta", "A etapa logo depois do paralelo tem de ser \"$junta\".");
                }
            }
        });
    }
}
