<?php

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\PlanoConta;
use Modules\Financeiro\Models\Titulo;

/**
 * Validação do Edit Sheet de título financeiro (Onda Edit 2026-05-18).
 *
 * Campos sempre editáveis (R-FIN-008): cliente_descricao, observacoes,
 * categoria_id, vencimento — não afetam contabilidade.
 *
 * Campos com guard de imutabilidade (ADR fin-tech/0002): valor_total só pode
 * ser editado se status='aberto' OR 'parcial' (sem baixa registrada). Pós-baixa
 * (status='quitado' OR 'cancelado'), valor é READ-ONLY pra preservar histórico
 * contábil/fiscal.
 *
 * Campos NUNCA editáveis (anti-corrupção): tipo, origem, origem_id, status,
 * emissao, competencia_mes, business_id. Alterar requer cancelar+criar novo
 * (workflow append-only).
 */
class UpdateTituloRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission gate já aplicada no Controller::__construct via
        // can:financeiro.dashboard.view. Re-check explícito (defesa em profundidade).
        return $this->user()?->can('financeiro.dashboard.view') ?? false;
    }

    public function rules(): array
    {
        $businessId = (int) session('user.business_id');

        return [
            'cliente_descricao' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'categoria_id' => [
                'nullable', 'integer',
                Rule::exists((new Categoria)->getTable(), 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
            'plano_conta_id' => [
                'nullable', 'integer',
                Rule::exists((new PlanoConta)->getTable(), 'id')
                    ->where('business_id', $businessId)
                    ->where('ativo', true)
                    ->where('aceita_lancamento', true)
                    ->whereNull('deleted_at'),
            ],
            'vencimento' => ['required', 'date'],
            'valor_total' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'categoria_id.exists' => 'Categoria não pertence a este negócio.',
            'plano_conta_id.exists' => 'Plano de contas inválido (inexistente, inativo ou sintético).',
            'vencimento.required' => 'Vencimento obrigatório.',
            'valor_total.min' => 'Valor deve ser positivo.',
        ];
    }

    /**
     * Guard de imutabilidade: bloqueia valor_total se status quitado/cancelado.
     * Chamado pelo Controller após validação básica passar.
     */
    public function assertValorMutavel(Titulo $titulo): void
    {
        if (! $this->has('valor_total')) {
            return;
        }
        if (in_array($titulo->status, ['quitado', 'cancelado'], true)) {
            abort(422, "Valor de título {$titulo->status} é imutável (preserva histórico contábil).");
        }
    }

    /**
     * Defesa em profundidade: garante que `plano_conta_id` é coerente com `titulo.tipo`.
     * Plano de Contas BR (DCASP) tem natureza fixa por código:
     *   - receber → só plano tipo IN (receita, ativo)
     *   - pagar   → só plano tipo IN (despesa, custo, passivo)
     * Patrimônio fica de fora — não é título corrente, é encerramento exercício.
     *
     * Frontend já filtra o combobox, mas back-end re-valida (anti tampering).
     */
    public function assertPlanoCoerente(Titulo $titulo): void
    {
        if (! $this->filled('plano_conta_id')) {
            return;
        }

        $plano = PlanoConta::query()
            ->where('business_id', $titulo->business_id)
            ->find($this->input('plano_conta_id'));

        if (! $plano) {
            return; // exists rule já tratou — defesa redundante
        }

        $permitidos = $titulo->tipo === 'receber'
            ? ['receita', 'ativo']
            : ['despesa', 'custo', 'passivo'];

        if (! in_array($plano->tipo, $permitidos, true)) {
            abort(422, "Plano de contas '{$plano->codigo} {$plano->nome}' (tipo {$plano->tipo}) é incompatível com título tipo '{$titulo->tipo}'.");
        }
    }
}
