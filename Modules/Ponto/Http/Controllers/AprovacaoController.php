<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\FilaGestorRepPService;
use Modules\Ponto\Services\IntercorrenciaService;

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

        // Wave 26 D6 Inertia::defer DEFAULT — paginate() + selectRaw() viram closures lazy.
        // Filtros (`estado/tipo/prioridade`) + tipos enum static permanecem eager (UI state).
        // (RUNBOOK-inertia-defer-pattern.md — pattern Dashboard Wave 25 replicado).
        return Inertia::render('Ponto/Aprovacoes/Index', [
            'aprovacoes' => Inertia::defer(fn () => $this->buildAprovacoesPagina(
                $businessId, $filtroEstado, $filtroTipo, $filtroPrioridade
            )),
            'contagens'  => Inertia::defer(fn () => $this->buildContagensEstado($businessId)),
            // Fila do gestor do REP-P (thread 06 · [W] 2026-09-29: seção nova aqui).
            'mobile'     => Inertia::defer(fn () => $this->buildFilaMobile($businessId)),
            // A rota de recusar exige a permissão; a tela só esconde o botão (quem decide é a rota).
            'pode_recusar_mobile' => (bool) $request->user()->can('ponto.aprovacoes.manage'),
            'filtros' => [
                'estado'     => $filtroEstado,
                'tipo'       => $filtroTipo,
                'prioridade' => $filtroPrioridade,
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

    /**
     * Paginação 20 aprovações filtradas — eager `colaborador.user` + `solicitante`.
     * Wave 26 extraído pra closure `Inertia::defer`.
     */
    private function buildAprovacoesPagina(int $businessId, ?string $filtroEstado, ?string $filtroTipo, ?string $filtroPrioridade)
    {
        $query = Intercorrencia::query()
            ->where('business_id', $businessId)
            ->when($filtroEstado, fn ($q) => $q->where('estado', $filtroEstado))
            ->when($filtroTipo, fn ($q) => $q->where('tipo', $filtroTipo))
            ->when($filtroPrioridade, fn ($q) => $q->where('prioridade', $filtroPrioridade))
            ->with(['colaborador.user', 'solicitante'])
            ->orderByRaw("FIELD(prioridade,'URGENTE','NORMAL')")
            ->orderByDesc('created_at');

        $paginated = $query->paginate(20)->withQueryString();

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

        return $paginated;
    }

    /**
     * Contadores por estado (6 buckets) — selectRaw groupBy. Wave 26 extraído.
     *
     * @return array<string,int>
     */
    private function buildContagensEstado(int $businessId): array
    {
        $contagens = Intercorrencia::where('business_id', $businessId)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        return [
            'RASCUNHO'  => (int) ($contagens[Intercorrencia::ESTADO_RASCUNHO]  ?? 0),
            'PENDENTE'  => (int) ($contagens[Intercorrencia::ESTADO_PENDENTE]  ?? 0),
            'APROVADA'  => (int) ($contagens[Intercorrencia::ESTADO_APROVADA]  ?? 0),
            'REJEITADA' => (int) ($contagens[Intercorrencia::ESTADO_REJEITADA] ?? 0),
            'APLICADA'  => (int) ($contagens[Intercorrencia::ESTADO_APLICADA]  ?? 0),
            'CANCELADA' => (int) ($contagens[Intercorrencia::ESTADO_CANCELADA] ?? 0),
        ];
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

    // =====================================================================
    // Fila do gestor — marcações do REP-P fora do geofence (thread 06 · D3)
    // =====================================================================
    // A regra mora em FilaGestorRepPService, a mesma que o app das lojas usa (tela 39).
    // Nenhuma das decisões faz UPDATE/DELETE em `ponto_marcacoes`.

    public function validarMobile(Request $request, string $id, FilaGestorRepPService $fila): RedirectResponse
    {
        $businessId = (int) (session('business.id') ?: $request->user()->business_id);
        $m = $fila->marcacao($businessId, $id) ?? abort(404);
        abort_unless($fila->estado($businessId, $m) === FilaGestorRepPService::PENDENTE, 422, 'Esta marcação já foi decidida.');

        // Logger desligado (ACTIVITY_LOGGER_ENABLED=false) = validar não teria onde gravar:
        // falha visível em vez de dizer "validada" sem registro.
        abort_unless($fila->validar($request->user(), $businessId, $m), 503, 'A trilha de auditoria está desligada — a validação não foi registrada.');

        return back()->with('success', "Marcação NSR {$m->nsr} validada.");
    }

    public function recusarMobile(Request $request, string $id, FilaGestorRepPService $fila): RedirectResponse
    {
        $businessId = (int) (session('business.id') ?: $request->user()->business_id);
        $m = $fila->marcacao($businessId, $id) ?? abort(404);
        abort_unless($fila->estado($businessId, $m) === FilaGestorRepPService::PENDENTE, 422, 'Esta marcação já foi decidida.');

        $fila->recusar($request->user(), $m);

        return back()->with('success', "Marcação NSR {$m->nsr} recusada — gravada a anulação; a original não muda.");
    }

    /** Marcações REP-P dos últimos 7 dias que o geofence sinalizou, com o estado da decisão. */
    private function buildFilaMobile(int $businessId): array
    {
        $fila = app(FilaGestorRepPService::class);
        $marcacoes = $fila->fila($businessId);
        $estados = $fila->estados($businessId, $marcacoes->pluck('id')->map(fn ($id) => (string) $id)->all());
        $nomes = $fila->nomes($businessId, $marcacoes);

        return $marcacoes->map(fn (Marcacao $m) => [
            'id'          => (string) $m->id,
            'nsr'         => (int) $m->nsr,
            'quando'      => $m->momento?->format('d/m H:i'),
            'tipo'        => (string) $m->tipo,
            'colaborador' => $nomes[$m->colaborador_config_id] ?? '—',
            'dispositivo' => (string) $m->dispositivo_id,
            'lat'         => (string) $m->latitude,
            'lng'         => (string) $m->longitude,
            'hash_trunc'  => substr((string) $m->hash, 0, 16),
            'estado'      => $estados[(string) $m->id] ?? FilaGestorRepPService::PENDENTE,
        ])->all();
    }
}
