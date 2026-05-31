<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\BankStatementLine;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * B3 — Model BankStatementLine (hardening Tier 0 da Conciliação OFX).
 *
 * A tabela fin_bank_statement_lines passou a ter Model própria (antes só
 * DB::table cru no ConciliacaoController). Esta Model adiciona o NET do global
 * scope BusinessScope (isolamento multi-tenant por business_id) + SoftDeletes +
 * casts. Estes testes provam as duas invariantes que justificam a Model:
 *
 *  (a) BusinessScope auto-filtra por business_id — uma linha de biz=99 fica
 *      INVISÍVEL quando a sessão age como outro business, MESMO numa query SEM
 *      where('business_id') explícito (o global scope segura sozinho). É a
 *      defesa que faltava: qualquer query futura que esquecer o where não vaza.
 *      Tier 0 ADR 0093 IRREVOGÁVEL.
 *
 *  (b) $fillable/$casts roundtrip — match_score volta como decimal cast
 *      (decimal:2, string "0.85") e data_movimento volta como date cast (Carbon).
 *
 * Padrão Financeiro (ConciliacaoAuditReabrirTest/CaixaControllerTest): sem
 * RefreshDatabase (UltimatePOS tem 100+ migrations + triggers), roda contra DB
 * dev real, skip gracioso quando greenfield ou módulo não instalado.
 *
 * biz=99 é o tenant-fantasma canônico dos testes (ADR 0101 — NUNCA biz=4 RotaLivre).
 *
 * Rodar local: `vendor/bin/pest Modules/Financeiro/Tests/Feature/BankStatementLineModelTest.php`
 */

/** Tenant-fantasma que nunca existe em dev — usado pra provar isolamento. */
const BSL_BIZ_FANTASMA = 99;

/** Bootstrap: exige business + user + tabela instalada, seta sessão. */
function bankStatementLineBootstrap(): User
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    if (! Schema::hasTable('fin_bank_statement_lines')) {
        test()->markTestSkipped('Tabela fin_bank_statement_lines ausente — financeiro:install pendente.');
    }

    Permission::firstOrCreate(['name' => 'financeiro.conciliacao.manage', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.conciliacao.manage')) {
        $user->givePermissionTo('financeiro.conciliacao.manage');
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'is_admin'         => true,
    ]);

    return $user;
}

/**
 * Insere uma linha RAW (DB::table) pra um business arbitrário — bypassa o
 * global scope de propósito, pra montar o cenário cross-tenant. Devolve o id.
 */
function inserirLinhaRaw(int $businessId, array $overrides = []): int
{
    return (int) DB::table('fin_bank_statement_lines')->insertGetId(array_merge([
        'business_id'    => $businessId,
        'fitid'          => 'pest-b3-'.uniqid('', true),
        'data_movimento' => '2026-05-20',
        'descricao'      => 'Linha de teste B3',
        'valor'          => -123.45,
        'tipo'           => 'debit',
        'status'         => 'pendente',
        'source_file'    => 'pest-b3.ofx',
        'created_at'     => now(),
        'updated_at'     => now(),
    ], $overrides));
}

