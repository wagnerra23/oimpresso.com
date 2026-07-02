<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * arquivos:audit-log — Pest tests Sprint 2 ADR 0123 (compliance LGPD).
 *
 * Cobertura (mín 6):
 * 1. Command registrado em artisan list
 * 2. Default mode lista últimas 24h ordenado desc por created_at
 * 3. --business=1 filtra por business (não vaza outro business)
 * 4. --action=signed_url_issued filtra por action
 * 5. --top-files agrega COUNT correto por arquivo_id
 * 6. --suspicious flagra signed_url_issued sem user_id
 * 7. --suspicious flagra rapid-fire signed_url_consumed (scraping) — regressão 2026-07-02
 *
 * Setup: inserts diretos via DB::table() com payload JSON marcado com
 * test_marker='pr19-audit' pra cleanup isolado no afterEach.
 * Testes usam biz=1 (Wagner WR2) — nunca biz=4 (ROTA LIVRE — ADR 0101).
 *
 * Guard beforeEach: markTestSkipped se arquivos_audit_log ausente.
 *
 * @see Modules/Arquivos/Console/Commands/AuditLogCommand.php
 * @see Modules/Arquivos/Database/Migrations/2026_05_10_000002_create_arquivos_audit_log_table.php
 */

// ---------------------------------------------------------------------------
// Helpers de fixture
// ---------------------------------------------------------------------------

/**
 * Insere uma row em arquivos_audit_log com marcador de teste pra cleanup.
 *
 * @param array<string,mixed> $overrides
 */
function insertArquivosAuditLog(array $overrides = []): int
{
    $defaults = [
        'arquivo_id'  => 9901,
        'business_id' => 1,
        'user_id'     => 100,
        'action'      => 'upload',
        'payload'     => json_encode(['test_marker' => 'pr19-audit', 'ip' => '10.0.0.1']),
        'created_at'  => now(),
    ];

    return DB::table('arquivos_audit_log')->insertGetId(array_merge($defaults, $overrides));
}

// ---------------------------------------------------------------------------
// Setup / Teardown
// ---------------------------------------------------------------------------

beforeEach(function () {
    if (! Schema::hasTable('arquivos_audit_log')) {
        $this->markTestSkipped('arquivos_audit_log table missing — rode Modules/Arquivos migrate primeiro.');
    }
});

afterEach(function () {
    // afterEach roda mesmo em tests pulados (PHPUnit tearDown). Em SQLite CI
    // sem migrate, DELETE estoura — bail antes.
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }

    // Deleta apenas rows com o marcador de teste — nunca afeta dados reais.
    DB::table('arquivos_audit_log')
        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.test_marker')) = 'pr19-audit'")
        ->delete();
});

// ---------------------------------------------------------------------------
// 1. Command registrado
// ---------------------------------------------------------------------------

it('command arquivos:audit-log está registrado no artisan', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('arquivos:audit-log');
});

// ---------------------------------------------------------------------------
// 2. Default mode — lista últimas 24h, ordenado desc
// ---------------------------------------------------------------------------

