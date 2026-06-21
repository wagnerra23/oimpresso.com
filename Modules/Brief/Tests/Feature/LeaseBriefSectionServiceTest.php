<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Brief\Services\BriefValidator;
use Modules\Brief\Services\LeaseBriefSectionService;

uses(Tests\TestCase::class);

/**
 * Item C2+C3 (SDD Leva 2, ADR 0278) — bloco de leases ATIVOS no Daily Brief.
 *
 * GUARDA o contrato do injetor pós-LLM:
 *   1. Leases ativos → bullet 🔒 + nudge literal "claim antes de pegar" SOB
 *      `## EM VOO AGORA`; resto do markdown intacto; ainda termina em ---END---.
 *   2. Zero leases → conteúdo byte-idêntico (best-effort silencioso).
 *   3. Lease EXPIRADO não vaza (roteia por activeLeases → sweepExpired).
 *   4. Kill-switch `brief.lease_section` false → no-op (mesmo com lease ativo).
 *   5. Pós-injeção, o brief AINDA passa BriefValidator::validate() (7 headers).
 *
 * Padrão era-sqlite (schema sintético inline + skip não-sqlite), igual a
 * WorkLeaseServiceTest: a lane per-PR roda sqlite :memory: e RefreshDatabase da
 * suite inteira inclui migrations MySQL-only — então monta-se só a tabela do D1.
 *
 * @see Modules/Brief/Services/LeaseBriefSectionService.php
 * @see Modules/Brief/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 * @see Modules/Jana/Services/WorkLease/WorkLeaseService.php (activeLeases)
 * @see Modules/Governance/Tests/Feature/SddBriefLineServiceTest.php (idiom espelhado)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente (floor SDD).');
    }

    Schema::dropIfExists('mcp_work_leases');

    Schema::create('mcp_work_leases', function ($table) {
        $table->bigIncrements('id');
        $table->string('task_id', 40);
        $table->string('human_principal', 60);
        $table->string('agent_id', 80)->nullable();
        $table->string('claude_code_session', 64)->nullable();
        $table->timestamp('acquired_at')->useCurrent();
        $table->timestamp('heartbeat_at')->useCurrent();
        $table->timestamp('expires_at');
        $table->timestamp('released_at')->nullable();
        $table->timestamps();
        $table->index('task_id', 'mwl_task_idx');
        $table->index('expires_at', 'mwl_expires_idx');
    });
    Schema::table('mcp_work_leases', function ($table) {
        $table->integer('active_marker')
            ->virtualAs('case when released_at is null then 1 else null end')
            ->nullable();
    });
    Schema::table('mcp_work_leases', function ($table) {
        $table->unique(['task_id', 'active_marker'], 'mwl_active_lease_unq');
    });
});

afterEach(function () {
    Schema::dropIfExists('mcp_work_leases');
});

function leaseC2Insert(string $taskId, string $principal, int $expiresInMinutes = 30): void
{
    DB::table('mcp_work_leases')->insert([
        'task_id' => $taskId,
        'human_principal' => $principal,
        'acquired_at' => now(),
        'heartbeat_at' => now(),
        'expires_at' => now()->addMinutes($expiresInMinutes),
        'released_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Brief sintético com os 7 headers ordenados, terminando em ---END--- (passa BriefValidator). */
function leaseC2Brief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- US-GOV-099: trabalho narrado\n\n"
        ."## DECISÕES RECENTES (24h)\n- x\n\n## SKILLS USO 7d\n- x\n\n"
        ."## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('lease ativo → bullet 🔒 + nudge literal injetado sob EM VOO AGORA (resto intacto, termina ---END---)', function () {
    leaseC2Insert('US-GOV-022', 'wagner');

    $out = (new LeaseBriefSectionService())->inject(leaseC2Brief());

    expect($out)->toContain("## EM VOO AGORA\n- 🔒 `US-GOV-022` → wagner (expira ")
        ->and($out)->toContain('↳ claim antes de pegar: rode tasks-claim pra não colidir (ADR 0278)')
        // bullet narrado original da seção preservado (não foi sobrescrito)
        ->and($out)->toContain('- US-GOV-099: trabalho narrado')
        // headers vizinhos intactos
        ->and($out)->toContain('## DECISÕES RECENTES (24h)')
        ->and($out)->toContain('- 🟢 Migration aging: ok')
        ->and(trim($out))->toEndWith('---END---');
})->group('brief', 'work-lease', 'ci');

it('zero leases → conteúdo byte-idêntico (best-effort silencioso)', function () {
    expect((new LeaseBriefSectionService())->inject(leaseC2Brief()))
        ->toBe(leaseC2Brief());
})->group('brief', 'work-lease', 'ci');

it('lease EXPIRADO NÃO é surfaceado (roteia por activeLeases → sweepExpired)', function () {
    leaseC2Insert('US-GOV-022', 'wagner', expiresInMinutes: -5); // já estourou o TTL

    $out = (new LeaseBriefSectionService())->inject(leaseC2Brief());

    expect($out)->toBe(leaseC2Brief())              // nenhum bloco injetado
        ->and($out)->not->toContain('🔒')
        ->and($out)->not->toContain('claim antes de pegar');
})->group('brief', 'work-lease', 'ci');

it('kill-switch brief.lease_section false → inject vira no-op mesmo com lease ativo', function () {
    config(['brief.lease_section' => false]);
    leaseC2Insert('US-GOV-022', 'wagner');

    expect((new LeaseBriefSectionService())->inject(leaseC2Brief()))
        ->toBe(leaseC2Brief());
})->group('brief', 'work-lease', 'ci');

it('brief pós-injeção AINDA passa BriefValidator (7 headers ordenados, ---END--- intacto)', function () {
    leaseC2Insert('US-GOV-022', 'wagner');
    leaseC2Insert('US-GOV-031', 'eliana');

    $out = (new LeaseBriefSectionService())->inject(leaseC2Brief());

    // 2 leases + nudge presentes (injeção real aconteceu)…
    expect($out)->toContain('`US-GOV-022` → wagner')
        ->and($out)->toContain('`US-GOV-031` → eliana')
        ->and($out)->toContain('claim antes de pegar');

    // …e o validador canônico continua aceitando (nenhum header novo criado).
    expect((new BriefValidator())->validate($out)->isOk())->toBeTrue();
})->group('brief', 'work-lease', 'ci');
