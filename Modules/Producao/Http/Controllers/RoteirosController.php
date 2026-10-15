<?php

namespace Modules\Producao\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Roteiro;
use Modules\Producao\Http\Requests\SalvarRoteiroRequest;

/**
 * Roteiros por família de produto — thread 03 do playbook de Produção (ADR 0431).
 * Forma: vista `pcv-roteiros` do protótipo · contrato: UC-PCV-13. Tudo pelo escopo da empresa.
 */
class RoteirosController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('producao.access'), 403);

        return Inertia::render('Producao/Roteiros/Index', [
            'roteiros' => Inertia::defer(fn () => Roteiro::query()->with('etapas')->orderBy('familia')->orderBy('nome')
                ->get()->map(fn (Roteiro $r) => $this->formato($r))->values()),
            'postos' => Inertia::defer(fn () => Posto::query()->where('ativo', true)->orderBy('nome')
                ->get(['id', 'nome', 'setor'])),
        ]);
    }

    public function store(SalvarRoteiroRequest $request): RedirectResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        DB::transaction(function () use ($request, $biz) {
            $roteiro = Roteiro::create(['business_id' => $biz, 'familia' => $request->input('familia'), 'nome' => $request->input('nome')]);
            $this->gravarEtapas($roteiro, $biz, $request->input('etapas'));
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Roteiro salvo.']);
    }

    public function update(SalvarRoteiroRequest $request, int $id): RedirectResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $roteiro = Roteiro::query()->findOrFail($id);
        DB::transaction(function () use ($request, $biz, $roteiro) {
            $roteiro->update(['familia' => $request->input('familia'), 'nome' => $request->input('nome')]);
            $roteiro->etapas()->delete();
            $this->gravarEtapas($roteiro, $biz, $request->input('etapas'));
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Roteiro salvo.']);
    }

    /** @param  array<int, array<string, mixed>>  $etapas */
    private function gravarEtapas(Roteiro $roteiro, int $biz, array $etapas): void
    {
        $linha = fn (array $p, int $bloco, int $trilha, int $ordem, ?string $junta) => [
            'business_id' => $biz, 'bloco' => $bloco, 'trilha' => $trilha, 'ordem' => $ordem, 'nome' => trim((string) $p['nome']),
            'posto_id' => $p['posto_id'] ?? null, 'horas' => $p['horas'] ?? null,
            'opcional_por' => filled($p['opcional_por'] ?? null) ? trim((string) $p['opcional_por']) : null, 'junta' => $junta,
        ];
        foreach (array_values($etapas) as $bloco => $item) {
            if (! array_key_exists('paralelo', $item)) {
                $roteiro->etapas()->create($linha($item, $bloco, 0, 0, null));

                continue;
            }
            foreach (array_values($item['paralelo']) as $t => $trilha) {
                foreach (array_values($trilha) as $ordem => $passo) {
                    $roteiro->etapas()->create($linha($passo, $bloco, $t + 1, $ordem, trim((string) $item['junta'])));
                }
            }
        }
    }

    /** Devolve o roteiro no mesmo formato que o formulário envia. */
    private function formato(Roteiro $r): array
    {
        $passo = fn ($e) => ['nome' => $e->nome, 'posto_id' => $e->posto_id, 'horas' => $e->horas, 'opcional_por' => $e->opcional_por];
        $etapas = $r->etapas->groupBy('bloco')->map(function ($linhas) use ($passo) {
            $primeira = $linhas->first();
            if ((int) $primeira->trilha === 0) {
                return $passo($primeira);
            }

            return ['junta' => $primeira->junta, 'paralelo' => $linhas->groupBy('trilha')->map(fn ($t) => $t->map($passo)->values())->values()];
        })->values();

        return ['id' => $r->id, 'familia' => $r->familia, 'nome' => $r->nome, 'etapas' => $etapas];
    }
}
