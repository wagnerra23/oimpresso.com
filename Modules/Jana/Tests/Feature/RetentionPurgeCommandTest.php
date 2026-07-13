<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Services\Privacy\RetentionPurgeService;

/**
 * jana:retention-purge — G1 P0 (AUDIT-SENIOR-2026-05-25 §6) — D7.d LGPD.
 *
 * DESQUARENTENA 2026-07-13 (evidência pro flip do canary ~28/jul). Antes o
 * beforeEach fazia `Schema::dropIfExists`/`create` de `business` + `jana_*` e
 * SKIPava fora de sqlite — quarentena universal ("era-sqlite floor" Onda 2 SDD).
 * Consequência: o path `anonymize` (exatamente o que o canary arma em prod) ficava
 * SEM cobertura em MySQL. Drop/create em MySQL persistente destruiria as tabelas
 * reais do `oimpresso_staging` (biz=1 dogfooding) — daí a quarentena era legítima.
 *
 * Agora roda na lane MySQL real (allowlist `jana-pest.yml`), espelhando o padrão
 * canônico do `BuscarHistoricoTest`:
 *  - `DatabaseTransactions` (rollback preserva schema + seed — NUNCA `migrate:fresh`,
 *    que dropa FK e envenena a catraca);
 *  - schema REAL (sem drop/create);
 *  - `business_id` SENTINELA (990001/990099 — sem FK em `jana_memoria_facts`) que
 *    nunca colide com tenant real (biz=1/biz=4);
 *  - insert/assert via `DB::table` raw (bypassa global scope/Scout/observers — igual
 *    ao próprio purge Service).
 *
 * Cobre:
 *  001. anonymize respeita TTL + PiiRedactor (default strategy) — o path do canary
 *  002. --dry-run não persiste nada
 *  003. Multi-tenant Tier 0: purge do tenant-alvo NUNCA toca outro tenant (ADR 0093)
 *  004. service listEntities() retorna 7 entidades canon
 *  005. entidade desconhecida retorna erro estruturado sem crash
 *
 * @see Modules\Jana\Console\Commands\RetentionPurgeCommand
 * @see Modules\Jana\Services\Privacy\RetentionPurgeService
 * @see Modules\Jana\Config\retention.php
 * @see memory/requisitos/Jana/EVIDENCE-retention-purge-dry-run-2026-07-12.md (§5 — execução real + desquarentena 2026-07-13)
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

const RETENTION_BIZ_ALVO = 990001;   // sentinela: purga aqui
const RETENTION_BIZ_NUNCA = 990099;  // sentinela: NUNCA tocar (cross-tenant)
const RETENTION_USER_SENT = 990777;  // sentinela user

beforeEach(function () {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('jana:retention-purge exercitado na lane MySQL (jana-pest.yml) — schema real + DatabaseTransactions.');
    }

    // Força enabled=true via runtime — NÃO toca .env real (flag prod segue false).
    config(['jana.retention.enabled' => true]);
    config(['jana.retention.strategy' => 'anonymize']);
});

/**
 * Semeia 1 fato memoria_fato com created_at fora do TTL (memoria_fato TTL=1825d).
 * business_id sentinela (sem FK) — DatabaseTransactions dá rollback ao final.
 */
function seedFatoAntigo(int $businessId, string $fato): int
{
    return (int) DB::table('jana_memoria_facts')->insertGetId([
        'business_id' => $businessId,
        'user_id' => RETENTION_USER_SENT,
        'fato' => $fato,
        'created_at' => now()->subDays(2000),
        'updated_at' => now()->subDays(2000),
    ]);
}

