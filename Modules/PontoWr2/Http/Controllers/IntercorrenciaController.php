<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Entities\Intercorrencia;
use Modules\PontoWr2\Http\Requests\IntercorrenciaRequest;
use Modules\PontoWr2\Services\IntercorrenciaAIClassifier;
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

    public function create(Request $request, IntercorrenciaAIClassifier $ai): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        $colaboradores = Colaborador::where('business_id', $businessId)
            ->where('controla_ponto', true)
            ->whereNull('desligamento')
            ->with(['user:id,first_name,last_name'])
            ->orderBy('matricula')
            ->get()
            ->map(fn ($c) => [
                'id'        => $c->id,
                'matricula' => $c->matricula,
                'nome'      => trim(optional($c->user)->first_name . ' ' . optional($c->user)->last_name),
            ]);

        return Inertia::render('Ponto/Intercorrencias/Create', [
            'colaboradores' => $colaboradores,
            'tipos' => [
                ['value' => 'CONSULTA_MEDICA',       'label' => 'Consulta médica'],
                ['value' => 'ATESTADO_MEDICO',       'label' => 'Atestado médico'],
                ['value' => 'REUNIAO_EXTERNA',       'label' => 'Reunião externa'],
                ['value' => 'VISITA_CLIENTE',        'label' => 'Visita a cliente'],
                ['value' => 'HORA_EXTRA_AUTORIZADA', 'label' => 'Hora extra autorizada'],
                ['value' => 'ESQUECIMENTO_MARCACAO', 'label' => 'Esquecimento de marcação'],
                ['value' => 'PROBLEMA_EQUIPAMENTO',  'label' => 'Problema no equipamento'],
                ['value' => 'OUTRO',                 'label' => 'Outro' ],
            ],
            'ai_enabled' => $ai->aiHabilitada(),
        ]);
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

    /**
     * Classifica descrição livre via IA (OpenAI). Retorna JSON com os campos
     * sugeridos (tipo, prioridade, justificativa formal, etc.) pra popular o
     * form do React. Endpoint dedicado (não parte do resource).
     */
    public function aiClassify(Request $request, IntercorrenciaAIClassifier $ai): JsonResponse
    {
        $request->validate([
            'descricao' => 'required|string|min:10|max:2000',
        ]);

        $result = $ai->classificar($request->input('descricao'));

        return response()->json($result);
    }
}
