<?php
// @covers-us US-WA-326

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DespacharMensagemDoSistemaJob;
use Modules\Whatsapp\Services\Regua\ReguaDeRelacionamento;
use Tests\Support\WithSeededTenant;

/**
 * Régua de relacionamento por WhatsApp — aniversário e recompra (US-WA-326).
 *
 * Âncora: SPEC US-WA-326 (critérios 1-6) e a regra LGPD da ADR 0268 (marketing só com
 * `whatsapp_opt_in_at`) + `Contact::canReceiveWhatsappNotification()`. Só o job é falso.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasTable('whatsapp_reguas_relacionamento')
        || ! Schema::hasColumn('contacts', 'aniversario_mmdd')
        || ! Schema::hasColumn('contacts', 'whatsapp_opt_in_at')) {
        $this->markTestSkipped('Requer MySQL com as tabelas da régua e as colunas de aniversário/opt-in.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    $this->local = DB::table('business_locations')->where('business_id', $this->biz->id)->value('id');
    Bus::fake([DespacharMensagemDoSistemaJob::class]);
    $this->criador = \App\User::factory()->create(['business_id' => $this->biz->id]);
    foreach ([$this->biz->id, $this->outra->id] as $b) {
        Channel::query()->withoutGlobalScopes()->forceCreate([
            'business_id' => $b, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Régua teste',
            'type' => Channel::TYPE_WHATSAPP_WHATSMEOW, 'status' => 'active', 'channel_health' => 'healthy',
            'handles_outbound_default' => true, 'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
        ]);
    }
    $this->hoje = CarbonImmutable::parse('2026-10-26 10:00:00');
    $this->regua = function (int $biz, string $tipo, string $texto, ?int $dias = null) {
        DB::table('whatsapp_reguas_relacionamento')->where('business_id', $biz)->where('tipo', $tipo)->delete();
        DB::table('whatsapp_reguas_relacionamento')->insert([
            'business_id' => $biz, 'tipo' => $tipo, 'ativo' => true, 'texto' => $texto, 'dias_sem_compra' => $dias,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $this->cliente = fn (int $biz, string $nome, array $extra = []) => (int) DB::table('contacts')->insertGetId(array_merge([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome, 'mobile' => '4898'.random_int(1000000, 9999999),
        'whatsapp_opt_in_at' => now()->subMonth(), 'created_by' => $this->criador->id, 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
    $this->mensagens = fn (int $biz) => DB::table('messages as m')->join('conversations as c', 'c.id', '=', 'm.conversation_id')
        ->where('m.business_id', $biz)->where('m.sender_kind', 'system')->orderBy('m.id')->pluck('m.body', 'c.contact_id')->all();
    $this->rodar = fn (int $biz) => app(ReguaDeRelacionamento::class)->executar($biz, $this->hoje, fn () => 30);
});

it('US-WA-326 · aniversário: só quem faz anos hoje e deu opt-in de marketing; uma vez por ano', function () {
    ($this->regua)($this->biz->id, 'aniversario', 'Parabéns, {nome}! Hoje tem desconto para você.');
    $hoje = ($this->cliente)($this->biz->id, 'Maria Souza', ['aniversario_mmdd' => '10-26']);
    $porDob = ($this->cliente)($this->biz->id, 'José Lima', ['aniversario_mmdd' => null, 'dob' => '1980-10-26']);
    $semOptIn = ($this->cliente)($this->biz->id, 'Sem Optin', ['aniversario_mmdd' => '10-26', 'whatsapp_opt_in_at' => null]);
    $recusou = ($this->cliente)($this->biz->id, 'Recusou', ['aniversario_mmdd' => '10-26', 'whatsapp_consent' => false]);
    ($this->cliente)($this->biz->id, 'Outro Dia', ['aniversario_mmdd' => '10-27']);
    ($this->cliente)($this->outra->id, 'Da Outra', ['aniversario_mmdd' => '10-26']);

    $r = ($this->rodar)($this->biz->id);
    expect($r)->toBe(['aniversario' => 2, 'recompra' => 0]);
    $msgs = ($this->mensagens)($this->biz->id);
    expect($msgs)->toHaveCount(2)
        ->and($msgs[$hoje])->toBe('Parabéns, Maria! Hoje tem desconto para você.')
        ->and($msgs[$porDob])->toBe('Parabéns, José! Hoje tem desconto para você.')
        ->and(array_key_exists($semOptIn, $msgs))->toBeFalse()
        ->and(array_key_exists($recusou, $msgs))->toBeFalse();
    expect(($this->mensagens)($this->outra->id))->toBe([]);
    // Espaçados pela fila (anti-ban): nenhum envio sem atraso.
    Bus::assertDispatched(DespacharMensagemDoSistemaJob::class, fn ($j) => $j->delay !== null);

    // Rodar de novo no mesmo dia não repete.
    expect(($this->rodar)($this->biz->id))->toBe(['aniversario' => 0, 'recompra' => 0]);
    expect(($this->mensagens)($this->biz->id))->toHaveCount(2);
});

it('US-WA-326 · recompra: última compra há exatamente N dias; compra mais recente tira da régua', function () {
    if ($this->local === null) {
        $this->markTestSkipped('Tenant 98 sem business_location semeada.');
    }
    ($this->regua)($this->biz->id, 'recompra', 'Oi, {nome}! Faz um tempo — que tal repor seu estoque?', 30);
    $venda = fn (int $c, string $quando) => DB::table('transactions')->insert([
        'business_id' => $this->biz->id, 'location_id' => $this->local, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'paid', 'contact_id' => $c, 'transaction_date' => $quando, 'final_total' => 10,
        'total_before_tax' => 10, 'created_by' => $this->criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $alvo = ($this->cliente)($this->biz->id, 'Ana Alvo');
    $venda($alvo, '2026-09-26 15:00:00');
    $voltou = ($this->cliente)($this->biz->id, 'Bia Voltou');
    $venda($voltou, '2026-09-26 15:00:00');
    $venda($voltou, '2026-10-20 15:00:00');
    $cedo = ($this->cliente)($this->biz->id, 'Caio Cedo');
    $venda($cedo, '2026-09-27 15:00:00');

    expect(($this->rodar)($this->biz->id))->toBe(['aniversario' => 0, 'recompra' => 1]);
    $msgs = ($this->mensagens)($this->biz->id);
    expect(array_keys($msgs))->toBe([$alvo])
        ->and($msgs[$alvo])->toBe('Oi, Ana! Faz um tempo — que tal repor seu estoque?');
});

it('US-WA-326 · o comando não faz nada com a régua desligada', function () {
    ($this->regua)($this->biz->id, 'aniversario', 'Parabéns, {nome}!');
    ($this->cliente)($this->biz->id, 'Maria', ['aniversario_mmdd' => now()->format('m-d')]);

    config(['whatsapp.regua_relacionamento.enabled' => false]);
    $this->artisan('whatsapp:regua-relacionamento', ['--business' => $this->biz->id])->assertSuccessful();
    expect(($this->mensagens)($this->biz->id))->toBe([]);

    // Controle: ligada, o mesmo comando manda.
    config(['whatsapp.regua_relacionamento.enabled' => true]);
    $this->artisan('whatsapp:regua-relacionamento', ['--business' => $this->biz->id])->assertSuccessful();
    expect(($this->mensagens)($this->biz->id))->toHaveCount(1);
});
