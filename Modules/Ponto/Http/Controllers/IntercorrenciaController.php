<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Http\Requests\IntercorrenciaRequest;
use Modules\Ponto\Services\IntercorrenciaAIClassifier;
use Modules\Ponto\Services\IntercorrenciaService;

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

        return Inertia::render('Ponto/Intercorrencias/Create', [
            'colaboradores' => $this->buildColaboradoresElegiveis($businessId),
            'tipos'         => self::tiposDisponiveis(),
            'ai_enabled'    => $ai->aiHabilitada(),
        ]);
    }

    /**
     * Os 8 tipos de intercorrência, no vocabulário que o `IntercorrenciaRequest`
     * aceita (`rules()['tipo']`).
     *
     * Extraído quando o `edit()` virou Inertia e passou a precisar da mesma lista:
     * sem isto seriam TRÊS cópias literais do array no repositório. Ainda há uma no
     * `AprovacaoController@index` (a fila de aprovação também rotula os tipos) —
     * unificar as duas é mudança de outro escopo, e fica declarada aqui em vez de
     * silenciosa: **mexeu nesta lista, confira a de lá.**
     *
     * @return array<int,array{value:string,label:string}>
     */
    private static function tiposDisponiveis(): array
    {
        return [
            ['value' => 'CONSULTA_MEDICA',       'label' => 'Consulta médica'],
            ['value' => 'ATESTADO_MEDICO',       'label' => 'Atestado médico'],
            ['value' => 'REUNIAO_EXTERNA',       'label' => 'Reunião externa'],
            ['value' => 'VISITA_CLIENTE',        'label' => 'Visita a cliente'],
            ['value' => 'HORA_EXTRA_AUTORIZADA', 'label' => 'Hora extra autorizada'],
            ['value' => 'ESQUECIMENTO_MARCACAO', 'label' => 'Esquecimento de marcação'],
            ['value' => 'PROBLEMA_EQUIPAMENTO',  'label' => 'Problema no equipamento'],
            ['value' => 'OUTRO',                 'label' => 'Outro'],
        ];
    }

    public function store(IntercorrenciaRequest $request): RedirectResponse
    {
        // US-PONTO-013 — o `business_id` NUNCA era atribuído no caminho de criação, e a
        // coluna é NOT NULL + FK sem default: registrar intercorrência pela tela não
        // gravava. Cadeia medida: o FormRequest não declara a chave (logo `validated()`
        // não a devolve) · o Service seta só `codigo`/`solicitante_id`/`estado` · o
        // `creating` só gera UUID · o trait `HasBusinessScope` só adiciona o scope de
        // LEITURA · 0 `observe()` no módulo. O próprio Service denunciava que sabia:
        // usa `($dados['business_id'] ?? 0)` no span.
        //
        // Injetado AQUI (e não no FormRequest) seguindo o padrão do próprio módulo —
        // `EscalaController@store` faz igual, e o docblock do `StoreEscalaRequest` crava
        // a regra: "business_id injetado pelo Controller via session/auth; nunca aceito
        // do request body (anti-tampering cross-tenant)" — ADR 0093.
        $dados = $request->validated();
        $dados['business_id'] = session('business.id') ?: $request->user()->business_id;

        $intercorrencia = $this->service->criar(
            $dados,
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

    /**
     * Edição de rascunho — a ÚLTIMA tela Blade viva do módulo, até 2026-08-28.
     *
     * O SDD §5.4 item 1 media a dívida: dos 21 renders dos controllers do Ponto,
     * 20 eram Inertia e **1** era Blade — este. O operador que clicava "editar"
     * num rascunho saía do shell React e caía no AdminLTE. Decisão [W] 2026-08-28:
     * a tela FICA (a alternativa era derrubar a rota e deixar cancelar+recriar),
     * então ela migra.
     *
     * O `abort_unless` de RASCUNHO **não muda** — é a âncora do `CU-PONTO-05`
     * (*"só rascunho é editável"*) e vale igual na versão React.
     *
     * A Blade `pontowr2::intercorrencias.edit` NÃO foi apagada de propósito: ela
     * vira fóssil como as outras 25 do módulo, e é o **contrato de paridade** que
     * permite conferir esta migração campo a campo. Apagar é outro escopo.
     */
    public function edit(Request $request, $id): Response
    {
        $intercorrencia = Intercorrencia::with('colaborador.user')->findOrFail($id);

        abort_unless(
            $intercorrencia->estado === Intercorrencia::ESTADO_RASCUNHO,
            403,
            'Apenas rascunhos podem ser editados.'
        );

        $businessId = session('business.id') ?: $request->user()->business_id;

        return Inertia::render('Ponto/Intercorrencias/Edit', [
            'intercorrencia' => [
                'id'                    => $intercorrencia->id,
                'codigo'                => $intercorrencia->codigo ?? ('#' . substr((string) $intercorrencia->id, 0, 8)),
                'estado'                => $intercorrencia->estado,
                'colaborador_config_id' => $intercorrencia->colaborador_config_id,
                'tipo'                  => $intercorrencia->tipo,
                'data'                  => optional($intercorrencia->data)->format('Y-m-d'),
                'dia_todo'              => (bool) $intercorrencia->dia_todo,
                // O `IntercorrenciaRequest` valida `date_format:H:i`; a coluna pode
                // vir como H:i:s. Cortar aqui evita o form nascer inválido contra a
                // própria regra que ele vai submeter.
                'intervalo_inicio'      => $intercorrencia->intervalo_inicio
                    ? substr((string) $intercorrencia->intervalo_inicio, 0, 5)
                    : '',
                'intervalo_fim'         => $intercorrencia->intervalo_fim
                    ? substr((string) $intercorrencia->intervalo_fim, 0, 5)
                    : '',
                'justificativa'         => (string) $intercorrencia->justificativa,
                'prioridade'            => $intercorrencia->prioridade ?: 'NORMAL',
                'impacta_apuracao'      => (bool) $intercorrencia->impacta_apuracao,
                'descontar_banco_horas' => (bool) $intercorrencia->descontar_banco_horas,
            ],
            'colaboradores' => $this->buildColaboradoresElegiveis($businessId),
            'tipos'         => self::tiposDisponiveis(),
        ]);
    }

    /**
     * Colaboradores selecionáveis — mesma regra do `create()`: só quem controla
     * ponto e não foi desligado, escopado ao business.
     *
     * ⚠️ SEM PII: `cpf`/`pis` existem na entity e não entram — o payload alimenta
     * um `<Select>` (proibicoes.md §Multi-tenant).
     */
    private function buildColaboradoresElegiveis(int $businessId)
    {
        return Colaborador::where('business_id', $businessId)
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
