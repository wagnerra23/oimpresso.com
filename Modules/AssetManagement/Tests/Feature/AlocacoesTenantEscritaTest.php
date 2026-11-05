<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetTransaction;

uses(Tests\TestCase::class);

/**
 * Lote 0.1 da auditoria do Patrimonio (2026-11-05) — escrita de alocacao e lista.
 *
 * Achados do eixo 5: A1 (`update` aceitava bem e receptor de outra empresa, sem trava de
 * saldo) e A2 (`store` com quantidade vazia/0 passava pela trava para bem alheio; receptor
 * nunca conferido). E a lista (`baseAllocationsQuery`) juntava bem e pessoa sem empresa.
 *
 * ADR 0358: tenant 98 (dono) x 99 (adversario). biz=1 e biz=4 nunca.
 *
 * REGRA MESTRE (quantidade) — os numeros dos casos de saldo, conferidos a mao:
 *   bem com 10 · alocacao A = 4 · alocacao B = 5 → livre = 10 - 9 = 1.
 *   Editar A: pode ir ate livre + o que A ja ocupa = 1 + 4 = 5. Pedir 6 e recusado.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('asset_transactions') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Tabelas asset_transactions/business ausentes — rode migrate primeiro');
    }
});

function alocTenUsuario(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'username' => 'aloc_ten_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
}

function alocTenBem(int $businessId, int $ownerId, float $quantidade = 10): Asset
{
    return Asset::create([
        'business_id' => $businessId,
        'name' => 'ALOC-TEN bem '.uniqid(),
        'asset_code' => 'ALOC-TEN-'.uniqid(),
        'quantity' => $quantidade,
        'unit_price' => 100,
        'is_allocatable' => 1,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);
}

function alocTenTransacao(int $businessId, int $assetId, int $receiverId, float $qtd, string $tipo = 'allocate', ?int $parentId = null): AssetTransaction
{
    return AssetTransaction::create([
        'business_id' => $businessId,
        'asset_id' => $assetId,
        'transaction_type' => $tipo,
        'ref_no' => 'ALOC-TEN-'.uniqid(),
        'receiver' => $receiverId,
        'quantity' => $qtd,
        'transaction_datetime' => now()->subDay(),
        'parent_id' => $parentId,
        'created_by' => $receiverId,
    ]);
}

function alocTenComo(User $user, int $businessId)
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);

    test()->flushHeaders();

    return test()->actingAs($user)->withSession([
        'user.business_id' => $businessId,
        'user.id' => $user->id,
        'user' => ['business_id' => $businessId, 'id' => $user->id],
        'business.date_format' => 'd/m/Y',
        'business.time_format' => 24,
    ])->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'X-Inertia' => 'true']);
}

function alocTenLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);
    AssetTransaction::where('ref_no', 'like', 'ALOC-TEN-%')->whereNotNull('parent_id')->delete();
    AssetTransaction::where('ref_no', 'like', 'ALOC-TEN-%')->delete();
    Asset::where('asset_code', 'like', 'ALOC-TEN-%')->forceDelete();
    foreach (User::withTrashed()->where('username', 'like', 'aloc_ten_%')->get() as $u) {
        $u->forceDelete();
    }
}

/** Corpo do PUT como o drawer manda (mesmas strings do UC-ALOC-06). */
function alocTenCorpo(int $assetId, int $receiverId, string $qtd): array
{
    return [
        'asset_id' => (string) $assetId, 'receiver' => (string) $receiverId, 'quantity' => $qtd,
        'transaction_datetime' => '30/09/2026 14:05', 'reason' => 'Balcão',
    ];
}

it('A1: editar alocacao recusa bem e pessoa de outra empresa, e a linha fica como estava', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = alocTenUsuario($dono);
    $uAdv = alocTenUsuario($adv);

    try {
        $bem = alocTenBem($dono, $u->id);
        $bemAlheio = alocTenBem($adv, $uAdv->id);
        $aloc = alocTenTransacao($dono, $bem->id, $u->id, 2);

        alocTenComo($u, $dono)->from('/asset/allocation')
            ->put("/asset/allocation/{$aloc->id}", alocTenCorpo($bemAlheio->id, $uAdv->id, '1'))
            ->assertSessionHasErrors(['asset_id', 'receiver']);

        $aloc->refresh();
        expect((int) $aloc->asset_id)->toBe($bem->id);
        expect((int) $aloc->receiver)->toBe($u->id);
        expect((float) $aloc->quantity)->toBe(2.0);
    } finally {
        alocTenLimpar();
    }
});

