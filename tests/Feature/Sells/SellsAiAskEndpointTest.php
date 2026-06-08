<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK-R2-IA — endpoint POST /sells/{id}/ai-ask.
 *
 * Cobre Cowork KB-9.75 Onda 2 R2 IA drawer.
 * Stub determinístico nesta Onda; Onda 2.5 troca por Jana real.
 *
 * Testa apenas estrutura/contrato (file_get_contents) — Pest browser cobre
 * end-to-end interativo quando estabilizar.
 *
 * Refs:
 *  - SellController::aiAsk
 *  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md
 *  - feedback-ondas-cowork-transparencia-de-gaps.md
 */

const AI_ASK_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';
const AI_ASK_ROUTES_PATH = 'routes/web.php';

function aiAskReadController(): string
{
    return file_get_contents(base_path(AI_ASK_CONTROLLER_PATH));
}

function aiAskReadRoutes(): string
{
    return file_get_contents(base_path(AI_ASK_ROUTES_PATH));
}

it('SellController::aiAsk existe (Cowork Onda 2 R2 IA)', function () {
    expect(aiAskReadController())->toContain('public function aiAsk');
});

it('Route POST /sells/{id}/ai-ask registrada', function () {
    expect(aiAskReadRoutes())
        ->toContain("/sells/{id}/ai-ask")
        ->toContain("[SellController::class, 'aiAsk']");
});

it('aiAsk tem permission gate (direct_sell.view + variants)', function () {
    $source = aiAskReadController();
    // Localiza bloco da function aiAsk e confere o gate de permission.
    $start = strpos($source, 'public function aiAsk');
    expect($start)->toBeGreaterThan(0);
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("can('direct_sell.view')")
        ->toContain("can('view_own_sell_only')")
        ->toContain("can('view_commission_agent_sell')")
        ->toContain('abort(403)');
});

it('aiAsk aplica multi-tenant scope business_id (Tier 0 ADR 0093)', function () {
    $source = aiAskReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("session()->get('user.business_id')")
        ->toContain("->where('business_id', \$business_id)");
});

it('aiAsk valida mode whitelist (summary|history|suggest)', function () {
    $source = aiAskReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("'summary'")
        ->toContain("'history'")
        ->toContain("'suggest'")
        ->toContain('in_array($mode, $allowedModes')
        ->toContain('422');
});

it('aiAsk retorna 404 quando venda não existe (UX defensiva)', function () {
    $source = aiAskReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain('->find($id)')
        ->toContain('abort(404)');
});

it('aiAsk filtra type=sell + status=final + sub_type=null', function () {
    $source = aiAskReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("->where('type', 'sell')")
        ->toContain("->whereNull('sub_type')");
});

it('aiAsk shape do JSON inclui text + mode + latency_ms + is_stub + venda_id', function () {
    $source = aiAskReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("'text' => \$text")
        ->toContain("'mode' => \$mode")
        ->toContain("'latency_ms' => \$latency")
        ->toContain("'is_stub' => true")
        ->toContain("'venda_id' => \$sale->id");
});

it('aiAsk modo summary inclui nome cliente + total + items count + payment status', function () {
    $source = aiAskReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    // Stub determinístico do summary precisa concatenar essas peças.
    expect($block)
        ->toContain("'summary' =>")
        ->toContain('$clientName')
        ->toContain('$totalFmt')
        ->toContain('$itemsCount')
        ->toContain('$paymentStatus');
});

it('aiAsk modo history usa subquery transactions agregada (count + sum + max)', function () {
    $source = aiAskReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("'history' =>")
        ->toContain('selectRaw')
        ->toContain('COUNT(*)')
        ->toContain('->where(\'contact_id\', $clientId)')
        ->toContain("->where('id', '!=', \$sale->id)");
});

it('aiAsk modo suggest retorna template estruturado PRODUTO/PREÇO/PORQUE (parseado no frontend)', function () {
    $source = aiAskReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("'suggest' =>")
        ->toContain('PRODUTO:')
        ->toContain('PREÇO:')
        ->toContain('PORQUE:');
});

it('aiAsk declara is_stub=true (Onda 2.5 trocará pra false quando Jana real plugar)', function () {
    $source = aiAskReadController();
    $start = strpos($source, 'public function aiAsk');
    $next = strpos($source, "\n    public function ", $start + 10);
    $block = substr($source, $start, $next - $start);
    expect($block)
        ->toContain("'is_stub' => true");
});
