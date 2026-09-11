<?php
// tests/Feature/NotificationTemplateTest.php
// Testes mínimos da tela Modelos de notificação (/notification-templates).
// Cobre UC-NOT-01..24 do cowork-inbox/NOTIFICACOES-F1-2026-08-19.md e os patches P1–P4.
// Pest. Ajustar os helpers de tenant para os do projeto (createBusiness/actingAsAdmin do TestCase).

use App\NotificationTemplate;
use App\User;

beforeEach(function () {
    [$this->business, $this->admin] = criarNegocioComAdmin();       // helper do projeto
    $this->admin->givePermissionTo('send_notification');
    entrarComoNegocio($this->admin, $this->business->id);            // seta session user.business_id
});

// ── Permissão (UC-NOT-01 · teste 1 e 7) ────────────────────────────────────────
it('nega o índice sem a permissão send_notification', function () {
    $semPerm = User::factory()->create(['business_id' => $this->business->id]);
    entrarComoNegocio($semPerm, $this->business->id);

    $this->get(action([\App\Http\Controllers\NotificationTemplateController::class, 'index']))
        ->assertForbidden();
});

it('nega o store sem a permissão send_notification', function () {
    $semPerm = User::factory()->create(['business_id' => $this->business->id]);
    entrarComoNegocio($semPerm, $this->business->id);

    $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
        'template_data' => ['new_sale' => ['subject' => 'x']],
    ])->assertForbidden();
});

// ── Índice (UC-NOT-02 · teste 2) ───────────────────────────────────────────────
it('entrega os 3 grupos com as 9 chaves de cada modelo', function () {
    $resposta = $this->get(action([\App\Http\Controllers\NotificationTemplateController::class, 'index']));

    $resposta->assertOk()
        ->assertViewHas('general_notifications')
        ->assertViewHas('customer_notifications')
        ->assertViewHas('supplier_notifications');

    $cliente = $resposta->viewData('customer_notifications');
    expect($cliente)->toHaveKeys(['new_sale', 'payment_received', 'payment_reminder', 'new_quotation']);
    expect($cliente['new_sale'])->toHaveKeys([
        'name', 'extra_tags', 'subject', 'email_body', 'sms_body', 'whatsapp_text',
        'auto_send', 'auto_send_sms', 'auto_send_wa_notif', 'cc', 'bcc',
    ]);
});

// ── Gravação (UC-NOT-04/05 · testes 3, 4, 6) ───────────────────────────────────
it('cria o registro quando o modelo ainda não existe', function () {
    NotificationTemplate::where('business_id', $this->business->id)->delete();

    $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
        'template_data' => ['new_sale' => campos(['subject' => 'Obrigado — {business_name}'])],
    ])->assertRedirect();

    expect(NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'new_sale')->value('subject'))->toBe('Obrigado — {business_name}');
});

it('atualiza sem duplicar a linha (unicidade business_id + template_for)', function () {
    foreach (['Um', 'Dois'] as $assunto) {
        $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
            'template_data' => ['new_sale' => campos(['subject' => $assunto])],
        ]);
    }

    expect(NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'new_sale')->count())->toBe(1);
});

it('grava vários modelos num único POST', function () {
    $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
        'template_data' => [
            'new_sale' => campos(['subject' => 'A']),
            'payment_received' => campos(['subject' => 'B']),
            'items_pending' => campos(['subject' => 'C']),
        ],
    ])->assertRedirect();

    expect(NotificationTemplate::where('business_id', $this->business->id)
        ->whereIn('template_for', ['new_sale', 'payment_received', 'items_pending'])
        ->pluck('subject', 'template_for')->all())
        ->toBe(['new_sale' => 'A', 'payment_received' => 'B', 'items_pending' => 'C']);
});

// ── Checkbox ausente = 0 (UC-NOT-17 · teste 5 · R2 do charter) ─────────────────
it('zera auto_send quando a checkbox não vem no POST', function () {
    NotificationTemplate::updateOrCreate(
        ['business_id' => $this->business->id, 'template_for' => 'payment_reminder'],
        ['auto_send' => 1, 'auto_send_sms' => 1, 'auto_send_wa_notif' => 1],
    );

    $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
        'template_data' => ['payment_reminder' => campos()], // sem nenhuma auto_send
    ]);

    $linha = NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'payment_reminder')->first();

    expect([$linha->auto_send, $linha->auto_send_sms, $linha->auto_send_wa_notif])->toBe([0, 0, 0]);
});

// ── Multi-tenant (teste 8) ─────────────────────────────────────────────────────
it('não deixa um negócio alterar o modelo de outro', function () {
    [$outro, ] = criarNegocioComAdmin();
    $antes = NotificationTemplate::updateOrCreate(
        ['business_id' => $outro->id, 'template_for' => 'new_sale'],
        ['subject' => 'Do outro negócio'],
    );

    $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
        'template_data' => ['new_sale' => campos(['subject' => 'Invadido'])],
    ]);

    expect($antes->fresh()->subject)->toBe('Do outro negócio');
});

