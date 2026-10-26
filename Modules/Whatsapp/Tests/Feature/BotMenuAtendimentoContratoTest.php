<?php
// @covers-us US-WA-324

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Message;
use Tests\Support\WithSeededTenant;

/**
 * Bot de atendimento com menu (US-WA-324 · PLANO-ATENDIMENTO-AUTOMATICO E4, destravada por [W]).
 *
 * Âncora: SPEC US-WA-324 (critérios 1-6). Caminho real: criar a `Message` inbound dispara o
 * observer → `OmnichannelMessageReceived` → listener → `MenuDeAtendimento`. Só o daemon é falso.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

const BOTM_DAEMON = 'https://daemon-falso-botmenu.test';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('whatsapp_bot_menus')) {
        $this->markTestSkipped('Requer MySQL com as tabelas do bot de menu.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    config(['whatsapp.baileys.daemon_url' => BOTM_DAEMON, 'whatsapp.baileys.api_key' => 'chave-falsa', 'whatsapp.bot_menu.enabled' => true]);
    Http::fake(['*' => Http::response(['message_id' => 'BOTM-'.Str::uuid(), 'status' => 'sent'])]);

    $this->canal = Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $this->biz->id, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Atendimento',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active', 'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ]);
    DB::table('whatsapp_queues')->where('business_id', $this->biz->id)->where('slug', 'financeiro-bot')->delete();
    DB::table('whatsapp_queues')->insert([
        'business_id' => $this->biz->id, 'slug' => 'financeiro-bot', 'label' => 'Financeiro', 'trigger_tags' => '[]', 'members' => '[]',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->menu = fn (int $biz, bool $perguntaNome = false) => DB::table('whatsapp_bot_menus')->insert([
        'business_id' => $biz, 'channel_id' => $this->canal->id, 'ativo' => true, 'saudacao' => 'Olá! Como podemos ajudar?',
        'opcoes' => json_encode([
            ['tecla' => '1', 'rotulo' => 'Financeiro', 'fila' => 'financeiro-bot', 'resposta' => null],
            ['tecla' => '2', 'rotulo' => 'Orçamento', 'fila' => 'fila-que-nao-existe', 'resposta' => 'Mande as medidas da peça.'],
        ]),
        'pergunta_nome' => $perguntaNome, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->conversa = fn (array $extra = []) => (int) DB::table('conversations')->insertGetId(array_merge([
        'business_id' => $this->biz->id, 'channel_id' => $this->canal->id, 'contact_id' => null,
        'customer_external_id' => '+5547'.random_int(900000000, 999999999), 'contact_name' => 'Fulano',
        'status' => 'open', 'is_blocked' => false, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
    // O cliente escreve: cria a Message inbound pelo Eloquent (dispara o observer real).
    $this->cliente = fn (int $conv, string $texto, array $payload = []) => Message::query()->withoutGlobalScopes()->create([
        'business_id' => $this->biz->id, 'conversation_id' => $conv, 'direction' => 'inbound', 'provider' => Channel::TYPE_WHATSAPP_BAILEYS,
        'type' => 'text', 'body' => $texto, 'payload' => $payload, 'status' => 'received',
    ]);
    $this->respostas = fn (int $conv) => DB::table('messages')->where('business_id', $this->biz->id)
        ->where('conversation_id', $conv)->where('direction', 'outbound')->orderBy('id')->pluck('body')->all();
});

it('US-WA-324 · primeira mensagem recebe o menu; a opção põe na fila e responde', function () {
    ($this->menu)($this->biz->id);
    $conv = ($this->conversa)();

    ($this->cliente)($conv, 'oi');
    ($this->cliente)($conv, '1');

    $r = ($this->respostas)($conv);
    expect($r)->toHaveCount(2)
        ->and($r[0])->toContain('Olá! Como podemos ajudar?')->toContain('1 - Financeiro')->toContain('2 - Orçamento')
        ->and($r[1])->toBe('Certo! Já vou te encaminhar para Financeiro.');
    expect(DB::table('conversations')->where('id', $conv)->value('queue_override'))->toBe('financeiro-bot');
    expect(DB::table('messages')->where('conversation_id', $conv)->where('direction', 'outbound')->pluck('sender_kind')->unique()->all())->toBe(['bot']);

    // Concluído: o bot fica quieto até a conversa parar.
    ($this->cliente)($conv, 'obrigado');
    expect(($this->respostas)($conv))->toHaveCount(2);
});

it('US-WA-324 · resposta inválida repete o menu uma vez e depois passa para um atendente', function () {
    ($this->menu)($this->biz->id);
    $conv = ($this->conversa)();

    ($this->cliente)($conv, 'oi');
    ($this->cliente)($conv, 'quero falar com alguém');
    ($this->cliente)($conv, 'ALÔ');
    ($this->cliente)($conv, '1');

    $r = ($this->respostas)($conv);
    expect($r)->toHaveCount(3)
        ->and($r[1])->toStartWith('Não entendi.')
        ->and($r[2])->toBe('Vou te passar para um atendente. Aguarde um instante, por favor.');
    expect(DB::table('conversations')->where('id', $conv)->value('queue_override'))->toBeNull();
});

it('US-WA-324 · pergunta o nome de quem não é cliente cadastrado; fila inexistente não é aplicada', function () {
    ($this->menu)($this->biz->id, true);
    $conv = ($this->conversa)();

    ($this->cliente)($conv, 'bom dia');
    ($this->cliente)($conv, '2');
    ($this->cliente)($conv, 'Maria Souza');

    $r = ($this->respostas)($conv);
    expect($r[1])->toBe('Mande as medidas da peça. Antes, qual é o seu nome?')
        ->and($r[2])->toBe('Obrigado, Maria! Um atendente já vai falar com você.');
    expect(DB::table('conversations')->where('id', $conv)->value('contact_name'))->toBe('Maria Souza')
        ->and(DB::table('conversations')->where('id', $conv)->value('queue_override'))->toBeNull();
});

it('US-WA-324 · não responde: flag desligada, conversa com atendente, histórico importado ou menu de outra empresa', function () {
    ($this->menu)($this->outra->id); // menu da outra empresa, mesmo id de canal
    $semMenuProprio = ($this->conversa)();
    ($this->cliente)($semMenuProprio, 'oi');
    expect(($this->respostas)($semMenuProprio))->toBe([]);

    ($this->menu)($this->biz->id);
    $atendida = ($this->conversa)(['assigned_user_id' => \App\User::factory()->create(['business_id' => $this->biz->id])->id]);
    ($this->cliente)($atendida, 'oi');
    $historico = ($this->conversa)();
    ($this->cliente)($historico, 'mensagem antiga', ['is_history_sync' => true]);
    config(['whatsapp.bot_menu.enabled' => false]);
    $desligado = ($this->conversa)();
    ($this->cliente)($desligado, 'oi');

    expect(($this->respostas)($atendida))->toBe([])
        ->and(($this->respostas)($historico))->toBe([])
        ->and(($this->respostas)($desligado))->toBe([]);

    // Controle: com a flag de volta, a mesma conversa recebe o menu.
    config(['whatsapp.bot_menu.enabled' => true]);
    ($this->cliente)($desligado, 'oi de novo');
    expect(($this->respostas)($desligado))->toHaveCount(1);
});
