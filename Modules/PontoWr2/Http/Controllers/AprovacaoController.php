<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PontoWr2\Entities\Intercorrencia;
use Modules\PontoWr2\Services\IntercorrenciaService;

class AprovacaoController extends Controller
{
    protected $service;

    public function __construct(IntercorrenciaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): View
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        $filtroEstado = $request->input('estado', Intercorrencia::ESTADO_PENDENTE);
        $filtroTipo   = $request->input('tipo');

        $aprovacoes = Intercorrencia::query()
            ->where('business_id', $businessId)
            ->when($filtroEstado, function ($q) use ($filtroEstado) { return $q->where('estado', $filtroEstado); })
            ->when($filtroTipo, function ($q) use ($filtroTipo) { return $q->where('tipo', $filtroTipo); })
            ->with(['colaborador.user', 'solicitante'])
            ->orderByRaw("FIELD(prioridade,'URGENTE','NORMAL')")
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('pontowr2::aprovacoes.index', compact('aprovacoes', 'filtroEstado', 'filtroTipo'));
    }

    public function aprovar(Request $request, $id): RedirectResponse
    {
        $intercorrencia = Intercorrencia::findOrFail($id);

        $this->service->aprovar(
            $intercorrencia,
            $request->user()->id,
            $request->input('observacao')
        );

        return back()->with('success', "Intercorrência {$intercorrencia->codigo} aprovada.");
    }

    public function rejeitar(Request $request, $id): RedirectResponse
    {
        $request->validate(['motivo' => 'required|string|max:500']);

        $intercorrencia = Intercorrencia::findOrFail($id);

        $this->service->rejeitar(
            $intercorrencia,
            $request->user()->id,
            $request->input('motivo')
        );

        return back()->with('success', "Intercorrência {$intercorrencia->codigo} rejeitada.");
    }

    public function aprovarEmLote(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'uuid']);

        $count = $this->service->aprovarEmLote(
            $request->input('ids'),
            $request->user()->id
        );

        return back()->with('success', "{$count} intercorrências aprovadas em lote.");
    }
}
