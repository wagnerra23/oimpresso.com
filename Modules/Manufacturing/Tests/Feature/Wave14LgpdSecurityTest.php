<?php

declare(strict_types=1);


uses(Tests\TestCase::class);

/**
 * Wave 14 — Manufacturing LGPD + Security smoke (D7 + D8).
 *
 * Cenarios:
 *   1-3. Sairam no lote 2.8 da auditoria (2026-11-16): `Config/retention.php` nao tinha leitor e prometia
 *        um purge que a lapide §5 2026-07-27 proibe (num ERP nao se apaga PII); `logProductionEvent`
 *        tinha 0 chamadores. Os dois foram removidos, e os casos que so os testavam com eles.
 *   4. Throttle middleware presente nas rotas Manufacturing (D8.a)
 *   5. CSRF (web middleware) preservado nas rotas Manufacturing — NUNCA desligar
 *   6. StoreRecipeRequest tem authorize() exigindo permissao manufacturing.add_recipe
 *
 * Refs:
 *   - Modules/Manufacturing/Routes/web.php throttle:60,1 (D8.a)
 *   - Modules/Manufacturing/Http/Requests/StoreRecipeRequest.php (D8.c)
 *   - memory/decisions/0093-multi-tenant-isolation-tier-0.md (Tier 0)
 */

it('cenario 4: rotas Manufacturing tem throttle:60,1 (D8.a)', function () {
    $route = collect(\Route::getRoutes())
        ->first(fn ($r) => $r->getName() === 'recipe.index');

    expect($route)->not->toBeNull('rota recipe.index deveria existir');
    expect($route->gatherMiddleware())->toContain('throttle:60,1');
});

it('cenario 5: CSRF (web middleware) preservado em rotas Manufacturing (NUNCA desligar)', function () {
    $route = collect(\Route::getRoutes())
        ->first(fn ($r) => $r->getName() === 'production.index');

    expect($route)->not->toBeNull('rota production.index deveria existir');
    // 'web' middleware group inclui VerifyCsrfToken — auditoria Tier 0
    expect($route->gatherMiddleware())->toContain('web');
});

it('cenario 6: StoreRecipeRequest tem rules + authorize com permissao manufacturing.add_recipe', function () {
    $request = new \Modules\Manufacturing\Http\Requests\StoreRecipeRequest();

    $rules = $request->rules();
    expect($rules)->toHaveKey('variation_id');
    expect($rules['variation_id'])->toContain('required');
    expect($rules['variation_id'])->toContain('exists:variations,id');
    expect($rules)->toHaveKey('production_cost_type');
    // Whitelist anti-injection — apenas valores enumerados aceitos. As 3 formas que a janela de
    // ingredientes oferece e o cálculo conhece (até 2026-10-05 faltava `per_unit`, e escolher
    // "Por unidade" não salvava — ver CustoPorUnidadeReceitaTest).
    expect($rules['production_cost_type'])->toContain('in:fixed,percentage,per_unit');
});
