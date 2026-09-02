<?php

declare(strict_types=1);

namespace Modules\Forja\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * Saúde da Forja — view `saude` do protótipo (PARIDADE §11 Onda 7).
 *
 * REUSA os builders que já existem em vez de refazer a consulta:
 *   - {@see ScorecardBuilderService} → facts (chamadas/devs 7d) + checks (ok/fail);
 *   - {@see ForjaQuadroService}      → as 7 fases canônicas e seus cards (WIP);
 *   - {@see ForjaChangelogService}   → throughput (o que shippou).
 * O único SQL próprio daqui é o que nenhum deles produz: as SÉRIES diárias (7 pontos)
 * que alimentam o sparkline, e o aging do backlog.
 *
 * **Sem dado fantasma, e a régua é explícita:** o sparkline só existe onde a série É a
 * história da própria métrica (chamadas/dia, movimentações/dia, devs/dia — todas com
 * `ts`/`occurred_at` reais). "Checks verdes" não tem histórico persistido em lugar
 * nenhum, então vem SEM série — em vez de uma linha inventada. Série rotulada como
 * histórico de outra grandeza seria medir a propriedade errada (§5 2026-07-16).
 *
 * **Custo (`custo_brl`) NÃO entra nesta tela**, embora `buildFacts()` o traga e o
 * `/team-mcp/scorecard` o mostre: valor em BRL vira screenshot e smoke, e este cockpit
 * é a superfície que mais recebe os dois. Proibição Tier 0 (memory/proibicoes.md).
 *
 * Multi-tenant Tier 0: as `mcp_*` são repo-wide por desenho (ADR 0070/0093), igual
 * Scorecard/Quadro/Changelog — sem filtro `business_id`, INTENCIONAL.
 */
class ForjaSaudeService
{
    /** Janela das séries e dos fatos do scorecard (dias). */
    private const JANELA_DIAS = 7;

    /** Aging do backlog: até 3 dias = fresco, até 7 = atenção, acima = parado. */
    private const AGING_FRESCO = 3;

    private const AGING_ATENCAO = 7;

    public function __construct(
        private ScorecardBuilderService $scorecard,
        private ForjaQuadroService $quadro,
        private ForjaChangelogService $changelog,
    ) {
    }

    /**
     * @return array{
     *   metricas: list<array{label:string,valor:string,lim:string,nota:string,estado:string,hue:int,serie:list<float>|null,drill:string|null}>,
     *   wip: list<array{id:string,label:string,n:int,hue:int}>,
     *   fluxo: array{entregas:int,aging:array{fresco:int,atencao:int,parado:int}},
     *   checks: list<array{id:string,fase:string,estado:string,rotulo:string}>,
     *   janelaDias: int
     * }
     */
    public function build(?int $projectId): array
    {
        $facts = $this->scorecard->buildFacts();
        $checks = $this->scorecard->buildChecks();

        return [
            'metricas' => $this->metricas($facts, $checks),
            'wip' => $this->wipPorFase($projectId),
            'fluxo' => [
                'entregas' => count($this->changelog->build()),
                'aging' => $this->aging($projectId),
            ],
            'checks' => $this->checks($checks),
            'janelaDias' => self::JANELA_DIAS,
        ];
    }

