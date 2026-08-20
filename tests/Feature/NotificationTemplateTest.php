<?php

declare(strict_types=1);

// tests/Feature/NotificationTemplateTest.php
//
// Contrato da tela "Modelos de notificação" (/notification-templates).
//
// Cada `it()` cita um UC-id (UC-NOT-NN). O `Index.casos.md` que os DECLARA ainda não está
// no repo: ele é artefato da Page Inertia, e esta tela ainda é Blade
// (resources/views/notification_template/). Charter sem `.tsx` irmão quebra o invariante
// duro IT2 do prototipo-ui/integrity-check.mjs — medido: dos 171 charters do repo, zero
// estão sem `.tsx`. Charter + casos + contrato entram juntos, na onda da migração; os ids
// aqui já ficam certos pra quando o casos.md chegar e reivindicá-los (ADR 0264 G-2 lê o
// heading `## UC-`, e o casos-results-collect colhe o id do título do teste no JUnit).
//
// Origem: pacote F1 do Cowork 2026-08-19, autorizado por [W]. Os helpers de tenant do
// pacote (criarNegocioComAdmin/entrarComoNegocio) NÃO existem neste projeto — o canônico é
// o trait Tests\Support\WithSeededTenant, tenant fictício biz=98 (ADR 0358; biz=1 é a WR2
// real e biz=4 é a ROTA LIVRE, ambos proibidos em teste).
//
// Os UCs que dependem dos patches P1–P4 entram FALHANDO de propósito? Não: entram como
// `todo()`, com a razão declarada. O vermelho de CI seria indistinguível de regressão real;
// o `todo()` registra o achado sem transformar a lane numa mentira. Cada um vira assert de
// verdade no PR que traz o patch correspondente.

use App\NotificationTemplate;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WithSeededTenant;

uses(WithSeededTenant::class);

/** Payload de um modelo, com todos os campos que o store() lê (defaults vazios). */
function camposDoModelo(array $sobrepor = []): array
{
    return array_merge([
        'subject' => '', 'cc' => '', 'bcc' => '',
        'email_body' => '', 'sms_body' => '', 'whatsapp_text' => '',
    ], $sobrepor);
}

function rotaIndice(): string
{
    return action([\App\Http\Controllers\NotificationTemplateController::class, 'index']);
}

function rotaStore(): string
{
    return action([\App\Http\Controllers\NotificationTemplateController::class, 'store']);
}

beforeEach(function () {
    // Guard de ambiente: a suíte precisa do schema UltimatePOS real (business + users +
    // notification_templates). Sem ele, skip com mensagem acionável em vez de erro opaco.
    if (! Schema::hasTable('business') || ! Schema::hasTable('notification_templates')) {
        $this->markTestSkipped(
            'Schema UltimatePOS ausente. Seed mínimo: .github/actions/pest-mysql-setup (CI) '
            . '· scripts/tests/ct100-fullsuite.sh (CT 100).'
        );
    }

    $this->business = $this->seededTenant();          // biz=98 fictício (ADR 0358)
    $this->admin = User::where('business_id', $this->business->id)->firstOrFail();

    $this->entrar = function (User $u) {
        return $this->actingAs($u)->withSession([
            'user.business_id' => $u->business_id,
            'business.id' => $u->business_id,
        ]);
    };
});

// ── Permissão ────────────────────────────────────────────────────────────────

it('UC-NOT-01 · nega o indice sem a permissao send_notification', function () {
    $semPerm = User::factory()->create(['business_id' => $this->business->id]);

    ($this->entrar)($semPerm)->get(rotaIndice())->assertForbidden();
})->skip(fn () => ! class_exists(\Database\Factories\UserFactory::class), 'UserFactory ausente neste schema.');

it('UC-NOT-01 · nega o store sem a permissao send_notification', function () {
    $semPerm = User::factory()->create(['business_id' => $this->business->id]);

    ($this->entrar)($semPerm)
        ->post(rotaStore(), ['template_data' => ['new_sale' => camposDoModelo(['subject' => 'x'])]])
        ->assertForbidden();
})->skip(fn () => ! class_exists(\Database\Factories\UserFactory::class), 'UserFactory ausente neste schema.');

// ── Índice ───────────────────────────────────────────────────────────────────

