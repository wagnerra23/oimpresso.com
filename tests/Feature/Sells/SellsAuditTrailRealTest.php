<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK-R3-CURADORIA Onda 3.5 — Audit Trail FSM real.
 *
 * Cobertura estrutural (file_get_contents + ReflectionClass) — garante que:
 *  - Controller SellAuditController existe com método show()
 *  - Multi-tenant Tier 0 (ADR 0093): scope explícito por business_id
 *  - Rota /sells/{sale}/audit registrada com FQCN (ADR rule routes.md)
 *  - SaleAuditTrail.tsx aceita prop opcional realApiUrl
 *  - Fallback determinístico preservado (sem realApiUrl continua igual Onda 3)
 *
 * Refs:
 *  - app/Http/Controllers/SellAuditController.php
 *  - routes/web.php (linha sells.audit)
 *  - resources/js/Pages/Sells/_components/SaleAuditTrail.tsx
 *  - ADR 0143 FSM Pipeline LIVE prod biz=1
 */

const ONDA35_CTRL_PATH = 'app/Http/Controllers/SellAuditController.php';
const ONDA35_ROUTES_PATH = 'routes/web.php';
const ONDA35_AUDIT_TSX_PATH = 'resources/js/Pages/Sells/_components/SaleAuditTrail.tsx';

function onda35Read(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Controller existe + estrutura ────────────────────────────────────

it('SellAuditController file existe no path canônico', function () {
    expect(file_exists(base_path(ONDA35_CTRL_PATH)))->toBeTrue();
});

it('SellAuditController class existe + tem método show', function () {
    expect(class_exists(\App\Http\Controllers\SellAuditController::class))->toBeTrue();
    expect(method_exists(\App\Http\Controllers\SellAuditController::class, 'show'))->toBeTrue();
});

it('SellAuditController estende Controller base', function () {
    $ref = new ReflectionClass(\App\Http\Controllers\SellAuditController::class);
    expect($ref->getParentClass()?->getName())->toBe(\App\Http\Controllers\Controller::class);
});

it('SellAuditController::show tem assinatura (Request, int sale): JsonResponse', function () {
    $ref = new ReflectionMethod(\App\Http\Controllers\SellAuditController::class, 'show');
    $params = $ref->getParameters();
    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('request');
    expect($params[1]->getName())->toBe('sale');
    expect($params[1]->getType()?->getName())->toBe('int');
    expect($ref->getReturnType()?->getName())->toBe(\Illuminate\Http\JsonResponse::class);
});

// ─── Multi-tenant Tier 0 (ADR 0093) ───────────────────────────────────

it('SellAuditController aplica multi-tenant Tier 0 (business_id scope explícito)', function () {
    $source = onda35Read(ONDA35_CTRL_PATH);
    expect($source)
        ->toContain("session()->get('user.business_id')")
        ->toContain("where('business_id', \$businessId)")
        ->toContain("Transaction::where('business_id', \$businessId)");
});

it('SellAuditController filtra type=sell pra não vazar compras/devoluções', function () {
    $source = onda35Read(ONDA35_CTRL_PATH);
    expect($source)->toContain("where('type', 'sell')");
});

it('SellAuditController consulta sale_stage_history canônica (ADR 0143)', function () {
    $source = onda35Read(ONDA35_CTRL_PATH);
    expect($source)
        ->toContain('SaleStageHistory::query()')
        ->toContain("where('transaction_id', \$venda->id)")
        ->toContain("orderBy('executed_at', 'asc')");
});

it('SellAuditController retorna 404 se venda não existe ou cross-tenant', function () {
    $source = onda35Read(ONDA35_CTRL_PATH);
    expect($source)
        ->toContain("'Venda não encontrada'")
        ->toContain('], 404);');
});

it('SellAuditController retorna 401 se não autenticado', function () {
    $source = onda35Read(ONDA35_CTRL_PATH);
    expect($source)
        ->toContain('auth()->check()')
        ->toContain('Response::HTTP_UNAUTHORIZED');
});

// ─── Payload formato esperado pelo componente ─────────────────────────

it('SellAuditController retorna formato flat amigável { id, when, from_stage, to_stage, action, user_name }', function () {
    $source = onda35Read(ONDA35_CTRL_PATH);
    expect($source)
        ->toContain("'id' => \$h->id")
        ->toContain("'when' => \$h->executed_at?->toIso8601String()")
        ->toContain("'from_stage' => \$h->fromStage?->name")
        ->toContain("'to_stage' => \$h->toStage?->name ?? '—'")
        ->toContain("'action' => \$h->action?->label ?? 'Pipeline iniciado'")
        ->toContain("'user_name' => \$userName");
});

it('SellAuditController eager-load minimalista (action,fromStage,toStage)', function () {
    $source = onda35Read(ONDA35_CTRL_PATH);
    expect($source)
        ->toContain("'action:id,key,label'")
        ->toContain("'fromStage:id,key,name'")
        ->toContain("'toStage:id,key,name'");
});

// ─── Rota registrada com FQCN ─────────────────────────────────────────

it('Rota /sells/{sale}/audit registrada com FQCN (ADR rule routes.md)', function () {
    $source = onda35Read(ONDA35_ROUTES_PATH);
    expect($source)
        ->toContain("Route::get('/sells/{sale}/audit'")
        ->toContain('\App\Http\Controllers\SellAuditController::class')
        ->toContain("->name('sells.audit')");
});

it('Rota sells.audit aparece em route:list', function () {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->getName())
        ->filter()
        ->values()
        ->toArray();
    expect($routes)->toContain('sells.audit');
});

