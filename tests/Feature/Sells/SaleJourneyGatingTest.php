<?php

declare(strict_types=1);

/**
 * GUARD do breadcrumb "onde a venda está" (Wagner 2026-06-05).
 *
 * O stepper de jornada só pode aparecer em venda de oficina — varejo (ROTA
 * LIVRE) recebe journey.show=false e NÃO renderiza. Estes asserts trancam o
 * gate em 3 camadas (backend computa, Show gateia o render, o componente
 * fail-safe retorna null) pra que nenhuma regressão exponha o breadcrumb pra
 * quem não é oficina.
 *
 * Estrutural (lê source, sem DB) → roda no lane SQLite do CI. A lógica da
 * jornada em si é coberta por tests/Unit/Services/SaleJourneyServiceTest.
 *
 * @see app/Services/SaleJourneyService.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse e mata o loader da suite inteira (FV-B4).

it('SellController computa o journey via SaleJourneyService e passa a prop', function () {
    $src = (string) file_get_contents(base_path('app/Http/Controllers/SellController.php'));
    expect($src)->toContain('App\Services\SaleJourneyService');
    expect($src)->toContain("'journey' => \$journey");
    // O gate per-business tem que estar na composição do estado (nunca hardcode).
    expect($src)->toContain("'has_oficina_auto' => \$hasOficinaAuto");
    expect($src)->toContain("'oficina_auto_module'");
});

it('Show.tsx só renderiza o stepper quando journey.show (varejo/ROTA LIVRE não vê)', function () {
    $src = (string) file_get_contents(base_path('resources/js/Pages/Sells/Show.tsx'));
    expect($src)->toContain('journey?.show && <SaleJourneyStepper');
});

it('SaleJourneyStepper é fail-safe: retorna null sem journey.show', function () {
    $src = (string) file_get_contents(base_path('resources/js/Pages/Sells/_components/SaleJourneyStepper.tsx'));
    // Guard de saída antecipada antes de qualquer render.
    expect($src)->toContain('if (!journey?.show');
    expect($src)->toContain('return null');
});

it('o serviço de jornada não acessa DB (função pura, testável no CI)', function () {
    $src = (string) file_get_contents(base_path('app/Services/SaleJourneyService.php'));
    // Sem Eloquent/DB dentro do serviço — o Controller resolve o estado.
    expect($src)->not->toContain('DB::');
    expect($src)->not->toContain('::where(');
    expect($src)->not->toContain('->get()');
});
