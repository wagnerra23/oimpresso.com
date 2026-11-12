<?php

declare(strict_types=1);
// Cobre UC-CIDX-12 (Index.casos.md) - G-2 rastreabilidade caso-teste.

use App\Notifications\CustomerNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Envio do extrato por e-mail pela aba Extrato da ficha React (`_show/LedgerTab`, também usada na
 * gaveta da lista de clientes): a tela manda só o e-mail, o formato e o período.
 *
 * Antes de 2026-11-12 a tela mandava `email`/`format`, nomes que `ContactController::sendLedger`
 * não lê; o método caía no erro, respondia 200 com `success: 0` e a tela dizia "Extrato enviado".
 * Agora a tela manda `to_email`/`ledger_format`, e o servidor completa assunto e texto com o
 * modelo "Enviar razão" da empresa — o que o modal Blade já fazia ao abrir.
 *
 * Tenant de teste 98 (ADR 0358). Nomes e e-mails fictícios.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts') || ! Schema::hasTable('notification_templates')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'business.id' => $this->business->id,
        // getLedgerDetails formata com num_f, que lê a moeda da sessão.
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
        // CustomerNotification configura o e-mail no construtor e lê estas chaves.
        'business.email_settings' => [
            'mail_driver' => 'log', 'mail_host' => 'localhost', 'mail_port' => 25, 'mail_username' => '',
            'mail_password' => '', 'mail_encryption' => '', 'mail_from_address' => 'extrato@example.com',
            'mail_from_name' => 'Teste',
        ],
    ]);

    $this->contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id, 'created_by' => $this->user->id, 'type' => 'customer',
        'name' => 'Cliente Extrato Email '.uniqid(), 'contact_status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->assunto = 'ZQ Extrato '.uniqid();
    DB::table('notification_templates')->where('business_id', $this->business->id)
        ->where('template_for', 'send_ledger')->delete();
    DB::table('notification_templates')->insert([
        'business_id' => $this->business->id, 'template_for' => 'send_ledger',
        'subject' => $this->assunto, 'email_body' => '<p>Segue o extrato.</p>',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Notification::fake();
});

/** POST como a aba Extrato manda (FormData, XHR, JSON). */
function cxeEnviar($teste, array $campos)
{
    return $teste->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post('/contacts/send-ledger', $campos);
}

test('UC-CIDX-12 só com o e-mail, o extrato sai com o assunto e o texto do modelo da empresa', function () {
    $r = cxeEnviar($this, ['contact_id' => $this->contato, 'to_email' => 'cliente@example.com', 'ledger_format' => 'format_1']);

    $r->assertOk();
    expect($r->json('success'))->toBe(1);

    Notification::assertSentOnDemand(CustomerNotification::class, function ($notificacao, $canais, AnonymousNotifiable $destino) {
        $mail = $notificacao->toMail($destino);

        return in_array('cliente@example.com', (array) $destino->routes['mail'], true)
            && $mail->subject === $this->assunto;
    });
});

test('UC-CIDX-12 sem e-mail não envia e diz por quê', function () {
    $r = cxeEnviar($this, ['contact_id' => $this->contato, 'ledger_format' => 'format_1']);

    $r->assertOk();
    expect($r->json('success'))->toBe(0);
    expect((string) $r->json('msg'))->toContain('e-mail');
    Notification::assertNothingSent();
});

test('UC-CIDX-12 os nomes antigos que a tela mandava (email/format) não enviam e não dizem que enviaram', function () {
    // Controle do defeito: era isto que a tela mandava. Agora o servidor responde a recusa,
    // e a tela (LedgerTab) mostra a mensagem em vez de "Extrato enviado".
    $r = cxeEnviar($this, ['contact_id' => $this->contato, 'email' => 'cliente@example.com', 'format' => 'format_1']);

    expect($r->json('success'))->toBe(0);
    Notification::assertNothingSent();
});
