<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreMetaRequest — criação de Meta via POST /jana/metas.
 *
 * D8.c (Wave 14 governance v3) — substitui `$request->validate([...])` inline
 * do MetasController@store, padroniza mensagens PT-BR e endurece regras:
 *  - business_id NULLABLE permite meta da plataforma (superadmin only)
 *  - slug regex `[a-z0-9_-]+` previne injection em rotas
 *  - whitelist explícita em unidade/tipo_agregacao (fail-secure)
 *
 * Multi-tenant Tier 0 (ADR 0093): se business_id veio no payload, controller
 * verifica que matcha session OU user é superadmin antes de persistir.
 *
 * Autorização: any auth user pode submeter; refinements (ex: só perfis com
 * `jana.metas.create`) ficam em policy posterior — request só valida forma.
 */
class StoreMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\-]+$/'],
            'nome' => ['required', 'string', 'max:150'],
            'unidade' => ['required', Rule::in(['R$', 'qtd', '%', 'dias'])],
            'tipo_agregacao' => ['required', Rule::in(['soma', 'media', 'ultimo', 'contagem'])],
            'business_id' => ['nullable', 'integer', 'min:1'],

            // ---- ALVO (opcional no contrato, obrigatorio na pratica) -------------
            //
            // POR QUE ENTROU AQUI (2026-09-21). Ate esta data o `store` criava SO a
            // `Meta`, enquanto o caminho via IA (`ChatController@escolher`) criava
            // `Meta` + `MetaPeriodo` + `MetaFonte` e ainda despachava o `ApurarMetaJob`.
            // O resultado, medido em producao, foram 5 metas com ZERO periodo, ZERO
            // apuracao e ZERO fonte — os 5 cards do Painel saiam identicos, em
            // "Aguardando apuracao...", sem valor, sem barra e sem projecao. Nao era
            // cadastro interrompido pelo usuario: sem campo de alvo no request, criar
            // uma meta completa por aqui era IMPOSSIVEL.
            //
            // As regras sao as MESMAS do `StorePeriodoRequest` de proposito — os dois
            // alimentam a mesma tabela, e divergir faria a validacao depender da porta
            // de entrada. Se um dia mudarem, mudam juntos.
            //
            // NULLABLE, e nao required, por retrocompatibilidade medida: o form Blade
            // legado (`metas/create.blade.php`) manda so os 4 campos de identidade, e
            // torna-los obrigatorios aqui devolveria 422 pra ele. O drawer do Painel
            // (caminho novo) manda o alvo sempre. O cutover do Blade e o PR-4 do
            // RUNBOOK-metas §9.4.
            //
            // `required_with` amarra os tres entre si: ou vem o alvo inteiro, ou nao
            // vem nada. Meia-declaracao (alvo sem janela, ou janela sem alvo) e
            // exatamente o estado quebrado que este bloco existe pra impedir.
            'valor_alvo' => ['nullable', 'numeric', 'min:0', 'required_with:data_ini,data_fim'],
            'data_ini' => ['nullable', 'date', 'required_with:valor_alvo,data_fim'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_ini', 'required_with:valor_alvo,data_ini'],
            'tipo_periodo' => ['nullable', Rule::in(['mes', 'trim', 'ano', 'custom'])],
            'trajetoria' => ['nullable', Rule::in(['linear', 'sazonal', 'exponencial', 'manual'])],
        ];
    }

    /** O alvo veio inteiro? (os tres campos que o `MetaPeriodo` exige) */
    public function temAlvo(): bool
    {
        $d = $this->validated();

        return isset($d['valor_alvo'], $d['data_ini'], $d['data_fim']);
    }

    public function messages(): array
    {
        return [
            'slug.required' => 'O slug é obrigatório.',
            'slug.regex' => 'O slug aceita apenas letras minúsculas, números, hífen e underline.',
            'nome.required' => 'Informe o nome da meta.',
            'unidade.in' => 'Unidade inválida. Use R$, qtd, % ou dias.',
            'tipo_agregacao.in' => 'Tipo de agregação inválido. Use soma, media, ultimo ou contagem.',
            'valor_alvo.required_with' => 'Informe o valor alvo — sem ele a meta não tem o que comparar.',
            'valor_alvo.numeric' => 'O valor alvo precisa ser um número.',
            'data_ini.required_with' => 'Informe a data inicial da janela.',
            'data_fim.required_with' => 'Informe a data final da janela.',
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'tipo_periodo.in' => 'Tipo de período inválido. Use mes, trim, ano ou custom.',
        ];
    }
}
