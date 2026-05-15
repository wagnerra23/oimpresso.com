<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Database\Seeders\McpActorsSeeder;
use Modules\TeamMcp\Entities\McpActor;
use Modules\TeamMcp\Services\ActorResolver;

uses(Tests\TestCase::class);

/**
 * Pest — McpActorsSeeder + ActorResolver integração.
 *
 * Cobre 7 invariantes do Identity Mesh (Constituição Art. 6 + ADR 0081):
 *   1. Seeder roda → 5 actors humanos canônicos existem
 *   2. Idempotência: rodar 2× não duplica
 *   3. Tier hierarchy: Wagner=L0, Felipe/Maira=L2, Luiz/Eliana=L3
 *   4. Felipe tem 'legacy-delphi/*' em modules_write (migração WR Comercial)
 *   5. Eliana tem 'NfeBrasil' em modules_write (fiscal owner)
 *   6. Maiara tem 'NfeBrasil' em modules_blocked (fiscal só L3 Eliana)
 *   7. ActorResolver::bySlug() resolve cada slug pro tier correto
 *
 * Convenções:
 *   - biz=1 sempre, nunca biz=4 (ROTA LIVRE — ADR 0101)
 *   - Guard SQLite hasTable (mcp_actors pode não ter migration rodada)
 *   - mcp_actors é cross-tenant (sem business_id) — apenas count actors humanos
 *   - PII Tier 0: nenhum email/credencial nos testes
 *
 * @see Modules/TeamMcp/Database/Seeders/McpActorsSeeder.php
 * @see memory/decisions/0081-identity-mesh-mcp-actors.md
 * @see memory/decisions/0086-fase-5-mvp-governance-actiongate-warn.md
 * @see memory/governance/IDENTITY-MESH-MANIFESTS.md
 */

beforeEach(function () {
    if (! Schema::hasTable('mcp_actors')) {
        $this->markTestSkipped('mcp_actors table missing — rode php artisan migrate primeiro (provider TeamMcp registra a migration).');
    }
});

/**
 * Limpa SOMENTE os 5 slugs canônicos (preserva outros actors tipo
 * 'claude-code-wagner-laptop' que vivem na mesma tabela — ADR 0081).
 */
function teamMcpResetCanonicalActors(): void
{
    DB::table('mcp_actors')
        ->whereIn('slug', ['wagner', 'felipe', 'maira', 'luiz', 'eliana'])
        ->delete();
}

// ------------------------------------------------------------------
// 1. Seeder cria os 5 humanos canônicos
// ------------------------------------------------------------------

it('McpActorsSeeder cria 5 actors humanos canônicos', function () {
    teamMcpResetCanonicalActors();

    (new McpActorsSeeder())->run();

    $slugs = McpActor::whereIn('slug', ['wagner', 'felipe', 'maira', 'luiz', 'eliana'])
        ->pluck('slug')
        ->sort()
        ->values()
        ->toArray();

    expect($slugs)->toEqual(['eliana', 'felipe', 'luiz', 'maira', 'wagner']);
});

// ------------------------------------------------------------------
// 2. Idempotência: 2× = mesmas 5 rows (não duplica)
// ------------------------------------------------------------------

it('McpActorsSeeder é idempotente — rodar 2× não duplica', function () {
    teamMcpResetCanonicalActors();

    (new McpActorsSeeder())->run();
    $countApos1 = McpActor::whereIn('slug', ['wagner', 'felipe', 'maira', 'luiz', 'eliana'])->count();

    (new McpActorsSeeder())->run();
    $countApos2 = McpActor::whereIn('slug', ['wagner', 'felipe', 'maira', 'luiz', 'eliana'])->count();

    expect($countApos1)->toBe(5);
    expect($countApos2)->toBe(5);
});

// ------------------------------------------------------------------
// 3. Tier hierarchy correta
// ------------------------------------------------------------------

it('tier hierarchy correta: Wagner=L0, Felipe/Maira=L2, Luiz/Eliana=L3', function () {
    teamMcpResetCanonicalActors();
    (new McpActorsSeeder())->run();

    expect(McpActor::where('slug', 'wagner')->value('trust_level'))->toBe('L0');
    expect(McpActor::where('slug', 'felipe')->value('trust_level'))->toBe('L2');
    expect(McpActor::where('slug', 'maira')->value('trust_level'))->toBe('L2');
    expect(McpActor::where('slug', 'luiz')->value('trust_level'))->toBe('L3');
    expect(McpActor::where('slug', 'eliana')->value('trust_level'))->toBe('L3');
});