// ── P2: whitelist de template_for (achado A3) ──────────────────────────────────
it('ignora chave de modelo desconhecida no POST', function () {
    $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
        'template_data' => ['modelo_inventado' => campos(['subject' => 'x'])],
    ])->assertRedirect();

    expect(NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'modelo_inventado')->exists())->toBeFalse();
});

// ── P3: validação de cc/bcc (UC-NOT-24 · teste 15) ─────────────────────────────
it('recusa cc inválido', function () {
    $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
        'template_data' => ['new_sale' => campos(['cc' => 'não-é-email'])],
    ])->assertSessionHasErrors('template_data.new_sale.cc');
});

// ── P1: seed PT-BR (teste 9) ───────────────────────────────────────────────────
it('semeia os modelos em português para negócio novo', function () {
    [$novo, ] = criarNegocioComAdmin(); // dispara BusinessUtil::createDefaultNotificationTemplates

    $venda = NotificationTemplate::where('business_id', $novo->id)->where('template_for', 'new_sale')->first();

    expect($venda->subject)->toContain('Obrigado pela compra');
    expect($venda->email_body)->toContain('Olá {contact_name}');
    expect($venda->email_body)->not->toContain('Dear ');
    expect($venda->whatsapp_text)->not->toBeEmpty(); // o seed antigo não preenchia
});

it('a migration de tradução preserva o modelo que o negócio editou', function () {
    $linha = NotificationTemplate::updateOrCreate(
        ['business_id' => $this->business->id, 'template_for' => 'new_sale'],
        ['subject' => 'Meu assunto próprio', 'email_body' => '<p>Texto meu</p>'],
    );

    (new \CreateTraduzirNotificationTemplatesPtBr)->up(); // ou Artisan::call('migrate') no ambiente do teste

    expect($linha->fresh()->subject)->toBe('Meu assunto próprio');
    expect($linha->fresh()->email_body)->toBe('<p>Texto meu</p>');
});

// ── Envio (testes 10, 11, 13) ──────────────────────────────────────────────────
it('não envia pelo canal cujo corpo está vazio', function () {
    NotificationTemplate::updateOrCreate(
        ['business_id' => $this->business->id, 'template_for' => 'new_sale'],
        ['email_body' => '', 'sms_body' => 'tem sms', 'whatsapp_text' => ''],
    );

    // Espera-se: nenhum Mail::send disparado; o SMS sim.
    Mail::fake();
    app(\App\Utils\NotificationUtil::class)->autoSendNotification($this->business->id, 'new_sale', vendaDeExemplo(), contatoDeExemplo());
    Mail::assertNothingSent();
});

it('o lembrete automático só pega modelos com auto_send ligado', function () {
    NotificationTemplate::updateOrCreate(
        ['business_id' => $this->business->id, 'template_for' => 'payment_reminder'],
        ['auto_send' => 0],
    );

    Mail::fake();
    $this->artisan('autosend:paymentreminder')->assertExitCode(0);
    Mail::assertNothingSent();
});

it('send_ledger ignora sms_body e whatsapp_text recebidos', function () {
    $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
        'template_data' => ['send_ledger' => campos(['sms_body' => 'não deveria', 'whatsapp_text' => 'nem isso'])],
    ]);

    $linha = NotificationTemplate::where('business_id', $this->business->id)->where('template_for', 'send_ledger')->first();
    expect([$linha->sms_body, $linha->whatsapp_text])->toBe(['', '']);
})->skip('R5: hoje o Blade só esconde o campo — decidir se o backend também deve ignorar (D5).');

// ── Tags (UC-NOT-10 · teste 12) ────────────────────────────────────────────────
it('preserva tag desconhecida no round-trip', function () {
    $this->post(action([\App\Http\Controllers\NotificationTemplateController::class, 'store']), [
        'template_data' => ['new_sale' => campos(['subject' => 'Oi {tag_que_nao_existe}'])],
    ]);

    expect(NotificationTemplate::getTemplate($this->business->id, 'new_sale')['subject'])
        ->toBe('Oi {tag_que_nao_existe}');
});

// ── P4: XSS (teste 16) ─────────────────────────────────────────────────────────
it('sanitiza script no corpo do e-mail ao renderizar', function () {
    NotificationTemplate::updateOrCreate(
        ['business_id' => $this->business->id, 'template_for' => 'new_sale'],
        ['email_body' => '<p>ok</p><script>alert(1)</script>'],
    );

    $corpo = app(\App\Utils\NotificationUtil::class)
        ->getNotificationData('new_sale', vendaDeExemplo(), contatoDeExemplo())['email_body'] ?? '';

    expect($corpo)->toContain('ok')->not->toContain('<script');
});

// ── Helper: payload de um modelo, com defaults vazios ──────────────────────────
function campos(array $sobrepor = []): array
{
    return array_merge([
        'subject' => '', 'cc' => '', 'bcc' => '',
        'email_body' => '', 'sms_body' => '', 'whatsapp_text' => '',
    ], $sobrepor);
}
