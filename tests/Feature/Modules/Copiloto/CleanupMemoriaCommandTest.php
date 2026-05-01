<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

/**
 * FASE 8 — CleanupMemoriaCommand: esquecimento controlado.
 *
 * Valida as três limpezas (bloat, expirados, órfãos MCP) + dry-run + --hard guard.
 * Roda contra MySQL dev real. DatabaseTransactions faz rollback após cada teste.
 * Skip automático se tabela não existir.
 */

beforeEach(function () {
    try {
        DB::table('copiloto_memoria_facts')->count();
    } catch (\Throwable $e) {
        test()->markTestSkipped('copiloto_memoria_facts indisponível: ' . $e->getMessage());
    }
    config(['scout.driver' => 'null']);
});

function cleanupInserirFato(array $attrs = []): int
{
    return DB::table('copiloto_memoria_facts')->insertGetId(array_merge([
        'business_id' => 1,
        'user_id'     => 88888, // user_id fictício pra isolar dos dados reais
        'fato'        => 'Fato fixture cleanup ' . uniqid(),
        'metadata'    => '{}',
        'valid_from'  => now(),
        'valid_until' => null,
        'hits_count'  => 0,
        'core_memory' => false,
        'created_at'  => now(),
        'updated_at'  => now(),
        'deleted_at'  => null,
    ], $attrs));
}

// ── Helper pra contar só fatos deste test (isolado pelo user_id fictício) ─────

function cleanupContar(string $filtro = 'ativos'): int
{
    $q = DB::table('copiloto_memoria_facts')->where('user_id', 88888);
    return match ($filtro) {
        'ativos'  => $q->whereNull('deleted_at')->count(),
        'deleted' => $q->whereNotNull('deleted_at')->count(),
        'total'   => $q->count(),
        default   => 0,
    };
}

// ── 1. BLOAT ──────────────────────────────────────────────────────────────────

it('Fase8: soft-deleta fatos bloat (hits=0 + >30d)', function () {
    cleanupInserirFato(['created_at' => now()->subDays(35)]);
    cleanupInserirFato(['created_at' => now()->subDays(40)]);

    // Recente — não deve ser deletado
    cleanupInserirFato(['created_at' => now()->subDays(5)]);

    $this->artisan('copiloto:cleanup-memoria --business=1')->assertSuccessful();

    expect(cleanupContar('deleted'))->toBe(2)
        ->and(cleanupContar('ativos'))->toBe(1);
});

it('Fase8: core_memory=true é excluído do bloat mesmo antigo', function () {
    cleanupInserirFato([
        'created_at'  => now()->subDays(60),
        'hits_count'  => 0,
        'core_memory' => true,
    ]);

    $this->artisan('copiloto:cleanup-memoria --business=1')->assertSuccessful();

    expect(cleanupContar('ativos'))->toBe(1);
});

it('Fase8: fato com hits_count>0 não é considerado bloat', function () {
    cleanupInserirFato([
        'created_at' => now()->subDays(60),
        'hits_count' => 3,
    ]);

    $this->artisan('copiloto:cleanup-memoria --business=1')->assertSuccessful();

    expect(cleanupContar('ativos'))->toBe(1);
});

// ── 2. EXPIRADOS ──────────────────────────────────────────────────────────────

it('Fase8: soft-deleta fatos com valid_until há mais de 90 dias', function () {
    cleanupInserirFato(['valid_until' => now()->subDays(95)]);
    cleanupInserirFato(['valid_until' => now()->subDays(100)]);

    // Expirado recente — não deleta
    cleanupInserirFato(['valid_until' => now()->subDays(10)]);

    $this->artisan('copiloto:cleanup-memoria --business=1')->assertSuccessful();

    expect(cleanupContar('deleted'))->toBe(2)
        ->and(cleanupContar('ativos'))->toBe(1);
});

it('Fase8: valid_until no futuro não é deletado', function () {
    cleanupInserirFato(['valid_until' => now()->addDays(30)]);

    $this->artisan('copiloto:cleanup-memoria --business=1')->assertSuccessful();

    expect(cleanupContar('ativos'))->toBe(1);
});

// ── 3. DRY-RUN ───────────────────────────────────────────────────────────────

it('Fase8: dry-run não altera banco', function () {
    cleanupInserirFato(['created_at' => now()->subDays(40)]);   // bloat
    cleanupInserirFato(['valid_until' => now()->subDays(95)]); // expirado

    $this->artisan('copiloto:cleanup-memoria --business=1 --dry-run')->assertSuccessful();

    expect(cleanupContar('ativos'))->toBe(2)
        ->and(cleanupContar('deleted'))->toBe(0);
});

// ── 4. FILTRO POR BUSINESS ────────────────────────────────────────────────────

it('Fase8: --business limita limpeza ao business_id correto', function () {
    cleanupInserirFato(['business_id' => 1, 'created_at' => now()->subDays(40)]);
    cleanupInserirFato(['business_id' => 4, 'created_at' => now()->subDays(40), 'user_id' => 88888]);

    $this->artisan('copiloto:cleanup-memoria --business=1')->assertSuccessful();

    // biz=1: deletado
    expect(DB::table('copiloto_memoria_facts')
        ->where('user_id', 88888)->where('business_id', 1)->whereNotNull('deleted_at')->count())->toBe(1);

    // biz=4: preservado
    expect(DB::table('copiloto_memoria_facts')
        ->where('user_id', 88888)->where('business_id', 4)->whereNull('deleted_at')->count())->toBe(1);
});

// ── 5. HARD-DELETE GUARD ──────────────────────────────────────────────────────

it('Fase8: --hard sem --business retorna FAILURE e não deleta', function () {
    cleanupInserirFato(['created_at' => now()->subDays(40)]);

    $this->artisan('copiloto:cleanup-memoria --hard')->assertFailed();

    // Linha ainda existe e não está deletada
    expect(cleanupContar('ativos'))->toBe(1);
});

// ── 6. BLOAT-DAYS CUSTOMIZADO ─────────────────────────────────────────────────

it('Fase8: --bloat-days personalizado respeita limiar diferente', function () {
    cleanupInserirFato(['created_at' => now()->subDays(10)]); // velho pra threshold=7
    cleanupInserirFato(['created_at' => now()->subDays(5)]);  // novo demais

    $this->artisan('copiloto:cleanup-memoria --business=1 --bloat-days=7')->assertSuccessful();

    expect(cleanupContar('deleted'))->toBe(1)
        ->and(cleanupContar('ativos'))->toBe(1);
});
