<?php

declare(strict_types=1);

/**
 * Pest — P4 parking lot #11 (pós-PR #1663) — Timeline cross-source unified.
 *
 * Cobertura estrutural (file_get_contents + ReflectionClass) — garante que:
 *  - SaleHistoryController::timelineUnified existe + assinatura correta
 *  - Multi-tenant Tier 0 (ADR 0093): business_id em TODA query
 *  - Permission gate sale.history.view
 *  - Agrega 5 sources (fsm/payment/activity/comment/audit)
 *  - Ordem cronológica reversa + limit 100
 *  - Rota /api/sells/{id}/timeline-unified registrada com FQCN
 *  - SaleTimeline.tsx aceita prop mode='fsm' | 'unified' + refreshKey
 *  - Show.tsx wire-up: SaleTimeline mode unified + event listeners
 *  - SaleSheet.tsx atualizado pra mode unified
 *
 * Refs:
 *  - app/Http/Controllers/SaleHistoryController.php (método timelineUnified)
 *  - routes/web.php (linha sells.timeline-unified)
 *  - resources/js/Pages/Sells/_components/SaleTimeline.tsx
 *  - resources/js/Pages/Sells/Show.tsx
 *  - resources/js/Pages/Sells/_components/SaleSheet.tsx
 *  - memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md (gap #11)
 */

const P4_CTRL_PATH = 'app/Http/Controllers/SaleHistoryController.php';
const P4_ROUTES_PATH = 'routes/web.php';
const P4_TIMELINE_TSX_PATH = 'resources/js/Pages/Sells/_components/SaleTimeline.tsx';
const P4_SHOW_TSX_PATH = 'resources/js/Pages/Sells/Show.tsx';
const P4_SHEET_TSX_PATH = 'resources/js/Pages/Sells/_components/SaleSheet.tsx';
const P4_CSS_PATH = 'resources/css/sells-cowork-show.css';

function p4Read(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Controller: método existe + assinatura ────────────────────────────

it('SaleHistoryController::timelineUnified existe + retorna JsonResponse', function () {
    expect(method_exists(\App\Http\Controllers\SaleHistoryController::class, 'timelineUnified'))->toBeTrue();
    $ref = new ReflectionMethod(\App\Http\Controllers\SaleHistoryController::class, 'timelineUnified');
    $params = $ref->getParameters();
    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('request');
    expect($params[1]->getName())->toBe('id');
    expect($params[1]->getType()?->getName())->toBe('int');
    expect($ref->getReturnType()?->getName())->toBe(\Illuminate\Http\JsonResponse::class);
});

// ─── Multi-tenant Tier 0 (ADR 0093) ────────────────────────────────────

it('timelineUnified aplica multi-tenant Tier 0 (business_id scope explícito)', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain("session()->get('user.business_id')")
        ->toContain("Transaction::query()")
        ->toContain("where('business_id', \$businessId)")
        ->toContain("where('type', 'sell')");
});

it('timelineUnified retorna 404 se venda não existe ou cross-tenant', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain("'Venda não encontrada'")
        ->toContain('], 404);');
});

it('timelineUnified retorna 401 se não autenticado e 403 sem permission', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain('auth()->check()')
        ->toContain('Response::HTTP_UNAUTHORIZED')
        ->toContain("can('sale.history.view')")
        ->toContain('Response::HTTP_FORBIDDEN');
});

// ─── Agrega 5 sources ──────────────────────────────────────────────────

it('timelineUnified agrega FSM transitions (SaleStageHistory)', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain('SaleStageHistory::query()')
        ->toContain("'type' => 'fsm_transition'");
});

it('timelineUnified agrega payments (TransactionPayment)', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain('TransactionPayment::query()')
        ->toContain("'type' => 'payment'");
});

it('timelineUnified agrega activities (Spatie/Activitylog)', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain('Activity::query()')
        ->toContain("'type' => 'activity'");
});

it('timelineUnified detecta sale_item_comments via Schema::hasTable (skip silencioso)', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain("Schema::hasTable('sale_item_comments')")
        ->toContain("'type' => 'comment'");
});

it('timelineUnified detecta audit_log via Schema::hasTable (skip silencioso)', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain("Schema::hasTable('audit_log')")
        ->toContain("'type' => 'audit'");
});

// ─── Ordem cronológica reversa + limit 100 ─────────────────────────────

it('timelineUnified ordena cronológico reverso e limita a 100', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain('sortByDesc')
        ->toContain('->take(100)');
});

it('timelineUnified resolve user names em batch (1 query) anti N+1', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain("DB::table('users')")
        ->toContain('whereIn(\'id\', $userIds)')
        ->toContain('keyBy(\'id\')');
});

// ─── Payload format ────────────────────────────────────────────────────

it('timelineUnified retorna shape { transaction_id, count, events[] }', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain("'transaction_id' => \$id")
        ->toContain("'count' => \$finalEvents->count()")
        ->toContain("'events' => \$finalEvents->values()");
});

it('timelineUnified event tem campos canônicos (type, occurred_at, user, icon, tone, title)', function () {
    $source = p4Read(P4_CTRL_PATH);
    expect($source)
        ->toContain("'type' => \$e['type']")
        ->toContain("'occurred_at' => \$e['occurred_at']")
        ->toContain("'icon' => \$e['icon']")
        ->toContain("'tone' => \$e['tone']")
        ->toContain("'title' => \$e['title']");
});

