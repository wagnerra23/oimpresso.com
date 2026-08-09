<?php

declare(strict_types=1);

namespace Modules\Forja\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Forja\Services\ForjaAprovacoesService;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * Mesa de Aprovações — a superfície do funil de admissão (ADR 0368).
 *
 * A ADR 0368 (aceita 2026-08-04) fechou a POLÍTICA e disse, textual, que "o código
 * vai em PR próprio, com evidência". O estado (`pending_approval`), o FSM e a trava
 * de recusa-sem-motivo chegaram em #5283/#5288. Faltava a tela: a fila existia no
 * banco e em lugar nenhum que o [W] pudesse olhar — o Daily Brief chegou a anunciar
 * "HITL pending: 4" sem que houvesse onde clicar.
 *
 * ⚠️ O `hitl_pending` do brief NÃO é esta fila. A procedure
 * (`2026_05_07_120000_fix_brief_aggregator...`) mede `status='blocked' AND
 * owner='wagner'` — exatamente o proxy que a ADR 0368 §3 aposentou por misturar
 * "espera decisão" com "travado por dependência". Reconciliar é migration de
 * procedure (+ ProcedureDriftSnapshotTest) e vai em PR próprio: 1 PR = 1 intent.
 *
 * Escrita: SEMPRE via {@see TaskCrudService}, o mesmo chokepoint da tool MCP
 * `tasks-update`. Não há caminho alternativo — é o que faz o FSM e a
 * recusa-com-motivo valerem também na web. Escrever direto no Eloquent aqui
 * deixaria as duas regras de fora, em silêncio.
 *
 * Permissão: `jana.mcp.usage.all`, igual às outras telas do hub. A ADR 0368 §4 é
 * explícita em NÃO criar permission de aprovação enquanto houver um só aprovador
 * ("com um único aprovador, permission é cerimônia sem função").
 *
 * Multi-tenant Tier 0 (ADR 0093): `mcp_tasks` é repo-wide (governança da
 * plataforma) — sem `business_id` por design, igual TriageController.
 */
class AprovacoesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:jana.mcp.usage.all');
    }

    /**
     * GET /forja/aprovacoes — a mesa.
     *
     * `Inertia::defer` nas props caras (a fila roda query): o 1º paint serve só o
     * cabeçalho, e o partial reload não repete a consulta quando não a pede.
     */
    public function index(): Response
    {
        $svc = app(ForjaAprovacoesService::class);

        return Inertia::render('Forja/Aprovacoes/Index', [
            'titulo'   => 'Aprovações',
            'subtitle' => 'O que espera por uma decisão sua — mais antigo primeiro.',
            // Não é caro (constante derivada do FSM) e a UI precisa dele no 1º paint
            // pra desenhar os botões: vai eager de propósito.
            'decisoes' => $svc->decisoesPossiveis(),
            'fila'     => Inertia::defer(fn () => $svc->fila()),
            'contagem' => Inertia::defer(fn () => $svc->contagem()),
        ]);
    }

    /**
     * POST /forja/aprovacoes/{taskId}/decidir — admite, parqueia ou recusa.
     *
     * Uma rota só pras três saídas porque as três são a MESMA operação de domínio
     * (uma transição a partir de `pending_approval`); o que muda é o destino, que
     * o FSM já valida. Três rotas seriam três lugares pra manter a mesma regra.
     */
    public function decidir(Request $request, string $taskId): JsonResponse
    {
        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_tasks é repo-wide (ADR 0070/0093), sem tenant por design

        $task = McpTask::where('task_id', $taskId)->orWhere('identifier', $taskId)->first();
        if (! $task) {
            return response()->json(['error' => 'Task não encontrada.'], 404);
        }

        // A mesa só decide o que espera por decisão. Item que já saiu da fila
        // (outra aba, outra sessão, a tool MCP) responde 409 em vez de reabrir —
        // decisão dupla sobre o mesmo item é pior que decisão perdida.
        if ($task->status !== McpTask::AWAITING_HUMAN) {
            return response()->json([
                'error' => "Este item não está mais esperando decisão (está em '{$task->status}'). Recarregue a fila.",
            ], 409);
        }

        $destino = trim((string) $request->input('destino', ''));
        // Sem `?? []`: a chave é constante e sempre existe — o PHPStan prova, e o
        // fallback vira código morto que o ratchet reprova.
        $permitidos = McpTask::TRANSITIONS[McpTask::AWAITING_HUMAN];
        if (! in_array($destino, $permitidos, true)) {
            return response()->json([
                'error' => 'Destino inválido. Permitidos: ' . implode(', ', $permitidos),
            ], 422);
        }

        $fields = ['status' => $destino];

        // ADR 0368 §5 — a recusa exige motivo. Quem enforça de verdade é o
        // TaskCrudService (throw dentro da transaction); aqui só carregamos o
        // motivo pro payload, preservando o que já houver em custom_fields.
        // O update faz ASSIGNMENT, não merge: mandar a chave sozinha apagaria o resto.
        if ($destino === 'cancelled') {
            $motivo = trim((string) $request->input('motivo', ''));
            $atual = is_array($task->custom_fields) ? $task->custom_fields : [];
            $fields['custom_fields'] = $atual + [McpTask::REFUSAL_REASON_KEY => $motivo];
        }

        try {
            app(TaskCrudService::class)->update($task->task_id, $fields, $this->resolveAuthor($request));
        } catch (\Throwable $e) {
            // Recusa sem motivo e transição ilegal chegam aqui como 422 —
            // a mensagem do service já explica o quê e por quê.
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'task_id' => $task->task_id,
            'status'  => $destino,
        ]);
    }

    /** Autor da decisão pro audit trail — mesmo critério do ForjaController. */
    protected function resolveAuthor(Request $request): string
    {
        $explicit = trim((string) $request->input('author', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $user = $request->user();
        if ($user) {
            return strtolower($user->username ?? $user->first_name ?? 'system');
        }

        return 'system';
    }
}