it('(a) BusinessScope auto-filtra: linha de biz=99 é invisível pra outro tenant SEM where explícito', function () {
    $user = bankStatementLineBootstrap();
    $businessId = (int) session('user.business_id');

    // Linha plantada no tenant-fantasma biz=99 (NÃO o tenant logado).
    $lineFantasma = inserirLinhaRaw(BSL_BIZ_FANTASMA, ['fitid' => 'pest-b3-fantasma-'.uniqid('', true)]);

    try {
        // Query SEM where('business_id') — só o global scope BusinessScope segura.
        // Sessão = tenant logado (≠ 99). A linha de biz=99 NÃO pode aparecer.
        // (sem auth check de superadmin aqui: actingAs não foi chamado → can()
        //  retorna false → scope ativo, exatamente como em produção pro user comum.)
        $aindaVisivelSemWhere = BankStatementLine::where('id', $lineFantasma)->exists();
        expect($aindaVisivelSemWhere)->toBeFalse(
            'VAZAMENTO Tier 0: global scope NÃO filtrou a linha de biz=99 (ADR 0093).'
        );

        // E o count geral do tenant logado também não conta a linha-fantasma.
        $fantasmaNoTenantLogado = BankStatementLine::where('id', $lineFantasma)
            ->where('business_id', $businessId)
            ->exists();
        expect($fantasmaNoTenantLogado)->toBeFalse();

        // Sanidade: bypassando o scope (admin/auditor), a linha existe de fato —
        // prova que o sumiço acima é o scope agindo, não a linha faltando.
        $existeCruDeFato = BankStatementLine::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('id', $lineFantasma)
            ->exists();
        expect($existeCruDeFato)->toBeTrue('Setup inválido: a linha de biz=99 nem foi inserida.');
    } finally {
        // Cleanup raw (hard delete) — bypassa scope/SoftDeletes.
        DB::table('fin_bank_statement_lines')->where('id', $lineFantasma)->delete();
    }
});

it('(a2) BusinessScope deixa o tenant logado VER a própria linha (não filtra demais)', function () {
    $user = bankStatementLineBootstrap();
    $businessId = (int) session('user.business_id');

    $linePropria = inserirLinhaRaw($businessId, ['fitid' => 'pest-b3-propria-'.uniqid('', true)]);

    try {
        // Sessão = dono da linha → scope deixa enxergar SEM where explícito.
        $visivel = BankStatementLine::where('id', $linePropria)->exists();
        expect($visivel)->toBeTrue('Scope filtrou demais: tenant não enxerga a própria linha.');
    } finally {
        DB::table('fin_bank_statement_lines')->where('id', $linePropria)->delete();
    }
});

it('(b) $fillable/$casts roundtrip: match_score (decimal) e data_movimento (date) voltam tipados', function () {
    $user = bankStatementLineBootstrap();
    $businessId = (int) session('user.business_id');

    // Insere com match_score + data_movimento conhecidos.
    $lineId = inserirLinhaRaw($businessId, [
        'fitid'          => 'pest-b3-casts-'.uniqid('', true),
        'status'         => 'sugerido',
        'match_score'    => 0.85,
        'data_movimento' => '2026-05-20',
        'valor'          => -123.45,
    ]);

    try {
        $linha = BankStatementLine::where('business_id', $businessId)
            ->where('id', $lineId)
            ->first();

        expect($linha)->not->toBeNull();

        // data_movimento: cast 'date' → instância Carbon, dia exato preservado.
        expect($linha->data_movimento)->toBeInstanceOf(Carbon::class);
        expect($linha->data_movimento->toDateString())->toBe('2026-05-20');

        // match_score: cast 'decimal:2' → string normalizada "0.85" (2 casas).
        expect($linha->match_score)->toBe('0.85');

        // valor: cast 'decimal:4' → string com 4 casas "-123.4500".
        expect($linha->valor)->toBe('-123.4500');

        // conciliado_at nulo → permanece nulo (não vira Carbon de "agora").
        expect($linha->conciliado_at)->toBeNull();

        // Campos $fillable básicos chegaram intactos.
        expect($linha->status)->toBe('sugerido');
        expect($linha->tipo)->toBe('debit');
        expect($linha->descricao)->toBe('Linha de teste B3');
    } finally {
        DB::table('fin_bank_statement_lines')->where('id', $lineId)->delete();
    }
});

it('Model usa BusinessScope + SoftDeletes (mesmo contrato das demais Models do Financeiro)', function () {
    $traits = class_uses_recursive(BankStatementLine::class);

    expect($traits)->toContain(\Modules\Financeiro\Models\Concerns\BusinessScope::class);
    expect($traits)->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class);

    // Aponta pra tabela certa.
    expect((new BankStatementLine())->getTable())->toBe('fin_bank_statement_lines');
});
