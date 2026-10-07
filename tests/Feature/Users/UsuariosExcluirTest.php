<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/01 — o pedido de excluir que a tela `Usuarios/Index` manda.
 *
 * A Page faz POST com `_method=DELETE` e `X-Requested-With` (o destroy() só responde a AJAX),
 * em vez do verbo DELETE da Blade. Este arquivo prova que esse corpo chega ao mesmo destroy().
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Nunca biz=4.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('users', 'is_cmmsn_agnt')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['user.view', 'user.delete'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

test('UC-USUA-04 Tier 0 — o pedido da tela exclui o usuário do negócio e não o de outra empresa', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = \App\User::factory()->create(['business_id' => $this->business->id]);
    $alheio = \App\User::factory()->create(['business_id' => $outro->id]);
    $h = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    $r = $this->withHeaders($h)->post("/users/{$meu->id}", ['_method' => 'DELETE']);
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect(\App\User::withTrashed()->find($meu->id)->trashed())->toBeTrue();

    $r = $this->withHeaders($h)->post("/users/{$alheio->id}", ['_method' => 'DELETE']);
    expect($r->json('success'))->toBeFalse();
    expect(\App\User::withTrashed()->find($alheio->id)->trashed())->toBeFalse();
});