// ─── Frontend: SaleAuditTrail.tsx aceita realApiUrl + preserva fallback ──

it('SaleAuditTrail.tsx aceita prop opcional realApiUrl', function () {
    $source = onda35Read(ONDA35_AUDIT_TSX_PATH);
    expect($source)
        ->toContain('realApiUrl?: string')
        ->toContain('realApiUrl?: string;');
});

it('SaleAuditTrail.tsx fetcha realApiUrl com credentials same-origin', function () {
    $source = onda35Read(ONDA35_AUDIT_TSX_PATH);
    expect($source)
        ->toContain('fetch(realApiUrl,')
        ->toContain("credentials: 'same-origin'")
        ->toContain("Accept: 'application/json'");
});

it('SaleAuditTrail.tsx tem tipos FsmHistoryEntry + FsmAuditResponse', function () {
    $source = onda35Read(ONDA35_AUDIT_TSX_PATH);
    expect($source)
        ->toContain('interface FsmHistoryEntry')
        ->toContain('interface FsmAuditResponse')
        ->toContain('export type { SaleAuditInput, FsmHistoryEntry, FsmAuditResponse }');
});

it('SaleAuditTrail.tsx preserva buildEntries determinístico (fallback Onda 3)', function () {
    $source = onda35Read(ONDA35_AUDIT_TSX_PATH);
    // Mesmas asserções do SellsOnda3CuradoriaTest pra garantir que fallback
    // continua funcional após refactor.
    expect($source)
        ->toContain("kind: 'create'")
        ->toContain("kind: 'payment'")
        ->toContain("kind: 'fiscal'")
        ->toContain("kind: 'reject'")
        ->toContain("'autorizada'")
        ->toContain("'rejeitada'")
        ->toContain('function buildEntries(venda: SaleAuditInput)');
});

it('SaleAuditTrail.tsx sem realApiUrl usa modo determinístico (UX preservada)', function () {
    $source = onda35Read(ONDA35_AUDIT_TSX_PATH);
    expect($source)
        ->toContain('if (!realApiUrl) return;')
        ->toContain('usingReal ? (realEntries as AuditEntry[]) : fallbackEntries');
});

it('SaleAuditTrail.tsx fallback em erro fetch (UX preservada)', function () {
    $source = onda35Read(ONDA35_AUDIT_TSX_PATH);
    expect($source)
        ->toContain('.catch(()')
        ->toContain('setError(true)')
        ->toContain('setRealEntries(null)');
});

it('SaleAuditTrail.tsx novo kind fsm tem icon + label', function () {
    $source = onda35Read(ONDA35_AUDIT_TSX_PATH);
    expect($source)
        ->toContain("kind: 'fsm'")
        ->toContain('GitBranch')
        ->toContain("fsm: 'pipeline'");
});

it('SaleAuditTrail.tsx fsmHistoryToEntries converte payload real pra AuditEntry', function () {
    $source = onda35Read(ONDA35_AUDIT_TSX_PATH);
    expect($source)
        ->toContain('function fsmHistoryToEntries(history: FsmHistoryEntry[]): AuditEntry[]')
        ->toContain('h.from_stage')
        ->toContain('h.to_stage')
        ->toContain('h.action');
});

it('SaleAuditTrail.tsx default export preservado (back-compat consumidores)', function () {
    $source = onda35Read(ONDA35_AUDIT_TSX_PATH);
    expect($source)
        ->toContain('export default function SaleAuditTrail(')
        ->toContain('export type { SaleAuditInput');
});
