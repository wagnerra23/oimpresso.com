<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Database\Seeders\McpActorsSeeder;
use Modules\TeamMcp\Entities\McpActor;

uses(Tests\TestCase::class);

/**
 * Pest — Matriz de permissoes per actor (gates dev/superadmin/AI).
 *
 * Cobertura crítica (US-TEAM-005 + US-TEAM-006):
 *   1. Wagner (L0/superadmin) tem modules_write=['*'] — passa em qualquer modulo
 *   2. Felipe (L2/dev) bloqueado em NfeBrasil — gate fiscal
 *   3. Eliana (L3/advogada-financeiro) tem NfeBrasil em modules_write — fiscal owner
 *   4. Luiz (L3/iniciante) bloqueado em modulos sensiveis (audit_required=true)
 *   5. Maiara (L2/dev) tem NfeBrasil em modules_blocked — fiscal so L3 Eliana
 *   6. isActionBlocked respeita actions_blocked per actor (drop_table etc)
 *   7. effectiveHumanSlug resolve IA -> parent humano (audit trail completo)
 *   8. parent_actor_id de IA aponta pra humano L0/L1 (cadeia de delegacao)
 *
 * Convenções:
 *   - biz=1 sempre (Wagner-superadmin), nunca biz=4 (ADR 0101)
 *   - Usa seeder canonico McpActorsSeeder (idempotente)
 *   - SQLite guard pra mcp_actors table
 *   - PII Tier 0: zero email/credencial real
 *
 * @see Modules/TeamMcp/Entities/McpActor.php
 * @see Modules/TeamMcp/Database/Seeders/McpActorsSeeder.php
 * @see memory/decisions/0081-identity-mesh-mcp-actors.md
 * @see memory/decisions/0086-fase-5-mvp-governance-actiongate-warn.md
 */

beforeEach(function () {
    // O beforeEach roda o McpActorsSeeder (abaixo) e os testes fazem McpActor::create —
    // McpActor usa LogsActivity, que grava em activity_log a cada create. Schema parcial
    // com mcp_actors presente mas activity_log ausente fazia o seeder/create estourar
    // QueryException (ERROR) em vez de SKIP. Guard cobre o set completo do write-path.
    foreach (['mcp_actors', 'activity_log'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped(
                "Tabela {$tabela} ausente — rode migrate:fresh contra o dump completo ".
                '(activity_log é dependência do trait LogsActivity em McpActor).'
            );
        }
    }

    // Reseta os 5 canonicos pra estado determinístico
    DB::table('mcp_actors')
        ->whereIn('slug', ['wagner', 'felipe', 'maira', 'luiz', 'eliana'])
        ->delete();

    (new McpActorsSeeder())->run();
});

// ------------------------------------------------------------------
// 1. Wagner (L0 superadmin) passa em qualquer modulo
// ------------------------------------------------------------------

it('Wagner (L0 superadmin) tem modules_write incluindo wildcard *', function () {
    $wagner = McpActor::where('slug', 'wagner')->firstOrFail();

    expect($wagner->trust_level)->toBe('L0');
    expect($wagner->canWriteModule('Crm'))->toBeTrue();
    expect($wagner->canWriteModule('NfeBrasil'))->toBeTrue();
    expect($wagner->canWriteModule('Financeiro'))->toBeTrue();
    expect($wagner->canWriteModule('QualquerModuloNovo'))->toBeTrue();
});

// ------------------------------------------------------------------
// 2. Felipe (L2 dev) bloqueado em NfeBrasil — gate fiscal
// ------------------------------------------------------------------

it('Felipe (L2 dev legacy-delphi) bloqueado em NfeBrasil — fiscal so L3 Eliana', function () {
    $felipe = McpActor::where('slug', 'felipe')->firstOrFail();

    expect($felipe->trust_level)->toBe('L2');
    expect($felipe->canWriteModule('NfeBrasil'))->toBeFalse(
        'Felipe nao pode escrever em NfeBrasil — fiscal owner = Eliana L3 ADR 0081'
    );
    // Felipe pode escrever em legacy-delphi e Officeimpresso (migracao WR)
    expect($felipe->canWriteModule('Officeimpresso'))->toBeTrue();
});

// ------------------------------------------------------------------
// 3. Eliana (L3 advogada+financeiro) tem NfeBrasil em modules_write
// ------------------------------------------------------------------

it('Eliana (L3 advogada+financeiro) e fiscal owner — escreve NfeBrasil + Financeiro', function () {
    $eliana = McpActor::where('slug', 'eliana')->firstOrFail();

    expect($eliana->trust_level)->toBe('L3');
    expect($eliana->canWriteModule('NfeBrasil'))->toBeTrue();
    expect($eliana->canWriteModule('Financeiro'))->toBeTrue();
    expect($eliana->canWriteModule('RecurringBilling'))->toBeTrue();
});

// ------------------------------------------------------------------
// 4. Maiara (L2 dev) NfeBrasil em modules_blocked — fiscal so L3 Eliana
// ------------------------------------------------------------------

