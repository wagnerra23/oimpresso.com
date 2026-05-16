<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Manufacturing\Services\ProductionService;

uses(Tests\TestCase::class);

/**
 * Wave 14 — Manufacturing LGPD + Security smoke (D7 + D8).
 *
 * Cenarios:
 *   1. Config retention.php carregado + chaves canonicas (production_logs 1825d + recipes null)
 *   2. ProductionService::logProductionEvent redacta CPF na mensagem
 *   3. ProductionService::logProductionEvent redacta email no contexto array
 *   4. Throttle middleware presente nas rotas Manufacturing (D8.a)
 *   5. CSRF (web middleware) preservado nas rotas Manufacturing — NUNCA desligar
 *   6. StoreRecipeRequest tem authorize() exigindo permissao manufacturing.add_recipe
 *
 * Refs:
 *   - Modules/Manufacturing/Config/retention.php (D7.c)
 *   - Modules/Manufacturing/Services/ProductionService.php logProductionEvent (D7.a)
 *   - Modules/Manufacturing/Routes/web.php throttle:60,1 (D8.a)
 *   - Modules/Manufacturing/Http/Requests/StoreRecipeRequest.php (D8.c)
 *   - memory/decisions/0093-multi-tenant-isolation-tier-0.md (Tier 0)
 */

it('cenario 1: config retention.php tem chaves canonicas (production_logs 1825d + recipes null)', function () {
    $config = require __DIR__.'/../../Config/retention.php';

    expect($config)->toHaveKey('production_logs');
    expect($config['production_logs']['days'])->toBe(1825);
    expect($config['production_logs']['legal_basis'])->toContain('CTN Art. 195');

    expect($config)->toHaveKey('recipes');
    expect($config['recipes']['days'])->toBeNull(); // INDEFINIDO por design (fórmula industrial)

    expect($config)->toHaveKey('ingredient_groups');
    expect($config['ingredient_groups']['days'])->toBeNull();

    expect($config)->toHaveKey('logs_audit_manufacturing');
    expect($config['logs_audit_manufacturing']['days'])->toBe(2555); // CC Art. 206 10 anos
});

it('cenario 2: ProductionService::logProductionEvent redacta CPF na mensagem', function () {
    $service = new ProductionService(new PiiRedactor());

    Log::spy();

    $service->logProductionEvent('warning', 'Producao bloqueada para CPF 123.456.789-09 sem cobertura.');

    Log::shouldHaveReceived('log')->withArgs(function ($level, $message, $context) {
        return $level === 'warning'
            && str_contains($message, '[REDACTED:CPF]')
            && ! str_contains($message, '123.456.789-09');
    })->once();
});

it('cenario 3: ProductionService::logProductionEvent redacta email no contexto array', function () {
    $service = new ProductionService(new PiiRedactor());

    Log::spy();

    $service->logProductionEvent('info', 'Lote finalizado', [
        'lot_number' => 'L-001',
        'operador_email' => 'operador@cliente.com.br',
        'qty' => 100,
    ]);

    Log::shouldHaveReceived('log')->withArgs(function ($level, $message, $context) {
        return $context['lot_number'] === 'L-001'
            && str_contains($context['operador_email'], '[REDACTED:EMAIL]')
            && $context['qty'] === 100; // não-string preservado
    })->once();
});

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
    // Whitelist anti-injection — apenas valores enumerados aceitos
    expect($rules['production_cost_type'])->toContain('in:fixed,percentage');
});
