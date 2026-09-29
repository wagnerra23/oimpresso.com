<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\MobileMarcacaoService;
use Spatie\Activitylog\Models\Activity;
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
    // O geofence SINALIZA (não recusa): a marcação já entrou e vale. Aqui o gestor
    //  - VALIDA  → registro na trilha (activity_log `ponto.repp` / `validada`), sem tocar a marcação;
    //  - RECUSA  → `Marcacao::anular()`: lançamento NOVO com ORIGEM_ANULACAO (D3, Portaria 671/2021).
    // Nenhum dos dois faz UPDATE/DELETE em `ponto_marcacoes`.

    public function validarMobile(Request $request, string $id): RedirectResponse
    {
        $businessId = (int) (session('business.id') ?: $request->user()->business_id);
        $m = $this->marcacaoMobile($businessId, $id);
        abort_unless($this->estadoMobile($businessId, [$m->id])[$m->id] === 'PENDENTE', 422, 'Esta marcação já foi decidida.');

        $registro = activity('ponto.repp')
            ->event('validada')
            ->causedBy($request->user())
            ->withProperties(['marcacao_id' => (string) $m->id, 'nsr' => (int) $m->nsr])
            ->tap(fn (Activity $a) => $a->setAttribute('business_id', $businessId))
            ->log('Marcação REP-P validada');
        // Logger desligado (ACTIVITY_LOGGER_ENABLED=false) = validar não teria onde gravar:
        // falha visível em vez de dizer "validada" sem registro.
        abort_if($registro === null, 503, 'A trilha de auditoria está desligada — a validação não foi registrada.');

        return back()->with('success', "Marcação NSR {$m->nsr} validada.");
    }

    public function recusarMobile(Request $request, string $id): RedirectResponse
    {
        $businessId = (int) (session('business.id') ?: $request->user()->business_id);
        $m = $this->marcacaoMobile($businessId, $id);
        abort_unless($this->estadoMobile($businessId, [$m->id])[$m->id] === 'PENDENTE', 422, 'Esta marcação já foi decidida.');

        $m->anular((int) $request->user()->id, 'Recusada na validação REP-P');

        return back()->with('success', "Marcação NSR {$m->nsr} recusada — gravada a anulação; a original não muda.");
    }

    /** Marcações REP-P dos últimos 7 dias que o geofence sinalizou, com o estado da decisão. */
    private function buildFilaMobile(int $businessId): array
    {
        $svc = app(MobileMarcacaoService::class);
        // O service devolve Collection<Model> (sem genérico de Marcacao) — por isso as closures
        // não tipam o parâmetro; o @var diz ao PHPStan o que de fato vem.
        /** @var \Illuminate\Support\Collection<int, Marcacao> $marcacoes */
        $marcacoes = $svc->listarMarcacoesMobilePendentesValidacao($businessId)
            ->filter(fn ($m) => $m->latitude !== null && $m->longitude !== null
                && ! $svc->validarGeolocation((float) $m->latitude, (float) $m->longitude, $businessId))
            ->values();

        $estados = $this->estadoMobile($businessId, $marcacoes->pluck('id')->all());
        $nomes = Colaborador::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $marcacoes->pluck('colaborador_config_id')->unique())
            ->with('user:id,first_name,last_name')
            ->get()
            ->mapWithKeys(fn (Colaborador $c) => [$c->id => trim(optional($c->user)->first_name . ' ' . optional($c->user)->last_name) ?: '—']);

        return $marcacoes->map(fn ($m) => [
            'id'          => (string) $m->id,
            'nsr'         => (int) $m->nsr,
            'quando'      => $m->momento?->format('d/m H:i'),
            'tipo'        => (string) $m->tipo,
            'colaborador' => $nomes[$m->colaborador_config_id] ?? '—',
            'dispositivo' => (string) $m->dispositivo_id,
            'lat'         => (string) $m->latitude,
            'lng'         => (string) $m->longitude,
            'hash_trunc'  => substr((string) $m->hash, 0, 16),
            'estado'      => $estados[$m->id] ?? 'PENDENTE',
        ])->all();
    }

    private function marcacaoMobile(int $businessId, string $id): Marcacao
    {
        return Marcacao::query()
            ->where('business_id', $businessId)
            ->where('dispositivo_id', 'like', 'mobile:%')
            ->where('origem', Marcacao::ORIGEM_REP_P)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * RECUSADA se existe anulação apontando pra ela · VALIDADA se há registro na trilha (não
     * revertido) · senão PENDENTE.
     *
     * @param  array<int,string>  $ids
     * @return array<string,string>
     */
    private function estadoMobile(int $businessId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $recusadas = Marcacao::query()
            ->where('business_id', $businessId)
            ->where('origem', Marcacao::ORIGEM_ANULACAO)
            ->whereIn('marcacao_anulada_id', $ids)
            ->pluck('marcacao_anulada_id')
            ->flip();
        $validadas = Activity::query()
            ->where('log_name', 'ponto.repp')
            ->where('event', 'validada')
            ->where('business_id', $businessId)
            ->whereNull('reverted_at')
            ->where('created_at', '>=', now()->subDays(8)) // a fila é de 7 dias (+1 de folga no fuso)
            ->get()
            ->map(fn (Activity $a) => (string) $a->getExtraProperty('marcacao_id'))
            ->flip();

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = isset($recusadas[$id]) ? 'RECUSADA' : (isset($validadas[$id]) ? 'VALIDADA' : 'PENDENTE');
        }

        return $out;
    }
}
