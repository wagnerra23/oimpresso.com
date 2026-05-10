<?php

declare(strict_types=1);

use Modules\ADS\Services\PolicyEngine;
use Modules\ADS\Services\PolicyResult;

uses(Tests\TestCase::class);

// ARQ-0006 — PolicyEngine: firewall de decisões

it('bloqueia event_types em BLOCK_ALWAYS', function (string $eventType) {
    $engine = new PolicyEngine();
    $result = $engine->check($eventType);

    expect($result->isBlocked())->toBeTrue();
    expect($result->action)->toBe(PolicyResult::ACTION_BLOCK);
    expect($result->rule)->toBe('BLOCK_ALWAYS');
})->with([
    'env_production',
    'append_only_table',
    'auth_middleware',
    'pii_direct_exposure',
    'delphi_contract',
    'composer_production',
    'db_trigger_removal',
    'billing_financial_flow',
]);

it('exige revisão humana para REQUIRE_HUMAN_REVIEW', function (string $eventType) {
    $engine = new PolicyEngine();
    $result = $engine->check($eventType);

    expect($result->requiresHuman())->toBeTrue();
    expect($result->action)->toBe(PolicyResult::ACTION_REQUIRE_HUMAN);
})->with([
    'new_module_creation',
    'new_adr_proposal',
    'threshold_change',
    'pattern_hardcode',
    'production_deploy',
]);

it('exige Brain B para REQUIRE_BRAIN_B', function (string $eventType) {
    $engine = new PolicyEngine();
    $result = $engine->check($eventType);

    expect($result->action)->toBe(PolicyResult::ACTION_REQUIRE_BRAIN_B);
    expect($result->isBlocked())->toBeFalse();
})->with([
    'lgpd_data_handling',
    'db_schema_change',
    'composer_json_change',
    'nfse_fiscal_logic',
    'security_rule_change',
    'multi_tenant_scope',
]);

it('permite Brain A para ALLOW_BRAIN_A', function (string $eventType) {
    $engine = new PolicyEngine();
    $result = $engine->check($eventType);

    expect($result->allowsBrainA())->toBeTrue();
    expect($result->action)->toBe(PolicyResult::ACTION_ALLOW_BRAIN_A);
    expect($result->isBlocked())->toBeFalse();
})->with([
    'lang_file_pt_br',
    'adr_frontmatter_fix',
    'md_link_fix',
    'comment_typo',
    'mcp_sync_memory',
    'session_log_creation',
]);

it('trata event_type desconhecido conservadoramente (REQUIRE_BRAIN_B)', function () {
    $engine = new PolicyEngine();
    $result = $engine->check('evento_nunca_visto_xyz');

    expect($result->action)->toBe(PolicyResult::ACTION_REQUIRE_BRAIN_B);
    expect($result->rule)->toBe('UNKNOWN_TYPE_CONSERVATIVE');
    expect($result->isBlocked())->toBeFalse();
});

it('BLOCK_ALWAYS nunca pode ser override pelo allowsBrainA()', function () {
    $engine = new PolicyEngine();
    $result = $engine->check('env_production');

    expect($result->allowsBrainA())->toBeFalse();
    expect($result->isBlocked())->toBeTrue();
});
