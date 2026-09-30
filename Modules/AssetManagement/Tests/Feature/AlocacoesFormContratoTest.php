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
 * Contrato da ESCRITA da tela Patrimonio/Alocacoes — thread 18 ([W] 2026-09-30, ADR 0414).
 *
 * REGRA MESTRE (quantidade): dupla prova por dois caminhos independentes.
 *   • Caminho 1: `tests/js/patrimonio-alocacoes-envio.test.tsx` fixa as STRINGS que o drawer
 *     monta (`_alocacoes/envio.ts`).
 *   • Caminho 2 (ESTE): posta essas MESMAS strings nos controllers reais e le o BANCO. Os
 *     literais sao repetidos de proposito, nao importados.
 *
 * Tier 0: `asset_transactions` NAO tem global scope. Cada cenario tem o adversario 99
 * plantado — alocacao, devolucao e bem de outra empresa nao podem ser lidos nem escritos.
 * ADR 0358: tenant 98 (dono) x 99 (adversario). biz=1 e biz=4 nunca.
 *
 * @see resources/js/Pages/Patrimonio/Alocacoes.casos.md (UC-ALOC-06..08)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('asset_transactions') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Tabelas asset_transactions/business ausentes — rode migrate primeiro');
    }
});

function alocFormUsuario(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'username' => 'aloc_form_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
}

function alocFormBem(int $businessId, int $ownerId, float $quantidade = 10): Asset
{
    return Asset::create([
        'business_id' => $businessId,
        'name' => 'ALOC-FORM bem '.uniqid(),
        'asset_code' => 'ALOC-FORM-'.uniqid(),
        'quantity' => $quantidade,
        'unit_price' => 100,
        'is_allocatable' => 1,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);
}

function alocFormTransacao(int $businessId, int $assetId, int $userId, string $tipo, float $qtd, ?int $parentId = null): AssetTransaction
{
    return AssetTransaction::create([
        'business_id' => $businessId,
        'asset_id' => $assetId,
        'transaction_type' => $tipo,
        'ref_no' => 'ALOC-FORM-'.uniqid(),
        'receiver' => $userId,
        'quantity' => $qtd,
        'transaction_datetime' => now()->subDay(),
        'parent_id' => $parentId,
        'created_by' => $userId,
    ]);
}

/** A assinatura do modulo e o unico gate destes controllers — sem neutraliza-la, tudo e 403. */
function alocFormAssinatura(): void
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);
}

/**
 * Cliente como o BROWSER manda: o Inertia envia `X-Requested-With` SEMPRE junto com `X-Inertia`
 * (@inertiajs/core `getHeaders()`). E exatamente por isso o ramo `ajax()` antigo nao podia
 * conviver com a tela — e o que estes cenarios reproduzem.
 */
function alocFormComo(User $user, int $businessId, bool $inertia = false)
{
    test()->flushHeaders();
    $req = test()->actingAs($user)->withSession([
        'user.business_id' => $businessId,
        'user.id' => $user->id,
        'user' => ['business_id' => $businessId, 'id' => $user->id],
        // `uf_date` le daqui. O drawer converte a data pra este formato antes do POST.
        'business.date_format' => 'd/m/Y',
        'business.time_format' => 24,
    ]);

    // `X-Requested-With` SEMPRE — inclusive nos GET que leem a root view. E o header que fazia
    // o `create`/`edit` antigo cair no fragmento de modal; sem ele o teste mediria uma
    // requisicao que o browser nunca manda (a cegueira do PR #7047).
    $req = $req->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);

    return $inertia ? $req->withHeaders(['X-Inertia' => 'true']) : $req;
}

/** `try/finally` e nao `afterEach` — mesma razao medida no AlocacoesContratoTest. */
function alocFormLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);
    AssetTransaction::where('ref_no', 'like', 'ALOC-FORM-%')->whereNotNull('parent_id')->delete();
    AssetTransaction::where('ref_no', 'like', 'ALOC-FORM-%')->delete();
    Asset::where('asset_code', 'like', 'ALOC-FORM-%')->forceDelete();
    foreach (User::withTrashed()->where('username', 'like', 'aloc_form_%')->get() as $u) {
        $u->forceDelete();
    }
}

