<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PontoWr2\Entities\Intercorrencia;
use Modules\PontoWr2\Services\IntercorrenciaService;

class AprovacaoController extends Controller
{
    protected $service;

    public function __construct(IntercorrenciaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        $filtroEstado = $request->input('estado', Intercorrencia::ESTADO_PENDENTE);
        $filtroTipo   = $request->input('tipo');
        $filtroPrioridade = $request->input('prioridade');

        $query = Intercorrencia::query()
            ->where('business_id', $businessId)
            ->when($filtroEstado, fn ($q) => $q->where('estado', $filtroEstado))
            ->when($filtroTipo, fn ($q) => $q->where('tipo', $filtroTipo))
            ->when($filtroPrioridade, fn ($q) => $q->where('prioridade', $filtroPrioridade))
            ->with(['colaborador.user', 'solicitante'])
            ->orderByRaw("FIELD(prioridade,'URGENTE','NORMAL')")
            ->orderByDesc('created_at');

        $paginated = $query->paginate(20)->withQueryString();

        // Shape JSON-friendly pra React
        $paginated->getCollection()->transform(fn ($i) => [
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
            'created_at_human' => optional($i->created_at)->diffForHumans(),
            'created_at'     => optional($i->created_at)->format('Y-m-d H:i'),
            'colaborador'    => [
                'id'        => optional($i->colaborador)->id,
                'matricula' => optional($i->colaborador)->matricula,
                'nome'      => trim(
                    optional(optional($i->colaborador)->user)->first_name . ' ' .
                    optional(optional($i->colaborador)->user)->last_name
                ) ?: '—',
            ],
            'solicitante'    => [
                'nome' => optional($i->solicitante)->first_name ?? '—',
            ],
        ]);

        // Contadores por estado (panorama visual rápido)
        $contagens = Intercorrencia::where('business_id', $businessId)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        return Inertia::render('Ponto/Aprovacoes/Index', [
            'aprovacoes' => $paginated,
            'filtros' => [
                'estado'     => $filtroEstado,
                'tipo'       => $filtroTipo,
                'prioridade' => $filtroPrioridade,
            ],
            'contagens' => [
                'RASCUNHO'  => (int) ($contagens[Intercorrencia::ESTADO_RASCUNHO]  ?? 0),
                'PENDENTE'  => (int) ($contagens[Intercorrencia::ESTADO_PENDENTE]  ?? 0),
                'APROVADA'  => (int) ($contagens[Intercorrencia::ESTADO_APROVADA]  ?? 0),
                'REJEITADA' => (int) ($contagens[Intercorrencia::ESTADO_REJEITADA] ?? 0),
                'APLICADA'  => (int) ($contagens[Intercorrencia::ESTADO_APLICADA]  ?? 0),
                'CANCELADA' => (int) ($contagens[Intercorrencia::ESTADO_CANCELADA] ?? 0),
            ],
            'tipos' => [
                ['value' => 'CONSULTA_MEDICA',       'label' => 'Consulta médica'],
                ['value' => 'ATESTADO_MEDICO',       'label' => 'Atestado médico'],
                ['value' => 'REUNIAO_EXTERNA',       'label' => 'Reunião externa'],
                ['value' => 'VISITA_CLIENTE',        'label' => 'Visita a cliente'],
                ['value' => 'HORA_EXTRA_AUTORIZADA', 'label' => 'Hora extra autorizada'],
                ['value' => 'ESQUECIMENTO_MARCACAO', 'label' => 'Esquecimento de marcação'],
                ['value' => 'PROBLEMA_EQUIPAMENTO',  'label' => 'Problema no equipamento'],
                ['value' => 'OUTRO',                 'label' => 'Outro'],
            ],
        ]);
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
