<?php
// @covers-us US-WA-327

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\BotMenuController;
use Tests\Support\WithSeededTenant;

/**
 * Configuração do menu do bot por canal (US-WA-327) — o que a aba "Menu do bot" grava é o que o
 * motor (US-WA-324) lê.
 *
 * Âncora: SPEC US-WA-327 (critérios 1-4) e `Channels/Show.casos.md` UC-CNLD-06.
 * Tenant 98 contra 99 (ADR 0358). Daemon falso. ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

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
    $user = \App\User::factory()->create(['business_id' => $this->biz->id]);
    $this->actingAs($user);
    session(['user.business_id' => $this->biz->id, 'user.id' => $user->id]);
    config(['whatsapp.baileys.daemon_url' => 'https://daemon-falso-botcfg.test', 'whatsapp.baileys.api_key' => 'x', 'whatsapp.bot_menu.enabled' => true]);
    Http::fake(['*' => Http::response(['message_id' => 'BCFG-'.Str::uuid(), 'status' => 'sent'])]);

    $this->canal = fn (int $biz) => Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Atendimento',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active', 'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ]);
    $this->fila = function (int $biz, string $slug) {
        DB::table('whatsapp_queues')->where('business_id', $biz)->where('slug', $slug)->delete();
        DB::table('whatsapp_queues')->insert([
            'business_id' => $biz, 'slug' => $slug, 'label' => ucfirst($slug), 'trigger_tags' => '[]', 'members' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $this->salvar = fn (int $canal, array $dados) => app(BotMenuController::class)
        ->update(Request::create("/atendimento/canais/{$canal}/menu-bot", 'PUT', $dados), $canal);
    $this->menu = [
        'ativo' => true, 'saudacao' => 'Bem-vindo! Escolha:', 'pergunta_nome' => false,
        'opcoes' => [['tecla' => '1', 'rotulo' => 'Financeiro', 'fila' => 'fin-cfg', 'resposta' => 'Já te passo ao financeiro.']],
    ];
});

it('UC-CNLD-06 · US-WA-327 · salvar grava o menu do canal e o bot passa a responder com ele', function () {
    ($this->fila)($this->biz->id, 'fin-cfg');
    $canal = ($this->canal)($this->biz->id);

    ($this->salvar)($canal->id, $this->menu);
    expect(BotMenuController::doCanal($this->biz->id, $canal->id))->toMatchArray(['ativo' => true, 'saudacao' => 'Bem-vindo! Escolha:'])
        ->and(BotMenuController::doCanal($this->biz->id, $canal->id)['opcoes'][0]['fila'])->toBe('fin-cfg');

    // Salvar de novo atualiza a mesma linha (um menu por canal).
    ($this->salvar)($canal->id, ['saudacao' => 'Olá de novo:'] + $this->menu);
    expect(DB::table('whatsapp_bot_menus')->where('business_id', $this->biz->id)->where('channel_id', $canal->id)->count())->toBe(1);

    // O motor lê o que a tela gravou.
    $conv = (int) DB::table('conversations')->insertGetId([
        'business_id' => $this->biz->id, 'channel_id' => $canal->id, 'customer_external_id' => '+5547'.random_int(900000000, 999999999),
        'contact_name' => 'Fulano', 'status' => 'open', 'is_blocked' => false, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    Message::query()->withoutGlobalScopes()->create([
        'business_id' => $this->biz->id, 'conversation_id' => $conv, 'direction' => 'inbound', 'provider' => Channel::TYPE_WHATSAPP_BAILEYS,
        'type' => 'text', 'body' => 'oi', 'payload' => [], 'status' => 'received',
    ]);
    expect(DB::table('messages')->where('conversation_id', $conv)->where('direction', 'outbound')->value('body'))
        ->toContain('Olá de novo:')->toContain('1 - Financeiro');
});

it('US-WA-327 · canal de outra empresa dá 404; fila de outra empresa e tecla repetida são recusadas', function () {
    ($this->fila)($this->outra->id, 'so-da-outra');
    $meu = ($this->canal)($this->biz->id);
    $alheio = ($this->canal)($this->outra->id);

    expect(fn () => ($this->salvar)($alheio->id, $this->menu))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    $comFilaAlheia = $this->menu;
    $comFilaAlheia['opcoes'][0]['fila'] = 'so-da-outra';
    expect(fn () => ($this->salvar)($meu->id, $comFilaAlheia))->toThrow(ValidationException::class);

    ($this->fila)($this->biz->id, 'fin-cfg');
    $repetida = $this->menu;
    $repetida['opcoes'][] = ['tecla' => '1', 'rotulo' => 'Outra', 'fila' => null, 'resposta' => null];
    expect(fn () => ($this->salvar)($meu->id, $repetida))->toThrow(ValidationException::class);

    expect(DB::table('whatsapp_bot_menus')->whereIn('channel_id', [$meu->id, $alheio->id])->count())->toBe(0);
});