it('UC-NOT-02 · entrega os 3 grupos com as colunas de cada modelo', function () {
    $resposta = ($this->entrar)($this->admin)->get(rotaIndice());

    $resposta->assertOk()
        ->assertViewHas('general_notifications')
        ->assertViewHas('customer_notifications')
        ->assertViewHas('supplier_notifications');

    $cliente = $resposta->viewData('customer_notifications');

    // As chaves core do grupo Cliente (módulo pode INJETAR mais — R1: nunca assumir lista fixa).
    expect(array_keys($cliente))->toContain('new_sale', 'payment_received', 'payment_reminder');

    // As 9 colunas que o __getTemplateDetails() promete para cada modelo.
    foreach (['subject', 'email_body', 'sms_body', 'whatsapp_text', 'auto_send',
        'auto_send_sms', 'auto_send_wa_notif', 'cc', 'bcc'] as $coluna) {
        expect($cliente['new_sale'])->toHaveKey($coluna);
    }
});

// ── Gravação ─────────────────────────────────────────────────────────────────

it('UC-NOT-04 · cria o registro quando o modelo ainda nao existe', function () {
    NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'new_sale')->delete();

    ($this->entrar)($this->admin)
        ->post(rotaStore(), ['template_data' => [
            'new_sale' => camposDoModelo(['subject' => 'Obrigado pela compra — {business_name}']),
        ]])
        ->assertRedirect();

    expect(NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'new_sale')->value('subject'))
        ->toBe('Obrigado pela compra — {business_name}');
});

it('UC-NOT-04 · atualiza sem duplicar a linha', function () {
    foreach (['Um', 'Dois'] as $assunto) {
        ($this->entrar)($this->admin)->post(rotaStore(), [
            'template_data' => ['new_sale' => camposDoModelo(['subject' => $assunto])],
        ]);
    }

    $linhas = NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'new_sale')->get();

    expect($linhas)->toHaveCount(1);                    // R1 — updateOrCreate, não insert
    expect($linhas->first()->subject)->toBe('Dois');
});

it('UC-NOT-05 · grava varios modelos num unico POST', function () {
    ($this->entrar)($this->admin)->post(rotaStore(), ['template_data' => [
        'new_sale' => camposDoModelo(['subject' => 'A']),
        'payment_received' => camposDoModelo(['subject' => 'B']),
        'payment_reminder' => camposDoModelo(['subject' => 'C']),
    ]])->assertRedirect();

    $assuntos = NotificationTemplate::where('business_id', $this->business->id)
        ->whereIn('template_for', ['new_sale', 'payment_received', 'payment_reminder'])
        ->pluck('subject', 'template_for');

    expect($assuntos['new_sale'])->toBe('A');
    expect($assuntos['payment_received'])->toBe('B');
    expect($assuntos['payment_reminder'])->toBe('C');
});

// ── Envio automático (checkbox) ──────────────────────────────────────────────

it('UC-NOT-16 · liga o envio automatico e grava auto_send=1', function () {
    ($this->entrar)($this->admin)->post(rotaStore(), ['template_data' => [
        'new_sale' => camposDoModelo(['auto_send' => '1']),
    ]]);

    expect((int) NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'new_sale')->value('auto_send'))->toBe(1);
});

it('UC-NOT-17 · zera os tres automaticos quando a checkbox nao vem no POST', function () {
    NotificationTemplate::updateOrCreate(
        ['business_id' => $this->business->id, 'template_for' => 'payment_reminder'],
        ['auto_send' => 1, 'auto_send_sms' => 1, 'auto_send_wa_notif' => 1],
    );

    // camposDoModelo() NÃO traz as chaves auto_send* — é exatamente o browser omitindo
    // a checkbox desmarcada. R2: ausente ⇒ 0, nunca "manteve o anterior".
    ($this->entrar)($this->admin)->post(rotaStore(), ['template_data' => [
        'payment_reminder' => camposDoModelo(),
    ]]);

    $linha = NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'payment_reminder')->first();

    expect([(int) $linha->auto_send, (int) $linha->auto_send_sms, (int) $linha->auto_send_wa_notif])
        ->toBe([0, 0, 0]);
});

// ── Tags ─────────────────────────────────────────────────────────────────────

it('UC-NOT-10 · preserva tag desconhecida no round-trip', function () {
    ($this->entrar)($this->admin)->post(rotaStore(), ['template_data' => [
        'new_sale' => camposDoModelo(['subject' => 'Oi {tag_que_nao_existe}']),
    ]]);

    // R7 — a UI avisa, o backend NÃO remove nem escapa.
    expect(NotificationTemplate::getTemplate($this->business->id, 'new_sale')['subject'])
        ->toBe('Oi {tag_que_nao_existe}');
});

// ── Multi-tenant (Tier 0 · ADR 0093) ─────────────────────────────────────────

