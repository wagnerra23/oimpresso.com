<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK-R2-IA Onda 2.5 — integração Jana real
 * (SaleInsightAgent + fallback stub).
 *
 * Cobre estrutura/contrato:
 *  - Feature flag `sells.ai.use_jana_real` controla path Jana vs stub
 *  - SellController::aiAsk invoca SaleInsightAgent quando flag ON
 *  - Fallback automático on Throwable (graceful degradation)
 *  - Shape JSON inclui `source: 'jana'|'stub'` além de `is_stub: bool`
 *  - Contexto construído inclui itens + cliente + total + payment_status
 *
 * Refs:
 *  - Modules/Jana/Ai/Agents/SaleInsightAgent.php
 *  - config/sells.php
 *  - app/Http/Controllers/SellController.php::aiAsk
 */

const JANA_REAL_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';
const JANA_REAL_AGENT_PATH = 'Modules/Jana/Ai/Agents/SaleInsightAgent.php';
const JANA_REAL_CONFIG_PATH = 'config/sells.php';

function janaReadController(): string
{
    return file_get_contents(base_path(JANA_REAL_CONTROLLER_PATH));
}

function janaReadAgent(): string
{
    return file_get_contents(base_path(JANA_REAL_AGENT_PATH));
}

function janaReadConfig(): string
{
    return file_get_contents(base_path(JANA_REAL_CONFIG_PATH));
}

// ─── SaleInsightAgent ─────────────────────────────────────────────────

it('SaleInsightAgent existe em Modules/Jana/Ai/Agents/', function () {
    expect(file_exists(base_path(JANA_REAL_AGENT_PATH)))->toBeTrue();
});

it('SaleInsightAgent usa Promptable + implementa Agent (laravel/ai pattern)', function () {
    $source = janaReadAgent();
    expect($source)
        ->toContain('use Laravel\\Ai\\Contracts\\Agent;')
        ->toContain('use Laravel\\Ai\\Promptable;')
        ->toContain('implements Agent')
        ->toContain('use Promptable;');
});

it('SaleInsightAgent tem #[Provider] + #[Model] attributes (ADR 0035)', function () {
    $source = janaReadAgent();
    expect($source)
        ->toContain("#[Provider('openai')]")
        ->toContain("#[Model('gpt-4o-mini')]");
});

it('SaleInsightAgent ctor recebe mode + contextoVenda readonly', function () {
    $source = janaReadAgent();
    expect($source)
        ->toContain('public readonly string $mode')
        ->toContain('public readonly string $contextoVenda');
});

it('SaleInsightAgent instructions() cobre 3 modos (summary|history|suggest)', function () {
    $source = janaReadAgent();
    expect($source)
        ->toContain("'summary' =>")
        ->toContain("'history' =>")
        ->toContain("'suggest' =>");
});

it('SaleInsightAgent suggest mode exige formato PRODUTO/PREÇO/PORQUE', function () {
    $source = janaReadAgent();
    expect($source)
        ->toContain('PRODUTO:')
        ->toContain('PREÇO:')
        ->toContain('PORQUE:');
});

// ─── Config flag ──────────────────────────────────────────────────────

it('config/sells.php existe e expõe ai.use_jana_real default false', function () {
    expect(file_exists(base_path(JANA_REAL_CONFIG_PATH)))->toBeTrue();
    $source = janaReadConfig();
    expect($source)
        ->toContain("'use_jana_real' => env('SELLS_AI_USE_JANA_REAL', false)")
        ->toContain("'timeout_seconds' =>")
        ->toContain("'model' => env('SELLS_AI_MODEL'");
});

it('config/sells.php documenta canary controlado (default false em prod)', function () {
    $source = janaReadConfig();
    expect($source)
        ->toContain('canary')
        ->toContain('SELLS_AI_USE_JANA_REAL');
});

// ─── SellController::aiAsk integração ─────────────────────────────────

it('aiAsk lê feature flag config sells.ai.use_jana_real', function () {
    $source = janaReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("config('sells.ai.use_jana_real'")
        ->toContain('class_exists(\\Modules\\Jana\\Ai\\Agents\\SaleInsightAgent::class)');
});

it('aiAsk invoca SaleInsightAgent->prompt() quando flag ON', function () {
    $source = janaReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain('new \\Modules\\Jana\\Ai\\Agents\\SaleInsightAgent(')
        ->toContain('mode: $mode')
        ->toContain('contextoVenda:')
        ->toContain('$agent->prompt(');
});

it('aiAsk tem try/catch com fallback graceful pro stub on Throwable', function () {
    $source = janaReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain('try {')
        ->toContain('} catch (\\Throwable $e)')
        ->toContain('SaleInsightAgent failed — fallback to stub')
        ->toContain('\\Log::warning');
});

it('aiAsk retorna source:jana quando agent sucesso, source:stub quando fallback', function () {
    $source = janaReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("'source' => 'jana'")
        ->toContain("'source' => 'stub'")
        ->toContain("'is_stub' => false")
        ->toContain("'is_stub' => true");
});

it('aiAsk retorna error_class no payload quando fallback (transparência)', function () {
    $source = janaReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain('class_basename($e)')
        ->toContain("\$payload['error_class'] = \$errorClass");
});

it('buildSaleAiContext helper extraído (limpeza método aiAsk)', function () {
    $source = janaReadController();
    expect($source)
        ->toContain('private function buildSaleAiContext(')
        ->toContain('private function buildSaleAiStub(');
});

it('buildSaleAiContext inclui itens + cliente + total + payment_status no prompt', function () {
    $source = janaReadController();
    $start = strpos($source, 'private function buildSaleAiContext');
    $next = strpos($source, "\n    private function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain('$clientName')
        ->toContain('$totalFmt')
        ->toContain('sell_lines')
        ->toContain('payment_status')
        ->toContain("Cliente:")
        ->toContain('Itens (');
});

it('buildSaleAiContext modo history agrega stats do cliente (count + sum + max)', function () {
    $source = janaReadController();
    $start = strpos($source, 'private function buildSaleAiContext');
    $next = strpos($source, "\n    private function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("if (\$mode === 'history'")
        ->toContain('COUNT(*) as cnt')
        ->toContain('SUM(final_total)')
        ->toContain('MAX(transaction_date)')
        ->toContain('->where(\'contact_id\', $sale->contact_id)');
});

it('Onda 2 contract preservado (stub fallback mesma lógica)', function () {
    $source = janaReadController();
    $start = strpos($source, 'private function buildSaleAiStub');
    $next = strpos($source, "\n    private function ", $start + 10);
    if ($next === false) {
        // Pode ser último método privado antes de fim de classe.
        $next = strpos($source, "\n}", $start);
    }
    $block = substr($source, $start, $next - $start);
    // Mesmas 3 strings-chave do stub original (Onda 2).
    expect($block)
        ->toContain("Venda #%s pra %s")
        ->toContain('Primeira venda deste cliente')
        ->toContain('PRODUTO: complemento de %s');
});

it('Multi-tenant Tier 0 preservado (business_id passado pro stub helper)', function () {
    $source = janaReadController();
    $start = strpos($source, 'private function buildSaleAiStub');
    expect($start)->toBeGreaterThan(0);
    expect($source)->toContain('private function buildSaleAiStub(\\App\\Transaction $sale, string $mode, int $business_id)');
});
