<?php

declare(strict_types=1);
// @covers-us US-CRM-074

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature — comando artisan `cliente:backfill-cpf-cnpj`.
 *
 * Slice 4 da restauração dos campos BR (Wagner aprovou 2026-05-21).
 *
 * Cobre:
 *   - Dry-run NÃO altera DB (idempotente em todos cenários)
 *   - --execute persiste apenas mod-11 válidos
 *   - Re-run --execute é idempotente (0 mudanças)
 *   - --business-id respeita Tier 0 isolation (ADR 0093)
 *   - Log JSON em storage/logs NÃO grava tax_number plain (LGPD)
 *
 * Skip-graceful em SQLite memory (CI) seguindo pattern de
 * tests/Feature/Auditoria/ContactPiiLogsActivityTest.php
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql (dev) ou CI integration job.');
    }
    if (! Schema::hasColumn('contacts', 'cpf_cnpj')) {
        $this->markTestSkipped('Migration 2026_05_21_140000 ainda não rodou neste ambiente.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    // Cria 4 contacts test: 2 válidos (1 CPF + 1 CNPJ) + 2 inválidos.
    // tax_number já preenchido, cpf_cnpj null (pré-condição do backfill).
    $now = now();
    $base = [
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'mobile' => '11999999999',
        'cpf_cnpj' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $this->cpfValidoId = DB::table('contacts')->insertGetId(array_merge($base, [
        'name' => 'PF Backfill Test Valido',
        'tax_number' => '111.444.777-35', // CPF válido mod-11 # pii-allowlist
    ]));

    $this->cnpjValidoId = DB::table('contacts')->insertGetId(array_merge($base, [
        'name' => 'PJ Backfill Test Valido',
        'tax_number' => '11.444.777/0001-61', // CNPJ válido mod-11 # pii-allowlist
    ]));

    $this->cpfInvalidoId = DB::table('contacts')->insertGetId(array_merge($base, [
        'name' => 'PF Backfill Test Invalido',
        'tax_number' => '11111111111', // CPF inválido (todos iguais)
    ]));

    $this->lixoId = DB::table('contacts')->insertGetId(array_merge($base, [
        'name' => 'Contact com tax_number lixo',
        'tax_number' => 'abc123', // Não-numérico
    ]));
});

it('dry-run nao altera DB', function () {
    $this->artisan('cliente:backfill-cpf-cnpj', ['--business-id' => $this->business->id])
        ->assertExitCode(0);

    foreach ([$this->cpfValidoId, $this->cnpjValidoId, $this->cpfInvalidoId, $this->lixoId] as $id) {
        $val = DB::table('contacts')->where('id', $id)->value('cpf_cnpj');
        expect($val)->toBeNull();
    }
});

it('execute persiste apenas mod-11 validos', function () {
    $this->artisan('cliente:backfill-cpf-cnpj', [
        '--execute' => true,
        '--business-id' => $this->business->id,
    ])->assertExitCode(0);

    // Válidos: cpf_cnpj preenchido com dígitos puros (sem máscara)
    expect(DB::table('contacts')->where('id', $this->cpfValidoId)->value('cpf_cnpj'))
        ->toBe('11144477735');
    expect(DB::table('contacts')->where('id', $this->cnpjValidoId)->value('cpf_cnpj'))
        ->toBe('11444777000161');

    // Inválidos: cpf_cnpj continua null (não copiou lixo)
    expect(DB::table('contacts')->where('id', $this->cpfInvalidoId)->value('cpf_cnpj'))
        ->toBeNull();
    expect(DB::table('contacts')->where('id', $this->lixoId)->value('cpf_cnpj'))
        ->toBeNull();
});

it('re-run execute eh idempotente', function () {
    // Primeiro run popula.
    $this->artisan('cliente:backfill-cpf-cnpj', [
        '--execute' => true,
        '--business-id' => $this->business->id,
    ])->assertExitCode(0);

    $cpfAntes = DB::table('contacts')->where('id', $this->cpfValidoId)->value('cpf_cnpj');
    $cnpjAntes = DB::table('contacts')->where('id', $this->cnpjValidoId)->value('cpf_cnpj');

    // Segundo run — não deve tocar (whereNull cpf_cnpj já filtra).
    $this->artisan('cliente:backfill-cpf-cnpj', [
        '--execute' => true,
        '--business-id' => $this->business->id,
    ])->assertExitCode(0);

    expect(DB::table('contacts')->where('id', $this->cpfValidoId)->value('cpf_cnpj'))->toBe($cpfAntes);
    expect(DB::table('contacts')->where('id', $this->cnpjValidoId)->value('cpf_cnpj'))->toBe($cnpjAntes);
});

it('respeita filtro --business-id (Tier 0)', function () {
    // Cria outro business + contact pra garantir isolamento.
    $otherBiz = \App\Business::where('id', '!=', $this->business->id)->first();
    if (! $otherBiz) {
        $this->markTestSkipped('Precisa 2+ businesses no DB pra este test.');
    }

    $otherUser = \App\User::where('business_id', $otherBiz->id)->first();
    if (! $otherUser) {
        $this->markTestSkipped('Outro business sem user.');
    }

    $otherContactId = DB::table('contacts')->insertGetId([
        'business_id' => $otherBiz->id,
        'created_by' => $otherUser->id,
        'type' => 'customer',
        'name' => 'Contact em outro business',
        'tax_number' => '111.444.777-35', // CPF válido — DEVE ser ignorado pelo filtro # pii-allowlist
        'mobile' => '11888888888',
        'cpf_cnpj' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Roda backfill SÓ pra business original.
    $this->artisan('cliente:backfill-cpf-cnpj', [
        '--execute' => true,
        '--business-id' => $this->business->id,
    ])->assertExitCode(0);

    // O contact do outro business NÃO foi tocado.
    expect(DB::table('contacts')->where('id', $otherContactId)->value('cpf_cnpj'))->toBeNull();

    // O do business correto foi tocado.
    expect(DB::table('contacts')->where('id', $this->cpfValidoId)->value('cpf_cnpj'))->toBe('11144477735');
});

it('NAO sobrescreve cpf_cnpj ja populado (Wagner guard 2026-05-21)', function () {
    // Cria contact com cpf_cnpj JA populado (cenario Wagner: v3.7 usava cpf_cnpj
    // e os dados sobreviveram ao upgrade UPOS 6.7 em prod).
    $existingValue = '99999999999'; // valor arbitrario — nao precisa ser mod-11 valido
    $contactComCpfCnpjPopuladoId = DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Contact com cpf_cnpj LEGADO populado',
        'tax_number' => '111.444.777-35', // CPF valido — TENTARIA backfill, mas tem cpf_cnpj # pii-allowlist
        'cpf_cnpj' => $existingValue,
        'mobile' => '11777777777',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Roda backfill em modo execute.
    $this->artisan('cliente:backfill-cpf-cnpj', [
        '--execute' => true,
        '--business-id' => $this->business->id,
    ])->assertExitCode(0);

    // CRITICO: o cpf_cnpj pre-existente NAO foi sobrescrito (nem com tax_number valido).
    $valorFinal = DB::table('contacts')->where('id', $contactComCpfCnpjPopuladoId)->value('cpf_cnpj');
    expect($valorFinal)->toBe($existingValue);

    // E o tax_number original tambem preservado (back-compat).
    $taxNumberFinal = DB::table('contacts')->where('id', $contactComCpfCnpjPopuladoId)->value('tax_number');
    expect($taxNumberFinal)->toBe('111.444.777-35'); // pii-allowlist (CPF/CNPJ sintético mod-11 de teste)
});

it('log JSON nao grava tax_number plain (LGPD)', function () {
    // Captura logs gerados após execute.
    $logsBefore = glob(storage_path('logs').'/backfill-cpfcnpj-*.json') ?: [];

    $this->artisan('cliente:backfill-cpf-cnpj', [
        '--execute' => true,
        '--business-id' => $this->business->id,
    ])->assertExitCode(0);

    $logsAfter = glob(storage_path('logs').'/backfill-cpfcnpj-*.json') ?: [];
    $newLogs = array_diff($logsAfter, $logsBefore);

    expect($newLogs)->not->toBeEmpty();

    $newLog = array_values($newLogs)[0];
    $content = file_get_contents($newLog);

    // PII redacted — não pode aparecer nenhum tax_number completo no log.
    expect($content)->not->toContain('11144477735');
    expect($content)->not->toContain('111.444.777-35'); // pii-allowlist (CPF/CNPJ sintético mod-11 de teste)
    expect($content)->not->toContain('11444777000161');
    expect($content)->not->toContain('11.444.777/0001-61'); // pii-allowlist (CPF/CNPJ sintético mod-11 de teste)

    // Mas tem o resumo numérico
    expect($content)->toContain('"valid_mod11": 2');

    // Cleanup
    @unlink($newLog);
});
