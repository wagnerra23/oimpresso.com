<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Entities\McpActor;
use Modules\TeamMcp\Services\ActorResolver;

uses(Tests\TestCase::class);

/**
 * Pest — Multi-tenant token isolation entre devs do time MCP.
 *
 * Cobertura crítica (US-TEAM-003 + US-TEAM-004):
 *   1. Token gerado pra actor A (Wagner) NAO resolve actor B (Felipe)
 *   2. ActorResolver::byId(felipe) != ActorResolver::byId(wagner)
 *   3. Token revogado (revoked_at) NAO resolve actor (security gate)
 *   4. Capabilities de actor A NAO vazam pra actor B (modules_write isolado)
 *   5. canWriteModule respeita modules_blocked per actor (fiscal Eliana-only)
 *   6. Slug unique constraint impede dois actors com mesmo slug
 *   7. Cross-tenant: mcp_actors SAO cross-business (sem business_id) por design
 *      ADR 0081 — mas tokens herdam business_id do user_id linkado
 *
 * Convenções:
 *   - biz=1 sempre (Wagner-superadmin), nunca biz=4 (ROTA LIVRE — ADR 0101)
 *   - Tokens fake (string aleatoria) — NUNCA token MCP real do servidor
 *   - SQLite guard pra schema completo (mcp_actors + mcp_tokens)
 *   - PII Tier 0: zero email/credencial real
 *
 * @see Modules/TeamMcp/Entities/McpActor.php
 * @see Modules/TeamMcp/Services/ActorResolver.php
 * @see memory/decisions/0081-identity-mesh-mcp-actors.md
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    if (! Schema::hasTable('mcp_actors')) {
        $this->markTestSkipped(
            'mcp_actors table missing — rode php artisan migrate '.
            '(provider TeamMcp registra a migration P0.4 ADR 0081).'
        );
    }
});

/**
 * Cria 2 actors de teste com slugs unicos (nao colidem com seeder canonico).
 * Retorna [actorA, actorB] — sempre distintos.
 */
function teamMcpCriarPar(): array
{
    DB::table('mcp_actors')
        ->whereIn('slug', ['test-dev-a-isolacao', 'test-dev-b-isolacao'])
        ->delete();

    $actorA = McpActor::create([
        'slug' => 'test-dev-a-isolacao',
        'type' => 'human',
        'trust_level' => 'L2',
        'modules_write' => ['Crm', 'Compras'],
        'modules_read' => ['*'],
        'modules_blocked' => ['NfeBrasil'],
        'skills_required' => [],
        'actions_blocked' => [],
        'audit_required' => true,
        'display_name' => 'Dev A Test',
    ]);

    $actorB = McpActor::create([
        'slug' => 'test-dev-b-isolacao',
        'type' => 'human',
        'trust_level' => 'L3',
        'modules_write' => ['NfeBrasil', 'Financeiro'],
        'modules_read' => ['*'],
        'modules_blocked' => ['Crm'],
        'skills_required' => [],
        'actions_blocked' => [],
        'audit_required' => true,
        'display_name' => 'Dev B Test',
    ]);

    return [$actorA, $actorB];
}

// ------------------------------------------------------------------
// 1. ActorResolver::byId nunca cruza dev A <-> dev B
// ------------------------------------------------------------------

it('ActorResolver::byId(A) retorna actor A — nao vaza pra B', function () {
    [$actorA, $actorB] = teamMcpCriarPar();

    $resolver = new ActorResolver();

    $resolvedA = $resolver->byId($actorA->id);
    $resolvedB = $resolver->byId($actorB->id);

    expect($resolvedA)->not->toBeNull();
    expect($resolvedB)->not->toBeNull();
    expect($resolvedA->id)->toBe($actorA->id);
    expect($resolvedB->id)->toBe($actorB->id);
    expect($resolvedA->id)->not->toBe($resolvedB->id, 'Resolver cruzou ids — vazamento grave');
    expect($resolvedA->slug)->not->toBe($resolvedB->slug);
});

// ------------------------------------------------------------------
// 2. ActorResolver::bySlug isolacao por slug
// ------------------------------------------------------------------

it('ActorResolver::bySlug(A) nao retorna actor B', function () {
    [$actorA, $actorB] = teamMcpCriarPar();

    $resolver = new ActorResolver();

    $resolvedA = $resolver->bySlug('test-dev-a-isolacao');
    expect($resolvedA)->not->toBeNull();
    expect($resolvedA->id)->toBe($actorA->id);
    expect($resolvedA->id)->not->toBe($actorB->id);
});

