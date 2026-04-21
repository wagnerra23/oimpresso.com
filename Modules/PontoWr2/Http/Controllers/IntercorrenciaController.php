<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\PontoWr2\Entities\Intercorrencia;
use Modules\PontoWr2\Http\Requests\IntercorrenciaRequest;
use Modules\PontoWr2\Services\IntercorrenciaService;

class IntercorrenciaController extends Controller
{
    protected $service;

    public function __construct(IntercorrenciaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): View
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        $intercorrencias = Intercorrencia::where('business_id', $businessId)
            ->with(['colaborador.user'])
            ->orderByDesc('data')
            ->paginate(25);

        return view('pontowr2::intercorrencias.index', compact('intercorrencias'));
    }

    public function create(): View
    {
        return view('pontowr2::intercorrencias.create');
    }

    public function store(IntercorrenciaRequest $request): RedirectResponse
    {
        $intercorrencia = $this->service->criar(
            $request->validated(),
            $request->user()->id
        );

        return redirect()
            ->route('ponto.intercorrencias.show', $intercorrencia->id)
            ->with('success', "Intercorrência {$intercorrencia->codigo} criada.");
    }

    public function show($id): View
    {
        $intercorrencia = Intercorrencia::with(['colaborador.user', 'solicitante', 'aprovador'])
            ->findOrFail($id);

        return view('pontowr2::intercorrencias.show', compact('intercorrencia'));
    }

    public function edit($id): View
    {
        $intercorrencia = Intercorrencia::findOrFail($id);

        abort_unless(
            $intercorrencia->estado === Intercorrencia::ESTADO_RASCUNHO,
            403,
            'Apenas rascunhos podem ser editados.'
        );

        return view('pontowr2::intercorrencias.edit', compact('intercorrencia'));
    }

    public function update(IntercorrenciaRequest $request, $id): RedirectResponse
    {
        $intercorrencia = Intercorrencia::findOrFail($id);
        $intercorrencia->update($request->validated());

        return redirect()
            ->route('ponto.intercorrencias.show', $id)
            ->with('success', 'Intercorrência atualizada.');
    }

    public function submeter($id): RedirectResponse
    {
        $intercorrencia = Intercorrencia::findOrFail($id);
        $this->service->submeter($intercorrencia);

        return back()->with('success', 'Intercorrência submetida para aprovação.');
    }

    public function cancelar($id): RedirectResponse
    {
        $intercorrencia = Intercorrencia::findOrFail($id);
        $this->service->cancelar($intercorrencia, auth()->id());

        return back()->with('success', 'Intercorrência cancelada.');
    }
}