it('RetentionPurge 001 — anonimiza memoria_fato fora do TTL preservando row (path do canary)', function () {
    $antigaId = seedFatoAntigo(RETENTION_BIZ_ALVO, 'Cliente CPF 123.456.789-00 prefere boleto'); // pii-allowlist

    $recenteId = (int) DB::table('jana_memoria_facts')->insertGetId([
        'business_id' => RETENTION_BIZ_ALVO,
        'user_id' => RETENTION_USER_SENT,
        'fato' => 'Outro fato recente do mesmo cliente',
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    /** @var RetentionPurgeService $svc */
    $svc = app(RetentionPurgeService::class);
    $result = $svc->purgeEntity(
        businessId: RETENTION_BIZ_ALVO,
        entityKey: 'memoria_fato',
        retentionDaysOverride: null,
        dryRun: false,
    );

    expect($result['error'])->toBeNull()
        ->and($result['rows_matched'])->toBeGreaterThan(0)
        ->and($result['rows_purged'])->toBeGreaterThan(0);

    // Row antiga (2000d) foi anonimizada — CPF redactado.
    $antiga = DB::table('jana_memoria_facts')->where('id', $antigaId)->first();
    expect($antiga)->not->toBeNull()
        ->and($antiga->fato)->toContain('[REDACTED:CPF]');

    // Row recente (10d) NÃO foi tocada.
    $recente = DB::table('jana_memoria_facts')->where('id', $recenteId)->first();
    expect($recente->fato)->toBe('Outro fato recente do mesmo cliente');
});

it('RetentionPurge 002 — dry-run não persiste nada', function () {
    $id = seedFatoAntigo(RETENTION_BIZ_ALVO, 'Cliente CPF 111.222.333-44 fato antigo'); // pii-allowlist

    $exit = Artisan::call('jana:retention-purge', [
        '--business' => (string) RETENTION_BIZ_ALVO,
        '--entity' => 'memoria_fato',
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0);

    // Dry-run não muda o conteúdo — CPF intacto.
    $row = DB::table('jana_memoria_facts')->where('id', $id)->first();
    expect($row->fato)->toBe('Cliente CPF 111.222.333-44 fato antigo') // pii-allowlist
        ->and($row->fato)->not->toContain('[REDACTED');
});

it('RetentionPurge 003 — Tier 0: purge do tenant-alvo NUNCA toca outro tenant (cross-tenant isolation)', function () {
    $alvoId = seedFatoAntigo(RETENTION_BIZ_ALVO, 'alvo: cliente CPF 123.456.789-00');          // pii-allowlist
    $nuncaId = seedFatoAntigo(RETENTION_BIZ_NUNCA, 'NUNCA TOCAR: cliente CPF 123.456.789-00');  // pii-allowlist

    $exit = Artisan::call('jana:retention-purge', [
        '--business' => (string) RETENTION_BIZ_ALVO,
        '--entity' => 'memoria_fato',
    ]);

    expect($exit)->toBe(0);

    // Outro tenant INTOCADO — mesma PII + mesma idade, mas business_id diferente.
    $nunca = DB::table('jana_memoria_facts')->where('id', $nuncaId)->first();
    expect($nunca->fato)->toBe('NUNCA TOCAR: cliente CPF 123.456.789-00') // pii-allowlist
        ->and($nunca->fato)->not->toContain('[REDACTED');

    // Tenant-alvo anonimizado.
    $alvo = DB::table('jana_memoria_facts')->where('id', $alvoId)->first();
    expect($alvo->fato)->toContain('[REDACTED:CPF]');
});

it('RetentionPurge 004 — service listEntities() retorna 7 entidades canon', function () {
    $service = app(RetentionPurgeService::class);

    expect($service->listEntities())
        ->toContain('conversa', 'mensagem', 'sugestao', 'cache_semantico', 'memoria_fato', 'memoria_metrica', 'health_narrative');
});

it('RetentionPurge 005 — entidade desconhecida retorna erro estruturado sem crash', function () {
    $service = app(RetentionPurgeService::class);

    $result = $service->purgeEntity(
        businessId: RETENTION_BIZ_ALVO,
        entityKey: 'entidade-fantasma',
        retentionDaysOverride: null,
        dryRun: true,
    );

    expect($result['error'])->toContain('desconhecida')
        ->and($result['rows_purged'])->toBe(0);
});
