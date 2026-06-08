<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 8c KB-9.75 — Sparkline com dados REAIS via endpoint backend.
 *
 * Substitui o path SVG estático (Onda 8) por dados agregados das baixas
 * (TituloBaixa) dia-a-dia nos últimos 30d.
 *
 * Cobre:
 *   - Rota GET /financeiro/unificado/saldo-sparkline registrada
 *   - Método saldoSparkline retorna JSON shape canon (points, saldo_atual, saldo_baseline_30d, periodo)
 *   - Algoritmo: 30 pontos × {date, saldo, in, out}
 *   - Multi-tenant Tier 0: filtra business_id explícito
 *   - SQL usa fin_titulo_baixas (não titulo_baixas)
 *   - Frontend: useSparkline30d hook + FinSparkline aceita prop points
 *   - Fallback placeholder quando points < 2 (loading/error/sem dados)
 */

const FIN_BASE_8C = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_CTRL_8C = __DIR__ . '/../../Http/Controllers/UnificadoController.php';
const FIN_ROUTES_8C = __DIR__ . '/../../Routes/web.php';

describe('Onda 8c — Endpoint backend saldoSparkline', function () {
    it('Routes/web.php registra GET unificado/saldo-sparkline', function () {
        $src = file_get_contents(FIN_ROUTES_8C);
        expect($src)->toContain("Route::get('/unificado/saldo-sparkline'");
        expect($src)->toContain("UnificadoController::class, 'saldoSparkline'");
        expect($src)->toContain('unificado.saldo-sparkline');
    });

    it('UnificadoController::saldoSparkline existe + return JsonResponse', function () {
        $src = file_get_contents(FIN_CTRL_8C);
        expect($src)->toContain('public function saldoSparkline(Request $request): JsonResponse');
        expect($src)->toContain('use Illuminate\Http\JsonResponse');
    });

    it('Algoritmo: 30 pontos com running sum, baseline 30d atrás', function () {
        $src = file_get_contents(FIN_CTRL_8C);
        // saldo atual de ContaBancaria
        expect($src)->toContain("ContaBancaria::where('business_id', \$businessId)");
        expect($src)->toContain("->sum('saldo_cached')");
        // for de 30 pontos
        expect($src)->toContain('for ($i = 0; $i < 30; $i++)');
        // shape retornado
        expect($src)->toContain("'points'");
        expect($src)->toContain("'saldo_atual'");
        expect($src)->toContain("'saldo_baseline_30d'");
        expect($src)->toContain("'periodo'");
    });

    it('Multi-tenant Tier 0: filtra business_id explícito no SQL', function () {
        $src = file_get_contents(FIN_CTRL_8C);
        // Pega o trecho do método saldoSparkline pra checar isolamento
        $start = strpos($src, 'public function saldoSparkline');
        $end = strpos($src, 'private function parseFilters');
        $methodSrc = substr($src, $start, $end - $start);

        expect($methodSrc)->toContain("session('user.business_id')");
        expect($methodSrc)->toContain("where('tb.business_id', \$businessId)");
        // proteção: business_id <= 0 retorna 400 (não pode vazar)
        expect($methodSrc)->toContain('businessId <= 0');
    });

    it('SQL usa tabela canônica fin_titulo_baixas (não titulo_baixas)', function () {
        $src = file_get_contents(FIN_CTRL_8C);
        $start = strpos($src, 'public function saldoSparkline');
        $end = strpos($src, 'private function parseFilters');
        $methodSrc = substr($src, $start, $end - $start);

        expect($methodSrc)->toContain('fin_titulo_baixas as tb');
        expect($methodSrc)->toContain('fin_titulos as t');
        // garante NÃO existe o nome errado
        expect($methodSrc)->not->toMatch('/[^\w_]titulo_baixas\b/');
    });

    it('Estornos não contam (whereNull estorno_de_id)', function () {
        $src = file_get_contents(FIN_CTRL_8C);
        $start = strpos($src, 'public function saldoSparkline');
        $end = strpos($src, 'private function parseFilters');
        $methodSrc = substr($src, $start, $end - $start);
        expect($methodSrc)->toContain('whereNull(\'tb.estorno_de_id\')');
    });
});

describe('Onda 8c — Frontend useSparkline30d + FinSparkline points', function () {
    it('FinSparkline aceita prop points opcional', function () {
        $src = file_get_contents(FIN_BASE_8C . '/Index.tsx');
        expect($src)->toContain('interface SparkPoint');
        expect($src)->toContain('points?: SparkPoint[] | null');
        expect($src)->toContain("function FinSparkline({ tone = 'pos', points }:");
    });

    it('Hook useSparkline30d fetch /financeiro/unificado/saldo-sparkline', function () {
        $src = file_get_contents(FIN_BASE_8C . '/Index.tsx');
        expect($src)->toContain('function useSparkline30d()');
        expect($src)->toContain("fetch('/financeiro/unificado/saldo-sparkline'");
        expect($src)->toContain("'X-Requested-With': 'XMLHttpRequest'");
        expect($src)->toContain("credentials: 'same-origin'");
    });

    it('Fallback placeholder quando points < 2 (loading/error/sem dados)', function () {
        $src = file_get_contents(FIN_BASE_8C . '/Index.tsx');
        expect($src)->toContain('!points || points.length < 2');
    });

    it('KpiBar instancia useSparkline30d + passa points pro FinSparkline', function () {
        $src = file_get_contents(FIN_BASE_8C . '/Index.tsx');
        expect($src)->toContain('const sparkPoints = useSparkline30d()');
        expect($src)->toContain('points={sparkPoints}');
    });

    it('Algoritmo SVG path canon: normalização min/max + linePath/fillPath', function () {
        $src = file_get_contents(FIN_BASE_8C . '/Index.tsx');
        expect($src)->toContain('const minS = Math.min(...saldos)');
        expect($src)->toContain('const maxS = Math.max(...saldos)');
        expect($src)->toContain('const range = maxS - minS || 1');
        expect($src)->toContain('linePath');
        expect($src)->toContain('fillPath');
    });
});
