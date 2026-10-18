<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Http\Requests\SalvarExercicioRequest;

/**
 * Biblioteca de exercícios — US-FISIO-004 (Fisio/Biblioteca/Index.casos.md, UC-FBI-01..04).
 *
 * Escopo (ADR 0093): `Exercise` usa `EscopoBibliotecaFisio` — a sessão vê os GLOBAIS
 * (`business_id` nulo) e os da própria empresa, nunca os de outra. Por isso exercício de outra
 * empresa responde 404 (não existe para quem pergunta) e o global responde 403 (existe, mas
 * nenhuma empresa o edita: ele é do oimpresso, com revisão clínica).
 *
 * Revisão clínica (ADR 0437 §12 · etapa 01 §3.8): global em `rascunho`, `em_revisao` ou
 * `retirado` NÃO aparece para a clínica. O seed L0 nasce `em_revisao` (pendente da Dra. Dejeane)
 * e fica fora da grade até a revisão, salvo `fisio.biblioteca.mostrar_em_revisao` (só
 * demo/homologação). Global sem `review_status` (nulo — não veio do seed governado; é o que a
 * suíte TDD fabrica) é tratado como liberado: a trava é sobre o conteúdo que o seed marca.
 *
 * Busca por texto NÃO entra (decisão nº 3 da ADR 0437 §14 não aceita por [W]): só os filtros por
 * região e equipamento (HANDOFF-F1, ajuste de conciliação nº 3).
 *
 * Respostas: o editor da tela usa Inertia (redirect + erros de sessão); cliente JSON (testes,
 * integração) recebe 201/200/204 e 422 de validação.
 */
class BibliotecaController extends Controller
{
    /** Estados do conteúdo global que a clínica não vê (etapa 01 §3.8). */
    private const FORA_DA_GRADE = ['rascunho', 'em_revisao', 'retirado'];

    public function index(Request $request): Response
    {
        $filtros = [
            'region' => $this->filtroValido($request->query('region'), 'regioes'),
            'equipamento' => $request->query('equipamento') === 'nenhum'
                ? 'nenhum'
                : $this->filtroValido($request->query('equipamento'), 'equipamentos'),
        ];
        $biz = (int) $request->session()->get('user.business_id');

        return Inertia::render('Fisio/Biblioteca/Index', [
            'filtros' => $filtros,
            'facetas' => [
                'regioes' => config('fisio.biblioteca.regioes'),
                'tipos' => config('fisio.biblioteca.tipos'),
                'posicoes' => config('fisio.biblioteca.posicoes'),
                'equipamentos' => config('fisio.biblioteca.equipamentos'),
                'contraindicacoes' => config('fisio.condition_flags'),
            ],
            'pode_editar' => (bool) $request->user()?->can('fisio.editar'),
            'exercicios' => Inertia::defer(fn () => $this->listar($filtros, $biz)),
            'aguardando_revisao' => Inertia::defer(fn () => Exercise::query()->whereNull('business_id')
                ->whereIn('review_status', self::FORA_DA_GRADE)->count()),
        ]);
    }

    public function store(SalvarExercicioRequest $request): JsonResponse|RedirectResponse
    {
        $biz = (int) $request->session()->get('user.business_id');

        // business_id SEMPRE da sessão; o do corpo é ignorado (UC-FBI-02).
        $ex = Exercise::query()->create(array_merge(
            ['equipment' => [], 'safety_notes' => [], 'contraindication_flags' => [], 'times_per_day' => 1, 'needs_support' => false],
            $request->conteudo(),
            ['business_id' => $biz, 'is_custom' => true, 'code' => null, 'review_status' => null],
        ));

        return $this->responder($request, 'Exercício da clínica criado.', ['id' => $ex->id], 201);
    }

