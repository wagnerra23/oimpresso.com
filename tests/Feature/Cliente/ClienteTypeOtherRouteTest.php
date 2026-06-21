<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;

// `uses(TestCase::class)` é aplicado globalmente em tests/Pest.php pra toda pasta Feature/.

/**
 * ADR 0246 (2026-06-03) — 5ª categoria "Outros" (`?type=other`).
 *
 * Regressão coberta: a aba "Outros" (Index.tsx SLOT2_TABS → `href: /cliente?type=other`)
 * caía silenciosamente em "Clientes". Causa: o frontend + o controller
 * (`$types`/`$inertiaTypes`) já aceitavam `other`, mas a whitelist `$allowed` da
 * rota `/cliente` (routes/web.php) ficou pra trás → `other` não passava no
 * `in_array` e era rebaixado pro fallback `customer` ANTES de chegar no controller.
 *
 * Refs:
 * - routes/web.php (closure /cliente → $allowed)
 * - app/Http/Controllers/ContactController.php ($types, $inertiaTypes, applyContactTypeFilter)
 * - resources/js/Pages/Cliente/Index.tsx (SLOT2_TABS 'other')
 * - memory/decisions/0246-tipo-outros-default-migracoes-legacy.md
 */

/** Guard estrutural — roda em qualquer ambiente (sem boot/DB). */
test('rota /cliente aceita type=other na whitelist (ADR 0246)', function () {
    $routesPath = __DIR__ . '/../../../routes/web.php';
    expect($routesPath)->toBeReadableFile();

    $contents = file_get_contents($routesPath);

    // A whitelist $allowed da closure /cliente PRECISA conter 'other', senão
    // ?type=other vira fallback 'customer' (aba Outros abre em Clientes).
    expect($contents)->toMatch(
        "/\\\$allowed\s*=\s*\[[^\]]*'other'[^\]]*\];/"
    );
});

/** As 3 camadas de whitelist (rota + 2 do controller) ficam em sincronia. */
test('controller $types e $inertiaTypes também aceitam other (3 camadas alinhadas)', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        // $types = [...,'other',...]; (guard de redirect()->back())
        ->toMatch("/\\\$types\s*=\s*\[[^\]]*'other'[^\]]*\];/")
        // $inertiaTypes = [...,'other',...]; (guard do Inertia::render)
        ->toMatch("/\\\$inertiaTypes\s*=\s*\[[^\]]*'other'[^\]]*\];/")
        // mapeamento de filtro 'other' => 'is_other'
        ->toContain("'other' => 'is_other'");
});

/**
 * Comportamental — prova que /cliente?type=other renderiza a aba Outros e
 * NÃO cai em customer. Skip gracioso (convention oimpresso) quando sem DB.
 */
test('GET /cliente?type=other renderiza Inertia com activeType=other (não customer)', function () {
    try {
        $business = test()->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    } catch (\Throwable $e) {
        test()->markTestSkipped('DB indisponível: ' . $e->getMessage());
    }

    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->first();

    if (! $user) {
        test()->markTestSkipped('Sem user não-customer no business.');
    }

    // Força o branch Inertia pra qualquer business (sem gate por biz).
    config([
        'mwart.cliente_index.enabled' => true,
        'mwart.cliente_index.business_ids' => [],
    ]);

    session([
        'user.id' => $user->id,
        'user.business_id' => $business->id,
        'business.id' => $business->id,
    ]);

    $response = $this->actingAs($user)->get('/cliente?type=other');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Cliente/Index')
        ->where('activeType', 'other')   // <- antes do fix vinha 'customer'
    );
});