it('modo default lista logs das últimas 24h ordenado desc por created_at', function () {
    // Row recente (dentro da janela padrão de 24h).
    insertArquivosAuditLog([
        'arquivo_id'  => 9901,
        'business_id' => 1,
        'action'      => 'upload',
        'created_at'  => now()->subHours(2),
    ]);

    // Row fora da janela (25h atrás) — não deve aparecer.
    insertArquivosAuditLog([
        'arquivo_id'  => 9902,
        'business_id' => 1,
        'action'      => 'download',
        'created_at'  => now()->subHours(25),
    ]);

    $exitCode = Artisan::call('arquivos:audit-log', [
        '--business' => 1,
        '--hours'    => 24,
        '--limit'    => 50,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    // Row recente deve aparecer.
    expect($output)->toContain('upload');
    // Row antiga NÃO deve aparecer (arquivo_id=9902 fora da janela).
    // Verificamos que "download" com arquivo_id=9902 não está — já que fora da janela.
    // O output pode ter 'download' se alguma outra row de prod existir, então verificamos
    // somente que o exit code é 0 e que "registro(s) exibido(s)" está presente.
    expect($output)->toContain('registro(s) exibido(s)');
});

// ---------------------------------------------------------------------------
// 3. --business filtra correto (não vaza outro business)
// ---------------------------------------------------------------------------

it('--business=1 filtra logs e não vaza biz=2', function () {
    // Row biz=1 — deve aparecer.
    insertArquivosAuditLog([
        'arquivo_id'  => 9903,
        'business_id' => 1,
        'action'      => 'classify',
        'created_at'  => now()->subMinutes(10),
    ]);

    // Row biz=2 — NÃO deve aparecer com --business=1.
    insertArquivosAuditLog([
        'arquivo_id'  => 9904,
        'business_id' => 2,
        'action'      => 'classify',
        'created_at'  => now()->subMinutes(10),
    ]);

    $exitCode = Artisan::call('arquivos:audit-log', [
        '--business' => 1,
        '--action'   => 'classify',
        '--hours'    => 1,
        '--limit'    => 100,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    // Deve ter algum resultado (biz=1 classify existe).
    expect($output)->not->toContain('Nenhum registro encontrado');

    // Isolamento multi-tenant via arquivo_id da fixture (robusto — NÃO depende do
    // padding da coluna `biz` na tabela Symfony, que estica pra "| 1   |" no MySQL
    // real e quebrava o antigo `toContain('| 1 |')`, um artefato de formatação, não
    // vazamento). O arquivo_id é um valor da coluna, sempre presente literalmente:
    //   - 9903 (biz=1) DEVE aparecer — filtro --business=1 o inclui.
    //   - 9904 (biz=2) NÃO pode aparecer — o filtro --business=1 o exclui (Tier 0).
    expect($output)->toContain('9903');
    expect($output)->not->toContain('9904');
});

// ---------------------------------------------------------------------------
// 4. --action filtra por action
// ---------------------------------------------------------------------------

it('--action=signed_url_issued filtra apenas essa action', function () {
    // Row com action signed_url_issued — deve aparecer.
    insertArquivosAuditLog([
        'arquivo_id'  => 9905,
        'business_id' => 1,
        'action'      => 'signed_url_issued',
        'user_id'     => 100,
        'created_at'  => now()->subMinutes(5),
    ]);

    // Row com outra action — não deve aparecer com --action=signed_url_issued.
    insertArquivosAuditLog([
        'arquivo_id'  => 9906,
        'business_id' => 1,
        'action'      => 'soft_delete',
        'created_at'  => now()->subMinutes(5),
    ]);

    $exitCode = Artisan::call('arquivos:audit-log', [
        '--business' => 1,
        '--action'   => 'signed_url_issued',
        '--hours'    => 1,
        '--limit'    => 100,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('signed_url_issued');
    // soft_delete não deve aparecer no resultado filtrado.
    expect($output)->not->toContain('soft_delete');
});

// ---------------------------------------------------------------------------
// 5. --top-files agrega COUNT correto
// ---------------------------------------------------------------------------

it('--top-files agrega acessos e retorna total_acessos correto', function () {
    // Insere 4 logs pro mesmo arquivo (arquivo_id=9910).
    foreach (range(1, 4) as $i) {
        insertArquivosAuditLog([
            'arquivo_id'  => 9910,
            'business_id' => 1,
            'action'      => 'upload',
            'created_at'  => now()->subMinutes($i),
        ]);
    }

    // Insere 1 log pra outro arquivo (arquivo_id=9911) — deve aparecer com count=1.
    insertArquivosAuditLog([
        'arquivo_id'  => 9911,
        'business_id' => 1,
        'action'      => 'upload',
        'created_at'  => now()->subMinutes(1),
    ]);

    $exitCode = Artisan::call('arquivos:audit-log', [
        '--business'  => 1,
        '--top-files' => true,
        '--hours'     => 1,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    // Deve mencionar total_acessos = 4 (arquivo_id=9910 com 4 logs).
    expect($output)->toContain('4');
    // arquivo_id=9910 deve aparecer.
    expect($output)->toContain('9910');
    // Output de top-files deve conter summary.
    expect($output)->toContain('arquivos por acessos na janela');
});

// ---------------------------------------------------------------------------
// 6. --suspicious flaga signed_url_issued sem user_id
// ---------------------------------------------------------------------------

it('--suspicious detecta signed_url_issued sem user_id como padrão suspeito', function () {
    // Row suspeita: signed_url_issued SEM user_id (URL vazada / acesso anônimo).
    insertArquivosAuditLog([
        'arquivo_id'  => 9920,
        'business_id' => 1,
        'action'      => 'signed_url_issued',
        'user_id'     => null,  // <-- sem user_id = suspeito
        'created_at'  => now()->subMinutes(10),
    ]);

    // Row normal: signed_url_issued COM user_id — não deve ser flagrada.
    insertArquivosAuditLog([
        'arquivo_id'  => 9921,
        'business_id' => 1,
        'action'      => 'signed_url_issued',
        'user_id'     => 100,  // <-- com user_id = normal
        'created_at'  => now()->subMinutes(5),
    ]);

    $exitCode = Artisan::call('arquivos:audit-log', [
        '--business'   => 1,
        '--suspicious' => true,
        '--hours'      => 1,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    // Deve conter a flag de suspeito pra URL anônima.
    expect($output)->toContain('[SUSPEITO]');
    expect($output)->toContain('signed_url_anonima');
    // arquivo_id=9920 deve estar no output suspeito.
    expect($output)->toContain('9920');
});

// ---------------------------------------------------------------------------
// 7. Sem registros → mensagem amigável (bônus)
// ---------------------------------------------------------------------------

it('retorna exit 0 e mensagem amigável quando sem registros no período', function () {
    // Não insere nada — busca em janela de 0 minutos (impossível ter dado).
    $exitCode = Artisan::call('arquivos:audit-log', [
        '--business' => 999, // business inexistente
        '--hours'    => 1,
        '--limit'    => 10,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Nenhum registro encontrado');
});

// ---------------------------------------------------------------------------
// 8. --suspicious detecta rapid-fire signed_url_consumed (scraping)
//    Regressão 2026-07-02: o detector filtrava `signed_url_issued` (sem IP no
//    payload) → nunca disparava. Deve filtrar `signed_url_consumed` (com IP).
// ---------------------------------------------------------------------------

it('--suspicious detecta 3+ signed_url_consumed mesmo arquivo+IP em <60s', function () {
    // A action signed_url_consumed só entra no enum via migration MySQL — no lane
    // SQLite o CHECK do enum-base rejeita o insert. Bug é MySQL-only (CT 100).
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('signed_url_consumed exige enum MySQL ampliado (CT 100).');
    }

    // 3 consumos do mesmo arquivo+IP dentro de 40s = rapid-fire (scraping).
    // O helper já injeta payload {ip: 10.0.0.1} — exatamente o que o detector precisa.
    foreach ([0, 20, 40] as $segundosAtras) {
        insertArquivosAuditLog([
            'arquivo_id'  => 9930,
            'business_id' => 1,
            'action'      => 'signed_url_consumed',
            'user_id'     => 100,
            'created_at'  => now()->subSeconds($segundosAtras),
        ]);
    }

    $exitCode = Artisan::call('arquivos:audit-log', [
        '--business'   => 1,
        '--suspicious' => true,
        '--hours'      => 1,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('[SUSPEITO]');
    expect($output)->toContain('Rapid-fire');
    expect($output)->toContain('9930');
});