it('A1: editar alocacao respeita o saldo descontando a propria alocacao (10 - 4 - 5: A vai ate 5, nao 6)', function () {
    $dono = (int) $this->seededTenant()->id;
    $u = alocTenUsuario($dono);

    try {
        $bem = alocTenBem($dono, $u->id, 10);
        $a = alocTenTransacao($dono, $bem->id, $u->id, 4);
        alocTenTransacao($dono, $bem->id, $u->id, 5);

        alocTenComo($u, $dono)->from('/asset/allocation')
            ->put("/asset/allocation/{$a->id}", alocTenCorpo($bem->id, $u->id, '6'))
            ->assertSessionHasErrors(['quantity']);
        expect((float) $a->refresh()->quantity)->toBe(4.0);

        alocTenComo($u, $dono)
            ->put("/asset/allocation/{$a->id}", alocTenCorpo($bem->id, $u->id, '5'))
            ->assertRedirect('/asset/allocation')
            ->assertSessionHasNoErrors();
        expect((float) $a->refresh()->quantity)->toBe(5.0);

        alocTenComo($u, $dono)->from('/asset/allocation')
            ->put("/asset/allocation/{$a->id}", alocTenCorpo($bem->id, $u->id, '0'))
            ->assertSessionHasErrors(['quantity']);
        expect((float) $a->refresh()->quantity)->toBe(5.0);
    } finally {
        alocTenLimpar();
    }
});

it('A1: o update de alocacao nao altera devolucao nem alocacao de outra empresa (404)', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = alocTenUsuario($dono);
    $uAdv = alocTenUsuario($adv);

    try {
        $bem = alocTenBem($dono, $u->id, 10);
        $aloc = alocTenTransacao($dono, $bem->id, $u->id, 4);
        $dev = alocTenTransacao($dono, $bem->id, $u->id, 1, 'revoke', $aloc->id);
        $alheia = alocTenTransacao($adv, alocTenBem($adv, $uAdv->id)->id, $uAdv->id, 1);

        alocTenComo($u, $dono)->put("/asset/allocation/{$dev->id}", alocTenCorpo($bem->id, $u->id, '3'))
            ->assertStatus(404);
        expect((float) $dev->refresh()->quantity)->toBe(1.0);

        alocTenComo($u, $dono)->put("/asset/allocation/{$alheia->id}", alocTenCorpo($bem->id, $u->id, '1'))
            ->assertStatus(404);
        expect((int) $alheia->refresh()->asset_id)->not->toBe($bem->id);
    } finally {
        alocTenLimpar();
    }
});

it('A2: cadastrar alocacao recusa quantidade vazia/0 com bem alheio e pessoa de outra empresa — nada nasce', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = alocTenUsuario($dono);
    $uAdv = alocTenUsuario($adv);
    $refVazia = 'ALOC-TEN-'.uniqid();
    $refPessoa = 'ALOC-TEN-'.uniqid();

    try {
        $bem = alocTenBem($dono, $u->id, 10);
        $bemAlheio = alocTenBem($adv, $uAdv->id, 10);

        // Quantidade vazia: `0 > 0` e falso, entao a trava de saldo deixava passar.
        alocTenComo($u, $dono)->from('/asset/allocation/create')
            ->post('/asset/allocation', alocTenCorpo($bemAlheio->id, $u->id, '') + ['ref_no' => $refVazia])
            ->assertSessionHasErrors(['asset_id', 'quantity']);
        expect(AssetTransaction::where('ref_no', $refVazia)->exists())->toBeFalse();

        // Bem meu, quantidade valida, pessoa de outra empresa.
        alocTenComo($u, $dono)->from('/asset/allocation/create')
            ->post('/asset/allocation', alocTenCorpo($bem->id, $uAdv->id, '1') + ['ref_no' => $refPessoa])
            ->assertSessionHasErrors(['receiver']);
        expect(AssetTransaction::where('ref_no', $refPessoa)->exists())->toBeFalse();
    } finally {
        alocTenLimpar();
    }
});

it('lista: linha com bem e pessoa de outra empresa nao mostra o nome deles', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = alocTenUsuario($dono);
    $uAdv = alocTenUsuario($adv);

    try {
        $bem = alocTenBem($dono, $u->id, 10);
        $bemAlheio = alocTenBem($adv, $uAdv->id, 10);
        $legitima = alocTenTransacao($dono, $bem->id, $u->id, 1);
        // Linha corrompida gravada direto (o estado que o `update` antigo deixava nascer).
        $corrompida = alocTenTransacao($dono, $bemAlheio->id, $uAdv->id, 1);

        $inicial = alocTenComo($u, $dono)->get('/asset/allocation?situacao=todas');
        $versao = (string) data_get($inicial->viewData('page'), 'version');

        $parcial = alocTenComo($u, $dono)->withHeaders([
            'X-Inertia-Version' => $versao,
            'X-Inertia-Partial-Component' => 'Patrimonio/Alocacoes',
            'X-Inertia-Partial-Data' => 'alocacoes',
        ])->get('/asset/allocation?situacao=todas');
        $parcial->assertStatus(200);

        $linhas = collect(data_get($parcial->json(), 'props.alocacoes.data', []));
        // Ancora positiva: a requisicao chegou e a lista tem a linha legitima.
        expect($linhas->pluck('ref_no')->all())->toContain($legitima->ref_no);
        expect($linhas->pluck('ref_no')->all())->not->toContain($corrompida->ref_no);
        expect($linhas->pluck('bem')->all())->not->toContain($bemAlheio->name);
    } finally {
        alocTenLimpar();
    }
});