    /**
     * As 4 métricas do protótipo, com dado real. `serie` null = sem histórico
     * persistido; o front NÃO desenha sparkline nesse caso (nada de linha inventada).
     *
     * @param  array<string,mixed>  $facts
     * @param  list<array{name:string,ok:bool,detail:string}>  $checks
     * @return list<array{label:string,valor:string,lim:string,nota:string,estado:string,hue:int,serie:list<float>|null,drill:string|null}>
     */
    private function metricas(array $facts, array $checks): array
    {
        $verdes = count(array_filter($checks, static fn (array $c): bool => $c['ok']));
        $totalChecks = count($checks);
        $chamadas = (int) ($facts['calls_7d'] ?? 0);
        $devs = (int) ($facts['users_ativos_7d'] ?? 0);
        $eventos = $this->serieEventos();
        $movimentacoes = array_sum($eventos);

        return [
            [
                'label' => 'Chamadas MCP',
                'valor' => (string) $chamadas,
                'lim' => self::JANELA_DIAS.'d',
                'nota' => 'tools chamadas pelo time e pelos agentes',
                'estado' => $chamadas > 0 ? 'ok' : 'warn',
                'hue' => $chamadas > 0 ? 150 : 68,
                'serie' => $this->normaliza($this->serieChamadas()),
                'drill' => '/team-mcp/scorecard',
            ],
            [
                'label' => 'Movimentações',
                'valor' => (string) $movimentacoes,
                'lim' => self::JANELA_DIAS.'d',
                'nota' => 'eventos de task no período (o loop andando)',
                'estado' => $movimentacoes > 0 ? 'ok' : 'warn',
                'hue' => 295,
                'serie' => $this->normaliza($eventos),
                'drill' => '/forja/trabalho',
            ],
            [
                'label' => 'Devs ativos',
                'valor' => (string) $devs,
                'lim' => self::JANELA_DIAS.'d',
                'nota' => 'contas distintas com chamada no período',
                'estado' => $devs > 0 ? 'ok' : 'warn',
                'hue' => $devs > 0 ? 150 : 68,
                'serie' => $this->normaliza($this->serieDevs()),
                'drill' => '/team-mcp/scorecard',
            ],
            [
                'label' => 'Checks verdes',
                'valor' => $verdes.'/'.$totalChecks,
                'lim' => 'meta '.$totalChecks.'/'.$totalChecks,
                'nota' => 'saúde do MCP — sem histórico persistido, logo sem série',
                'estado' => $totalChecks > 0 && $verdes === $totalChecks ? 'ok' : 'warn',
                'hue' => 150,
                // Sem série DE PROPÓSITO: nenhuma tabela guarda o histórico dos checks.
                'serie' => null,
                'drill' => '/team-mcp/scorecard',
            ],
        ];
    }

    /**
     * WIP por fase — conta os cards que o {@see ForjaQuadroService} já projeta,
     * sem repetir a query dele.
     *
     * @return list<array{id:string,label:string,n:int,hue:int}>
     */
    private function wipPorFase(?int $projectId): array
    {
        // Hues das fases = os do protótipo (`forja-data.jsx` FORJA_PHASES), na ordem
        // canônica do board. Fase fora do mapa cai no hue de F0 em vez de quebrar.
        $hues = ['F0' => 250, 'F1' => 295, 'F1.5' => 270, 'F2' => 60, 'F3' => 195, 'F3.5' => 150, 'F4' => 145];

        // Sem `?? []` nem `array_values()`: o `build()` do quadro declara
        // `array{fases: list<array{key,label,cards}>}`, então as chaves SEMPRE existem e o
        // `array_map` sobre uma list já devolve list — o PHPStan reprova a redundância.
        return array_map(static fn (array $f): array => [
            'id' => (string) $f['key'],
            'label' => (string) $f['label'],
            'n' => count($f['cards']),
            'hue' => $hues[$f['key']] ?? 250,
        ], $this->quadro->build($projectId)['fases']);
    }

    /**
     * Aging do backlog por `updated_at` — há quanto tempo cada issue parada está sem toque.
     *
     * @return array{fresco:int,atencao:int,parado:int}
     */
    private function aging(?int $projectId): array
    {
        $out = ['fresco' => 0, 'atencao' => 0, 'parado' => 0];

        if ($projectId === null || ! Schema::hasTable('mcp_tasks')) {
            return $out;
        }

        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design
        $tasks = McpTask::query()
            ->where('project_id', $projectId)
            ->where('status', 'backlog')
            ->limit(500)
            ->get(['updated_at']);

        foreach ($tasks as $t) {
            $dias = $t->updated_at ? (int) $t->updated_at->diffInDays(Carbon::now()) : PHP_INT_MAX;

            if ($dias <= self::AGING_FRESCO) {
                $out['fresco']++;
            } elseif ($dias <= self::AGING_ATENCAO) {
                $out['atencao']++;
            } else {
                $out['parado']++;
            }
        }

        return $out;
    }