// ------------------------------------------------------------------
// 4. Felipe tem 'legacy-delphi/*' em modules_write (migração WR Comercial)
// ------------------------------------------------------------------

it('Felipe tem legacy-delphi/* em modules_write (migração WR Comercial)', function () {
    teamMcpResetCanonicalActors();
    (new McpActorsSeeder())->run();

    $felipe = McpActor::where('slug', 'felipe')->firstOrFail();

    expect($felipe->modules_write)->toContain('legacy-delphi/*');
    expect($felipe->modules_write)->toContain('Officeimpresso');
    expect($felipe->modules_write)->toContain('OficinaAuto');
    expect($felipe->modules_write)->toContain('ComunicacaoVisual');
});

// ------------------------------------------------------------------
// 5. Eliana tem NfeBrasil em modules_write (fiscal owner)
// ------------------------------------------------------------------

it('Eliana tem NfeBrasil em modules_write (advogada+financeiro, fiscal owner)', function () {
    teamMcpResetCanonicalActors();
    (new McpActorsSeeder())->run();

    $eliana = McpActor::where('slug', 'eliana')->firstOrFail();

    expect($eliana->modules_write)->toContain('NfeBrasil');
    expect($eliana->modules_write)->toContain('Financeiro');
    expect($eliana->modules_write)->toContain('RecurringBilling');
    expect($eliana->canWriteModule('NfeBrasil'))->toBeTrue();
});

// ------------------------------------------------------------------
// 6. Maiara tem NfeBrasil em modules_blocked (fiscal só L3 Eliana)
// ------------------------------------------------------------------

it('Maiara tem NfeBrasil em modules_blocked — fiscal só L3 Eliana ou Wagner', function () {
    teamMcpResetCanonicalActors();
    (new McpActorsSeeder())->run();

    $maira = McpActor::where('slug', 'maira')->firstOrFail();

    expect($maira->modules_blocked)->toContain('NfeBrasil');
    expect($maira->modules_blocked)->toContain('RecurringBilling');
    expect($maira->canWriteModule('NfeBrasil'))->toBeFalse();
    expect($maira->canWriteModule('Crm'))->toBeTrue(); // Maiara pode CRM
});

// ------------------------------------------------------------------
// 7. ActorResolver::bySlug() resolve cada slug pro tier correto
// ------------------------------------------------------------------

it('ActorResolver::bySlug() resolve os 5 slugs canônicos com tier correto', function () {
    teamMcpResetCanonicalActors();
    (new McpActorsSeeder())->run();

    $resolver = new ActorResolver();

    $expected = [
        'wagner' => 'L0',
        'felipe' => 'L2',
        'maira'  => 'L2',
        'luiz'   => 'L3',
        'eliana' => 'L3',
    ];

    foreach ($expected as $slug => $tier) {
        $actor = $resolver->bySlug($slug);
        expect($actor)->not->toBeNull("ActorResolver não resolveu slug={$slug}");
        expect($actor->trust_level)->toBe($tier, "Tier divergente pra {$slug}");
        expect($actor->isRevoked())->toBeFalse("{$slug} não pode estar revogado");
    }
});

// ------------------------------------------------------------------
// Bônus invariante: parent_actor_id de não-Wagner aponta pra wagner.id
// ------------------------------------------------------------------

it('parent_actor_id de Felipe/Maiara/Luiz/Eliana aponta pra wagner.id', function () {
    teamMcpResetCanonicalActors();
    (new McpActorsSeeder())->run();

    $wagnerId = McpActor::where('slug', 'wagner')->value('id');
    expect($wagnerId)->not->toBeNull();

    foreach (['felipe', 'maira', 'luiz', 'eliana'] as $slug) {
        $parentId = McpActor::where('slug', $slug)->value('parent_actor_id');
        expect($parentId)->toBe($wagnerId, "parent_actor_id de {$slug} deveria ser wagner.id={$wagnerId}");
    }

    expect(McpActor::where('slug', 'wagner')->value('parent_actor_id'))->toBeNull();
});
