<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Services\MidiaExercicio;
use Modules\Fisio\Services\BibliotecaFacetas;
use Modules\Fisio\Services\LinguagemAnvisa;

/**
 * Biblioteca de exercícios do Fisio (US-FISIO-004).
 *
 * Dois tipos de exercício convivem na mesma tabela (ADR 0437 §4):
 *  - global (`business_id` nulo): conteúdo do oimpresso, LEITURA para toda clínica, editável por nenhuma;
 *  - próprio (`business_id` = empresa da sessão, `is_custom`): dado da clínica, invisível para as outras.
 *
 * O escopo `EscopoBibliotecaFisio` do model já entrega "global OU da sessão". Por isso o próprio de
 * outra empresa responde 404 sem código extra (o `findOrFail` não o enxerga), e o global responde 403
 * na escrita (ele é visível, mas não é seu) — UC-FBI-03.
 *
 * `business_id` vem sempre da sessão, nunca do corpo (ADR 0093). O software não sugere exercício para
 * paciente nenhum: a tela só lista e filtra; quem escolhe é o profissional (ADR 0437 §5).
 */
class BibliotecaController extends Controller
{
    private const LIMITE_LISTA = 300;

    public function index(Request $request): Response
    {
        $regiao = trim((string) $request->query('region', ''));
        $q = trim((string) $request->query('q', ''));
        // Posição, equipamento e "só favoritos" (BibliotecaFacetas): combinam com região e busca.
        $facetas = BibliotecaFacetas::doRequest($request);

        return Inertia::render('Fisio/Biblioteca/Index', [
            'filtros' => ['region' => $regiao, 'q' => $q, ...$facetas->paraTela()],
            'pode_editar' => $this->podeEditar($request),
            'exercicios' => Inertia::defer(fn () => $this->listar($regiao, $q, $facetas)),
            'regioes' => Inertia::defer(fn () => $this->regioes()),
            'facetas' => BibliotecaFacetas::opcoes(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->exigirEdicao($request);

        $dados = $request->validate($this->regras(false), $this->mensagens());
        $this->exigirLinguagem($dados);

        $exercicio = Exercise::query()->create([
            ...$this->normalizar($dados),
            // Da sessão, nunca do corpo: um `business_id` enviado pelo cliente é ignorado (ADR 0093).
            'business_id' => $this->businessId($request),
            'is_custom' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $exercicio->id], 201);
        }

        return back()->with('status', 'Exercício da clínica cadastrado.');
    }

    public function update(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->exigirEdicao($request);
        // Próprio de outra empresa → 404 (UC-FBI-03). O escopo do model já filtra; o where
        // explícito por business_id é a segunda camada, legível no próprio método.
        $biz = (int) $request->session()->get('user.business_id');
        $exercicio = Exercise::query()
            ->where(fn (Builder $w) => $w->whereNull('business_id')->orWhere('business_id', $biz))
            ->findOrFail($id);
        abort_if($exercicio->ehGlobal(), 403, 'Exercício da biblioteca global não pode ser alterado pela clínica.');

        $dados = $request->validate($this->regras(true), $this->mensagens());
        $this->exigirLinguagem($dados);
        $exercicio->fill($this->normalizar($dados))->save();

        if ($request->expectsJson()) {
            return response()->json(['id' => $exercicio->id]);
        }

        return back()->with('status', 'Exercício atualizado.');
    }

    /** @return array<string, mixed> */
    private function regras(bool $parcial): array
    {
        $obrigatorio = $parcial ? 'sometimes' : 'required';

        return [
            'name' => [$obrigatorio, 'string', 'max:160'],
            'region' => [$obrigatorio, 'string', 'max:40'],
            'type' => [$obrigatorio, 'string', 'max:40'],
            'pose' => ['sometimes', 'nullable', 'string', 'max:40'],
            'steps' => [$obrigatorio, 'array', 'min:1', 'max:20'],
            'steps.*' => ['string', 'max:500'],
            'synonyms' => ['sometimes', 'nullable', 'array', 'max:20'],
            'synonyms.*' => ['string', 'max:80'],
            'default_sets' => ['sometimes', 'nullable', 'integer', 'between:0,20'],
            'default_reps' => ['sometimes', 'nullable', 'integer', 'between:0,200'],
            'default_hold_s' => ['sometimes', 'nullable', 'integer', 'between:0,600'],
            'times_per_day' => ['sometimes', 'integer', 'between:1,10'],
            'safety_notes' => ['sometimes', 'nullable', 'array', 'max:10'],
            'safety_notes.*' => ['string', 'max:300'],
            'needs_support' => ['sometimes', 'boolean'],
            ...BibliotecaFacetas::regrasCadastro(),
        ];
    }

    /**
     * Texto que o paciente lê sem promessa clínica (B-06 · UC-FBI-12; ADR 0437 §5) → 422.
     *
     * @param  array<string, mixed>  $dados
     */
    private function exigirLinguagem(array $dados): void
    {
        LinguagemAnvisa::exigirLimpo(array_intersect_key($dados, array_flip(['name', 'steps', 'synonyms', 'safety_notes'])));
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'name.required' => 'Dê um nome ao exercício.',
            'steps.required' => 'Escreva ao menos um passo.',
            'steps.min' => 'Escreva ao menos um passo.',
            ...BibliotecaFacetas::mensagensCadastro(),
        ];
    }

    /**
     * Só os campos validados; listas sem itens vazios. `business_id` e `is_custom` nunca saem daqui.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normalizar(array $dados): array
    {
        foreach (['steps', 'synonyms', 'safety_notes'] as $lista) {
            if (array_key_exists($lista, $dados)) {
                $dados[$lista] = array_values(array_filter(
                    array_map(fn ($v) => trim((string) $v), $dados[$lista] ?? []),
                    fn (string $v) => $v !== '',
                ));
            }
        }

        return BibliotecaFacetas::normalizarCadastro($dados);
    }

    /** @return list<array<string, mixed>> */
    private function listar(string $regiao, string $q, BibliotecaFacetas $facetas): array
    {
        return $facetas->aplicar(Exercise::query())
            ->when($regiao !== '', fn (Builder $query) => $query->where('region', $regiao))
            ->when($q !== '', fn (Builder $query) => $this->buscar($query, $q))
            // Próprios da clínica primeiro, depois a global; dentro de cada grupo, por nome.
            ->orderByRaw('business_id IS NULL')
            ->orderBy('name')
            ->limit(self::LIMITE_LISTA)
            ->with('arquivos') // mídia do exercício próprio (UC-FBI-05) — escopo do Arquivo filtra pela sessão
            ->get()
            ->map(fn (Model $e) => [...$this->linha($e), ...$facetas->marcar($e)])
            ->all();
    }

    /** @return array<string, mixed> */
    private function linha(Model $e): array
    {
        assert($e instanceof Exercise);

        return [
            'id' => $e->id,
            'name' => $e->name,
            'region' => $e->region,
            'type' => $e->type,
            'pose' => $e->pose,
            'steps' => $e->steps,
            'synonyms' => $e->synonyms ?? [],
            'default_sets' => $e->default_sets,
            'default_reps' => $e->default_reps,
            'default_hold_s' => $e->default_hold_s,
            'times_per_day' => $e->times_per_day,
            'safety_notes' => $e->safety_notes ?? [],
            'needs_support' => $e->needs_support,
            'global' => $e->ehGlobal(),
            'midia' => MidiaExercicio::resumo($e),
        ];
    }

    /**
     * Busca por texto em nome e sinônimos — decisão nº 3 da ADR 0437 §14, aceita por [W] em 2026-10-18.
     * LIKE simples, sem motor externo. No MySQL a coluna `name` usa a collation da tabela (sem
     * distinção de maiúscula/acento); o JSON de sinônimos é binário, então é convertido para texto
     * com collation unicode antes de comparar.
     */
    private function buscar(Builder $query, string $q): Builder
    {
        $padrao = '%'.addcslashes($q, '%_\\').'%';
        $mysql = DB::connection()->getDriverName() === 'mysql';

        return $query->where(function (Builder $w) use ($padrao, $mysql): void {
            $w->where('name', 'like', $padrao);
            if ($mysql) {
                $w->orWhereRaw('CAST(synonyms AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci LIKE ?', [$padrao]);
            } else {
                $w->orWhere('synonyms', 'like', $padrao);
            }
        });
    }

    /** Regiões que existem na biblioteca visível — o filtro nunca oferece região vazia. @return list<string> */
    private function regioes(): array
    {
        return Exercise::query()
            ->select('region')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->filter(fn ($r) => is_string($r) && $r !== '')
            ->values()
            ->all();
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function podeEditar(Request $request): bool
    {
        return (bool) $request->user()?->can('fisio.editar');
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless($this->podeEditar($request), 403);
    }
}
