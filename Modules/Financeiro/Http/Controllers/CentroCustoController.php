<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\CentroCusto;

/**
 * Cadastro de centros de custo da empresa (US-FIN-075). Tela: Financeiro/CentrosCusto/Index.
 *
 * Multi-tenant: toda leitura e escrita filtra `business_id` da sessão explicitamente, além do
 * BusinessScope do model. Centro com título rateado não é excluído (o model recusa): inativa.
 */
class CentroCustoController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:financeiro.dashboard.view')->only('index');
        // Mesma régua das categorias: cadastrar o que classifica lançamento é parte de lançar.
        $this->middleware('can:financeiro.lancamentos.create')->except('index');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');
        $mes = now()->format('Y-m');

        return Inertia::render('Financeiro/CentrosCusto/Index', [
            'mes' => $mes,
            'centros' => fn () => $this->centros($businessId, $mes),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $dados = $this->validar($request, $businessId);

        CentroCusto::query()->create($dados + ['business_id' => $businessId, 'ativo' => true]);

        return back()->with('success', 'Centro de custo criado.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $centro = CentroCusto::query()->where('business_id', $businessId)->findOrFail($id);

        $centro->update($this->validar($request, $businessId, $centro->id));

        return back()->with('success', 'Centro de custo atualizado.');
    }

    public function toggleAtivo(int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $centro = CentroCusto::query()->where('business_id', $businessId)->findOrFail($id);
        $centro->ativo = ! $centro->ativo;
        $centro->save();

        return back()->with('success', $centro->ativo ? 'Centro de custo ativado.' : 'Centro de custo inativado.');
    }

    /** @return array{codigo: string|null, nome: string} */
    private function validar(Request $request, int $businessId, ?int $ignorar = null): array
    {
        $unico = fn (string $col) => Rule::unique('fin_centros_custo', $col)
            ->where('business_id', $businessId)->whereNull('deleted_at')->ignore($ignorar);

        $v = $request->validate([
            'codigo' => ['nullable', 'string', 'max:20', $unico('codigo')],
            'nome' => ['required', 'string', 'max:100', $unico('nome')],
        ], [
            'codigo.unique' => 'Já existe um centro com esse código.',
            'nome.unique' => 'Já existe um centro com esse nome.',
            'nome.required' => 'Informe o nome do centro de custo.',
        ]);

        return ['codigo' => ($v['codigo'] ?? '') !== '' ? $v['codigo'] : null, 'nome' => trim($v['nome'])];
    }

    /** Centros com quantos títulos e quanto foi rateado na competência do mês. */
    private function centros(int $businessId, string $mes): array
    {
        $uso = DB::table('fin_titulo_rateios as r')
            ->join('fin_titulos as t', function ($j) use ($businessId) {
                $j->on('t.id', '=', 'r.titulo_id')->where('t.business_id', '=', $businessId);
            })
            ->where('r.business_id', $businessId)
            ->where('t.competencia_mes', $mes)
            ->where('t.status', '!=', 'cancelado')
            ->whereNull('t.deleted_at')
            ->groupBy('r.centro_custo_id')
            ->selectRaw('r.centro_custo_id AS id, COUNT(*) AS n, SUM(r.valor) AS v')
            ->get()->keyBy('id');

        return DB::table('fin_centros_custo')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderByDesc('ativo')->orderBy('nome')
            ->get(['id', 'codigo', 'nome', 'ativo'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'codigo' => $c->codigo,
                'nome' => $c->nome,
                'ativo' => (bool) $c->ativo,
                'titulos_mes' => (int) ($uso[$c->id]->n ?? 0),
                'rateado_mes' => round((float) ($uso[$c->id]->v ?? 0), 2),
            ])->all();
    }
}
