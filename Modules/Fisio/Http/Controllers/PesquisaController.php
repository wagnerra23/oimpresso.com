<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use InvalidArgumentException;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Study;
use Modules\Fisio\Entities\StudyAdverseEvent;
use Modules\Fisio\Entities\StudyParticipant;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Services\Pesquisa\EventosAdversos;
use Modules\Fisio\Services\Pesquisa\ExportacaoEstudo;
use Modules\Fisio\Services\Questionarios\CatalogoQuestionarios;

/**
 * Área do estudo (etapa 07 P-04): criar estudo, incluir participante, registrar saída.
 *
 * Só quem tem `fisio.pesquisa` (pesquisador do estudo); `fisio.access` sozinho não basta.
 * O `business_id` vem da sessão; estudo, participante e paciente são buscados com o escopo da
 * empresa, então id de outra empresa responde 404 (ADR 0093).
 */
class PesquisaController extends Controller
{
    public const PERMISSAO = 'fisio.pesquisa';

    public function __construct(
        private readonly EstudoService $estudos,
        private readonly EventosAdversos $eventos,
        private readonly ExportacaoEstudo $exportacao,
    ) {}

    /**
     * Área do estudo. O nome do paciente só vai à tela para quem também atende (`fisio.editar`):
     * o pesquisador de fora da clínica vê só o código (a chave de volta fica na clínica).
     */
    public function index(Request $request): Response
    {
        $this->autorizar($request);
        $estudos = Study::orderByDesc('starts_on')->get(['id', 'title', 'status', 'starts_on']);
        $escolhido = $request->integer('estudo') ?: $estudos->first()?->id;
        $estudo = $escolhido ? Study::findOrFail($escolhido) : null;
        $veNome = (bool) $request->user()?->can('fisio.editar');

        return Inertia::render('Fisio/Pesquisa/Index', [
            'estudos' => $estudos->map(fn (Study $e) => ['id' => $e->id, 'title' => $e->title, 'status' => $e->status])->values(),
            'estudo' => $estudo === null ? null : [
                'id' => $estudo->id, 'title' => $estudo->title, 'status' => $estudo->status,
                'cep_opinion_number' => $estudo->cep_opinion_number, 'protocol_version' => $estudo->protocol_version,
                'tcle_version' => $estudo->tcle_version, 'starts_on' => $estudo->starts_on->toDateString(),
                'ends_on' => $estudo->ends_on?->toDateString(),
                'locked_at' => $estudo->locked_at?->toIso8601String(), 'lock_reason' => $estudo->lock_reason,
                'momentos' => $estudo->moments->map(fn ($m) => [
                    'label' => $m->label, 'day_offset' => $m->day_offset, 'window_days' => $m->window_days, 'instruments' => $m->instruments,
                ])->values(),
            ],
            've_nome' => $veNome,
            'instrumentos' => CatalogoQuestionarios::LIVRES,
            'participantes' => Inertia::defer(fn () => $estudo === null ? [] : $this->participantes($estudo, $veNome)),
            'pendencias' => Inertia::defer(fn () => $estudo === null ? [] : $this->estudos->pendencias($estudo, now())),
            'pacientes' => Inertia::defer(fn () => $veNome && $estudo !== null ? $this->pacientesForaDoEstudo($estudo) : []),
            'eventos' => Inertia::defer(fn () => $estudo === null ? [] : $this->eventos->doEstudo($estudo)),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->autorizar($request);
        $dados = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'cep_opinion_number' => ['required', 'string', 'max:60'],
            'protocol_version' => ['required', 'string', 'max:30'],
            'tcle_version' => ['required', 'string', 'max:30'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'momentos' => ['required', 'array', 'min:1', 'max:12'],
            'momentos.*.label' => ['required', 'string', 'max:60'],
            'momentos.*.day_offset' => ['required', 'integer', 'min:0', 'max:3650'],
            'momentos.*.window_days' => ['required', 'integer', 'min:0', 'max:60'],
            'momentos.*.instruments' => ['present', 'array'],
            'momentos.*.instruments.*' => ['string', 'in:'.implode(',', CatalogoQuestionarios::LIVRES)],
        ]);
        $biz = (int) $request->session()->get('user.business_id');

        $estudo = $this->estudos->criar($biz, $dados + ['responsible_user_id' => (int) $request->user()->id], $dados['momentos'], (int) $request->user()->id);

        return $this->responder($request, ['id' => $estudo->id], 201, 'Estudo criado.', ['estudo' => $estudo->id]);
    }

    public function incluir(Request $request, int $study): JsonResponse|RedirectResponse
    {
        $this->autorizar($request);
        $dados = $request->validate([
            'patient_id' => ['required', 'integer'],
            // O TCLE foi assinado na clínica, na versão aprovada pelo CEP.
            'tcle_assinado' => ['accepted'],
        ]);
        $estudo = Study::findOrFail($study);
        $paciente = Patient::findOrFail((int) $dados['patient_id']);
        if ($estudo->status !== Study::STATUS_ATIVO) {
            return $this->recusar($request, 'estudo_fora_de_andamento');
        }
        if ($falta = $this->justificativaAposTravamento($request, $estudo, 'participante_incluido')) {
            return $falta;
        }

        try {
            $p = $this->estudos->incluir($estudo, $paciente, (int) $request->user()->id, $request->ip());
        } catch (InvalidArgumentException $e) {
            return $this->recusar($request, $e->getMessage());
        }

        return $this->responder($request, ['id' => $p->id, 'code' => $p->code, 'entered_on' => $p->entered_on->toDateString()], 201, "Participante {$p->code} incluído.");
    }

    public function saida(Request $request, int $study, int $participant): JsonResponse|RedirectResponse
    {
        $this->autorizar($request);
        $dados = $request->validate([
            'tipo' => ['required', 'string', 'in:'.implode(',', StudyParticipant::SAIDAS)],
            'motivo' => ['required', 'string', 'max:500'],
        ]);
        $p = StudyParticipant::where('study_id', $study)->findOrFail($participant);
        if ($falta = $this->justificativaAposTravamento($request, Study::findOrFail($study), 'saida_registrada')) {
            return $falta;
        }

        try {
            $this->estudos->registrarSaida($p, (string) $dados['tipo'], (string) $dados['motivo'], (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->recusar($request, $e->getMessage());
        }

        return $this->responder($request, ['exited_on' => $p->exited_on?->toDateString(), 'exit_type' => $p->exit_type], 200, 'Saída registrada.');
    }

    /** Revisão do evento adverso pela pesquisadora (etapa 07 P-06). O software não classifica. */
    public function revisarEvento(Request $request, int $study, int $evento): JsonResponse|RedirectResponse
    {
        $this->autorizar($request);
        $nota = (string) $request->validate(['nota' => ['required', 'string', 'max:1000']])['nota'];
        $e = StudyAdverseEvent::where('study_id', $study)->findOrFail($evento);
        if ($falta = $this->justificativaAposTravamento($request, Study::findOrFail($study), 'evento_revisado')) {
            return $falta;
        }
        $this->eventos->revisar($e, $nota, (int) $request->user()->id);

        return $this->responder($request, ['reviewed_at' => $e->reviewed_at?->toIso8601String()], 200, 'Revisão registrada.');
    }

    /**
     * Exportação para a universidade (etapa 07 P-07): um arquivo CSV por chamada, sem
     * identificação, e uma linha no activity_log só com ids e o nome do arquivo.
     */
    public function exportar(Request $request, int $study, string $arquivo): HttpResponse
    {
        $this->autorizar($request);
        abort_unless(in_array($arquivo, ExportacaoEstudo::ARQUIVOS, true), 404);
        $estudo = Study::findOrFail($study);
        $csv = $this->exportacao->csv($this->exportacao->linhas($estudo, $arquivo));

        activity('fisio')->performedOn($estudo)->causedBy($request->user())
            ->withProperties(['business_id' => (int) $estudo->business_id, 'study_id' => (int) $estudo->id, 'arquivo' => $arquivo, 'travado' => $estudo->locked_at !== null])
            ->log('estudo_exportado');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="estudo-'.$estudo->id.'-'.$arquivo.'-'.now()->format('Ymd-His').'.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Travar é uma vez só e exige motivo (etapa 07 P-07). */
    public function travar(Request $request, int $study): JsonResponse|RedirectResponse
    {
        $this->autorizar($request);
        $motivo = (string) $request->validate(['motivo' => ['required', 'string', 'max:500']])['motivo'];
        $estudo = Study::findOrFail($study);
        if ($estudo->locked_at !== null) {
            return $this->recusar($request, 'estudo já travado');
        }
        $estudo->forceFill(['locked_at' => now(), 'locked_by' => (int) $request->user()->id, 'lock_reason' => $motivo])->save();
        activity('fisio')->performedOn($estudo)->causedBy($request->user())
            ->withProperties(['business_id' => (int) $estudo->business_id, 'study_id' => (int) $estudo->id, 'motivo' => $motivo])
            ->log('estudo_travado');

        return $this->responder($request, ['locked_at' => $estudo->locked_at->toIso8601String()], 200, 'Estudo travado.');
    }

    /**
     * Depois do travamento, mudar o estudo exige `justificativa`, que vai para o activity_log.
     * Devolve a resposta de recusa, ou null para seguir.
     */
    private function justificativaAposTravamento(Request $request, Study $estudo, string $acao): JsonResponse|RedirectResponse|null
    {
        if ($estudo->locked_at === null) {
            return null;
        }
        $justificativa = trim((string) $request->input('justificativa', ''));
        if ($justificativa === '') {
            return $this->recusar($request, 'estudo travado: informe a justificativa');
        }
        activity('fisio')->performedOn($estudo)->causedBy($request->user())
            ->withProperties(['business_id' => (int) $estudo->business_id, 'study_id' => (int) $estudo->id, 'acao' => $acao, 'justificativa' => mb_substr($justificativa, 0, 500)])
            ->log('estudo_alterado_apos_travamento');

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function participantes(Study $estudo, bool $veNome): array
    {
        $nomes = $veNome ? DB::table('fisio_patients as p')->join('contacts as c', 'c.id', '=', 'p.contact_id')
            ->where('p.business_id', $estudo->business_id)->where('c.business_id', $estudo->business_id)
            ->pluck('c.name', 'p.id') : collect();

        return StudyParticipant::where('study_id', $estudo->id)->orderBy('code')->get()
            ->map(fn (StudyParticipant $p) => [
                'id' => $p->id, 'code' => $p->code, 'nome' => $veNome ? ($nomes[$p->patient_id] ?? null) : null,
                'entered_on' => $p->entered_on->toDateString(), 'exited_on' => $p->exited_on?->toDateString(),
                'exit_type' => $p->exit_type, 'exit_reason' => $p->exit_reason,
            ])->values()->all();
    }

    /** @return list<array{id:int,nome:string}> */
    private function pacientesForaDoEstudo(Study $estudo): array
    {
        return DB::table('fisio_patients as p')->join('contacts as c', 'c.id', '=', 'p.contact_id')
            ->where('p.business_id', $estudo->business_id)->where('c.business_id', $estudo->business_id)
            ->whereNull('p.blocked_at')
            ->whereNotExists(fn ($q) => $q->from('fisio_study_participants as sp')->whereColumn('sp.patient_id', 'p.id')->where('sp.study_id', $estudo->id))
            ->orderBy('c.name')->limit(500)->get(['p.id', 'c.name'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'nome' => (string) $r->name])->all();
    }

    /** Chamada da tela (Inertia) volta para a área do estudo; chamada de API recebe JSON. */
    private function responder(Request $request, array $json, int $status, string $msg, array $query = []): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            return $query === [] ? back()->with('success', $msg) : redirect()->route('fisio.pesquisa.index', $query)->with('success', $msg);
        }

        return response()->json($json, $status);
    }

    private function recusar(Request $request, string $motivo): JsonResponse|RedirectResponse
    {
        return $request->header('X-Inertia') ? back()->withErrors(['geral' => $motivo]) : response()->json(['erro' => $motivo], 422);
    }

    private function autorizar(Request $request): void
    {
        abort_unless((bool) $request->user()?->can(self::PERMISSAO), 403);
    }
}
