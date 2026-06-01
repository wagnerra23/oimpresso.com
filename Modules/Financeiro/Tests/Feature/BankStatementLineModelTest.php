<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\BankStatementLine;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;

uses(Tests\TestCase::class);

/**
 * Model BankStatementLine — hardening Tier 0 da Conciliação OFX (re-impl #2045
 * sobre a estrutura de 2 origens da ADR 0236).
 *
 * A tabela `fin_bank_statement_lines` (origem OFX) passou a ter Model própria
 * (antes só `DB::table` cru no ConciliacaoController). A Model adiciona o NET do
 * global scope BusinessScope (isolamento multi-tenant por business_id) +
 * SoftDeletes + casts. Estes testes provam as invariantes que justificam a Model:
 *
 *  (a) BusinessScope auto-filtra por business_id — uma linha do tenant logado
 *      fica INVISÍVEL quando a sessão passa a agir como OUTRO business, MESMO
 *      numa query SEM where('business_id') explícito (o global scope segura
 *      sozinho). É a defesa que faltava: qualquer query futura que esquecer o
 *      where não vaza. Tier 0 ADR 0093 IRREVOGÁVEL.
 *
 *  (b) $fillable/$casts roundtrip — match_score volta como decimal cast
 *      (decimal:2, string "0.85"), valor como decimal:4 ("-123.4500") e
 *      data_movimento como date cast (Carbon).
 *
 * FK-safe (CT 100, ADR 0101): a tabela tem FK business_id → business(id). Por
 * isso TODA inserção de teste vai pro biz=1 (dogfooding, NUNCA cliente) — que
 * existe no clone anonimizado. O "outro tenant" do teste (a) é só um VALOR de
 * sessão (99 = tenant-fantasma canônico): a BusinessScopeImpl lê
 * session('user.business_id') e filtra por ele, sem precisar que esse business
 * exista em DB. Assim provamos o isolamento sem violar a FK nem tocar dado de
 * cliente.
 *
 * Padrão Financeiro (ConciliacaoMatchScoreTest/ConciliacaoAuditReabrirTest): sem
 * RefreshDatabase (UltimatePOS tem 100+ migrations + triggers), roda contra o DB
 * real (staging biz=1), skip gracioso quando schema/seed ausente.
 *
 * Rodar no CT 100: php artisan test Modules/Financeiro/Tests/Feature/BankStatementLineModelTest.php
 */

/** Tenant-fantasma — só usado como VALOR de sessão (nunca como row em DB). */
const BSL_BIZ_FANTASMA = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + Financeiro.');
    }
    if (! Schema::hasTable('fin_bank_statement_lines') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema ausente (fin_bank_statement_lines / business) — financeiro:install pendente.');
    }
});

/** Bootstrap: exige business=1 + user no biz=1, seta sessão como biz=1 (dogfooding). */
function bslBootstrap(): User
{
    $business = Business::withoutGlobalScopes()->find(1);
    if (! $business) {
        test()->markTestSkipped('Sem business_id=1 no banco (dogfooding biz).');
    }

    $user = User::withoutGlobalScopes()->where('business_id', 1)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business_id=1.');
    }

    // Só a sessão importa pro BusinessScope (sem actingAs → can('superadmin')
    // = false → scope ATIVO, igual ao user comum em produção).
    session([
        'user.business_id' => 1,
        'user.id'          => $user->id,
    ]);

    return $user;
}

/**
 * Insere uma linha RAW (DB::table, bypassa o global scope de propósito) no biz
 * informado. Sempre biz=1 nos testes (FK-safe). Devolve o id.
 */
function bslInserirLinhaRaw(int $businessId = 1, array $overrides = []): int
{
    return (int) DB::table('fin_bank_statement_lines')->insertGetId(array_merge([
        'business_id'    => $businessId,
        'fitid'          => 'pest-bsl-'.uniqid('', true),
        'data_movimento' => '2026-05-20',
        'descricao'      => 'Linha de teste BankStatementLine (re-impl #2045)',
        'valor'          => -123.45,
        'tipo'           => 'debit',
        'status'         => 'pendente',
        'source_file'    => 'pest-bsl.ofx',
        'created_at'     => now(),
        'updated_at'     => now(),
    ], $overrides));
}

it('(a) BusinessScope auto-filtra: a linha some quando a sessão age como OUTRO tenant (SEM where explícito)', function () {
    bslBootstrap(); // sessão = biz=1
    $lineId = bslInserirLinhaRaw(1, ['fitid' => 'pest-bsl-a-'.uniqid('', true)]);

    try {
        // Como dono (biz=1), o scope DEIXA enxergar SEM where('business_id') explícito.
        expect(BankStatementLine::where('id', $lineId)->exists())->toBeTrue(
            'Setup inválido: o tenant dono nem enxerga a própria linha.'
        );

        // Agora age como OUTRO tenant (99 = só valor de sessão, sem row/FK).
        // A MESMA query SEM where('business_id') agora NÃO pode achar a linha de biz=1.
        session(['user.business_id' => BSL_BIZ_FANTASMA]);
        $visivelComoOutro = BankStatementLine::where('id', $lineId)->exists();
        expect($visivelComoOutro)->toBeFalse(
            'VAZAMENTO Tier 0: global scope NÃO filtrou cross-tenant (ADR 0093).'
        );

        // Bypass admin/auditor: a linha existe de fato — prova que o sumiço acima
        // é o scope agindo, não a linha faltando.
        $existeCru = BankStatementLine::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('id', $lineId)
            ->exists();
        expect($existeCru)->toBeTrue('Setup inválido: a linha nem foi inserida.');
    } finally {
        session(['user.business_id' => 1]); // restaura contexto
        DB::table('fin_bank_statement_lines')->where('id', $lineId)->delete(); // hard delete (bypassa scope/SoftDeletes)
    }
});

it('(a2) BusinessScope deixa o tenant logado VER a própria linha (não filtra demais)', function () {
    bslBootstrap(); // biz=1
    $lineId = bslInserirLinhaRaw(1, ['fitid' => 'pest-bsl-a2-'.uniqid('', true)]);

    try {
        $visivel = BankStatementLine::where('id', $lineId)->exists();
        expect($visivel)->toBeTrue('Scope filtrou demais: tenant não enxerga a própria linha.');
    } finally {
        DB::table('fin_bank_statement_lines')->where('id', $lineId)->delete();
    }
});

it('(b) $fillable/$casts roundtrip: match_score (decimal:2), valor (decimal:4) e data_movimento (date) voltam tipados', function () {
    bslBootstrap(); // biz=1

    $lineId = bslInserirLinhaRaw(1, [
        'fitid'          => 'pest-bsl-casts-'.uniqid('', true),
        'status'         => 'sugerido',
        'match_score'    => 0.85,
        'data_movimento' => '2026-05-20',
        'valor'          => -123.45,
    ]);

    try {
        $linha = BankStatementLine::where('business_id', 1)
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
    } finally {
        DB::table('fin_bank_statement_lines')->where('id', $lineId)->delete();
    }
});

it('Model usa BusinessScope + SoftDeletes e aponta pra tabela certa (mesmo contrato das demais Models do Financeiro)', function () {
    $traits = class_uses_recursive(BankStatementLine::class);

    expect($traits)->toContain(\Modules\Financeiro\Models\Concerns\BusinessScope::class);
    expect($traits)->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new BankStatementLine())->getTable())->toBe('fin_bank_statement_lines');
});
