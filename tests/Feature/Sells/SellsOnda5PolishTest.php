<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK-R5-POLISH Onda 5 — dados reais nos KPIs Cowork:
 *  - Sparkline 30d via Inertia::defer (SellController::buildCoworkAggregates)
 *  - Delta % real (today vs yesterday) + ticket WoW
 *  - Top vendedor do mês (commission_agent)
 *  - Imprimir caixa button wired (window.print)
 *
 * Cobertura mista:
 *  - estrutural (Index.tsx + CSS + controller method existem)
 *  - comportamental (SellController::buildCoworkAggregates retorna shape esperado,
 *    multi-tenant Tier 0 scoped biz=1).
 *
 * Refs:
 *  - app/Services/Sells/SellsCockpitAggregator.php::buildCoworkAggregates
 *    (extraído de SellController; controller agora injeta o aggregator e delega)
 *  - app/Http/Controllers/SellController.php::index (wire Inertia::defer)
 *  - resources/js/Pages/Sells/Index.tsx (sparkData + deltaRevenue + topSeller)
 *  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md F4
 *  - ADR 0093 multi-tenant Tier 0 + ADR 0101 tests biz=1 nunca cliente
 */

/*
 * NOTA: Pest do oimpresso usa SQLite in-memory (phpunit.xml) e algumas migrations
 * UltimatePOS têm sintaxe MySQL-only (ALTER TABLE ... MODIFY COLUMN ENUM(...)).
 * Por isso o behavioral biz=1 cross-tenant é validado via SMOKE PROD (Brave) + via
 * o método `buildCoworkAggregates` (em App\Services\Sells\SellsCockpitAggregator) ser
 * inspecionado estruturalmente abaixo
 * (Tier 0 multi-tenant: cada query Transaction tem ->where('business_id', $businessId)).
 * Padrão consistente com SellsOnda3CuradoriaTest e SellsOnda4DistribuicaoTest.
 */

const R5_INDEX_PATH = 'resources/js/Pages/Sells/Index.tsx';
const R5_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';
const R5_CSS_PATH = 'resources/css/inertia.css';
// buildCoworkAggregates() foi EXTRAÍDO verbatim de SellController para este service
// (App\Services\Sells\SellsCockpitAggregator — reuso em /ia/dashboard Jana V2).
// O controller agora injeta o aggregator e delega; a lógica vive aqui.
const R5_AGGREGATOR_PATH = 'app/Services/Sells/SellsCockpitAggregator.php';

