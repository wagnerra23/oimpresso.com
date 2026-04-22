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

    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        $estado = $request->input('estado');
        $tipo   = $request->input('tipo');

        $paginated = Intercorrencia::query()
            ->where('business_id', $businessId)
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->with(['colaborador.user:id,first_name,last_name'])
            ->orderByDesc('data')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($i) => [
            'id'           => $i->id,
            'codigo'       => $i->codigo ?? ('#' . substr((string) $i->id, 0, 8)),
            'tipo'         => $i->tipo,
            'estado'       => $i->estado,
            'prioridade'   => $i->prioridade,
            'data'         => optional($i->data)->format('Y-m-d'),
            'justificativa'=> mb_substr((string) $i->justificativa, 0, 120),
            'created_at_human' => optional($i->created_at)->diffForHumans(),
            'colaborador'  => [
                'nome'      => trim(optional(optional($i->colaborador)->user)->first_name ?? '—'),
                'matricula' => optional($i->colaborador)->matricula,
            ],
        ]);

        return Inertia::render('Ponto/Intercorrencias/Index', [
            'intercorrencias' => $paginated,
            'filtros' => ['estado' => $estado, 'tipo' => $tipo],
        ]);
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

    public function show($id): Response
    {
        $i = Intercorrencia::with(['colaborador.user', 'solicitante', 'aprovador'])->findOrFail($id);

        return Inertia::render('Ponto/Intercorrencias/Show', [
            'intercorrencia' => [
                'id'             => $i->id,
                'codigo'         => $i->codigo ?? ('#' . substr((string) $i->id, 0, 8)),
                'tipo'           => $i->tipo,
                'estado'         => $i->estado,
                'prioridade'     => $i->prioridade,
                'data'           => optional($i->data)->format('Y-m-d'),
                'dia_todo'       => (bool) $i->dia_todo,
                'intervalo_inicio' => $i->intervalo_inicio,
                'intervalo_fim'  => $i->intervalo_fim,
                'justificativa'  => $i->justificativa,
                'impacta_apuracao' => (bool) $i->impacta_apuracao,
                'descontar_banco_horas' => (bool) $i->descontar_banco_horas,
                'motivo_rejeicao'=> $i->motivo_rejeicao,
                'created_at'     => optional($i->created_at)->format('Y-m-d H:i'),
                'updated_at'     => optional($i->updated_at)->format('Y-m-d H:i'),
                'colaborador'    => [
                    'id'        => optional($i->colaborador)->id,
                    'matricula' => optional($i->colaborador)->matricula,
                    'nome'      => trim(optional(optional($i->colaborador)->user)->first_name ?? '—'),
                ],
                'solicitante'    => ['nome' => optional($i->solicitante)->first_name ?? '—'],
                'aprovador'      => ['nome' => optional($i->aprovador)->first_name ?? null],
            ],
        ]);
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
