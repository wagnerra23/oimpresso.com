<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * `can:jana.access` no grupo /ia — a permissão que EXISTIA e não valia nada.
 *
 * ── O CONTRATO (derivado do pedido de [W], não do código) ────────────────────
 * [W] 2026-07-27: *"isso deve ser feito por permissões de acesso do usuário,
 * controlado pela spatie de permissões do nivel que o usuário tem"*.
 *
 * Estado medido ANTES deste gate: as 5 permissões da Jana estavam DECLARADAS em 3
 * lugares e aplicadas em ZERO. O grupo `/ia` não tinha `can:` nenhum; o
 * `'can' => 'jana.chat'` do topnav só ESCONDE o item do menu. Menu escondido ≠ rota
 * protegida — quem soubesse a URL entrava.
 *
 * ── O QUE ESTE TESTE PROVA (e o que ele deliberadamente NÃO promete) ─────────
 * PROVA: o nível do usuário passou a decidir o acesso a /ia.
 * NÃO PROMETE: proteção contra o DONO. O `Gate::before` de AuthServiceProvider:34-47
 * devolve `true` em qualquer ability pra quem tem `Admin#{business_id}` — o 3º caso
 * abaixo TRAVA esse limite por escrito, pra ninguém ler o gate como "agora o admin
 * também é barrado". Permissão de IA só morde FUNCIONÁRIO.
 *
 * biz=1 sempre (ADR 0101 — nunca biz de cliente real).
 */
function janaGateBootstrap(): array
{
    try {
        $business = Business::find(1) ?? Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }
    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UPos antes.');
    }

    try {
        Permission::findOrCreate('jana.access', 'web');
    } catch (\Throwable $e) {
        test()->markTestSkipped('Não foi possível garantir a permission jana.access: '.$e->getMessage());
    }

    // Usuário SEM o papel Admin#{biz} — é o único que o gate realmente morde.
    // (Pegar `User::first()` daria falso-verde: em biz=1 o primeiro costuma ser admin.)
    $user = User::where('business_id', $business->id)
        ->get()
        ->first(fn (User $u) => ! $u->hasRole('Admin#'.$business->id));

    if (! $user) {
        test()->markTestSkipped("Sem usuário NÃO-admin em business_id={$business->id} — o gate só morde não-admin.");
    }

    return [$business, $user];
}

beforeEach(function () {
    [$this->business, $this->user] = janaGateBootstrap();
    session([
        'user.business_id' => $this->business->id,
        'business' => ['id' => $this->business->id, 'name' => $this->business->name],
    ]);
});

it('MORDE: não-admin SEM jana.access leva 403 em /ia', function () {
    $this->user->revokePermissionTo('jana.access');
    $this->user->forgetCachedPermissions();

    $this->actingAs($this->user)->get('/ia')->assertStatus(403);
});

it('não-admin COM jana.access NÃO leva 403 (o gate libera quem tem o nível)', function () {
    $this->user->givePermissionTo('jana.access');
    $this->user->forgetCachedPermissions();

    // NÃO assertamos 200 de propósito: a tela pode redirecionar por outra regra de
    // sessão/negócio, e amarrar o teste a isso o tornaria frágil por motivo alheio.
    // O contrato DESTE gate é exatamente um: com a permissão, ele PARA de barrar.
    $status = $this->actingAs($this->user)->get('/ia')->status();

    expect($status)->not->toBe(403);
});

it('LIMITE HONESTO: admin passa MESMO sem a permissão (Gate::before)', function () {
    $admin = User::where('business_id', $this->business->id)
        ->get()
        ->first(fn (User $u) => $u->hasRole('Admin#'.$this->business->id));

    if (! $admin) {
        test()->markTestSkipped("Sem usuário Admin#{$this->business->id} — nada a provar aqui.");
    }

    $admin->revokePermissionTo('jana.access');
    $admin->forgetCachedPermissions();

    // Se um dia isto virar 403, o Gate::before mudou — e aí TODA permissão do ERP
    // mudou de significado junto. Este assert é a sentinela desse contrato.
    $status = $this->actingAs($admin)->get('/ia')->status();

    expect($status)->not->toBe(403);
});
