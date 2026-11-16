<?php

declare(strict_types=1);
// Cobre UC-CIDX-17 (Index.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Ações da ficha do cliente que o servidor não conferia (achados de 2026-11-16, pedido do Felipe):
 *  - anexos (listar, enviar, apagar) e envio do extrato por e-mail não pediam permissão nenhuma —
 *    a tela só mostra a ficha a quem vê clientes, mas a requisição direta passava;
 *  - ativar/desativar só invertia o estado: confirmar "Desativar" reativava um cadastro que outra
 *    pessoa já tinha desativado; e id de outra empresa dava 500 em vez de 404.
 *
 * Tenant de teste 98 × 99 (ADR 0358). Nomes e e-mails fictícios.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->novoContato = function (int $businessId, int $createdBy, string $status = 'active'): int {
        return (int) DB::table('contacts')->insertGetId([
            'business_id' => $businessId, 'created_by' => $createdBy, 'type' => 'customer',
            'name' => 'Cliente Acoes '.uniqid(), 'contact_status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    };
});

function capEntrar($teste, array $permissoes): \App\User
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);

    return $user;
}

/** Requisição como a ficha React manda (XHR + JSON). */
function capXhr($teste)
{
    return $teste->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);
}

function capStatus(int $id): string
{
    return (string) DB::table('contacts')->where('id', $id)->value('contact_status');
}

test('UC-CIDX-17 anexos: sem permissão de ver clientes, listar, enviar e apagar respondem 403', function () {
    $user = capEntrar($this, ['sell.view']);
    $id = ($this->novoContato)($this->business->id, $user->id);

    capXhr($this)->get("/cliente/{$id}/anexos")->assertForbidden();
    capXhr($this)->post("/cliente/{$id}/anexos", [])->assertForbidden();
    capXhr($this)->delete("/cliente/{$id}/anexos/999999999")->assertForbidden();
});

test('UC-CIDX-17 anexos: com "Ver clientes", a lista abre e apagar anexo inexistente chega no 404', function () {
    $user = capEntrar($this, ['customer.view']);
    $id = ($this->novoContato)($this->business->id, $user->id);

    capXhr($this)->get("/cliente/{$id}/anexos")->assertOk();
    // 404 (e não 403) prova que a trava deixou passar e quem respondeu foi a busca do anexo.
    capXhr($this)->delete("/cliente/{$id}/anexos/999999999")->assertNotFound();
});

test('UC-CIDX-17 extrato por e-mail: sem permissão de ver clientes responde 403 e nada é enviado', function () {
    Notification::fake();
    $user = capEntrar($this, ['sell.view']);
    $id = ($this->novoContato)($this->business->id, $user->id);

    capXhr($this)->post('/contacts/send-ledger', [
        'contact_id' => $id, 'to_email' => 'alguem@example.com', 'ledger_format' => 'format_1',
    ])->assertForbidden();

    Notification::assertNothingSent();
});

test('UC-CIDX-17 desativar um cadastro que já está inativo mantém inativo (não reativa)', function () {
    $user = capEntrar($this, ['customer.view', 'customer.update']);
    $id = ($this->novoContato)($this->business->id, $user->id, 'inactive');

    capXhr($this)->post("/contacts/update-status/{$id}", ['status' => 'inactive'])
        ->assertOk()->assertJsonPath('success', true);

    expect(capStatus($id))->toBe('inactive');
});

test('UC-CIDX-17 reativar aplica o estado pedido, e o botão Blade sem estado segue invertendo', function () {
    $user = capEntrar($this, ['customer.view', 'customer.update']);
    $id = ($this->novoContato)($this->business->id, $user->id, 'inactive');

    capXhr($this)->post("/contacts/update-status/{$id}", ['status' => 'active'])->assertOk();
    expect(capStatus($id))->toBe('active');

    capXhr($this)->get("/contacts/update-status/{$id}")->assertOk();
    expect(capStatus($id))->toBe('inactive');
});

test('UC-CIDX-17 Tier 0 — ativar/desativar cadastro de outra empresa responde 404 e não muda nada', function () {
    $user = capEntrar($this, ['customer.view', 'customer.update']);
    $outra = $this->seededSupportClientTenant();
    $alheio = ($this->novoContato)((int) $outra->id, $user->id);

    capXhr($this)->post("/contacts/update-status/{$alheio}", ['status' => 'inactive'])->assertNotFound();

    expect(capStatus($alheio))->toBe('active');
});
