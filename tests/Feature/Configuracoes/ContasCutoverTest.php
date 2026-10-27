<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cutover das contas do núcleo (F5 MWART) — decisão [W] 2026-10-27, opção A.
 *
 * A lista, o cadastro e a edição de `/account/account` redirecionam para a tela única do Financeiro; transferência,
 * depósito e edição/remoção de lançamento deixaram de existir (uso zero medido em prod). Fica o livro da conta.
 * Casos: resources/js/Pages/Financeiro/ContasBancarias/Index.casos.md (UC-CTB-12/13). Tenant 98 × 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('accounts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['account.access', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

test('UC-CTB-12 lista, cadastro, edição e fluxo de caixa antigos redirecionam (301) para o Financeiro', function () {
    $this->get('/account/account')->assertStatus(301)->assertRedirect('/financeiro/contas-bancarias');
    $this->get('/account/account/create')->assertStatus(301)->assertRedirect('/financeiro/contas-bancarias');
    $this->get('/account/account/5/edit')->assertStatus(301)->assertRedirect('/financeiro/contas-bancarias');
    $this->get('/account/cash-flow')->assertStatus(301)->assertRedirect('/financeiro/fluxo?tab=realizado');
});

test('UC-CTB-13 transferência, depósito e edição de lançamento não existem mais, e nada é gravado', function () {
    $conta = DB::table('accounts')->insertGetId([
        'business_id' => $this->business->id, 'name' => 'Corte '.uniqid(), 'account_number' => 'N'.uniqid(),
        'created_by' => $this->user->id, 'is_closed' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $antes = DB::table('account_transactions')->where('account_id', $conta)->count();

    $this->get("/account/fund-transfer/{$conta}")->assertNotFound();
    $this->get("/account/deposit/{$conta}")->assertNotFound();
    $this->post('/account/deposit', ['account_id' => $conta, 'amount' => '10,00'])->assertNotFound();
    $this->post('/account/fund-transfer', ['from_account' => $conta, 'to_account' => $conta, 'amount' => '10,00'])->assertNotFound();
    $this->get('/account/edit-account-transaction/1')->assertNotFound();
    $this->get('/account/delete-account-transaction/1')->assertNotFound();
    $this->get("/account/close/{$conta}")->assertNotFound();

    expect(DB::table('account_transactions')->where('account_id', $conta)->count())->toBe($antes);
    expect((int) DB::table('accounts')->where('id', $conta)->value('is_closed'))->toBe(0);
});