it('UC-NOT-26 · nao deixa um negocio alterar o modelo de outro', function () {
    $outro = $this->seededSupportClientTenant();        // biz=99 fictício

    $antes = NotificationTemplate::updateOrCreate(
        ['business_id' => $outro->id, 'template_for' => 'new_sale'],
        ['subject' => 'Do outro negocio'],
    );

    ($this->entrar)($this->admin)->post(rotaStore(), ['template_data' => [
        'new_sale' => camposDoModelo(['subject' => 'Invadido']),
    ]]);

    expect($antes->fresh()->subject)->toBe('Do outro negocio');
});

// ── Achados que dependem de patch (P1–P4) ────────────────────────────────────
// Entram como todo() com a razão: o comportamento HOJE é o oposto do aceite. Cada um vira
// assert real no PR do patch — e é assim que o UC sai de ⬜ para 🧪 no casos.md.

it('UC-NOT-27 · ignora chave de modelo desconhecida no POST', function () {
    ($this->entrar)($this->admin)->post(rotaStore(), ['template_data' => [
        'modelo_inventado' => camposDoModelo(['subject' => 'x']),
    ]])->assertRedirect();

    expect(NotificationTemplate::where('business_id', $this->business->id)
        ->where('template_for', 'modelo_inventado')->exists())->toBeFalse();
});

it('UC-NOT-24 · recusa cc invalido no servidor', function () {
    ($this->entrar)($this->admin)->post(rotaStore(), ['template_data' => [
        'new_sale' => camposDoModelo(['cc' => 'nao-e-email']),
    ]])->assertSessionHasErrors('template_data.new_sale.cc');
});

it('UC-NOT-28 · semeia os modelos em portugues para negocio novo', function () {
    // Semeia num business descartável usando o MESMO caminho de produção do seed.
    $novoId = 97;
    DB::table('notification_templates')->where('business_id', $novoId)->delete();
    DB::table('notification_templates')->insert(
        NotificationTemplate::defaultNotificationTemplates($novoId)
    );

    $venda = NotificationTemplate::where('business_id', $novoId)
        ->where('template_for', 'new_sale')->first();

    expect($venda->subject)->toContain('Obrigado pela compra');
    expect($venda->email_body)->toContain('Olá {contact_name}');
    expect($venda->email_body)->not->toContain('Dear ');
    expect($venda->whatsapp_text)->not->toBeEmpty();    // o seed antigo nem tinha a coluna
});

it('UC-NOT-29 · a traducao preserva o modelo que o negocio editou', function () {
    $linha = NotificationTemplate::updateOrCreate(
        ['business_id' => $this->business->id, 'template_for' => 'new_sale'],
        ['subject' => 'Meu assunto proprio', 'email_body' => '<p>Texto meu</p>'],
    );

    // A migration e uma classe anonima retornada pelo arquivo — um require so, sem
    // require_once antes (que faria o segundo require devolver true em vez do objeto).
    $migration = require base_path('database/migrations/2026_08_19_000000_traduzir_notification_templates_pt_br.php');
    $migration->up();

    expect($linha->fresh()->subject)->toBe('Meu assunto proprio');
    expect($linha->fresh()->email_body)->toBe('<p>Texto meu</p>');
});

it('UC-NOT-30 · sanitiza script no corpo do e-mail ao montar a mensagem', function () {
    $dados = [
        'subject' => 'assunto',
        'email_body' => '<p>ok</p><script>alert(1)</script>',
    ];

    $mail = (new \App\Notifications\CustomerNotification($dados))->toMail(new \stdClass);
    $corpo = $mail->viewData['content'] ?? '';

    expect($corpo)->toContain('ok');
    expect($corpo)->not->toContain('<script');
});

// ── Envio: dependem de fixture de venda/contato ──────────────────────────────
// autoSendNotification($business_id, $tipo, $transaction, $contact) precisa de uma
// Transaction + Contact reais do tenant. Montar isso é trabalho de fixture que este pacote
// não trouxe — declarar todo() é mais honesto que um assert que não exercita o caminho.

it('UC-NOT-18 · nao envia pelo canal cujo corpo esta vazio', function () {
    // R9 — email_body vazio ⇒ nada sai por e-mail.
})->todo('Precisa de Transaction + Contact de exemplo no tenant 98 (fixture não veio no pacote F1).');

it('UC-NOT-25 · o lembrete automatico nao dispara com auto_send desligado', function () {
    // R10 — quem dispara é o comando AutoSendPaymentReminder, não o request.
})->todo('Precisa de fatura vencida + contato no tenant 98 (fixture não veio no pacote F1).');
