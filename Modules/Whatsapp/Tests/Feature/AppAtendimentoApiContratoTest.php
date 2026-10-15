<?php

declare(strict_types=1);

use App\Http\Controllers\Api\App\AtendimentoController;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Atendimento WhatsApp do app das lojas — tela 41 (P5), PR 18 do playbook propostas-app.
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-41-atendimento.md e o handoff
 * `propostas-app/api/tela-41-atendimento.md` (tabela de códigos e a regra LGPD: envio ativo, fora da
 * janela de 24 h, só com `whatsapp_consent = true`; responder dentro da janela é permitido). As
 * respostas esperadas vêm do contrato, não do controller.
 *
 * NENHUMA mensagem real sai: o daemon é falso (`Http::fake` + `preventStrayRequests`) e os eventos
 * de Centrifugo são falsos. Cada caso conta as chamadas ao daemon — o bloqueio prova que a chamada
 * NÃO aconteceu, e a idempotência prova que aconteceu uma vez só.
 *
 * Tier 0 (ADR 0358): tenant fictício 98 contra o 2 da lane. DatabaseTransactions reverte tudo.
 */

const APPAT_BIZ = 98;
const APPAT_OUTRO = 2;
const APPAT_DAEMON = 'https://daemon-falso.test';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS exige MySQL (ADR 0358).');
    }
    foreach (['channels', 'conversations', 'messages', 'channel_user_access', 'app_idempotencia', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    if (DB::table('business')->whereIn('id', [APPAT_BIZ, APPAT_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
    foreach (['whatsapp.access', 'whatsapp.send', 'whatsapp.view-all-phones'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    // Plano com o módulo WhatsApp (e só ele — o resto do pacote não importa aqui).
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(fn ($biz, $perm) => $perm === 'whatsapp_module');
    app()->instance(ModuleUtil::class, $mu);

    config(['whatsapp.baileys.daemon_url' => APPAT_DAEMON, 'whatsapp.baileys.api_key' => 'chave-falsa']);
    Http::preventStrayRequests();
    Http::fake([APPAT_DAEMON . '/*' => fn () => Http::response(['message_id' => 'APPAT-' . Str::uuid(), 'status' => 'sent'])]);
    Event::fake([OmnichannelMessageSent::class, OmnichannelMessageReceived::class]);
});

function appatUsuario(int $biz, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Atend', 'username' => 'app_atend_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    if ($permissoes !== []) {
        $u->givePermissionTo($permissoes);
    }

    return $u;
}

function appatCanal(int $biz, string $status = 'active', string $tipo = Channel::TYPE_WHATSAPP_BAILEYS): Channel
{
    return Channel::query()->forceCreate([
        'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Comercial',
        'type' => $tipo, 'status' => $status, 'display_identifier' => '+55 47 3' . random_int(100, 999) . '-' . random_int(1000, 9999),
    ]);
}

function appatAcesso(int $biz, int $userId, int $canalId): void
{
    DB::table('channel_user_access')->insert([
        'business_id' => $biz, 'user_id' => $userId, 'channel_id' => $canalId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appatContato(int $biz, int $criador, ?bool $consentimento): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente APPAT ' . uniqid(), 'is_customer' => 1,
        'contact_id' => 'APPAT' . random_int(10000, 99999), 'mobile' => '00000000000',
        'whatsapp_consent' => $consentimento, 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Conversa com a última mensagem do cliente há `$horas` horas (null = o cliente nunca escreveu). */
function appatConversa(int $biz, int $canalId, ?int $contatoId, ?int $horas): int
{
    $ultima = $horas === null ? null : now()->subHours($horas);

    return (int) DB::table('conversations')->insertGetId([
        'business_id' => $biz, 'channel_id' => $canalId, 'contact_id' => $contatoId,
        'customer_external_id' => '+5547' . random_int(900000000, 999999999), 'contact_name' => 'Fulano',
        'status' => 'open', 'last_inbound_at' => $ultima, 'last_message_at' => $ultima ?? now(),
        'unread_count' => 2, 'last_message_preview' => 'Pode mandar o orçamento?',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appatChamadasAoDaemon(): int
{
    return count(Http::recorded(fn (HttpRequest $r) => str_starts_with($r->url(), APPAT_DAEMON)));
}

it('UC-APPAT-01: lista as conversas do canal ligado, com consentimento e janela; a outra empresa não aparece', function () {
    $u = appatUsuario(APPAT_BIZ, ['whatsapp.access']);
    $canal = appatCanal(APPAT_BIZ);
    appatAcesso(APPAT_BIZ, $u->id, $canal->id);
    $comConsent = appatConversa(APPAT_BIZ, $canal->id, appatContato(APPAT_BIZ, $u->id, true), 2);
    $semConsent = appatConversa(APPAT_BIZ, $canal->id, appatContato(APPAT_BIZ, $u->id, null), 30);

    $canalOutro = appatCanal(APPAT_OUTRO);
    $alheia = appatConversa(APPAT_OUTRO, $canalOutro->id, null, 1);

    Passport::actingAs($u, [], 'api');
    $r = $this->getJson('/api/app/atendimento')->assertOk();

    expect($r->json('canal'))->toBe(['ligado' => true, 'numero' => $canal->display_identifier]);
    $porId = collect($r->json('conversas'))->keyBy('id');
    expect($porId->keys()->all())->toContain($comConsent, $semConsent);
    expect($porId->keys()->all())->not->toContain($alheia);
    expect($porId[$comConsent]['consentimento'])->toBeTrue();
    expect($porId[$comConsent]['janela_aberta_ate'])->not->toBeNull();
    expect($porId[$comConsent]['nao_lidas'])->toBe(2);
    expect($porId[$semConsent]['consentimento'])->toBeFalse();
    expect($porId[$semConsent]['janela_aberta_ate'])->toBeNull();
    expect($r->json('pagina'))->toBe(1);
    expect($r->json('tem_mais'))->toBeFalse();
});

it('UC-APPAT-02: sem canal ligado visível, `canal.ligado` é false e a área `atendimento` não entra', function () {
    $u = appatUsuario(APPAT_BIZ, ['whatsapp.access']);
    $desligado = appatCanal(APPAT_BIZ, 'disconnected');
    appatAcesso(APPAT_BIZ, $u->id, $desligado->id);
    appatCanal(APPAT_BIZ); // ligado, mas sem acesso do usuário

    Passport::actingAs($u, [], 'api');
    $this->getJson('/api/app/atendimento')->assertOk()
        ->assertJsonPath('canal.ligado', false)->assertJsonPath('conversas', []);
    expect(app(AtendimentoController::class)->areaVisivel($u))->toBeFalse();

    $ligado = appatCanal(APPAT_BIZ);
    appatAcesso(APPAT_BIZ, $u->id, $ligado->id);
    expect(app(AtendimentoController::class)->areaVisivel($u))->toBeTrue();
});

it('UC-APPAT-03: sem `whatsapp.access` → 403 sem_permissao; sem `whatsapp.send` não envia', function () {
    $semNada = appatUsuario(APPAT_BIZ, []);
    Passport::actingAs($semNada, [], 'api');
    $this->getJson('/api/app/atendimento')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    $soLe = appatUsuario(APPAT_BIZ, ['whatsapp.access', 'whatsapp.view-all-phones']);
    $canal = appatCanal(APPAT_BIZ);
    $conv = appatConversa(APPAT_BIZ, $canal->id, appatContato(APPAT_BIZ, $soLe->id, true), 1);
    Passport::actingAs($soLe, [], 'api');
    $this->withHeader('Idempotency-Key', 'k-' . uniqid())
        ->postJson("/api/app/atendimento/{$conv}/mensagens", ['texto' => 'Oi'])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(appatChamadasAoDaemon())->toBe(0);
});

it('UC-APPAT-04: detalhe traz as mensagens sem nota interna; conversa da outra empresa → 404', function () {
    $u = appatUsuario(APPAT_BIZ, ['whatsapp.access', 'whatsapp.view-all-phones']);
    $canal = appatCanal(APPAT_BIZ);
    $conv = appatConversa(APPAT_BIZ, $canal->id, null, 1);
    foreach ([['inbound', null, false, 'Pode mandar?'], ['outbound', 'human', false, 'Mando sim'], ['outbound', 'human', true, 'nota da equipe']] as [$dir, $kind, $nota, $txt]) {
        DB::table('messages')->insert([
            'business_id' => APPAT_BIZ, 'conversation_id' => $conv, 'direction' => $dir, 'provider' => 'whatsapp_baileys',
            'type' => 'text', 'body' => $txt, 'status' => $dir === 'inbound' ? 'received' : 'sent',
            'sender_kind' => $kind, 'is_internal_note' => $nota, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $alheia = appatConversa(APPAT_OUTRO, appatCanal(APPAT_OUTRO)->id, null, 1);

    Passport::actingAs($u, [], 'api');
    $r = $this->getJson("/api/app/atendimento/{$conv}")->assertOk();
    expect(collect($r->json('mensagens'))->pluck('texto')->all())->toBe(['Pode mandar?', 'Mando sim']);
    expect(collect($r->json('mensagens'))->pluck('de')->all())->toBe(['cliente', 'eu']);
    expect($r->json('conversa.id'))->toBe($conv);

    $this->getJson("/api/app/atendimento/{$alheia}")->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
});

it('UC-APPAT-05: LGPD — fora da janela e sem consentimento, o envio é recusado ANTES de gravar e de chamar o daemon', function () {
    $u = appatUsuario(APPAT_BIZ, ['whatsapp.access', 'whatsapp.send', 'whatsapp.view-all-phones']);
    $canal = appatCanal(APPAT_BIZ);
    $nuncaEscreveu = appatConversa(APPAT_BIZ, $canal->id, appatContato(APPAT_BIZ, $u->id, null), null);
    $recusou = appatConversa(APPAT_BIZ, $canal->id, appatContato(APPAT_BIZ, $u->id, false), 48);
    $semCliente = appatConversa(APPAT_BIZ, $canal->id, null, 48);

    Passport::actingAs($u, [], 'api');
    foreach ([$nuncaEscreveu, $recusou, $semCliente] as $conv) {
        $this->withHeader('Idempotency-Key', 'k-' . uniqid())
            ->postJson("/api/app/atendimento/{$conv}/mensagens", ['texto' => 'Promoção da semana'])
            ->assertStatus(422)->assertJsonPath('erro', 'sem_consentimento');
    }
    expect(appatChamadasAoDaemon())->toBe(0);
    expect(DB::table('messages')->whereIn('conversation_id', [$nuncaEscreveu, $recusou, $semCliente])->count())->toBe(0);
    expect(DB::table('app_idempotencia')->where('business_id', APPAT_BIZ)->where('user_id', $u->id)->count())->toBe(0);
});

it('UC-APPAT-06: dentro da janela responde mesmo sem consentimento; mesma chave não envia duas vezes', function () {
    $u = appatUsuario(APPAT_BIZ, ['whatsapp.access', 'whatsapp.send', 'whatsapp.view-all-phones']);
    $canal = appatCanal(APPAT_BIZ);
    $conv = appatConversa(APPAT_BIZ, $canal->id, appatContato(APPAT_BIZ, $u->id, false), 3);
    Passport::actingAs($u, [], 'api');

    $chave = 'k-' . uniqid();
    $r = $this->withHeader('Idempotency-Key', $chave)
        ->postJson("/api/app/atendimento/{$conv}/mensagens", ['texto' => 'Seu pedido está em produção.'])
        ->assertStatus(201);
    expect($r->json('mensagem.de'))->toBe('eu');
    expect($r->json('mensagem.texto'))->toBe('Seu pedido está em produção.');
    expect($r->json('mensagem.status'))->toBe('sent');
    expect(appatChamadasAoDaemon())->toBe(1);

    // Repetição (rede caiu no meio): mesma resposta, nenhum envio a mais.
    $this->withHeader('Idempotency-Key', $chave)
        ->postJson("/api/app/atendimento/{$conv}/mensagens", ['texto' => 'Seu pedido está em produção.'])
        ->assertStatus(200)->assertJsonPath('mensagem.id', $r->json('mensagem.id'));
    // Mesma chave, texto diferente: conflito.
    $this->withHeader('Idempotency-Key', $chave)
        ->postJson("/api/app/atendimento/{$conv}/mensagens", ['texto' => 'Outra coisa'])
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');
    expect(appatChamadasAoDaemon())->toBe(1);
    expect(DB::table('messages')->where('conversation_id', $conv)->where('direction', 'outbound')->count())->toBe(1);

    // Sem chave → validação, sem envio. (withHeader vale para as requisições seguintes do caso.)
    $this->flushHeaders()->postJson("/api/app/atendimento/{$conv}/mensagens", ['texto' => 'x'])
        ->assertStatus(422)->assertJsonPath('erro', 'validacao');
    expect(appatChamadasAoDaemon())->toBe(1);
});

it('UC-APPAT-07: cross-tenant — enviar na conversa da empresa 2 → 404, sem gravar nem chamar o daemon', function () {
    $u = appatUsuario(APPAT_BIZ, ['whatsapp.access', 'whatsapp.send', 'whatsapp.view-all-phones']);
    appatCanal(APPAT_BIZ);
    $outroCanal = appatCanal(APPAT_OUTRO);
    $alheia = appatConversa(APPAT_OUTRO, $outroCanal->id, null, 1);
    Passport::actingAs($u, [], 'api');

    $this->withHeader('Idempotency-Key', 'k-' . uniqid())
        ->postJson("/api/app/atendimento/{$alheia}/mensagens", ['texto' => 'Oi'])
        ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    $this->withHeader('Idempotency-Key', 'k-' . uniqid())
        ->postJson("/api/app/atendimento/{$alheia}/atalho", ['tipo' => 'status_pedido', 'ref_id' => 1])
        ->assertStatus(404);
    expect(appatChamadasAoDaemon())->toBe(0);
    expect(DB::table('messages')->where('conversation_id', $alheia)->count())->toBe(0);
});

it('UC-APPAT-08: canal desligado → 503 sem_configuracao', function () {
    $u = appatUsuario(APPAT_BIZ, ['whatsapp.access', 'whatsapp.send', 'whatsapp.view-all-phones']);
    $canal = appatCanal(APPAT_BIZ, 'disconnected');
    $conv = appatConversa(APPAT_BIZ, $canal->id, appatContato(APPAT_BIZ, $u->id, true), 1);
    Passport::actingAs($u, [], 'api');

    $this->withHeader('Idempotency-Key', 'k-' . uniqid())
        ->postJson("/api/app/atendimento/{$conv}/mensagens", ['texto' => 'Oi'])
        ->assertStatus(503)->assertJsonPath('erro', 'sem_configuracao');
    expect(appatChamadasAoDaemon())->toBe(0);
});

it('UC-APPAT-09: atalho de status monta o texto no ERP com o pedido DO cliente; de outro cliente → 404; ajuste desligado → 403', function () {
    $u = appatUsuario(APPAT_BIZ, ['whatsapp.access', 'whatsapp.send', 'whatsapp.view-all-phones']);
    $canal = appatCanal(APPAT_BIZ);
    $cliente = appatContato(APPAT_BIZ, $u->id, true);
    $outroCliente = appatContato(APPAT_BIZ, $u->id, true);
    $conv = appatConversa(APPAT_BIZ, $canal->id, $cliente, 1);
    $venda = fn (int $contato, string $numero) => DB::table('transactions')->insertGetId([
        'business_id' => APPAT_BIZ, 'created_by' => $u->id, 'contact_id' => $contato, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'due', 'invoice_no' => $numero,
        'transaction_date' => now(), 'total_before_tax' => 10, 'final_total' => 10,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $numero = 'APPAT-' . random_int(1000, 9999);
    $doCliente = $venda($cliente, $numero);
    $deOutro = $venda($outroCliente, 'APPAT-X' . random_int(1000, 9999));
    Passport::actingAs($u, [], 'api');

    $r = $this->withHeader('Idempotency-Key', 'k-' . uniqid())
        ->postJson("/api/app/atendimento/{$conv}/atalho", ['tipo' => 'status_pedido', 'ref_id' => $doCliente])
        ->assertStatus(201);
    expect($r->json('mensagem.texto'))->toBe("Seu pedido #{$numero} está registrado.");
    expect(appatChamadasAoDaemon())->toBe(1);

    $this->withHeader('Idempotency-Key', 'k-' . uniqid())
        ->postJson("/api/app/atendimento/{$conv}/atalho", ['tipo' => 'status_pedido', 'ref_id' => $deOutro])
        ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');

    $pos = json_decode((string) DB::table('business')->where('id', APPAT_BIZ)->value('pos_settings'), true) ?: [];
    DB::table('business')->where('id', APPAT_BIZ)->update(['pos_settings' => json_encode(array_merge($pos, ['app_atendimento_atalhos' => false]))]);
    $this->withHeader('Idempotency-Key', 'k-' . uniqid())
        ->postJson("/api/app/atendimento/{$conv}/atalho", ['tipo' => 'status_pedido', 'ref_id' => $doCliente])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(appatChamadasAoDaemon())->toBe(1);
});
