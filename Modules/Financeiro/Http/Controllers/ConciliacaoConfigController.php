<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Financeiro\Models\CartaoAdquirente;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\ConciliacaoRegra;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\PlanoConta;

/**
 * Painel "Regras e maquininha" da Conciliação (US-FIN-080 + US-FIN-083).
 *
 * Cadastra as regras de conciliação e a maquininha padrão. Nada aqui concilia nem calcula:
 * quem aplica é o `sugerirMatches` (upload ou "Aplicar regras"), e só com a flag ligada.
 * Tier 0: toda escrita é do business da sessão, e todo id de alvo (conta, plano, categoria)
 * é conferido contra esse business antes de gravar.
 */
class ConciliacaoConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:financeiro.conciliacao.manage');
    }

    public function storeRegra(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $dados = $this->validarRegra($request, $businessId);

        ConciliacaoRegra::create($dados + [
            'business_id' => $businessId,
            'created_by'  => $request->user()?->id,
        ]);

        return back()->with('success', 'Regra criada.');
    }

    public function updateRegra(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $regra = ConciliacaoRegra::where('business_id', $businessId)->findOrFail($id);

        if ($request->has('ativo') && count($request->all()) === 1) {
            $regra->update(['ativo' => $request->boolean('ativo')]);
        } else {
            $regra->update($this->validarRegra($request, $businessId));
        }

        return back()->with('success', 'Regra atualizada.');
    }

    public function destroyRegra(int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        ConciliacaoRegra::where('business_id', $businessId)->findOrFail($id)->delete();

        return back()->with('success', 'Regra removida.');
    }

    /** Cria ou atualiza a maquininha padrão do business. */
    public function salvarMaquininha(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $dados = $request->validate([
            'nome'               => 'required|string|max:80',
            'descricao_extrato'  => 'required|string|min:3|max:80',
            'conta_bancaria_id'  => ['nullable', 'integer', Rule::exists('fin_contas_bancarias', 'id')->where('business_id', $businessId)],
            'taxa_debito'        => 'required|numeric|min:0|max:30',
            'prazo_debito_dias'  => 'required|integer|min:0|max:60',
            'taxa_credito'       => 'required|numeric|min:0|max:30',
            'prazo_credito_dias' => 'required|integer|min:0|max:120',
        ]);

        $adq = CartaoAdquirente::where('business_id', $businessId)->where('padrao', true)->first();
        $adq
            ? $adq->update($dados)
            : CartaoAdquirente::create($dados + [
                'business_id' => $businessId, 'padrao' => true, 'ativo' => true, 'created_by' => $request->user()?->id,
            ]);

        return back()->with('success', 'Maquininha salva.');
    }

    /** @return array<string,mixed> */
    private function validarRegra(Request $request, int $businessId): array
    {
        $dados = $request->validate([
            'nome'              => 'required|string|max:120',
            'descricao_contem'  => 'nullable|string|max:120',
            'sentido'           => 'nullable|in:C,D',
            'valor_min'         => 'nullable|numeric|min:0',
            'valor_max'         => 'nullable|numeric|min:0|gte:valor_min',
            'conta_bancaria_id' => ['nullable', 'integer', Rule::exists('fin_contas_bancarias', 'id')->where('business_id', $businessId)],
            'plano_conta_id'    => ['nullable', 'integer', Rule::exists('fin_planos_conta', 'id')->where('business_id', $businessId)],
            'categoria_id'      => ['nullable', 'integer', Rule::exists('fin_categorias', 'id')->where('business_id', $businessId)],
            'acao'              => 'required|in:sugerir,conciliar',
            'prioridade'        => 'nullable|integer|min:1|max:999',
        ]);

        // Regra sem nenhum critério casaria qualquer linha com qualquer título de mesmo valor.
        if (trim((string) ($dados['descricao_contem'] ?? '')) === ''
            && empty($dados['plano_conta_id']) && empty($dados['categoria_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'descricao_contem' => 'Informe um trecho da descrição ou um plano de contas / categoria.',
            ]);
        }

        return $dados + ['prioridade' => $dados['prioridade'] ?? 100];
    }

    /**
     * Dados do painel (prop deferida `config` da Conciliação). Business da sessão.
     *
     * @return array<string,mixed>
     */
    public static function painel(int $businessId): array
    {
        $regras = ConciliacaoRegra::where('business_id', $businessId)
            ->orderBy('prioridade')->orderBy('id')
            ->get(['id', 'nome', 'ativo', 'prioridade', 'descricao_contem', 'sentido', 'valor_min', 'valor_max',
                'conta_bancaria_id', 'plano_conta_id', 'categoria_id', 'acao']);

        $adq = CartaoAdquirente::where('business_id', $businessId)->where('padrao', true)->first();

        $recebiveis = ['previstos' => 0, 'previsto_liquido' => 0.0, 'recebidos_30d' => 0, 'taxa_30d' => 0.0];
        if (\Illuminate\Support\Facades\Schema::hasTable('fin_cartao_recebiveis')) {
            $base = fn () => \Modules\Financeiro\Models\CartaoRecebivel::where('business_id', $businessId);
            $recebiveis = [
                'previstos'        => (clone $base())->where('status', 'previsto')->count(),
                'previsto_liquido' => round((float) (clone $base())->where('status', 'previsto')->sum('valor_liquido'), 2),
                'recebidos_30d'    => (clone $base())->where('status', 'recebido')->where('recebido_em', '>=', now()->subDays(30)->toDateString())->count(),
                'taxa_30d'         => round((float) (clone $base())->where('status', '!=', 'cancelado')->where('data_venda', '>=', now()->subDays(30)->toDateString())->sum('valor_taxa'), 2),
            ];
        }

        return [
            'regras_ligadas'     => (bool) config('financeiro.conciliacao.regras_enabled', false),
            'cartao_ligado'      => (bool) config('financeiro.cartao.recebiveis_enabled', false),
            'regras'             => $regras,
            'planos'             => PlanoConta::where('business_id', $businessId)->orderBy('codigo')->get(['id', 'codigo', 'nome']),
            'categorias'         => Categoria::where('business_id', $businessId)->orderBy('nome')->get(['id', 'nome']),
            'contas'             => ContaBancaria::where('business_id', $businessId)->with('account:id,name')->get(['id', 'account_id'])
                ->map(fn ($c) => ['id' => $c->id, 'nome' => $c->nome])->values(),
            'maquininha'         => $adq?->only(['nome', 'descricao_extrato', 'conta_bancaria_id', 'taxa_debito',
                'prazo_debito_dias', 'taxa_credito', 'prazo_credito_dias']),
            'recebiveis'         => $recebiveis,
        ];
    }
}