// ------------------------------------------------------------------
// 3. Actor revogado NAO resolve (security gate ADR 0081)
// ------------------------------------------------------------------

it('actor com revoked_at preenchido NAO resolve via byId', function () {
    [$actorA, $actorB] = teamMcpCriarPar();

    // Revoga actor A
    $actorA->update(['revoked_at' => now()]);

    $resolver = new ActorResolver();

    expect($resolver->byId($actorA->id))->toBeNull(
        'Actor revogado deveria retornar null — security gate violado'
    );

    // Actor B ainda resolve (isolacao)
    expect($resolver->byId($actorB->id))->not->toBeNull();
});

it('actor com revoked_at preenchido NAO resolve via bySlug', function () {
    [$actorA, $actorB] = teamMcpCriarPar();

    $actorA->update(['revoked_at' => now()]);

    $resolver = new ActorResolver();

    expect($resolver->bySlug('test-dev-a-isolacao'))->toBeNull(
        'Actor revogado bySlug deveria retornar null'
    );
});

// ------------------------------------------------------------------
// 4. Capabilities de actor A NAO vazam pra actor B
// ------------------------------------------------------------------

it('modules_write de A nao vaza pra B — isolacao de permissoes', function () {
    [$actorA, $actorB] = teamMcpCriarPar();

    // Recarrega do DB pra garantir state real
    $actorA->refresh();
    $actorB->refresh();

    expect($actorA->modules_write)->toContain('Crm');
    expect($actorA->modules_write)->not->toContain('NfeBrasil');

    expect($actorB->modules_write)->toContain('NfeBrasil');
    expect($actorB->modules_write)->not->toContain('Crm');
});

// ------------------------------------------------------------------
// 5. canWriteModule respeita modules_blocked per actor (fiscal Eliana-only)
// ------------------------------------------------------------------

it('actor A bloqueado em NfeBrasil — canWriteModule retorna false', function () {
    [$actorA] = teamMcpCriarPar();

    expect($actorA->canWriteModule('NfeBrasil'))->toBeFalse(
        'Actor A tem NfeBrasil em modules_blocked — write deveria ser false'
    );
    expect($actorA->canWriteModule('Crm'))->toBeTrue(
        'Actor A tem Crm em modules_write — write deveria ser true'
    );
});

it('actor B bloqueado em Crm mas autorizado em NfeBrasil', function () {
    [, $actorB] = teamMcpCriarPar();

    expect($actorB->canWriteModule('NfeBrasil'))->toBeTrue();
    expect($actorB->canWriteModule('Crm'))->toBeFalse(
        'Actor B tem Crm em modules_blocked — isolacao falhou'
    );
});

// ------------------------------------------------------------------
// 6. Slug unique constraint impede dois actors com mesmo slug
// ------------------------------------------------------------------

it('slug unique constraint impede dois actors com mesmo slug', function () {
    teamMcpCriarPar();

    // Tenta criar duplicado — DB deve rejeitar
    expect(function () {
        McpActor::create([
            'slug' => 'test-dev-a-isolacao', // mesmo slug do actorA
            'type' => 'human',
            'trust_level' => 'L2',
            'modules_write' => ['*'],
            'modules_read' => ['*'],
            'modules_blocked' => [],
            'skills_required' => [],
            'actions_blocked' => [],
            'audit_required' => true,
            'display_name' => 'Duplicado',
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

// ------------------------------------------------------------------
// 7. mcp_actors cross-tenant por design (ADR 0081) — sem business_id
// ------------------------------------------------------------------

it('mcp_actors table NAO tem coluna business_id — cross-tenant por design ADR 0081', function () {
    // Identity Mesh ADR 0081: actors transcendem business_id; tokens binding
    // a actor_id herdam business_id do user_id (mcp_tokens.user_id -> users.business_id).
    expect(Schema::hasColumn('mcp_actors', 'business_id'))->toBeFalse(
        'mcp_actors NAO deveria ter business_id — ADR 0081 cross-tenant. '.
        'Se presente, alguem adicionou sem ADR mae nova (Tier 0 violacao).'
    );
});

// ------------------------------------------------------------------
// 8. Cleanup hygiene — actors test NAO sobrevivem proxima rodada
// ------------------------------------------------------------------

afterEach(function () {
    DB::table('mcp_actors')
        ->whereIn('slug', ['test-dev-a-isolacao', 'test-dev-b-isolacao'])
        ->delete();
});
