<?php

declare(strict_types=1);

namespace Modules\Forja\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Forja\Services\ForjaAprovacoesService;
use Modules\Forja\Services\ForjaQuadroService;
use Modules\Forja\Services\TrabalhoService;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * Trabalho — a lista única do hub Forja (`/forja/trabalho`).
 *
 * Fusão dos TRÊS backlogs que respondiam a mesma pergunta com escopos diferentes
 * (US-FORJA-006 · medido 2026-08-08). A lógica está no {@see TrabalhoService};
 * aqui só se resolve filtro de request e se defere o que é caro.
 *
 * HISTÓRICO da US-FORJA-006: esta tela nasceu (2026-08-08) convivendo com
 * `/project-mgmt/backlog`, `/forja/backlog` e `/team-mcp/tasks` de propósito —
 * [W] precisava ver as três no ar pra escolher qual sobrevivia.
 *
 * ESCOLHIDO. Em 2026-09-02 [W] decidiu pelo protótipo (PARIDADE §11) e a Onda 11
 * revogou `/project-mgmt/backlog`: virou 301 pra cá (`?visao=lista`). `/forja/backlog`
 * e `/team-mcp/tasks` seguem no ar — saíram do topnav, não da árvore, e a remoção
 * deles depende da Onda 3 (o Cockpit ainda os serve).
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
            // Badge de pendências do topnav (§3.1 do export): no protótipo ele vive no
            // destino Aprovações em TODA view — é o que avisa que há algo esperando
            // decisão enquanto você está em OUTRA tela. COUNT indexado, deferido.
            'pendencias' => Inertia::defer(fn () => app(ForjaAprovacoesService::class)->contagem()),
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
            // PARIDADE §11 Onda 4 — as allowlists dos controles novos vêm do
            // BACKEND pelo mesmo motivo de `sorts` e `filtrosGantt`: espelhá-las
            // no front criaria uma 2ª declaração pra divergir na 1ª mudança.
            'grupos'   => TrabalhoService::GRUPOS,
            'papeis'   => TrabalhoService::PAPEIS,
            // O selo de fase da linha mostra o rótulo (`F3 Code` → `Code`), e o
            // dono do pipeline é o `ForjaQuadroService` — travado contra a fonte de
            // design pelo `PipelineParidadeTest`. Servir daqui evita uma 3ª
            // declaração das fases no front.
            'fases'    => ForjaQuadroService::fases(),
            // A lista é o pool RECORTADO pelo KPI-filtro e pelo Papel; os KPIs
            // seguem medindo o pool inteiro (é o cartão que diz o tamanho do
            // problema). A memoização de `build` garante UMA query pras duas.
            'tasks'    => Inertia::defer(fn () => $svc->filtrar($svc->build($filtros)['tasks'], $filtros)),
            'kpis'     => Inertia::defer(fn () => $svc->build($filtros)['kpis']),
            'frentes'  => Inertia::defer(fn () => $svc->frentes()),
            // Alimenta o <ActorSeal> (agente vs humano) — deferido igual ao irmão
            // em TasksAdminController: é lista pequena, mas sai de outra tabela e
            // não vale segurar o 1º paint por ela.
            'agents'   => Inertia::defer(fn () => $svc->agentes()),
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
        // Mesma allowlist pelos mesmos dois motivos: valor livre viraria estado
        // desconhecido no front (que renderiza vazio sem erro) e, no caso de
        // `saude`/`papel`, recorte silencioso que ninguém pediu.
        $grupo = (string) $request->get('grupo', 'frente');
        $saude = (string) $request->get('saude', '');
        $papel = (string) $request->get('papel', '');

        return array_merge(TrabalhoService::filtrosPadrao(), [
            'visao'    => in_array($visao, ['lista', 'quadro'], true) ? $visao : 'lista',
            'eixo'     => in_array($eixo, ['execucao', 'pipeline'], true) ? $eixo : 'execucao',
            'grupo'    => in_array($grupo, TrabalhoService::GRUPOS, true) ? $grupo : 'frente',
            'saude'    => in_array($saude, TrabalhoService::SAUDE, true) ? $saude : null,
            'papel'    => in_array($papel, TrabalhoService::PAPEIS, true) ? $papel : null,
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
