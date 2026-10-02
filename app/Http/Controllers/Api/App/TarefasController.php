<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Contracts\Tarefas\JustificativasPonto;
use App\Contracts\Tarefas\TarefasEssentials;
use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Tarefas do app das lojas (oimpresso-app). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §3.
 * D11 ([W]): Tarefas = ToDo + justificativas do Ponto. Urgente = atrasado (prazo vencido).
 *
 * - ToDo: as do usuário pelo mesmo escopo da tela web (TodoService::scopedQueryForUser — admin vê
 *   todas da empresa; demais, as criadas por ele ou atribuídas a ele), não concluídas, e só se a
 *   empresa tem o módulo Essentials no pacote.
 * - Ponto: quem pode abrir o Ponto (CheckPontoAccess::permite, o mesmo critério das aprovações)
 *   vê as justificativas PENDENTES da empresa; o colaborador vê só as próprias pendentes.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token, explícito em toda consulta.
 *
 * Os dois módulos entram por contrato do núcleo (app/Contracts/Tarefas), implementado e
 * registrado em cada módulo — a seta de dependência fica módulo → núcleo. Sem o módulo,
 * vale a implementação vazia de app/Contracts/Tarefas/Nulo.
 */
class TarefasController extends Controller
{
    public function __construct(
        private ModuleUtil $moduleUtil,
        private TarefasEssentials $todos,
        private JustificativasPonto $justificativas,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bizId = (int) $user->business_id;

        $todo = $this->itensTodo($user, $bizId);
        $ponto = $this->itensPonto($user, $bizId);

        $origem = (string) $request->query('origem', 'todas');
        $itens = match ($origem) {
            'todo' => $todo,
            'ponto' => $ponto,
            default => $todo->concat($ponto),
        };

        return response()->json([
            'itens' => $itens->sortBy(fn ($i) => $i['prazo'] ?? '9999-12-31')->values(),
            'contadores' => [
                'todas' => $todo->count() + $ponto->count(),
                'todo' => $todo->count(),
                'ponto' => $ponto->count(),
            ],
        ]);
    }

    /** POST /api/app/tarefas/todo/{id}/concluir — só ToDo visível ao usuário. */
    public function concluirTodo(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $bizId = (int) $user->business_id;

        if (! $this->temEssentials($user, $bizId)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Sua empresa não tem o módulo de tarefas.'], 403);
        }

        if (! $this->todos->concluir($user, $bizId, $id)) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Tarefa não encontrada.'], 404);
        }

        return response()->json(['id' => 'todo:' . $id, 'concluida' => true]);
    }

    /**
     * GET /api/app/tarefas/todo/{id} — detalhe da ToDo (tela 28), só se visível ao usuário (§3.1).
     * O Essentials não tem checklist, cliente nem origem: saem vazios, e a tela se adapta.
     */
    public function todo(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $bizId = (int) $user->business_id;

        if (! $this->temEssentials($user, $bizId)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Sua empresa não tem o módulo de tarefas.'], 403);
        }

        $t = $this->todos->detalhe($user, $bizId, $id);
        if ($t === null) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Tarefa não encontrada.'], 404);
        }

        return response()->json([
            'id' => 'todo:' . $t['id'],
            'titulo' => $t['titulo'],
            'descricao' => $t['descricao'],
            'modulo' => $t['rotulo'],
            'responsavel' => $t['responsavel'],
            'cliente' => null,
            'prazo' => $t['prazo'],
            'atrasado' => ! $t['concluida'] && $t['prazo'] !== null && Carbon::parse($t['prazo'])->lt(Carbon::today()),
            'origem' => null,
            'checklist' => [],
            'comentarios' => array_map(fn ($c) => $c + ['detalhe' => null], $t['comentarios']),
            'concluida' => $t['concluida'],
        ]);
    }

    /** As próximas N tarefas do usuário (ToDo + Ponto), prazo mais próximo primeiro — para o Início. */
    public function proximasPara(User $user, int $n = 3): array
    {
        $bizId = (int) $user->business_id;

        return $this->itensTodo($user, $bizId)
            ->concat($this->itensPonto($user, $bizId))
            ->sortBy(fn ($i) => $i['prazo'] ?? '9999-12-31')
            ->take($n)
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------

    /**
     * Quem tem a aba Tarefas: tarefas do Essentials no plano, ou quem aprova justificativas do
     * Ponto. O colaborador vê as próprias justificativas em Mais › Ponto, não precisa da aba.
     */
    public function podeVerTarefas(User $user): bool
    {
        return $this->temEssentials($user, (int) $user->business_id) || $this->justificativas->aprova($user);
    }

    private function temEssentials(User $user, int $bizId): bool
    {
        return $user->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($bizId, 'essentials_module');
    }

    private function itensTodo(User $user, int $bizId): Collection
    {
        if (! $this->temEssentials($user, $bizId)) {
            return collect();
        }

        return collect($this->todos->pendentes($user, $bizId))
            ->map(fn (array $t) => $this->item('todo:' . $t['id'], 'todo', $t['titulo'], $t['subtitulo'], $t['prazo']));
    }

    private function itensPonto(User $user, int $bizId): Collection
    {
        return collect($this->justificativas->pendentes($user, $bizId))
            ->map(fn (array $j) => $this->item('ponto:' . $j['id'], 'ponto', $j['titulo'], $j['subtitulo'], $j['prazo']));
    }

    private function item(string $id, string $origem, string $titulo, string $subtitulo, ?string $prazo): array
    {
        $hoje = Carbon::today();
        $grupo = 'depois';
        if ($prazo !== null) {
            $d = Carbon::parse($prazo)->startOfDay();
            $grupo = match (true) {
                $d->lt($hoje) => 'atrasadas',
                $d->eq($hoje) => 'hoje',
                $d->eq($hoje->copy()->addDay()) => 'amanha',
                $d->lte($hoje->copy()->addDays(7)) => 'semana',
                default => 'depois',
            };
        }

        return [
            'id' => $id,
            'origem' => $origem,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'prazo' => $prazo,
            'atrasado' => $grupo === 'atrasadas',
            'grupo' => $grupo,
        ];
    }
}