it('Maiara (L2 dev) bloqueada em NfeBrasil — fiscal so L3 Eliana ou Wagner', function () {
    $maira = McpActor::where('slug', 'maira')->firstOrFail();

    expect($maira->trust_level)->toBe('L2');
    expect($maira->canWriteModule('NfeBrasil'))->toBeFalse();
    expect($maira->canWriteModule('RecurringBilling'))->toBeFalse();
    // Mas pode CRM/Compras dela
    expect($maira->canWriteModule('Crm'))->toBeTrue();
});

// ------------------------------------------------------------------
// 5. Luiz (L3 iniciante) audit_required=true — tracking obrigatorio
// ------------------------------------------------------------------

it('Luiz (L3 iniciante) tem audit_required=true — tracking obrigatorio', function () {
    $luiz = McpActor::where('slug', 'luiz')->firstOrFail();

    expect($luiz->trust_level)->toBe('L3');
    expect($luiz->audit_required)->toBeTrue(
        'L3 iniciante exige audit_required=true (cada acao logada em mcp_audit_log)'
    );
});

// ------------------------------------------------------------------
// 6. isActionBlocked respeita actions_blocked per actor
// ------------------------------------------------------------------

it('isActionBlocked retorna true pra actions explicitamente bloqueadas', function () {
    // Cria actor de teste com actions_blocked especifico
    DB::table('mcp_actors')->where('slug', 'test-action-blocked')->delete();

    $actor = McpActor::create([
        'slug' => 'test-action-blocked',
        'type' => 'human',
        'trust_level' => 'L3',
        'modules_write' => ['Crm'],
        'modules_read' => ['*'],
        'modules_blocked' => [],
        'skills_required' => [],
        'actions_blocked' => ['drop_table', 'schema_destructive', 'truncate_business'],
        'audit_required' => true,
        'display_name' => 'Test Action Blocked',
    ]);

    expect($actor->isActionBlocked('drop_table'))->toBeTrue();
    expect($actor->isActionBlocked('schema_destructive'))->toBeTrue();
    expect($actor->isActionBlocked('truncate_business'))->toBeTrue();
    expect($actor->isActionBlocked('read_tasks'))->toBeFalse(
        'read_tasks nao esta em actions_blocked — deveria permitir'
    );

    // Cleanup
    $actor->delete();
});

// ------------------------------------------------------------------
// 7. effectiveHumanSlug resolve IA -> parent humano (audit trail)
// ------------------------------------------------------------------

it('IA actor com parent humano valido resolve effectiveHumanSlug pro humano', function () {
    $wagner = McpActor::where('slug', 'wagner')->firstOrFail();

    DB::table('mcp_actors')->where('slug', 'test-ai-pareada-wagner')->delete();

    $iaActor = McpActor::create([
        'slug' => 'test-ai-pareada-wagner',
        'type' => 'ai_agent',
        'trust_level' => 'L2',
        'parent_actor_id' => $wagner->id,
        'modules_write' => ['*'],
        'modules_read' => ['*'],
        'modules_blocked' => [],
        'skills_required' => [],
        'actions_blocked' => [],
        'audit_required' => true,
        'display_name' => 'Claude Code Wagner Test',
    ]);

    expect($iaActor->isAi())->toBeTrue();
    expect($iaActor->effectiveHumanSlug())->toBe(
        'wagner',
        'IA pareada com Wagner deveria resolver pro humano Wagner (audit trail)'
    );

    // Cleanup
    $iaActor->delete();
});

// ------------------------------------------------------------------
// 8. parent_actor_id de IA com parent revogado fallback pro proprio slug
// ------------------------------------------------------------------

it('IA actor com parent revogado NAO resolve via parent — fallback proprio slug', function () {
    DB::table('mcp_actors')->where('slug', 'test-human-revogado')->delete();
    DB::table('mcp_actors')->where('slug', 'test-ai-orfa')->delete();

    $humano = McpActor::create([
        'slug' => 'test-human-revogado',
        'type' => 'human',
        'trust_level' => 'L3',
        'modules_write' => ['Crm'],
        'modules_read' => ['*'],
        'modules_blocked' => [],
        'skills_required' => [],
        'actions_blocked' => [],
        'audit_required' => true,
        'display_name' => 'Humano Revogado Test',
        'revoked_at' => now(),
    ]);

    $iaOrfa = McpActor::create([
        'slug' => 'test-ai-orfa',
        'type' => 'ai_agent',
        'trust_level' => 'L3',
        'parent_actor_id' => $humano->id,
        'modules_write' => ['Crm'],
        'modules_read' => ['*'],
        'modules_blocked' => [],
        'skills_required' => [],
        'actions_blocked' => [],
        'audit_required' => true,
        'display_name' => 'IA Orfa Test',
    ]);

    // Parent revogado — fallback pro proprio slug da IA
    expect($iaOrfa->effectiveHumanSlug())->toBe('test-ai-orfa');

    $humano->delete();
    $iaOrfa->delete();
});