    /**
     * Checks do scorecard no vocabulário visual da lista de gates do protótipo.
     * `fase` recebe o detalhe do check (é o slot de texto secundário da linha).
     *
     * @param  list<array{name:string,ok:bool,detail:string}>  $checks
     * @return list<array{id:string,fase:string,estado:string,rotulo:string}>
     */
    private function checks(array $checks): array
    {
        // `array_map` sobre a list de checks já devolve list — `array_values()` seria no-op.
        return array_map(static fn (array $c): array => [
            'id' => (string) $c['name'],
            'fase' => (string) $c['detail'],
            'estado' => $c['ok'] ? 'green' : 'red',
            'rotulo' => $c['ok'] ? 'verde' : 'vermelho',
        ], $checks);
    }

    /**
     * Série diária de chamadas MCP (7 pontos, do mais antigo pro mais novo).
     *
     * @return list<int>
     */
    private function serieChamadas(): array
    {
        return $this->seriePorDia('mcp_audit_log', 'ts', static fn ($q): int => (int) $q->count());
    }

    /**
     * Série diária de contas distintas com chamada.
     *
     * @return list<int>
     */
    private function serieDevs(): array
    {
        return $this->seriePorDia('mcp_audit_log', 'ts', static fn ($q): int => (int) $q->distinct()->count('user_id'));
    }

    /**
     * Série diária de eventos de task (o loop andando).
     *
     * @return list<int>
     */
    private function serieEventos(): array
    {
        return $this->seriePorDia('mcp_task_events', 'occurred_at', static fn ($q): int => (int) $q->count());
    }

    /**
     * Conta uma métrica por dia nos últimos {@see self::JANELA_DIAS} dias.
     *
     * Um SELECT por dia (7) em vez de GROUP BY porque o agregador varia (count ×
     * distinct) e 7 counts indexados por data custam menos que generalizar o GROUP BY
     * pros dois casos. Tabela ausente devolve zeros — o front distingue "sem dado" de
     * "zero" pelo `serie: null` das métricas que não têm histórico.
     *
     * @param  callable(\Illuminate\Database\Query\Builder): int  $agregador
     * @return list<int>
     */
    private function seriePorDia(string $tabela, string $coluna, callable $agregador): array
    {
        if (! Schema::hasTable($tabela)) {
            return array_fill(0, self::JANELA_DIAS, 0);
        }

        $tenancy = 'business_id'; // marker NoMissingTenantScopeRule — mcp_* repo-wide (ADR 0070/0093), sem tenant por design
        $serie = [];

        for ($i = self::JANELA_DIAS - 1; $i >= 0; $i--) {
            $dia = Carbon::today()->subDays($i);
            $serie[] = $agregador(
                DB::table($tabela)->whereBetween($coluna, [$dia->copy()->startOfDay(), $dia->copy()->endOfDay()])
            );
        }

        return $serie;
    }

    /**
     * Normaliza a série pro intervalo 0..1 que o sparkline desenha (o pico vira 1).
     * Série toda zerada devolve zeros — linha rente à base, que é a verdade.
     *
     * @param  list<int>  $serie
     * @return list<float>
     */
    private function normaliza(array $serie): array
    {
        $max = $serie === [] ? 0 : max($serie);

        return $max > 0
            ? array_map(static fn (int $v): float => round($v / $max, 4), $serie)
            : array_map(static fn (int $v): float => 0.0, $serie);
    }
}
