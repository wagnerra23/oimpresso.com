<?php

namespace Modules\Financeiro\Tests\Feature;


/**
 * Regressao: /financeiro/contas-bancarias dava 500 em prod com user logado
 * porque o controller selecionava `accounts.account_type` — coluna removida
 * pela migration core 2019_10_18_155633_create_account_types_table.php
 * (DROP COLUMN). O FK novo é `accounts.account_type_id`.
 *
 * Esse teste hits a rota com user real e garante 200 (sem SQL exception).
 * Roda contra DB dev real (mesmo pattern do CategoriaCrudTest).
 */

uses(FinanceiroTestCase::class);

beforeEach(function () {
    $this->actAsAdmin();

});

it('UC-CTB-01 · index responde 200 e nao quebra em accounts account type', function () {
    $response = $this->inertiaGet('/financeiro/contas-bancarias');

    $response->assertStatus(200);

    $payload = json_decode($response->getContent(), true);
    $this->assertIsArray($payload);
    $this->assertArrayHasKey('props', $payload);
    $this->assertArrayHasKey('accounts', $payload['props']);
    $this->assertArrayHasKey('bancos_suportados', $payload['props']);

});

it('resposta nao inclui coluna removida account type', function () {
    // Conta própria do caso: no seed do CI a empresa não tem account e o caso pulava sempre.
    $accountId = \Illuminate\Support\Facades\DB::table('accounts')->insertGetId([
        'business_id' => $this->business->id, 'name' => 'CTB index teste', 'account_number' => 'CTB-IDX-'.uniqid(),
        'created_by' => $this->admin->id, 'is_closed' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->inertiaGet('/financeiro/contas-bancarias');
    \Illuminate\Support\Facades\DB::table('accounts')->where('id', $accountId)->delete();
    $response->assertStatus(200);

    $payload = json_decode($response->getContent(), true);
    $accounts = $payload['props']['accounts'] ?? [];

    if (empty($accounts)) {
        $this->markTestSkipped('Nenhuma account no business pra inspecionar shape.');
    }

    $first = $accounts[0];
    $this->assertArrayNotHasKey('account_type', $first, 'account_type foi removida — não deve voltar do controller.');
    $this->assertArrayHasKey('id', $first);
    $this->assertArrayHasKey('name', $first);

});