// ─── Rota registrada com FQCN (ADR rule routes.md) ─────────────────────

it('Rota /api/sells/{id}/timeline-unified registrada com FQCN', function () {
    $source = p4Read(P4_ROUTES_PATH);
    expect($source)
        ->toContain("Route::get('/api/sells/{id}/timeline-unified'")
        ->toContain('\App\Http\Controllers\SaleHistoryController::class')
        ->toContain("'timelineUnified'")
        ->toContain("->name('sells.timeline-unified')");
});

it('Rota sells.timeline-unified aparece em route:list', function () {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->getName())
        ->filter()
        ->values()
        ->toArray();
    expect($routes)->toContain('sells.timeline-unified');
});

// ─── Frontend SaleTimeline.tsx ─────────────────────────────────────────

it('SaleTimeline.tsx aceita prop opcional mode (fsm | unified) com default fsm', function () {
    $source = p4Read(P4_TIMELINE_TSX_PATH);
    expect($source)
        ->toContain("mode?: 'fsm' | 'unified'")
        ->toContain("mode = 'fsm'");
});

it('SaleTimeline.tsx aceita prop opcional refreshKey pra re-fetch externo', function () {
    $source = p4Read(P4_TIMELINE_TSX_PATH);
    expect($source)
        ->toContain('refreshKey?: number')
        ->toContain('refreshKey = 0');
});

it('SaleTimeline.tsx escolhe endpoint dinâmico por mode', function () {
    $source = p4Read(P4_TIMELINE_TSX_PATH);
    $dollar = '$';
    expect($source)
        ->toContain("`/api/sells/{$dollar}{saleId}/timeline-unified`")
        ->toContain("`/api/sells/{$dollar}{saleId}/history`");
});

it('SaleTimeline.tsx tem tipos UnifiedEvent + UnifiedResponse + UnifiedEventType', function () {
    $source = p4Read(P4_TIMELINE_TSX_PATH);
    expect($source)
        ->toContain('interface UnifiedEvent')
        ->toContain('interface UnifiedResponse')
        ->toContain('export type { UnifiedEvent, UnifiedResponse');
});

it('SaleTimeline.tsx render unified com avatar + icon + colorbar + tone', function () {
    $source = p4Read(P4_TIMELINE_TSX_PATH);
    expect($source)
        ->toContain('sb-timeline-unified')
        ->toContain('sb-timeline-avatar')
        ->toContain('sb-timeline-colorbar')
        ->toContain('sb-timeline-icon');
});

it('SaleTimeline.tsx tem empty state ilustrado com Inbox icon', function () {
    $source = p4Read(P4_TIMELINE_TSX_PATH);
    expect($source)
        ->toContain('sb-timeline-empty')
        ->toContain('Sem eventos ainda');
});

it('SaleTimeline.tsx tem loading skeleton 3 rows pulse pro mode unified', function () {
    $source = p4Read(P4_TIMELINE_TSX_PATH);
    expect($source)
        ->toContain('sb-timeline-loading')
        ->toContain('animate-pulse');
});

it('SaleTimeline.tsx tem formatRelative com pt-BR (agora/min/h/d)', function () {
    $source = p4Read(P4_TIMELINE_TSX_PATH);
    $d = '$';
    expect($source)
        ->toContain('formatRelative')
        ->toContain("'agora'")
        ->toContain("há {$d}{min}min")
        ->toContain("há {$d}{hr}h")
        ->toContain("há {$d}{day}d");
});

it('SaleTimeline.tsx fallback FSM-mode preservado (back-compat)', function () {
    $source = p4Read(P4_TIMELINE_TSX_PATH);
    expect($source)
        ->toContain('Nenhuma transição registrada ainda')
        ->toContain('via <strong className="text-foreground">{item.action.label}</strong>');
});

// ─── Wire-up Show.tsx ──────────────────────────────────────────────────

it('Show.tsx importa SaleTimeline + usa mode="unified" + refreshKey', function () {
    $source = p4Read(P4_SHOW_TSX_PATH);
    expect($source)
        ->toContain("import SaleTimeline from './_components/SaleTimeline'")
        ->toContain('mode="unified"')
        ->toContain('refreshKey={timelineRefreshKey}');
});

it('Show.tsx listener pra eventos venda-invoiced/paid/emitted-{nfe,nfse}', function () {
    $source = p4Read(P4_SHOW_TSX_PATH);
    expect($source)
        ->toContain("'oimpresso:venda-invoiced'")
        ->toContain("'oimpresso:venda-paid'")
        ->toContain("'oimpresso:venda-emitted-nfe'")
        ->toContain("'oimpresso:venda-emitted-nfse'")
        ->toContain('setTimelineRefreshKey');
});

// ─── Wire-up SaleSheet.tsx ─────────────────────────────────────────────

it('SaleSheet.tsx atualiza SaleTimeline pra mode unified', function () {
    $source = p4Read(P4_SHEET_TSX_PATH);
    expect($source)
        ->toContain('<SaleTimeline saleId={data.id} enabled={open} mode="unified" />');
});

// ─── CSS tokens ────────────────────────────────────────────────────────

it('sells-cowork-show.css tem tokens .sb-timeline-* scopados', function () {
    $source = p4Read(P4_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork-show .sb-timeline-unified')
        ->toContain('.sells-cowork-show .sb-timeline-event')
        ->toContain('.sells-cowork-show .sb-timeline-avatar')
        ->toContain('.sells-cowork-show .sb-timeline-colorbar');
});
