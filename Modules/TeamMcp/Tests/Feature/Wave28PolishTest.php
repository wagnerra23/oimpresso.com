<?php

declare(strict_types=1);

use Modules\TeamMcp\Services\McpTokenIssuer;

uses(Tests\TestCase::class);

/**
 * Wave 28 TeamMcp POLISH — saturação final ≥95.
 *
 * 2 Pest adicionais (Wave 28 sentry token rotate W23 G3 FICHA + W25):
 *   1. McpTokenIssuer preserva trio API canônico (issue + revoke + rotate)
 *      — regression guard W23 G3 FICHA self-service rotation.
 *   2. RotateTokenCommand artifact + signature preservados (regression guard
 *      W22 G3 CLI rotation — Tier 0 segredo: raw token 1× via warn(), NUNCA log).
 *
 * Tier 0 IRREVOGÁVEL ({@see ADR 0081}):
 *   - mcp_tokens hash-only (raw NUNCA persistido)
 *   - RotateTokenCommand --detail (não --verbose, Symfony reservado)
 *   - PT-BR + cross-tenant by design (mcp_actors sem business_id)
 *
 * @see Modules\TeamMcp\Tests\Feature\Wave18RetryTeamMcpSaturationTest (predecessor)
 * @see memory/decisions/0081-identity-mesh-actor-trust-mcp.md
 */
describe('Wave 28 TeamMcp Polish — saturação final ≥95', function () {

    it('W28 sentry — McpTokenIssuer preserva trio canônico issue/revoke/rotate', function () {
        $svc = app(McpTokenIssuer::class);
        expect($svc)->toBeInstanceOf(McpTokenIssuer::class);

        $ref = new ReflectionClass($svc);
        // Trio canônico (W18 issue/revoke + W23 G3 FICHA rotate)
        expect($ref->hasMethod('issue'))->toBeTrue('issue() removido — geração token quebrada');
        expect($ref->hasMethod('revoke'))->toBeTrue('revoke() removido — revogação token quebrada');
        expect($ref->hasMethod('rotate'))->toBeTrue('rotate() removido — W23 G3 FICHA self-service quebrado');

        // rotate signature: 3 params (userId, oldTokenId, ?note)
        $rotate = $ref->getMethod('rotate');
        expect(count($rotate->getParameters()))->toBe(3);
        expect($rotate->getParameters()[0]->getName())->toBe('userId');
        expect($rotate->getParameters()[1]->getName())->toBe('oldTokenId');
    });

    it('W28 sentry — RotateTokenCommand preserva signature canônica (Tier 0 segredo)', function () {
        $cmdPath = __DIR__ . '/../../Console/Commands/RotateTokenCommand.php';
        expect(file_exists($cmdPath))->toBeTrue(
            'RotateTokenCommand ausente — W22 G3 FICHA self-service token rotation quebrado'
        );

        $source = file_get_contents($cmdPath);

        // Tier 0 segredo: raw token via warn() (stdout 1×), NUNCA info() nem log
        expect($source)->toContain('teammcp:token:rotate');
        // Convenção .claude/rules/commands.md: --detail (não --verbose Symfony reserved)
        expect($source)->toContain('--detail');
        expect($source)->not->toContain('--verbose : ');
        // Dry-run obrigatório
        expect($source)->toContain('--dry-run');
    });
});