it('UC-ALOC-06: /asset/allocation/create devolve a Page com o drawer de alocar, so com bens e pessoas do business', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = alocFormUsuario($dono);
    $uAdv = alocFormUsuario($adv);

    try {
        alocFormAssinatura();
        $meu = alocFormBem($dono, $u->id, 10);
        $alheio = alocFormBem($adv, $uAdv->id, 10);

        // Visita INERTIA (com X-Requested-With): antes caia no fragmento de modal.
        $page = alocFormComo($u, $dono)->get('/asset/allocation/create?asset_id='.$meu->id);
        $page->assertStatus(200)->assertInertia(fn ($p) => $p
            ->component('Patrimonio/Alocacoes')
            ->where('formulario.modo', 'alocar')
            ->where('formulario.asset_id', $meu->id));

        $form = $page->viewData('page')['props']['formulario'];
        $ids = collect($form['bens'])->pluck('id')->all();
        expect($ids)->toContain($meu->id);
        expect($ids)->not->toContain($alheio->id);
        $linha = collect($form['bens'])->firstWhere('id', $meu->id);
        expect($linha['saldo'])->toBe(10.0);
        expect(collect($form['pessoas'])->pluck('id')->all())->not->toContain($uAdv->id);
    } finally {
        alocFormLimpar();
    }
});

it('UC-ALOC-06: o store() grava a quantidade e a data que o drawer posta (caminho 2 da dupla prova)', function () {
    $dono = (int) $this->seededTenant()->id;
    $u = alocFormUsuario($dono);
    $ref = 'ALOC-FORM-'.uniqid();

    try {
        alocFormAssinatura();
        $bem = alocFormBem($dono, $u->id, 10);

        // AS MESMAS strings do vitest UC-ALOC-06 (quantidade 2,5 · 30/09/2026 14:05 · 15/10/2026).
        alocFormComo($u, $dono, true)->post('/asset/allocation', [
            'asset_id' => (string) $bem->id, 'receiver' => (string) $u->id, 'quantity' => '2,5',
            'transaction_datetime' => '30/09/2026 14:05', 'allocated_upto' => '15/10/2026',
            'reason' => 'Balcão', 'ref_no' => $ref,
        ])->assertRedirect('/asset/allocation');

        $gravada = AssetTransaction::where('ref_no', $ref)->first();
        expect($gravada)->not->toBeNull();
        expect((int) $gravada->business_id)->toBe($dono);
        expect($gravada->transaction_type)->toBe('allocate');
        expect((string) $gravada->quantity)->toBe('2.5000');
        expect((string) $gravada->transaction_datetime)->toStartWith('2026-09-30 14:05');
        expect((string) $gravada->allocated_upto)->toStartWith('2026-10-15');
    } finally {
        alocFormLimpar();
    }
});

it('UC-ALOC-06: saldo insuficiente volta como erro do campo quantity e nada e gravado', function () {
    $dono = (int) $this->seededTenant()->id;
    $u = alocFormUsuario($dono);
    $ref = 'ALOC-FORM-'.uniqid();

    try {
        alocFormAssinatura();
        $bem = alocFormBem($dono, $u->id, 10);

        alocFormComo($u, $dono, true)
            ->from('/asset/allocation/create')
            ->post('/asset/allocation', [
                'asset_id' => (string) $bem->id, 'receiver' => (string) $u->id, 'quantity' => '11',
                'transaction_datetime' => '30/09/2026 14:05', 'ref_no' => $ref,
            ])
            ->assertRedirect('/asset/allocation/create')
            ->assertSessionHasErrors(['quantity']);

        expect(AssetTransaction::where('ref_no', $ref)->exists())->toBeFalse();
    } finally {
        alocFormLimpar();
    }
});

