<?php

namespace Modules\Financeiro\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\PlanoConta;

/**
 * Validação do Insert manual de título financeiro (Onda 25, 2026-05-25, US-FIN-021).
 *
 * Substitui o stub `/unificado/novo` (Non-Goal #1 do charter v6) por form
 * inline drawer no próprio /financeiro/unificado.
 *
 * Campos obrigatórios:
 *   - tipo: 'receber' OR 'pagar'
 *   - valor_total: > 0
 *   - vencimento: date >= hoje OR retroativo (skill multi-tenant-patterns —
 *     títulos retroativos via boleto OCR ou DFe importer também valem)
 *
 * Campos opcionais:
 *   - cliente_descricao (texto livre — quando ainda não há FK contacts.id)
 *   - observacoes
 *   - categoria_id (scope business)
 *   - plano_conta_id (scope business + ativo + aceita_lancamento + coerência tipo)
 *
 * Defesa em profundidade tipo↔plano_conta.tipo via `assertPlanoCoerente()`.
 * Mesma lógica usada em UpdateTituloRequest (DRY).
 */
class StoreTituloRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission gate já aplicada no Controller::__construct via
        // can:financeiro.dashboard.view. Re-check (defesa em profundidade).
        return $this->user()?->can('financeiro.dashboard.view') ?? false;
    }

    public function rules(): array
    {
        $businessId = (int) session('user.business_id');

        return [
            'tipo' => ['required', Rule::in(['receber', 'pagar'])],
            'valor_total' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'vencimento' => ['required', 'date'],
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
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Tipo obrigatório (receber ou pagar).',
            'tipo.in' => 'Tipo inválido (deve ser "receber" ou "pagar").',
            'valor_total.required' => 'Valor obrigatório.',
            'valor_total.min' => 'Valor deve ser positivo.',
            'vencimento.required' => 'Vencimento obrigatório.',
            'categoria_id.exists' => 'Categoria não pertence a este negócio.',
            'plano_conta_id.exists' => 'Plano de contas inválido (inexistente, inativo ou sintético).',
        ];
    }

    /**
     * Defesa em profundidade: garante que `plano_conta_id` é coerente com `tipo`.
     * Diferente do Update (que tem $titulo já carregado), aqui usa o tipo do
     * próprio request — chamado pelo Controller após validação básica passar.
     */
    public function assertPlanoCoerente(): void
    {
        if (! $this->filled('plano_conta_id')) {
            return;
        }

        $businessId = (int) session('user.business_id');
        $plano = PlanoConta::query()
            ->where('business_id', $businessId)
            ->find($this->input('plano_conta_id'));

        if (! $plano) {
            return; // exists rule já tratou
        }

        $permitidos = $this->input('tipo') === 'receber'
            ? ['receita', 'ativo']
            : ['despesa', 'custo', 'passivo'];

        if (! in_array($plano->tipo, $permitidos, true)) {
            // PR D — G12 audit log violação coerência (mesmo padrão Update).
            Log::warning('financeiro.plano_coerencia.violada', [
                'route'        => 'store',
                'business_id'  => $businessId,
                'tipo_titulo'  => $this->input('tipo'),
                'plano_id'     => $plano->id,
                'plano_codigo' => $plano->codigo,
                'plano_tipo'   => $plano->tipo,
                'user_id'      => $this->user()?->id,
                'ip'           => $this->ip(),
            ]);
            abort(422, "Plano de contas '{$plano->codigo} {$plano->nome}' (tipo {$plano->tipo}) é incompatível com título tipo '{$this->input('tipo')}'.");
        }
    }
}
