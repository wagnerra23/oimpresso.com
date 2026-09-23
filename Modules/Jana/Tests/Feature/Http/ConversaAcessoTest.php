<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-JPERM-06 — `jana.chat` autoriza conversar, não ler conversa alheia.
 *
 * ── O CONTRATO: emenda de casos do Cowork (thread 04 do playbook jana). "Trocar o id na
 * URL é o teste mais barato que existe": GET e PATCH de conversa de OUTRO usuário do MESMO
 * business voltam o MESMO status.
 *
 * ── O QUE JÁ EXISTIA: `Chat/ChatAntiHooksTier0Test` (UC-JCHAT-05) prova outro BUSINESS e
 * só o GET. Aqui o eixo é outro — mesmo business, outro usuário — e cobre o PATCH, que
 * escreve. O `business_id` sozinho não separa os dois casos; só o dono da conversa.
 *
 * TENANT: 98 (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 */
const CONVACESSO_BIZ = 98;

function convAcessoCria(int $userId, string $titulo): int
{
    return (int) DB::table('jana_conversas')->insertGetId([
        'business_id' => CONVACESSO_BIZ,
        'user_id' => $userId,
        'titulo' => $titulo,
        'status' => 'ativa',
        'iniciada_em' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function convAcessoTitulo(int $id): ?string
{
    return DB::table('jana_conversas')->where('id', $id)->value('titulo');
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }
    if (! Business::find(CONVACESSO_BIZ)) {
        $this->markTestSkipped('business_id='.CONVACESSO_BIZ.' ausente — rode o seed do pest-mysql-setup.');
    }
    $user = User::where('business_id', CONVACESSO_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id='.CONVACESSO_BIZ.'.');
    }
    // O "outro usuário" só precisa ser outro id: a conversa dele nasce em biz 98, então o
    // escopo de tenant NÃO a esconde — quem barra é o dono da conversa, que é o que se mede.
    $outro = User::where('id', '!=', $user->id)->first();
    if (! $outro) {
        $this->markTestSkipped('Precisa de um 2º usuário no banco pra ser o dono da conversa alheia.');
    }

    // Cache do Spatie sobrevive ao rollback da transação: sem limpar, uma permissão
    // criada (e revertida) por um caso anterior volta com id morto — FK/PermissionDoesNotExist
    // conforme a ordem aleatória. Medido no 1º run no CT 100.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('jana.access', 'web');
    Permission::findOrCreate('jana.chat', 'web');
    $user->syncRoles([]);
    $user->syncPermissions(['jana.access', 'jana.chat']);
    $user->forgetCachedPermissions();
    $this->user = $user;
    $this->outro = $outro;

    $this->actingAs($user);
    session([
        'user.business_id' => CONVACESSO_BIZ,
        'business' => ['id' => CONVACESSO_BIZ, 'name' => Business::find(CONVACESSO_BIZ)->name],
    ]);
});

it('UC-JPERM-06 · GET e PATCH da conversa de outro usuário do mesmo business dão o MESMO 403', function () {
    $alheia = convAcessoCria((int) $this->outro->id, 'TITULO-ORIGINAL-DO-OUTRO');

    $sGet = $this->get(route('jana.conversas.show', $alheia))->status();
    $sPatch = $this->patch(route('jana.conversas.update', $alheia), ['titulo' => 'SEQUESTRADO'])->status();

    expect($sGet)->toBe(403);
    expect($sPatch)->toBe($sGet);
    expect(convAcessoTitulo($alheia))->toBe('TITULO-ORIGINAL-DO-OUTRO');
})->group('tier0');

it('UC-JPERM-06 · anti-vácuo: a PRÓPRIA conversa aceita o mesmo PATCH', function () {
    // Sem isto, um PATCH quebrado (rota morta, 500) deixaria o título intacto e o caso de
    // cima passaria sem provar dono nenhum.
    $minha = convAcessoCria((int) $this->user->id, 'TITULO-MEU');

    $this->patch(route('jana.conversas.update', $minha), ['titulo' => 'RENOMEADO'])->assertStatus(200);
    expect(convAcessoTitulo($minha))->toBe('RENOMEADO');
})->group('tier0');