it('UC-ALOC-07: editar abre o drawer so para alocacao do business — de outra empresa, ou devolucao, da 404', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = alocFormUsuario($dono);
    $uAdv = alocFormUsuario($adv);

    try {
        alocFormAssinatura();
        $bem = alocFormBem($dono, $u->id, 10);
        $aloc = alocFormTransacao($dono, $bem->id, $u->id, 'allocate', 3);
        $dev = alocFormTransacao($dono, $bem->id, $u->id, 'revoke', 1, $aloc->id);
        $alheia = alocFormTransacao($adv, alocFormBem($adv, $uAdv->id)->id, $uAdv->id, 'allocate', 1);

        alocFormComo($u, $dono)->get("/asset/allocation/{$aloc->id}/edit")
            ->assertStatus(200)
            ->assertInertia(fn ($p) => $p
                ->where('formulario.modo', 'editar')
                ->where('formulario.alocacao.id', $aloc->id)
                // Por VALOR: no payload JSON `3.0` chega como `3` (int) — `where` compara com ===.
                ->where('formulario.alocacao.quantidade', fn ($v) => (float) $v === 3.0));

        alocFormComo($u, $dono)->get("/asset/allocation/{$alheia->id}/edit")->assertStatus(404);
        alocFormComo($u, $dono)->get("/asset/allocation/{$dev->id}/edit")->assertStatus(404);
    } finally {
        alocFormLimpar();
    }
});

it('UC-ALOC-08: o drawer de devolucao lista e soma so devolucoes do business (Tier 0 na DEVOLUCAO)', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = alocFormUsuario($dono);
    $uAdv = alocFormUsuario($adv);

    try {
        alocFormAssinatura();
        $bem = alocFormBem($dono, $u->id, 10);
        $aloc = alocFormTransacao($dono, $bem->id, $u->id, 'allocate', 4);
        $minha = alocFormTransacao($dono, $bem->id, $u->id, 'revoke', 1, $aloc->id);
        // Linha filha de OUTRA empresa pendurada na minha alocacao — a pre-condicao do
        // residuo que `_saida-16b` manda cobrar. Nao pode entrar na lista nem na soma.
        $intrusa = alocFormTransacao($adv, $bem->id, $uAdv->id, 'revoke', 2, $aloc->id);

        $page = alocFormComo($u, $dono)->get('/asset/revocation/create?id='.$aloc->id);
        $page->assertStatus(200)->assertInertia(fn ($p) => $p
            ->component('Patrimonio/Alocacoes')
            ->where('formulario.modo', 'devolver')
            // Por VALOR, pelo mesmo motivo do UC-ALOC-07: o JSON devolve `1.0` como `1`.
            ->where('formulario.alocacao.devolvido', fn ($v) => (float) $v === 1.0)
            ->where('formulario.alocacao.restante', fn ($v) => (float) $v === 3.0));

        $ids = collect($page->viewData('page')['props']['formulario']['devolucoes'])->pluck('id')->all();
        expect($ids)->toBe([$minha->id]);
        expect($ids)->not->toContain($intrusa->id);
    } finally {
        alocFormLimpar();
    }
});

