<?php

declare(strict_types=1);

namespace App\Console\Commands\Governance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * route-hits:export — deriva governance/route-hits.json (ledger versionável de
 * execução real) da tabela agregada `route_hits`. Par PRODUTOR do sinal
 * "servido" — mesmo pattern do governance:prod-flags (dry-run default; --write
 * grava; roda no host de PROD; commit manual; NÃO editar o JSON à mão).
 *
 * Seções:
 *   rotas — identidade (nome/URI-pattern) → {hits, ultima_data} na janela
 *   pages — componente Inertia → {hits, ultima_data}: pra cada rota com hit,
 *           resolve o método de action (Reflection, só as linhas do método) e
 *           extrai o alvo de cada render Inertia literal — atribuição por
 *           MÉTODO, não por controller inteiro (evita over-attribution).
 *           (Prosa sem o token de chamada de propósito: o crawler do
 *           OrphanRenderGateTest varre app/+Modules/ por esse token e trataria
 *           um exemplo em comentário como render órfão real.)
 *
 * Consumidores: scripts/governance/anchor-lint.mjs (4º veredito advisory
 * `servido`) e scripts/governance/charter-live-signal.mjs (3ª fonte de sinal).
 *
 * ZERO PII/tenant por contrato — o JSON é público no git.
 *
 *   php artisan route-hits:export [--dias=30] [--write]
 */
class RouteHitsExportCommand extends Command
{
    protected $signature = 'route-hits:export
        {--dias=30 : Janela (dias) de hits agregados incluída no ledger}
        {--write : grava governance/route-hits.json (default: dry-run imprime)}';

    protected $description = 'Deriva governance/route-hits.json (ledger de execução real por rota/página) da tabela route_hits.';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $corte = now()->subDays($dias)->format('Y-m-d');

        $linhas = DB::table('route_hits')
            ->where('data', '>=', $corte)
            ->groupBy('rota')
            ->selectRaw('rota, SUM(hits) as hits, MAX(data) as ultima_data')
            ->get();

        $rotas = [];
        foreach ($linhas as $l) {
            $rotas[(string) $l->rota] = ['hits' => (int) $l->hits, 'ultima_data' => (string) $l->ultima_data];
        }
        ksort($rotas);

        $pages = $this->atribuirPages($rotas);

        $payload = [
            '_meta' => [
                'schema' => 'route-hits/v1',
                'purpose' => 'Ledger de EXECUCAO REAL por rota/pagina em producao (sinal "servido" — eixo runtime da governanca, regua Coverband/Wallarm). Agregado diario, janela movel.',
                'contrato' => 'rotas[<identidade>] = {hits, ultima_data} na janela; <identidade> = nome canonico Laravel OU URI-pattern (sells/{id}) — NUNCA URL resolvida. pages[<component>] = idem, atribuido via Inertia::render do METODO de action da rota. ZERO PII, ZERO tenant.',
                'fonte' => 'gerado por `php artisan route-hits:export --write` no host de prod (coleta: middleware ContadorHitsRota → route-hits:flush → tabela route_hits). Commit manual, igual prod-flags.json. NAO editar a mao — re-rodar o comando.',
                'nao_e' => 'NAO e prova de correcao (hit != funciona) nem lista de telas existentes (isso e anchor/charter). E prova de USO: 0 hits em Nd = wired-porem-nao-servido.',
                'consumido_por' => [
                    'scripts/governance/anchor-lint.mjs (advisory servido/nao_servido)',
                    'scripts/governance/charter-live-signal.mjs (3a fonte de sinal live)',
                ],
            ],
            'janela_dias' => $dias,
            'sample_rate' => (float) config('route_hits.sample_rate', 1.0),
            'rotas' => (object) $rotas,
            'pages' => (object) $pages,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

        if ($this->option('write')) {
            file_put_contents(base_path('governance/route-hits.json'), $json);
            $this->info('governance/route-hits.json escrito — '.count($rotas).' rota(s), '.count($pages).' page(s), janela '.$dias.'d.');

            return self::SUCCESS;
        }

        $this->line($json);
        $this->comment('dry-run — use --write pra gravar. '.count($rotas).' rota(s), '.count($pages).' page(s).');

        return self::SUCCESS;
    }

    /**
     * Atribui hits de rota → componente Inertia via Reflection do MÉTODO de
     * action (getStartLine..getEndLine + regex Inertia::render literal).
     * Conservador: closure/controller irresolvível/render por variável = skip.
     *
     * @param  array<string, array{hits:int, ultima_data:string}>  $rotas
     * @return array<string, array{hits:int, ultima_data:string}>
     */
    private function atribuirPages(array $rotas): array
    {
        $pages = [];
        foreach (Route::getRoutes() as $route) {
            $nome = $route->getName();
            $identidade = ($nome !== null && $nome !== '') ? $nome : $route->uri();
            if (! isset($rotas[$identidade])) {
                continue;
            }
            foreach ($this->pagesDoAction($route->getActionName()) as $component) {
                $atual = $pages[$component] ?? ['hits' => 0, 'ultima_data' => '0000-00-00'];
                $pages[$component] = [
                    'hits' => $atual['hits'] + $rotas[$identidade]['hits'],
                    'ultima_data' => max($atual['ultima_data'], $rotas[$identidade]['ultima_data']),
                ];
            }
        }
        ksort($pages);

        return $pages;
    }

    /** @return list<string> componentes Inertia renderizados pelo action `Classe@metodo` */
    private function pagesDoAction(string $action): array
    {
        if (! str_contains($action, '@')) {
            return []; // Closure — sem atribuição
        }
        [$classe, $metodo] = explode('@', $action, 2);

        try {
            $ref = new ReflectionMethod($classe, $metodo);
            $arquivo = (new ReflectionClass($classe))->getFileName();
            if ($arquivo === false || $ref->getStartLine() === false) {
                return [];
            }
            $corpo = implode('', array_slice(
                file($arquivo) ?: [],
                $ref->getStartLine() - 1,
                max(1, $ref->getEndLine() - $ref->getStartLine() + 1)
            ));
            preg_match_all('/Inertia::render\(\s*[\'"]([^\'"]+)[\'"]/', $corpo, $m);

            return array_values(array_unique($m[1]));
        } catch (Throwable) {
            return []; // action irresolvível — conservador, não inventa atribuição
        }
    }
}
