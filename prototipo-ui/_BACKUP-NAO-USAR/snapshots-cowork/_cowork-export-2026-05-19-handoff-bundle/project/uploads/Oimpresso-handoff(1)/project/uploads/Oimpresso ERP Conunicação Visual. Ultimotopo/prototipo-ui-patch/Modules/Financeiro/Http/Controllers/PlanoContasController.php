<?php

// @memcofre
//   controller: PlanoContasController
//   tela: /financeiro/plano-de-contas
//   module: Financeiro
//   status: stub-mock-data
//   tests: Modules/Financeiro/Tests/Feature/PlanoContasControllerTest

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class PlanoContasController extends Controller
{
    public function index(Request $request): Response
    {
        $busca = $request->string('busca', '')->toString();

        // TODO[CL]: substituir por ChartOfAccount::with('saldoMes', 'qtdLancamentosMes')->tenantScoped()->ordered()->get()
        $contas = collect([
            ['id' => 1,  'codigo' => '1',      'nome' => 'Receitas',                       'level' => 0, 'tipo' => 'rec', 'saldo_mes' => 14860, 'qtd_lancamentos_mes' => 14],
            ['id' => 2,  'codigo' => '1.1',    'nome' => 'Vendas de produtos',             'level' => 1, 'tipo' => 'rec', 'saldo_mes' => 14260, 'qtd_lancamentos_mes' => 12],
            ['id' => 3,  'codigo' => '1.1.01', 'nome' => 'Banner / Lona',                  'level' => 2, 'tipo' => 'rec', 'saldo_mes' => 6720,  'qtd_lancamentos_mes' => 5],
            ['id' => 4,  'codigo' => '1.1.02', 'nome' => 'Adesivo / Envelopamento',        'level' => 2, 'tipo' => 'rec', 'saldo_mes' => 8180,  'qtd_lancamentos_mes' => 4],
            ['id' => 5,  'codigo' => '1.1.03', 'nome' => 'Fachada / ACM',                  'level' => 2, 'tipo' => 'rec', 'saldo_mes' => 2770,  'qtd_lancamentos_mes' => 3],
            ['id' => 6,  'codigo' => '1.1.04', 'nome' => 'Gráfica rápida',                 'level' => 2, 'tipo' => 'rec', 'saldo_mes' => 1600,  'qtd_lancamentos_mes' => 4],
            ['id' => 7,  'codigo' => '1.2',    'nome' => 'Receitas financeiras',           'level' => 1, 'tipo' => 'rec', 'saldo_mes' => 0,     'qtd_lancamentos_mes' => 0],
            ['id' => 8,  'codigo' => '2',      'nome' => 'Despesas',                       'level' => 0, 'tipo' => 'exp', 'saldo_mes' => -13482,'qtd_lancamentos_mes' => 13],
            ['id' => 9,  'codigo' => '2.1',    'nome' => 'Custos diretos',                 'level' => 1, 'tipo' => 'exp', 'saldo_mes' => -5180, 'qtd_lancamentos_mes' => 6],
            ['id' => 10, 'codigo' => '2.1.01', 'nome' => 'Insumos gráficos',               'level' => 2, 'tipo' => 'exp', 'saldo_mes' => -3420, 'qtd_lancamentos_mes' => 3],
            ['id' => 11, 'codigo' => '2.1.02', 'nome' => 'Acabamento terceirizado',        'level' => 2, 'tipo' => 'exp', 'saldo_mes' => -1120, 'qtd_lancamentos_mes' => 1],
            ['id' => 12, 'codigo' => '2.1.03', 'nome' => 'Frete e instalação',             'level' => 2, 'tipo' => 'exp', 'saldo_mes' => -640,  'qtd_lancamentos_mes' => 2],
            ['id' => 13, 'codigo' => '2.2',    'nome' => 'Despesas administrativas',       'level' => 1, 'tipo' => 'exp', 'saldo_mes' => -6190, 'qtd_lancamentos_mes' => 5],
            ['id' => 14, 'codigo' => '2.2.01', 'nome' => 'Aluguel',                        'level' => 2, 'tipo' => 'exp', 'saldo_mes' => -4500, 'qtd_lancamentos_mes' => 1],
            ['id' => 15, 'codigo' => '2.2.02', 'nome' => 'Utilidades (energia/internet)',  'level' => 2, 'tipo' => 'exp', 'saldo_mes' => -1500, 'qtd_lancamentos_mes' => 2],
            ['id' => 16, 'codigo' => '2.2.03', 'nome' => 'Impostos e taxas',               'level' => 2, 'tipo' => 'exp', 'saldo_mes' => -190,  'qtd_lancamentos_mes' => 2],
            ['id' => 17, 'codigo' => '2.3',    'nome' => 'Folha de pagamento',             'level' => 1, 'tipo' => 'exp', 'saldo_mes' => -2800, 'qtd_lancamentos_mes' => 1],
            ['id' => 18, 'codigo' => '2.4',    'nome' => 'Manutenção',                     'level' => 1, 'tipo' => 'exp', 'saldo_mes' => -780,  'qtd_lancamentos_mes' => 1],
        ]);

        if ($busca !== '') {
            $contas = $contas->filter(fn ($c) => str_contains(mb_strtolower($c['nome']), mb_strtolower($busca))
                || str_contains($c['codigo'], $busca));
        }

        return Inertia::render('Financeiro/PlanoContas/Index', [
            'modelo' => 'Comunicação Visual · 2 níveis',
            'contas' => $contas->values(),
            'filters' => ['busca' => $busca],
        ]);
    }

    public function create(): Response
    {
        // TODO[CL]: form de criação
        abort(501, 'Tela de criação ainda não implementada.');
    }

    public function edit(int $id): Response
    {
        // TODO[CL]: form de edição com check R-FIN-011 (código imutável se houver lançamentos)
        abort(501, "Edição da conta {$id} ainda não implementada.");
    }

    public function importar(): Response
    {
        // TODO[CL]: tela de seleção de modelo (Comunicação Visual / Gráfica / Genérico)
        abort(501, 'Importação de modelo ainda não implementada.');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('financeiro.plano-de-contas.index');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        return redirect()->route('financeiro.plano-de-contas.index');
    }

    public function destroy(int $id): RedirectResponse
    {
        return redirect()->route('financeiro.plano-de-contas.index');
    }
}