    public function update(SalvarExercicioRequest $request, int $id): JsonResponse|RedirectResponse
    {
        $ex = $this->proprioOuFalha($id);
        $ex->update($request->conteudo());

        return $this->responder($request, 'Exercício atualizado.', ['id' => $ex->id], 200);
    }

    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()?->can('fisio.editar'), 403);
        $ex = $this->proprioOuFalha($id);

        // Exercício já prescrito não sai: o programa do paciente (e o prontuário) aponta para ele.
        $emUso = DB::table('fisio_program_items')->where('business_id', $ex->business_id)->where('exercise_id', $ex->id)->exists();
        if ($emUso) {
            $msg = 'Este exercício está em programa de paciente e não pode ser removido.';
            if ($this->querJson($request)) {
                return response()->json(['erro' => 'em_uso', 'message' => $msg], 409);
            }

            return back()->with('status', ['success' => 0, 'msg' => $msg]);
        }

        $ex->delete();

        return $this->querJson($request)
            ? response()->noContent()
            : back()->with('status', ['success' => 1, 'msg' => 'Exercício removido.']);
    }

    /**
     * Resolve pelo escopo da biblioteca: de outra empresa → 404; global → 403 (de ninguém).
     */
    private function proprioOuFalha(int $id): Exercise
    {
        $ex = Exercise::query()->findOrFail($id);
        abort_if($ex->ehGlobal(), 403, 'Exercício da biblioteca do oimpresso não é editável. Crie um exercício próprio.');

        return $ex;
    }

    /**
     * @param  array{region: ?string, equipamento: ?string}  $filtros
     * @return list<array<string, mixed>>
     */
    private function listar(array $filtros, int $biz): array
    {
        $mostrarEmRevisao = $this->mostrarEmRevisao();

        return Exercise::query()
            ->when(! $mostrarEmRevisao, fn (Builder $q) => $q->where(fn (Builder $v) => $v
                ->whereNotNull('business_id')
                ->orWhereNull('review_status')
                ->orWhereNotIn('review_status', self::FORA_DA_GRADE)))
            ->when($filtros['region'], fn (Builder $q, string $r) => $q->where('region', $r))
            ->when($filtros['equipamento'] === 'nenhum', fn (Builder $q) => $q->where(fn (Builder $e) => $e
                ->whereNull('equipment')->orWhereJsonLength('equipment', 0)))
            ->when($filtros['equipamento'] && $filtros['equipamento'] !== 'nenhum', fn (Builder $q) => $q->whereJsonContains('equipment', $filtros['equipamento']))
            ->orderByRaw('business_id IS NULL') // os da clínica primeiro
            ->orderBy('name')
            ->get()
            ->map(fn (Exercise $e) => [
                'id' => $e->id,
                'code' => $e->code,
                'name' => $e->name,
                'region' => $e->region,
                'type' => $e->type,
                'pose' => $e->pose,
                'equipment' => array_values((array) $e->equipment),
                'steps' => array_values((array) $e->steps),
                'default_sets' => $e->default_sets,
                'default_reps' => $e->default_reps,
                'default_hold_s' => $e->default_hold_s,
                'times_per_day' => $e->times_per_day,
                'safety_notes' => array_values((array) $e->safety_notes),
                'contraindication_flags' => array_values((array) $e->contraindication_flags),
                'needs_support' => (bool) $e->needs_support,
                'is_custom' => (bool) $e->is_custom,
                'global' => $e->ehGlobal(),
                'editavel' => (int) $e->business_id === $biz,
                'review_status' => $e->review_status,
            ])
            ->values()
            ->all();
    }

    private function mostrarEmRevisao(): bool
    {
        return (bool) config('fisio.biblioteca.mostrar_em_revisao', false);
    }

    private function filtroValido(mixed $valor, string $faceta): ?string
    {
        return is_string($valor) && array_key_exists($valor, (array) config("fisio.biblioteca.{$faceta}")) ? $valor : null;
    }

    private function querJson(Request $request): bool
    {
        return ! $request->header('X-Inertia') && $request->expectsJson();
    }

    /** @param  array<string, mixed>  $dados */
    private function responder(Request $request, string $msg, array $dados, int $status): JsonResponse|RedirectResponse
    {
        if ($this->querJson($request)) {
            return response()->json($dados, $status);
        }

        return back()->with('status', ['success' => 1, 'msg' => $msg]);
    }
}