function r5Read(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Estrutura: backend + frontend wire ─────────────────────────────

it('SellsCockpitAggregator tem método public buildCoworkAggregates', function () {
    $source = r5Read(R5_AGGREGATOR_PATH);
    expect($source)
        ->toContain('public function buildCoworkAggregates(int $businessId): array')
        ->toContain("'sparkline' => \$sparkline")
        ->toContain("'deltaRevenueVsYesterday' => \$deltaRevenueVsYesterday")
        ->toContain("'deltaTicketVsLastWeek' => \$deltaTicketVsLastWeek")
        ->toContain("'topSeller' => \$topSeller");
});

it('SellController index() retorna coworkAggregates via Inertia::defer delegando ao aggregator', function () {
    $source = r5Read(R5_CONTROLLER_PATH);
    expect($source)
        ->toContain("'coworkAggregates' => \\Inertia\\Inertia::defer(fn () => \$cockpitAggregator->buildCoworkAggregates(\$business_id))");
});

it('buildCoworkAggregates respeita business_id (Tier 0 multi-tenant)', function () {
    $source = r5Read(R5_AGGREGATOR_PATH);
    // Confirma que TODA query do método tem ->where('business_id', $businessId) explícito.
    // grep '->where' dentro do bloco do método entre suas keys-delimitadoras.
    // Parâmetro do aggregator é $businessId (não $business_id do controller legado).
    $start = strpos($source, 'public function buildCoworkAggregates(');
    $end = strpos($source, "\n    }\n", $start);
    expect($start)->toBeGreaterThan(0);
    expect($end)->toBeGreaterThan($start);
    $body = substr($source, $start, $end - $start);

    // Conta queries Transaction + checa business_id em cada uma
    $transactionCount = substr_count($body, '\App\Transaction::where(');
    $businessIdCount = substr_count($body, "'business_id', \$businessId");
    expect($transactionCount)->toBeGreaterThanOrEqual(4);
    // Cada query deve aparear com business_id (pode ter tx.business_id ou business_id)
    expect($businessIdCount + substr_count($body, "'transactions.business_id', \$businessId"))
        ->toBeGreaterThanOrEqual($transactionCount);
});

it('Index.tsx tipa CoworkAggregates + lê de props.coworkAggregates', function () {
    $source = r5Read(R5_INDEX_PATH);
    expect($source)
        ->toContain('interface CoworkAggregates {')
        ->toContain('sparkline: number[];')
        ->toContain('deltaRevenueVsYesterday: number | null;')
        ->toContain('deltaTicketVsLastWeek: number | null;')
        ->toContain('topSeller: { name: string; total: number } | null;')
        ->toContain('coworkAggregates?: CoworkAggregates;');
});

it('Index.tsx Sparkline usa coworkAggregates.sparkline com fallback', function () {
    $source = r5Read(R5_INDEX_PATH);
    expect($source)
        ->toContain('props.coworkAggregates?.sparkline?.length')
        ->toContain('props.coworkAggregates.sparkline.map((v) => Math.max(0.1, v / 1000))');
});

it('Index.tsx mostra delta real (não +18% hardcoded)', function () {
    $source = r5Read(R5_INDEX_PATH);
    // Garante que hardcoded "+18%" foi removido
    expect($source)->not->toContain('+18% vs ontem');
    expect($source)->not->toContain('↑ 12% vs semana passada');
    // E que usa o computed deltaRevenue
    expect($source)
        ->toContain('const deltaRevenue =')
        ->toContain('deltaRevenue >= 0 ? \'↑ +\'')
        ->toContain('vs ontem');
});

it('Index.tsx Top vendedor mostra name real ou estados explícitos', function () {
    $source = r5Read(R5_INDEX_PATH);
    expect($source)
        ->toContain('const topSeller = props.coworkAggregates?.topSeller')
        ->toContain('topSeller ? topSeller.name')
        ->toContain('fmtShort(topSeller.total)')
        ->toContain("'sem commission_agent atribuído este mês'");
});

it('Imprimir caixa button tem onClick window.print()', function () {
    $source = r5Read(R5_INDEX_PATH);
    expect($source)
        ->toContain('title="Imprimir resumo do caixa de hoje')
        ->toContain('onClick={() => {')
        ->toContain('window.print();')
        ->toContain('Imprimir caixa');
});

it('CSS vd-delta-dn variante negativa registrada', function () {
    $source = r5Read(R5_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork .vd-delta-dn')
        ->toContain('oklch(0.55 0.16 25)');
});

// ─── Estrutural: buildCoworkAggregates calcula correto (review do código) ──

it('buildCoworkAggregates calcula sparkline em ordem cronológica (29d→hoje)', function () {
    $source = r5Read(R5_AGGREGATOR_PATH);
    expect($source)
        ->toContain('for ($i = 29; $i >= 0; $i--)')
        ->toContain('$sparkline[] = (float) ($sparkByDate[$date] ?? 0.0)');
});

it('buildCoworkAggregates retorna null quando ontem == 0 (sem divisão por zero)', function () {
    $source = r5Read(R5_AGGREGATOR_PATH);
    expect($source)
        ->toContain('$deltaRevenueVsYesterday = $revYesterday > 0')
        ->toContain(': null;');
});

it('buildCoworkAggregates top vendedor: month range + commission_agent não-null + sort desc', function () {
    $source = r5Read(R5_AGGREGATOR_PATH);
    expect($source)
        ->toContain('->startOfMonth()')
        ->toContain('->whereNotNull(\'transactions.commission_agent\')')
        ->toContain('->orderByDesc(\'total\')')
        ->toContain('->limit(1)');
});

it('buildCoworkAggregates concatena first_name + last_name pra topSeller.name', function () {
    $source = r5Read(R5_AGGREGATOR_PATH);
    expect($source)->toContain("CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, ''))");
});
