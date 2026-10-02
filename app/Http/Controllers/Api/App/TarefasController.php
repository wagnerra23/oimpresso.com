<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Essentials\Entities\ToDo;
use Modules\Essentials\Services\TodoService;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Http\Middleware\CheckPontoAccess;

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
 */
class TarefasController extends Controller
{
    public function __construct(private ModuleUtil $moduleUtil, private TodoService $todos)
    {
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

        /** @var ToDo|null $todo */
        $todo = $this->todos->scopedQueryForUser($bizId, $user)->find($id);
        if (! $todo) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Tarefa não encontrada.'], 404);
        }

        $todo->update(['status' => 'completed']);

        return response()->json(['id' => 'todo:' . $todo->id, 'concluida' => true]);
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

    private function temEssentials(User $user, int $bizId): bool
    {
        return $user->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($bizId, 'essentials_module');
    }

    private function itensTodo(User $user, int $bizId): Collection
    {
        if (! $this->temEssentials($user, $bizId)) {
            return collect();
        }

        return $this->todos->scopedQueryForUser($bizId, $user)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'completed'))
            ->orderBy('date')
            ->limit(100)
            ->get()
            ->map(function ($t) {
                /** @var ToDo $t */
                $prazo = $t->end_date ?? $t->date;

                return $this->item(
                    'todo:' . $t->id,
                    'todo',
                    trim(strip_tags((string) $t->task)),
                    'Tarefa' . ($t->priority ? ' · ' . $this->prioridade((string) $t->priority) : ''),
                    $prazo ? Carbon::parse($prazo)->toDateString() : null,
                );
            });
    }

    private function itensPonto(User $user, int $bizId): Collection
    {
        $q = DB::table('ponto_intercorrencias as i')
            ->join('ponto_colaborador_config as c', 'c.id', '=', 'i.colaborador_config_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where('i.business_id', $bizId)
            ->where('c.business_id', $bizId)
            ->where('i.estado', Intercorrencia::ESTADO_PENDENTE);

        if (! CheckPontoAccess::permite($user)) {
            $q->where('c.user_id', $user->id);
        }

        return $q->orderBy('i.data')
            ->limit(100)
            ->get(['i.id', 'i.codigo', 'i.tipo', 'i.data', 'u.first_name', 'u.last_name'])
            ->map(fn ($i) => $this->item(
                'ponto:' . $i->id,
                'ponto',
                'Justificativa ' . $i->codigo,
                trim(((string) $i->first_name) . ' ' . ((string) $i->last_name)) . ' · ' . $this->tipo((string) $i->tipo),
                substr((string) $i->data, 0, 10),
            ));
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

    private function prioridade(string $p): string
    {
        return ['low' => 'baixa', 'medium' => 'média', 'high' => 'alta', 'urgent' => 'urgente'][$p] ?? $p;
    }

    private function tipo(string $t): string
    {
        foreach (\Modules\Ponto\Http\Controllers\IntercorrenciaController::tiposDisponiveis() as $op) {
            if ($op['value'] === $t) {
                return $op['label'];
            }
        }

        return $t;
    }
}
