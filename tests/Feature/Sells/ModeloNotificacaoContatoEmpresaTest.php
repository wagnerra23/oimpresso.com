<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modal de envio do extrato (GET notification/get-template/{id}/send_ledger,
 * NotificationController@getTemplate). O modal pré-preenche e-mail e celular do contato.
 *
 * Tier 0 (ADR 0093): contato de outra empresa → 404 e nenhum dado dele na resposta (controle
 * negativo); contato da própria empresa → 200 com o e-mail dele (controle positivo, em par).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) \App\Business::where('id', '!=', $this->biz)->orderBy('id')->value('id');
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    $this->user = $this->usuarioComPermissoes([], $this->seededTenant());
});

function modeloNotifContato(int $biz, int $criador, string $email): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Modelo Notif ' . uniqid(), 'email' => $email,
        'mobile' => '11999990000', 'contact_status' => 'active', 'created_by' => $criador,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function modeloNotifGet($t, int $contato)
{
    return $t->actingAs($t->user)
        ->withSession(['user.business_id' => $t->biz, 'business.id' => $t->biz, 'user.id' => $t->user->id])
        ->get("/notification/get-template/{$contato}/send_ledger");
}

it('não abre o modal com contato de outra empresa', function () {
    $email = 'alheio-' . uniqid() . '@exemplo.test';
    $alheio = modeloNotifContato($this->outro, (int) $this->user->id, $email);

    $r = modeloNotifGet($this, $alheio);

    expect($r->status())->toBe(404);
    expect($r->getContent())->not->toContain($email);
});

it('abre o modal com contato da própria empresa (controle positivo)', function () {
    $email = 'meu-' . uniqid() . '@exemplo.test';
    $meu = modeloNotifContato($this->biz, (int) $this->user->id, $email);

    $r = modeloNotifGet($this, $meu);

    $r->assertOk();
    expect($r->getContent())->toContain($email);
});
