<?php

declare(strict_types=1);

use Modules\ADS\Services\DecisionRouter;
use Modules\ADS\Services\PolicyEngine;
use Modules\ADS\Services\RiskEngine;
use Modules\ADS\Services\ConfidenceEngine;
use Modules\ADS\Services\RoutingInput;
use Modules\ADS\Services\RoutingDecision;

uses(Tests\TestCase::class);

// ARQ-0003 + ARQ-0010 — DecisionRouter: roteamento + hierarquia
// Testes sem DB: substitui DecisionRouter por subclasse que não grava no banco

class TestableDecisionRouter extends DecisionRouter
{
    public ?RoutingDecision $lastDecision = null;

    protected function record(
        RoutingInput $input,
        string       $destination,
        float        $riskScore,
        float        $confidenceScore,
        string       $policyApplied,
        int          $hitlLevel,
    ): RoutingDecision {
        $decision = new RoutingDecision(
            decisionId:      999,
            destination:     $destination,
            riskScore:       $riskScore,
            confidenceScore: $confidenceScore,
            policyApplied:   $policyApplied,
            hitlLevel:       $hitlLevel,
        );
        $this->lastDecision = $decision;
        return $decision;
    }

    protected function hasActiveLock(array $files): bool
    {
        return false; // sem DB nos testes unitários
    }

    protected function getThreshold(string $domain, string $eventType): array
    {
        return [
            'brain_a_risk_max' => 0.30,
            'brain_a_conf_min' => 0.70,
            'brain_b_risk_max' => 0.70,
        ];
    }
}

function makeRouter(float $confidenceScore = 0.5): TestableDecisionRouter
{
    $confidence = Mockery::mock(ConfidenceEngine::class);
    $confidence->shouldReceive('getScore')->andReturn($confidenceScore);
    $confidence->shouldReceive('getHitlLevel')->andReturn(2);

    return new TestableDecisionRouter(new PolicyEngine(), new RiskEngine(), $confidence);
}

function makeInput(string $eventType, string $domain = 'Financeiro'): RoutingInput
{
    return new RoutingInput(
        businessId:    1,
        eventType:     $eventType,
        eventSource:   'brain_a',
        domain:        $domain,
        filesAffected: [],
    );
}

it('bloqueia eventos BLOCK_ALWAYS independente de confiança máxima', function () {
    $router   = makeRouter(confidenceScore: 1.0);
    $decision = $router->route(makeInput('env_production'));

    expect($decision->isBlocked())->toBeTrue();
    expect($decision->destination)->toBe('blocked');
});

it('envia para pending_wagner eventos REQUIRE_HUMAN_REVIEW', function () {
    $router   = makeRouter(confidenceScore: 1.0);
    $decision = $router->route(makeInput('production_deploy'));

    expect($decision->needsWagner())->toBeTrue();
    expect($decision->destination)->toBe('pending_wagner');
});

it('envia para brain_b eventos REQUIRE_BRAIN_B', function () {
    $router   = makeRouter(confidenceScore: 0.95);
    $decision = $router->route(makeInput('db_schema_change'));

    expect($decision->goesToBrainB())->toBeTrue();
});

it('envia para brain_a evento trivial com confiança >= 0.70', function () {
    $router   = makeRouter(confidenceScore: 0.90);
    $decision = $router->route(makeInput('lang_file_pt_br'));

    expect($decision->goesToBrainA())->toBeTrue();
});

it('envia para brain_b quando confiança abaixo do gate', function () {
    $router   = makeRouter(confidenceScore: 0.30);
    $decision = $router->route(makeInput('lang_file_pt_br'));

    // confiança 0.30 < gate 0.70 → não vai para brain_a
    expect($decision->goesToBrainA())->toBeFalse();
});

it('envia para pending_wagner quando risco >= 0.70', function () {
    $router   = makeRouter(confidenceScore: 0.99);
    // auth_middleware tem risco alto no prior
    $decision = $router->route(makeInput('auth_middleware'));

    expect($decision->needsWagner())->toBeTrue();
});

it('BLOCK_ALWAYS vence sobre confiança 1.0 — hierarquia ARQ-0010', function () {
    $router   = makeRouter(confidenceScore: 1.0);
    $decision = $router->route(makeInput('billing_financial_flow'));

    expect($decision->isBlocked())->toBeTrue();
});

it('decision retorna riskScore e confidenceScore corretos', function () {
    $router   = makeRouter(confidenceScore: 0.60);
    $decision = $router->route(makeInput('lang_file_pt_br'));

    expect($decision->riskScore)->toBeFloat();
    expect($decision->confidenceScore)->toBe(0.60);
    expect($decision->decisionId)->toBe(999);
});

it('delphi_contract é sempre bloqueado — contrato imutável', function () {
    $router   = makeRouter(confidenceScore: 1.0);
    $decision = $router->route(makeInput('delphi_contract'));

    expect($decision->isBlocked())->toBeTrue();
});
