<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Services\CentroCustoFiltro;
use Modules\Financeiro\Services\OrcamentoService;

/**
 * Orçado × realizado do mês por conta do plano de contas (US-FIN-076).
 * Tela: Financeiro/Orcamento/Index. A conta toda mora no OrcamentoService.
 *
 * Filtro de centro: null = todos (o orçado é a soma de geral + centros, só leitura);
 * 0 = geral (orçado sem centro, editável); N = o centro N (editável).
 */
class OrcamentoController extends Controller
{
    public function __construct(private OrcamentoService $service)
    {
        $this->middleware('can:financeiro.relatorios.view')->only('index');
        $this->middleware('can:financeiro.lancamentos.create')->only('salvar');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');
        $mes = $this->mes($request->query('mes'));
        $centro = CentroCustoFiltro::daQuery($request->query('centro_custo'));
        $regime = $request->query('regime') === 'caixa' ? 'caixa' : 'competencia';

        $comparativo = null;
        $dados = function () use (&$comparativo, $businessId, $mes, $centro, $regime): array {
            return $comparativo ??= $this->comparativo($businessId, $mes, $centro, $regime);
        };

        return Inertia::render('Financeiro/Orcamento/Index', [
            'mes' => $mes,
            'regime' => $regime,
            'centro_custo' => $centro,
            'meses' => $this->meses(),
            'centros_custo' => fn () => CentroCustoFiltro::opcoes($businessId),
            'linhas' => fn () => $dados()['linhas'],
            'totais' => fn () => $dados()['totais'],
        ]);
    }

    public function salvar(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $v = $request->validate([
            'mes' => ['required', 'string'],
            'plano_conta_id' => ['required', 'integer', 'min:1'],
            'centro_custo_id' => ['nullable', 'integer', 'min:1'],
            'valor' => ['nullable', 'string', 'max:30'],
        ]);

        $this->service->definir(
            $businessId,
            $v['mes'],
            (int) $v['plano_conta_id'],
            isset($v['centro_custo_id']) ? (int) $v['centro_custo_id'] : null,
            (string) ($v['valor'] ?? ''),
            (int) $request->user()->id,
        );

        return back()->with('success', 'Orçado atualizado.');
    }

    /** Todas as contas de resultado que aceitam lançamento, com o comparativo do mês. */
    private function comparativo(int $businessId, string $mes, ?int $centro, string $regime): array
    {
        $cmp = $this->service->comparar($businessId, $mes, $centro, $regime);
        $porConta = collect($cmp['linhas'])->keyBy('plano_conta_id');

        $linhas = DB::table('fin_planos_conta')
            ->where('business_id', $businessId)
            ->whereIn('tipo', ['receita', 'despesa', 'custo'])
            ->where('aceita_lancamento', true)
            ->where('ativo', true)
            ->whereNull('deleted_at')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome', 'tipo'])
            ->map(fn ($c) => $porConta[(int) $c->id] ?? [
                'plano_conta_id' => (int) $c->id, 'codigo' => $c->codigo, 'nome' => $c->nome, 'tipo' => $c->tipo,
                'orcado' => 0.0, 'realizado' => 0.0, 'desvio' => 0.0, 'desvio_pct' => null, 'favoravel' => true,
            ]);

        // Conta inativa com orçado ou realizado no mês continua aparecendo: o dinheiro existiu.
        $faltantes = $porConta->except($linhas->pluck('plano_conta_id')->all())->values();

        return [
            'linhas' => $linhas->concat($faltantes)
                ->sortBy(fn ($l) => [$l['tipo'] !== 'receita', (string) $l['codigo']])
                ->values()->all(),
            'totais' => $cmp['totais'],
        ];
    }

    private function mes(mixed $q): string
    {
        return is_string($q) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $q) ? $q : now()->format('Y-m');
    }

    /** Doze meses para trás e três para frente (orçar o próximo trimestre). */
    private function meses(): array
    {
        $out = [];
        $ini = now()->startOfMonth()->subMonths(12);
        for ($i = 0; $i <= 15; $i++) {
            $out[] = $ini->copy()->addMonths($i)->format('Y-m');
        }

        return array_reverse($out);
    }
}
