<?php

declare(strict_types=1);

use App\Scopes\ScopeByBusiness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\JanaCorrecao;
use Modules\Whatsapp\Entities\LidPhoneMap;
use Modules\Whatsapp\Entities\WhatsappReminder;
use Modules\Whatsapp\Services\Contacts\LidPhoneResolver;
use Tests\Support\WithSeededTenant;

/**
 * Isolamento entre empresas das tabelas do Whatsapp que não tinham teste (auditoria
 * 2026-11-05, lote 1 PR 1.4): `whatsapp_lid_pn_map`, `whatsapp_reminders` e
 * `whatsapp_jana_correcoes`. As outras três do lote têm o caso no teste dono do tema:
 * `whatsapp_bot_menu_sessoes` em BotMenuAtendimentoContratoTest, `whatsapp_regua_envios`
 * em ReguaRelacionamentoContratoTest e `channel_health_snapshots` em
 * ChannelHealthSnapshotCommandTest.
 *
 * Âncora: ADR 0093 (business_id Tier 0) — a mesma chave natural em duas empresas é
 * dado de duas empresas. Tenant 98 contra o outro tenant semeado (ADR 0358).
 * ⚠️ SKIP em SQLite (LC-13): roda na lane Whatsapp · Pest (MySQL).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasTable('whatsapp_lid_pn_map')
        || ! Schema::hasTable('whatsapp_reminders')
        || ! Schema::hasTable('whatsapp_jana_correcoes')) {
        $this->markTestSkipped('Requer MySQL com as tabelas de LID, lembretes e correções.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Cache::flush();

    $this->usuario = fn (int $biz) => \App\User::factory()->create(['business_id' => $biz]);
    $this->conversa = function (int $biz): int {
        $canal = Channel::query()->withoutGlobalScopes()->forceCreate([
            'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Isolamento',
            'type' => Channel::TYPE_WHATSAPP_WHATSMEOW, 'status' => 'active',
            'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
        ]);

        return (int) DB::table('conversations')->insertGetId([
            'business_id' => $biz, 'channel_id' => $canal->id, 'contact_id' => null,
            'customer_external_id' => '+5547'.random_int(900000000, 999999999), 'contact_name' => 'Fulano',
            'status' => 'open', 'is_blocked' => false, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    // Entra como usuário da empresa: é o que liga o ScopeByBusiness.
    $this->entrarComo = function (\App\User $u): void {
        $this->actingAs($u);
        session(['user.business_id' => (int) $u->business_id]);
    };
});

it('whatsapp_lid_pn_map · o mesmo LID resolve para o telefone de cada empresa', function () {
    $resolver = app(LidPhoneResolver::class);
    $lid = '5196'.random_int(100000000, 999999999);
    $resolver->record($this->biz->id, $lid, '+5547999990001');
    $resolver->record($this->outra->id, $lid, '+5548988880002');

    expect($resolver->resolve($this->biz->id, $lid))->toBe('+5547999990001')
        ->and($resolver->resolve($this->outra->id, $lid))->toBe('+5548988880002');

    // Reescrever o telefone numa empresa não mexe na outra.
    $resolver->record($this->biz->id, $lid, '+5547999990003');
    $outraRow = LidPhoneMap::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $this->outra->id)->where('lid', $lid)->first();
    expect($outraRow->phone_e164)->toBe('+5548988880002');
    expect(LidPhoneMap::query()->withoutGlobalScope(ScopeByBusiness::class)->where('lid', $lid)->count())->toBe(2);
});

it('whatsapp_lid_pn_map · mapeamento manual não aproveita o webhook visto em outra empresa', function () {
    $resolver = app(LidPhoneResolver::class);
    $lid = '5197'.random_int(100000000, 999999999);
    $resolver->record($this->outra->id, $lid, null); // só a outra empresa viu este LID pelo webhook

    expect(fn () => $resolver->record($this->biz->id, $lid, '+5547999990004', LidPhoneMap::SOURCE_MANUAL))
        ->toThrow(\DomainException::class);
    expect($resolver->resolve($this->biz->id, $lid))->toBeNull();

    // Controle: com o webhook visto na própria empresa, o manual entra.
    $resolver->record($this->biz->id, $lid, null);
    $resolver->record($this->biz->id, $lid, '+5547999990004', LidPhoneMap::SOURCE_MANUAL);
    Cache::flush();
    expect($resolver->resolve($this->biz->id, $lid))->toBe('+5547999990004');
});

it('whatsapp_reminders · o usuário só enxerga os lembretes da própria empresa', function () {
    $minha = ($this->conversa)($this->biz->id);
    $daOutra = ($this->conversa)($this->outra->id);
    $eu = ($this->usuario)($this->biz->id);
    $vizinho = ($this->usuario)($this->outra->id);
    $novo = fn (int $biz, int $conv, int $user, string $texto) => (int) DB::table('whatsapp_reminders')->insertGetId([
        'business_id' => $biz, 'conversation_id' => $conv, 'atendente_user_id' => $user, 'created_by_user_id' => $user,
        'due_at' => now()->addDay(), 'body' => $texto, 'status' => WhatsappReminder::STATUS_PENDING,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $meu = $novo($this->biz->id, $minha, $eu->id, 'cobrar boleto');
    $deles = $novo($this->outra->id, $daOutra, $vizinho->id, 'ligar amanhã');

    ($this->entrarComo)($eu);
    expect(WhatsappReminder::query()->whereIn('id', [$meu, $deles])->pluck('id')->all())->toBe([$meu])
        ->and(WhatsappReminder::query()->find($deles))->toBeNull();

    ($this->entrarComo)($vizinho);
    expect(WhatsappReminder::query()->whereIn('id', [$meu, $deles])->pluck('id')->all())->toBe([$deles]);

    // Controle: sem o escopo, os dois existem (o filtro é o escopo, não a falta de dado).
    expect(WhatsappReminder::query()->withoutGlobalScope(ScopeByBusiness::class)->whereIn('id', [$meu, $deles])->count())->toBe(2);
});

it('whatsapp_jana_correcoes · o usuário só enxerga as correções da própria empresa', function () {
    $minha = ($this->conversa)($this->biz->id);
    $daOutra = ($this->conversa)($this->outra->id);
    $eu = ($this->usuario)($this->biz->id);
    $vizinho = ($this->usuario)($this->outra->id);
    $msgDoBot = fn (int $biz, int $conv) => (int) DB::table('messages')->insertGetId([
        'business_id' => $biz, 'conversation_id' => $conv, 'direction' => 'outbound', 'provider' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'type' => 'text', 'body' => 'resposta errada do bot', 'status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $nova = fn (int $biz, int $conv, int $user) => (int) DB::table('whatsapp_jana_correcoes')->insertGetId([
        'business_id' => $biz, 'conversation_id' => $conv, 'message_id_errada' => $msgDoBot($biz, $conv),
        'correcao_texto' => 'Deveria ter dito o prazo certo.', 'atendente_user_id' => $user,
        'training_status' => JanaCorrecao::STATUS_PENDING_REVIEW, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $minhaCorrecao = $nova($this->biz->id, $minha, $eu->id);
    $daOutraCorrecao = $nova($this->outra->id, $daOutra, $vizinho->id);

    ($this->entrarComo)($eu);
    expect(JanaCorrecao::query()->whereIn('id', [$minhaCorrecao, $daOutraCorrecao])->pluck('id')->all())->toBe([$minhaCorrecao]);

    ($this->entrarComo)($vizinho);
    expect(JanaCorrecao::query()->whereIn('id', [$minhaCorrecao, $daOutraCorrecao])->pluck('id')->all())->toBe([$daOutraCorrecao]);

    expect(JanaCorrecao::query()->withoutGlobalScope(ScopeByBusiness::class)->whereIn('id', [$minhaCorrecao, $daOutraCorrecao])->count())->toBe(2);
});
