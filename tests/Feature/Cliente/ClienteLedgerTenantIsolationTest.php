<?php

declare(strict_types=1);
// Cobre UC-CLED-06 (Ledger.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O extrato (GET /contacts/ledger) e o envio por e-mail (POST /contacts/send-ledger) só
 * abrem contato da empresa da sessão. Antes, `Contact::find($contact_id)` sem business_id
 * devolvia nome, celular e e-mail do contato de outra empresa (Tier 0, ADR 0093).
 *
 * Âncora: SDD Cliente CU-CLI-09/CU-CLI-11 (isolamento por empresa), via UC-CLED-06.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant(); // tenant de teste (ADR 0358)
    $this->user = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    config(['mwart.cliente_ledger.enabled' => true, 'mwart.cliente_ledger.business_ids' => []]);

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'business.id' => $this->business->id,
        // getLedgerDetails formata valores com num_f, que lê a moeda da sessão (sem ela: 500).
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    $this->novoContato = function (int $businessId, int $createdBy, string $nome): int {
        return (int) DB::table('contacts')->insertGetId([
            'business_id' => $businessId,
            'created_by' => $createdBy, // FK NOT NULL para users
            'type' => 'customer',
            'name' => $nome,
            'mobile' => '48999990000',
            'contact_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };
});

test('UC-CLED-06 — extrato do próprio contato abre; o de outra empresa responde 404', function () {
    $outra = $this->seededSupportClientTenant(); // adversário fictício 99 (ADR 0358), nunca biz=4

    $meu = ($this->novoContato)($this->business->id, $this->user->id, 'Cliente Proprio Ledger '.uniqid());
    $nomeAlheio = 'Cliente Alheio Ledger '.uniqid();
    $alheio = ($this->novoContato)(
        (int) $outra->id,
        \App\User::factory()->create(['business_id' => $outra->id])->id,
        $nomeAlheio
    );

    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia];

    // Anti-vácuo: o contato da própria empresa abre o extrato React. Sem isto, um 404
    // geral (rota quebrada, permissão) passaria pelo assert de isolamento abaixo.
    $proprio = $this->withHeaders($headers)->get('/contacts/ledger?contact_id='.$meu);
    $proprio->assertStatus(200);
    expect(json_decode($proprio->getContent(), true)['props']['contact']['id'] ?? null)->toBe($meu);

    $cruzado = $this->withHeaders($headers)->get('/contacts/ledger?contact_id='.$alheio);
    $cruzado->assertStatus(404);
    expect($cruzado->getContent())->not->toContain($nomeAlheio);
});

test('UC-CLED-06 — envio do extrato por e-mail recusa contato de outra empresa', function () {
    $outra = $this->seededSupportClientTenant(); // adversário fictício 99 (ADR 0358), nunca biz=4

    $alheio = ($this->novoContato)(
        (int) $outra->id,
        \App\User::factory()->create(['business_id' => $outra->id])->id,
        'Cliente Alheio Envio '.uniqid()
    );

    \Illuminate\Support\Facades\Notification::fake();

    $payload = fn (int $id) => [
        'contact_id' => $id,
        'to_email' => 'teste@example.com',
        'subject' => 'Extrato',
        'email_body' => 'Corpo',
        'ledger_format' => 'format_1',
    ];

    // Anti-vácuo: com contato da própria empresa a rota responde (envia ou cai no catch
    // com success=0), nunca 404 — prova que o 404 abaixo vem da trava, não da rota.
    $meu = ($this->novoContato)($this->business->id, $this->user->id, 'Cliente Proprio Envio '.uniqid());
    expect($this->post('/contacts/send-ledger', $payload($meu))->status())->not->toBe(404);
    \Illuminate\Support\Facades\Notification::fake(); // zera o que o envio próprio registrou

    $this->post('/contacts/send-ledger', $payload($alheio))->assertStatus(404);

    \Illuminate\Support\Facades\Notification::assertNothingSent();
});
