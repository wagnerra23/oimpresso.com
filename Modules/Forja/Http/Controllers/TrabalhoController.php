<?php

declare(strict_types=1);

namespace Modules\Forja\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Forja\Services\TrabalhoService;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * Trabalho — a lista única do hub Forja (`/forja/trabalho`).
 *
 * Fusão dos TRÊS backlogs que respondiam a mesma pergunta com escopos diferentes
 * (US-FORJA-006 · medido 2026-08-08). A lógica está no {@see TrabalhoService};
 * aqui só se resolve filtro de request e se defere o que é caro.
 *
 * ⚠️ **ESTA ONDA NÃO DELETA NEM REDIRECIONA NADA.** `/project-mgmt/backlog`,
 * `/forja/backlog` e `/team-mcp/tasks` seguem no ar, intactos. O motivo é
 * deliberado: remover implementação em uso é irreversível na prática, e a
 * US-FORJA-006 exige que [W] veja qual sobrevive. Com as duas no ar lado a lado,
 * a comparação é olhando — não lendo diff. Os 301 e a remoção da perdedora são a
 * onda seguinte, depois do smoke.
 *
 * Sem filtro de FRENTE por decisão [W] (2026-08-08): a lista abre com TODAS as
 * `mcp_tasks`; o recorte por projeto se faz agrupando ou buscando, não por um chip
 * que esconde o resto.
 *
 * Multi-tenant Tier 0 (ADR 0093): `mcp_tasks` é repo-wide — sem `business_id` por
 * design, igual às três telas que ele funde.
 */
class TrabalhoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:jana.mcp.usage.all');
    }

    /**
     * GET /forja/trabalho
     *
     * `Inertia::defer` nas props caras (a query da lista + os mapas de apoio); o
     * 1º paint serve só cabeçalho e filtros, que a UI precisa pra desenhar.
     */
    public function index(Request $request): Response
    {
        $svc = app(TrabalhoService::class);
        $filtros = $this->filtrosDaRequest($request);

        return Inertia::render('Forja/Trabalho/Index', [
            'titulo'   => 'Trabalho',
            'subtitle' => 'Todas as tasks do time — o backlog do projeto e o resto, na mesma lista.',
            'filtros'  => $filtros,
            // Eager: são constantes, e a UI monta os selects no 1º paint.
            'sorts'    => TrabalhoService::SORTS,
            // Quais filtros o atalho "Gantt" pode carregar. Vem do BACKEND de
            // propósito: se o front espelhasse a lista, viraria uma segunda
            // declaração que diverge na 1ª mudança — foi o custo que o espelho
            // das fases cobrou no `UC-TRAB-07`. Aqui não há o que divergir.
            'filtrosGantt' => TrabalhoService::FILTROS_ATALHO_GANTT,
            'statuses' => McpTask::STATUSES,
            'tasks'    => Inertia::defer(fn () => $svc->build($filtros)['tasks']),
            'kpis'     => Inertia::defer(fn () => $svc->build($filtros)['kpis']),
            'frentes'  => Inertia::defer(fn () => $svc->frentes()),
        ]);
    }

    /**
     * Lê os filtros da query string sobre os defaults.
     *
     * `sort` é validado contra a allowlist — valor livre viraria `FIELD(...)` sem
     * correspondência e a ordem sairia aleatória, sem erro visível.
     *
     * @return array<string,mixed>
     */
    private function filtrosDaRequest(Request $request): array
    {
        $sort = (string) $request->get('sort', 'rank');
        // `visao`/`eixo` viajam nos filtros pra que a URL carregue a vista inteira
        // (compartilhar link leva a mesma coisa que se está vendo). Ambos com
        // allowlist pela mesma razão do `sort`: valor livre viraria estado
        // desconhecido no front, que renderiza vazio sem erro.
        $visao = (string) $request->get('visao', 'lista');
        $eixo  = (string) $request->get('eixo', 'execucao');

        return array_merge(TrabalhoService::filtrosPadrao(), [
            'visao'    => in_array($visao, ['lista', 'quadro'], true) ? $visao : 'lista',
            'eixo'     => in_array($eixo, ['execucao', 'pipeline'], true) ? $eixo : 'execucao',
            'status'   => $request->get('status'),
            'priority' => $request->get('priority'),
            'owner'    => $request->get('owner'),
            'module'   => $request->get('module'),
            'frente'   => (int) $request->get('frente', 0) ?: null,
            'epic'     => (int) $request->get('epic', 0) ?: null,
            'cycle'    => (int) $request->get('cycle', 0) ?: null,
            'sprint'   => $request->get('sprint'),
            'q'        => trim((string) $request->get('q', '')),
            'sort'     => in_array($sort, TrabalhoService::SORTS, true) ? $sort : 'rank',
        ]);
    }
}
