<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Modules\Jana\Http\Middleware\McpAuthMiddleware;

uses(Tests\TestCase::class);

/**
 * SDD D-COST-FIX (ADR 0278) — GUARD numérico do custo estimado de chamada MCP.
 *
 * Bug original em McpAuthMiddleware::handle():
 *   1. Lia `config('copiloto.openai.pricing.*')` — chave INEXISTENTE → caía
 *      sempre no fallback hardcoded, ignorando o pricing canônico.
 *   2. O pricing canônico (`copiloto.ai.pricing`) é cotado em USD POR 1k
 *      TOKENS; multiplicar `tokens × input` direto inflava o custo em ~1000×.
 *
 * Fix: estimarCustoBrl() lê a config CERTA (`copiloto.ai.pricing.{modelo}`) e
 * divide a contagem de tokens por 1000 pra casar a unidade.
 *
 * Estes asserts são puramente numéricos (sem DB) — provam que o fator-de-mil
 * sumiu: o custo de uma call conhecida bate o esperado, e NÃO o valor inflado.
 *
 * @see Modules/Jana/Http/Middleware/McpAuthMiddleware.php (estimarCustoBrl)
 * @see Modules/Jana/Config/config.php (copiloto.ai.pricing — única chave canônica)
 */

beforeEach(function () {
    // Fixa pricing canônico conhecido (snapshot abr/2026) pra assert determinístico.
    Config::set('copiloto.ai.pricing_default_model', 'gpt-4o-mini');
    Config::set('copiloto.ai.pricing.gpt-4o-mini', [
        'input'  => 0.00015, // USD / 1k tokens
        'output' => 0.0006,  // USD / 1k tokens
    ]);
    Config::set('copiloto.ai.cambio_brl_usd', 5.5);
    // A chave ERRADA (openai) não pode existir nem influenciar.
    Config::set('copiloto.openai', null);
});

it('estima o custo de 1k in + 1k out no valor canônico (sem fator 1000x)', function () {
    // input 0.00015 + output 0.0006 = 0.00075 USD → × 5.5 = 0.004125 BRL.
    $esperadoBrl = 0.004125;

    $custo = McpAuthMiddleware::estimarCustoBrl(1000, 1000);

    expect($custo)->toEqualWithDelta($esperadoBrl, 1e-9);

    // O bug do fator-de-mil produziria 1000× isso (≈ R$ 4,125 por call).
    $inflado1000x = $esperadoBrl * 1000;
    expect($custo)->toBeLessThan($inflado1000x / 100);
});

it('escala linearmente e usa a unidade por-1k (10k tokens = 10x o custo de 1k)', function () {
    $umMil   = McpAuthMiddleware::estimarCustoBrl(1000, 0);
    $dezMil  = McpAuthMiddleware::estimarCustoBrl(10000, 0);

    // 1k input: (1000/1000)*0.00015*5.5 = 0.000825 BRL.
    expect($umMil)->toEqualWithDelta(0.000825, 1e-9);
    // 10k input deve ser exatamente 10× — não 10000× (prova divisão por 1000).
    expect($dezMil)->toEqualWithDelta($umMil * 10, 1e-9);
});

it('lê o pricing canônico copiloto.ai.pricing (não o fallback hardcoded)', function () {
    // Troca o pricing canônico pra um valor distinto e prova que estimarCustoBrl
    // reflete a config — logo NÃO está preso ao fallback nem à chave errada.
    Config::set('copiloto.ai.pricing.gpt-4o-mini', [
        'input'  => 0.00200, // 0.002 USD / 1k tokens
        'output' => 0.00000,
    ]);

    // (1000/1000)*0.002*5.5 = 0.011 BRL.
    $custo = McpAuthMiddleware::estimarCustoBrl(1000, 0);

    expect($custo)->toEqualWithDelta(0.011, 1e-9);
});

it('respeita o modelo default configurado em copiloto.ai.pricing_default_model', function () {
    Config::set('copiloto.ai.pricing_default_model', 'gpt-4o');
    Config::set('copiloto.ai.pricing.gpt-4o', [
        'input'  => 0.0025, // USD / 1k tokens
        'output' => 0.01,
    ]);

    // input: (2000/1000)*0.0025 = 0.005 ; output: (1000/1000)*0.01 = 0.01
    // total USD = 0.015 → × 5.5 = 0.0825 BRL.
    $custo = McpAuthMiddleware::estimarCustoBrl(2000, 1000);

    expect($custo)->toEqualWithDelta(0.0825, 1e-9);
});
