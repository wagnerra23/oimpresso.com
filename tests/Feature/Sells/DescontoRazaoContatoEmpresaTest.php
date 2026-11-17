<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desconto no razão do contato (POST /ledger-discount, LedgerDiscountController@store).
 *
 * Contrato: o `contact_id` vem do request; o desconto só é gravado se o contato for da empresa da
 * sessão. Tier 0 (ADR 0093): contato de outra empresa → nada gravado (controle negativo); contato da
 * própria empresa → desconto gravado (controle positivo, em par).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('transactions')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) \App\Business::where('id', '!=', $this->biz)->orderBy('id')->value('id');
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    // Desde 2026-11-17 o servidor exige `discount.manage` para lançar desconto (UC-CIDX-18,
    // tests/Feature/Cliente/ClienteDescontoExtratoTest.php). Sem ela os dois casos daqui
    // parariam no 403, antes da conferência de empresa que este arquivo prova.
    $this->user = $this->usuarioComPermissoes(['discount.manage'], $this->seededTenant());
});

function descRazaoContato(int $biz, int $criador): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Desc Razao ' . uniqid(),
        'contact_status' => 'active', 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function descRazaoPost($t, int $contato)
{
    return $t->actingAs($t->user)
        ->withSession([
            'user.business_id' => $t->biz, 'business.id' => $t->biz, 'user.id' => $t->user->id,
            'business.date_format' => 'Y-m-d', 'business.time_format' => 24,
        ])
        ->postJson('/ledger-discount', [
            'contact_id' => $contato, 'amount' => '10', 'date' => '2026-11-15 10:00', 'note' => 'teste',
        ]);
}

it('não grava desconto para contato de outra empresa', function () {
    $alheio = descRazaoContato($this->outro, (int) $this->user->id);

    descRazaoPost($this, $alheio)->assertOk()->assertJsonPath('success', 0);

    expect(DB::table('transactions')->where('type', 'ledger_discount')->where('contact_id', $alheio)->exists())
        ->toBeFalse();
});

it('grava desconto para contato da própria empresa (controle positivo)', function () {
    $meu = descRazaoContato($this->biz, (int) $this->user->id);

    descRazaoPost($this, $meu)->assertOk()->assertJsonPath('success', true);

    expect(DB::table('transactions')->where('type', 'ledger_discount')->where('contact_id', $meu)
        ->where('business_id', $this->biz)->exists())->toBeTrue();
});