it('UC-ALOC-08: o store() de devolucao grava a quantidade postada, com o bem DA ALOCACAO, e recusa passar do restante', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = alocFormUsuario($dono);
    $uAdv = alocFormUsuario($adv);
    $ref = 'ALOC-FORM-'.uniqid();
    $refDemais = 'ALOC-FORM-'.uniqid();
    $refAlheia = 'ALOC-FORM-'.uniqid();

    try {
        alocFormAssinatura();
        $bem = alocFormBem($dono, $u->id, 10);
        $outroBem = alocFormBem($dono, $u->id, 10);
        $aloc = alocFormTransacao($dono, $bem->id, $u->id, 'allocate', 4);
        $alheia = alocFormTransacao($adv, alocFormBem($adv, $uAdv->id)->id, $uAdv->id, 'allocate', 4);

        // AS MESMAS strings do vitest UC-ALOC-06 (1,5 · 30/09/2026 09:00). `asset_id` forjado
        // no POST: o servidor tem de ignora-lo e usar o bem da alocacao.
        alocFormComo($u, $dono, true)->post('/asset/revocation', [
            'parent_id' => (string) $aloc->id, 'asset_id' => (string) $outroBem->id, 'quantity' => '1,5',
            'transaction_datetime' => '30/09/2026 09:00', 'reason' => 'Fim', 'ref_no' => $ref,
        ])->assertRedirect('/asset/allocation');

        $dev = AssetTransaction::where('ref_no', $ref)->first();
        expect($dev)->not->toBeNull();
        expect((int) $dev->business_id)->toBe($dono);
        expect($dev->transaction_type)->toBe('revoke');
        expect((int) $dev->parent_id)->toBe($aloc->id);
        expect((int) $dev->asset_id)->toBe($bem->id);
        expect((string) $dev->quantity)->toBe('1.5000');
        expect((string) $dev->transaction_datetime)->toStartWith('2026-09-30 09:00');

        // Restante agora e 2,5: devolver 3 e recusado no campo, e nada nasce.
        alocFormComo($u, $dono, true)->from('/asset/revocation/create?id='.$aloc->id)->post('/asset/revocation', [
            'parent_id' => (string) $aloc->id, 'quantity' => '3',
            'transaction_datetime' => '30/09/2026 10:00', 'ref_no' => $refDemais,
        ])->assertSessionHasErrors(['quantity']);
        expect(AssetTransaction::where('ref_no', $refDemais)->exists())->toBeFalse();

        // Pai de outra empresa: 404, nada nasce.
        alocFormComo($u, $dono, true)->post('/asset/revocation', [
            'parent_id' => (string) $alheia->id, 'quantity' => '1',
            'transaction_datetime' => '30/09/2026 10:00', 'ref_no' => $refAlheia,
        ])->assertStatus(404);
        expect(AssetTransaction::where('ref_no', $refAlheia)->exists())->toBeFalse();
    } finally {
        alocFormLimpar();
    }
});

it('UC-ALOC-08: Excluir devolucao pelo drawer apaga so devolucao do business — nunca uma alocacao', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = alocFormUsuario($dono);
    $uAdv = alocFormUsuario($adv);

    try {
        alocFormAssinatura();
        $bem = alocFormBem($dono, $u->id, 10);
        $aloc = alocFormTransacao($dono, $bem->id, $u->id, 'allocate', 4);
        $dev = alocFormTransacao($dono, $bem->id, $u->id, 'revoke', 1, $aloc->id);
        $alheiaAloc = alocFormTransacao($adv, alocFormBem($adv, $uAdv->id)->id, $uAdv->id, 'allocate', 4);
        $alheiaDev = alocFormTransacao($adv, $alheiaAloc->asset_id, $uAdv->id, 'revoke', 1, $alheiaAloc->id);

        alocFormComo($u, $dono, true)->from('/asset/revocation/create?id='.$aloc->id)
            ->delete("/asset/revocation/{$dev->id}")
            ->assertRedirect('/asset/revocation/create?id='.$aloc->id);
        expect(AssetTransaction::whereKey($dev->id)->exists())->toBeFalse();

        // Id de ALOCACAO no endpoint de devolucao: antes apagava; agora 404 e ela fica.
        alocFormComo($u, $dono, true)->delete("/asset/revocation/{$aloc->id}")->assertStatus(404);
        expect(AssetTransaction::whereKey($aloc->id)->exists())->toBeTrue();

        // Devolucao de outra empresa: 404 e ela fica.
        alocFormComo($u, $dono, true)->delete("/asset/revocation/{$alheiaDev->id}")->assertStatus(404);
        expect(AssetTransaction::whereKey($alheiaDev->id)->exists())->toBeTrue();
    } finally {
        alocFormLimpar();
    }
});
