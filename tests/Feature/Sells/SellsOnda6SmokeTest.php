<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK-R6-SMOKE Onda 6 — smoke automatizado + suite combined.
 *
 * Valida:
 *  - Comando artisan sells:smoke-daily existe + signature canônica
 *  - 5 checks declarados (schema/tenancy/manifest/css/aggregates)
 *  - Cron registrado em Kernel.php (06:30 BRT --notify environments=live)
 *  - RUNBOOK manual smoke Brave existe + cobre 5 cenários
 *  - Suite combined (5 testes Onda 2-6) reportável
 *
 * Cobertura estrutural pura (padrão Sells canônico — Pest SQLite in-memory
 * não cobre artisan execution por causa de migrations MySQL-only).
 */

const R6_CMD_PATH = 'app/Console/Commands/Sells/SmokeDailyCommand.php';
const R6_KERNEL_PATH = 'app/Console/Kernel.php';
const R6_RUNBOOK_PATH = 'memory/requisitos/Sells/RUNBOOK-smoke-cowork.md';

function r6Read(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Comando smoke ──────────────────────────────────────────────────

it('SmokeDailyCommand.php existe', function () {
    expect(file_exists(base_path(R6_CMD_PATH)))->toBeTrue();
});

it('SmokeDailyCommand tem signature sells:smoke-daily --notify', function () {
    $source = r6Read(R6_CMD_PATH);
    expect($source)
        ->toContain("protected \$signature = 'sells:smoke-daily {--notify : Loga ALERT se algum check falhar}';")
        ->toContain('protected $description = ');
});

it('SmokeDailyCommand declara 5 checks canônicos', function () {
    $source = r6Read(R6_CMD_PATH);
    expect($source)
        ->toContain('checkSchemaEssencial')
        ->toContain('checkMultiTenantScope')
        ->toContain('checkViteManifest')
        ->toContain('checkCssScopedImports')
        ->toContain('checkCoworkAggregatesSchema');
});

it('SmokeDailyCommand checa biz=1 + biz=4 (ROTA LIVRE) com vendas 30d', function () {
    $source = r6Read(R6_CMD_PATH);
    expect($source)
        ->toContain("Transaction::where('business_id', 1)")
        ->toContain("Transaction::where('business_id', 4)")
        ->toContain('ROTA LIVRE')
        ->toContain('->subDays(30)');
});

it('SmokeDailyCommand checa 8 chunks Vite canônicos das Ondas', function () {
    $source = r6Read(R6_CMD_PATH);
    expect($source)
        ->toContain("'SaleSheet'")
        ->toContain("'SaleAiPanel'")
        ->toContain("'SaleAuditTrail'")
        ->toContain("'SaleItemComments'")
        ->toContain("'SaleLinkifier'")
        ->toContain("'SaleTranscriptPDF'")
        ->toContain("'SalePresentationMode'")
        ->toContain("'SaleMessagePreview'");
});

it('SmokeDailyCommand checa 4 CSS scoped imports em inertia.css', function () {
    $source = r6Read(R6_CMD_PATH);
    expect($source)
        ->toContain("'./sells-cowork.css'")
        ->toContain("'./sells-cowork-ia.css'")
        ->toContain("'./sells-cowork-curadoria.css'")
        ->toContain("'./sells-cowork-distribuicao.css'");
});

it('SmokeDailyCommand checa shape coworkAggregates canônico', function () {
    $source = r6Read(R6_CMD_PATH);
    expect($source)
        ->toContain('buildCoworkAggregates(int $business_id)')
        ->toContain("'sparkline' => \\\$sparkline")
        ->toContain("'topSeller' => \\\$topSeller")
        ->toContain('Inertia::defer(');
});

it('SmokeDailyCommand falha graciosamente: log ALERT se --notify e check falha', function () {
    $source = r6Read(R6_CMD_PATH);
    expect($source)
        ->toContain('if ($this->option(\'notify\'))')
        ->toContain('Log::channel(\'single\')->error(')
        ->toContain('return self::FAILURE');
});

// ─── Cron registrado ────────────────────────────────────────────────

it('Kernel.php registra sells:smoke-daily 06:30 BRT --notify environments=live', function () {
    $source = r6Read(R6_KERNEL_PATH);
    expect($source)
        ->toContain("\$schedule->command('sells:smoke-daily --notify')")
        ->toContain("->dailyAt('06:30')")
        ->toContain("->timezone('America/Sao_Paulo')")
        ->toContain("->environments(['live'])")
        ->toContain('Schedule sells:smoke-daily FALHOU');
});

// ─── RUNBOOK manual ─────────────────────────────────────────────────

it('RUNBOOK smoke Cowork existe', function () {
    expect(file_exists(base_path(R6_RUNBOOK_PATH)))->toBeTrue();
});

it('RUNBOOK cobre 5 cenários (lista + drawer/IA + Onda 4 + Onda 5 + tenant)', function () {
    $source = r6Read(R6_RUNBOOK_PATH);
    expect($source)
        ->toContain('## Cenário 1')
        ->toContain('## Cenário 2')
        ->toContain('## Cenário 3')
        ->toContain('## Cenário 4')
        ->toContain('## Cenário 5')
        ->toContain('Onda 1 R1 Fundação')
        ->toContain('Onda 4 R4 Distribuição')
        ->toContain('Multi-tenant Tier 0');
});

it('RUNBOOK aponta pra biz=1 (NÃO biz=4 cliente piloto ADR 0101)', function () {
    $source = r6Read(R6_RUNBOOK_PATH);
    expect($source)
        ->toContain('biz=1 (NÃO biz=4 cliente piloto — ADR 0101)')
        ->toContain('ROTA LIVRE');
});

it('RUNBOOK lista 4 diagnósticos quando smoke falha', function () {
    $source = r6Read(R6_RUNBOOK_PATH);
    expect($source)
        ->toContain('manifest: chunks Cowork ausentes')
        ->toContain('css: imports ausentes')
        ->toContain('tenancy: biz=4 ZERO vendas 30d')
        ->toContain('aggregates: SellController drift');
});
