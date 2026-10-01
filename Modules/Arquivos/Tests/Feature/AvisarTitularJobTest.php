<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Jobs\AvisarTitularJob;
use Modules\Arquivos\Mail\AvisoTitularMail;
use Modules\Arquivos\Services\AvisoTitularCanais;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

uses(Tests\TestCase::class);

/**
 * Aviso ao titular — ENVIO. ADR 0422 (canal: [W] 2026-10-01 *"e-mail e whatsapp. pode ter
 * configuração"*), sobre o registro da ADR 0421.
 *
 * Contrato:
 *   - canal desligado no negócio (default) → nada sai, nada é registrado;
 *   - e-mail ligado + opt-in → Mail enviado ao titular e aviso registrado (canal=email);
 *   - WhatsApp ligado + opt-in + número outbound → SendWhatsappMessageJob síncrono e registro;
 *   - opt-out LGPD (`email_consent`/`whatsapp_consent` = false) bloqueia aquele canal;
 *   - nenhum canal saiu → NÃO registra (registro falso é o pior desfecho);
 *   - cross-tenant: job com business errado, ou titular de outro business → nada sai.
 *
 * Nada é enviado de verdade: Mail::fake + Bus::fake do job de WhatsApp.
 * Tenant fictício 98, adversário 99 (ADR 0358). MySQL-only (enum `notice`).
 */

const AVISAR_TITULAR_MARCA = 'test-adr0422-avisar-titular';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('enum `notice` é MySQL-only — roda na lane arquivos-pest.');
    }
    if (! Schema::hasColumn('arquivos', 'titular_avisado_at') || ! Schema::hasColumn('contacts', 'email_consent')) {
        $this->markTestSkipped('migrations do aviso ao titular / consent não aplicadas.');
    }
    if (! DB::table('business')->where('id', 98)->exists()) {
        $this->markTestSkipped('tenant fictício 98 não semeado.');
    }

    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    Mail::fake();
    Bus::fake([SendWhatsappMessageJob::class]);

    $this->settings98 = DB::table('business')->where('id', 98)->value('common_settings');
    $this->user98 = (int) DB::table('users')->where('business_id', 98)->value('id');
});

afterEach(function () {
    Carbon::setTestNow(null);
    if (DB::connection()->getDriverName() === 'sqlite' || ! isset($this->user98)) {
        return;
    }
    DB::table('business')->where('id', 98)->update(['common_settings' => $this->settings98]);

    $ids = DB::table('arquivos')->where('classified_by', AVISAR_TITULAR_MARCA)->pluck('id');
    DB::table('arquivos_audit_log')->whereIn('arquivo_id', $ids)->delete();
    DB::table('arquivos')->whereIn('id', $ids)->delete();
    DB::table('contacts')->where('name', 'like', 'Titular ' . AVISAR_TITULAR_MARCA . '%')->delete();
    if (Schema::hasTable('whatsapp_business_phones')) {
        DB::table('whatsapp_business_phones')->where('label', AVISAR_TITULAR_MARCA)->delete();
    }
});

function avisarTitularContato(int $biz, int $userId, array $over = []): int
{
    return (int) DB::table('contacts')->insertGetId(array_merge([
        'business_id' => $biz,
        'type'        => 'customer',
        'name'        => 'Titular ' . AVISAR_TITULAR_MARCA,
        'email'       => 'titular@example.test',
        'mobile'      => '5548999990000',
        'created_by'  => $userId,
        'created_at'  => now(),
        'updated_at'  => now(),
    ], $over));
}

/** Arquivo sensível do contato, vencendo em 15 dias (prazo 365d). */
function avisarTitularArquivo(int $biz, int $contactId): int
{
    $criado = Carbon::now()->copy()->subDays(365 - 15);

    return (int) DB::table('arquivos')->insertGetId([
        'business_id'     => $biz,
        'arquivable_type' => \App\Contact::class,
        'arquivable_id'   => $contactId,
        'disk'            => 'local',
        'storage_path'    => "biz-{$biz}/adr0422-{$contactId}.pdf",
        'original_name'   => "adr0422-{$contactId}.pdf",
        'mime_type'       => 'application/pdf',
        'size_bytes'      => 10,
        'md5'             => md5("adr0422-{$contactId}"),
        'bucket'          => 'sensitive',
        'retention_days'  => 365,
        'classified_by'   => AVISAR_TITULAR_MARCA,
        'created_at'      => $criado,
        'updated_at'      => $criado,
    ]);
}

function avisarTitularRodar(int $biz, int $arquivoId): array
{
    return (new AvisarTitularJob($biz, $arquivoId))->handle(
        app(\Modules\Arquivos\Services\AvisoTitularService::class),
        app(AvisoTitularCanais::class),
    );
}

function avisarTitularNumeroWhatsapp(int $biz): void
{
    DB::table('whatsapp_business_phones')->insert([
        'business_id'              => $biz,
        'phone_uuid'               => (string) \Illuminate\Support\Str::uuid(),
        'label'                    => AVISAR_TITULAR_MARCA,
        'handles_outbound_default' => true,
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);
}

it('aviso-envio 1 · canais desligados (default) → nada sai e nada é registrado', function () {
    $id = avisarTitularArquivo(98, avisarTitularContato(98, $this->user98));

    expect(app(AvisoTitularCanais::class)->ativos(98))->toBe(['email' => false, 'whatsapp' => false]);
    expect(avisarTitularRodar(98, $id))->toBe([]);

    Mail::assertNothingSent();
    Bus::assertNotDispatchedSync(SendWhatsappMessageJob::class);
    expect(DB::table('arquivos')->where('id', $id)->value('titular_avisado_at'))->toBeNull();
});

it('aviso-envio 2 · e-mail ligado + opt-in → e-mail ao titular e aviso registrado com canal=email', function () {
    app(AvisoTitularCanais::class)->definir(98, true, null);
    $id = avisarTitularArquivo(98, avisarTitularContato(98, $this->user98));

    expect(avisarTitularRodar(98, $id))->toBe(['email']);

    Mail::assertSent(AvisoTitularMail::class, fn (AvisoTitularMail $m) => $m->hasTo('titular@example.test')
        && $m->venceEm === '16/10/2026');
    expect(DB::table('arquivos')->where('id', $id)->value('titular_avisado_at'))->not->toBeNull();

    $payload = json_decode((string) DB::table('arquivos_audit_log')
        ->where('arquivo_id', $id)->where('action', 'notice')->value('payload'), true);
    expect($payload['canal'])->toBe('email');
    expect(json_encode($payload))->not->toContain('titular@example.test');
});

it('aviso-envio 3 · opt-out de e-mail (email_consent=false) bloqueia e não registra', function () {
    app(AvisoTitularCanais::class)->definir(98, true, false);
    $id = avisarTitularArquivo(98, avisarTitularContato(98, $this->user98, ['email_consent' => false]));

    expect(avisarTitularRodar(98, $id))->toBe([]);

    Mail::assertNothingSent();
    expect(DB::table('arquivos')->where('id', $id)->value('titular_avisado_at'))->toBeNull();
    expect(DB::table('arquivos_audit_log')->where('arquivo_id', $id)->where('action', 'notice')->count())->toBe(0);
});

it('aviso-envio 4 · WhatsApp ligado + opt-in + número outbound → envio síncrono e registro', function () {
    if (! Schema::hasTable('whatsapp_business_phones')) {
        $this->markTestSkipped('módulo Whatsapp sem tabela de números.');
    }
    app(AvisoTitularCanais::class)->definir(98, false, true);
    avisarTitularNumeroWhatsapp(98);
    $id = avisarTitularArquivo(98, avisarTitularContato(98, $this->user98));

    expect(avisarTitularRodar(98, $id))->toBe(['whatsapp']);

    Bus::assertDispatchedSync(SendWhatsappMessageJob::class, fn (SendWhatsappMessageJob $j) => $j->businessId === 98
        && $j->to === '5548999990000'
        && $j->kind === 'freeform'
        && str_contains($j->payload['body'], '16/10/2026'));
    Mail::assertNothingSent();
    expect(DB::table('arquivos')->where('id', $id)->value('titular_avisado_at'))->not->toBeNull();
});

it('aviso-envio 5 · opt-out de WhatsApp bloqueia só o WhatsApp; e-mail segue', function () {
    if (! Schema::hasTable('whatsapp_business_phones')) {
        $this->markTestSkipped('módulo Whatsapp sem tabela de números.');
    }
    app(AvisoTitularCanais::class)->definir(98, true, true);
    avisarTitularNumeroWhatsapp(98);
    $id = avisarTitularArquivo(98, avisarTitularContato(98, $this->user98, ['whatsapp_consent' => false]));

    expect(avisarTitularRodar(98, $id))->toBe(['email']);

    Bus::assertNotDispatchedSync(SendWhatsappMessageJob::class);
    Mail::assertSent(AvisoTitularMail::class);
});

it('aviso-envio 6 · cross-tenant: job com business 99 não envia nem marca arquivo do 98', function () {
    app(AvisoTitularCanais::class)->definir(98, true, null);
    $id = avisarTitularArquivo(98, avisarTitularContato(98, $this->user98));

    expect(avisarTitularRodar(99, $id))->toBe([]);

    Mail::assertNothingSent();
    expect(DB::table('arquivos')->where('id', $id)->value('titular_avisado_at'))->toBeNull();
});

it('aviso-envio 7 · cross-tenant: titular de OUTRO business como dono do arquivo do 98 → nada sai', function () {
    app(AvisoTitularCanais::class)->definir(98, true, null);
    // Adversário canônico biz=99 (ADR 0358) — nunca "qualquer business ≠ 98", que cairia no
    // biz=1 (empresa real) no CT 100.
    $bizAlheio = (int) $this->seededSupportClientTenant()->id;
    $userAlheio = (int) DB::table('users')->where('business_id', $bizAlheio)->value('id');
    $contatoAlheio = avisarTitularContato($bizAlheio, $userAlheio);
    $id = avisarTitularArquivo(98, $contatoAlheio);

    expect(avisarTitularRodar(98, $id))->toBe([]);

    Mail::assertNothingSent();
    expect(DB::table('arquivos')->where('id', $id)->value('titular_avisado_at'))->toBeNull();
});

it('aviso-envio 8 · idempotente: segunda rodada não reenvia', function () {
    app(AvisoTitularCanais::class)->definir(98, true, null);
    $id = avisarTitularArquivo(98, avisarTitularContato(98, $this->user98));

    expect(avisarTitularRodar(98, $id))->toBe(['email']);
    expect(avisarTitularRodar(98, $id))->toBe([]);

    Mail::assertSent(AvisoTitularMail::class, 1);
});

it('aviso-envio 9 · configuração é por negócio: ligar no 98 não liga no 99 e preserva o resto do JSON', function () {
    DB::table('business')->where('id', 98)->update(['common_settings' => json_encode(['enable_lot_number' => 1])]);

    $estado = app(AvisoTitularCanais::class)->definir(98, true, false);

    expect($estado)->toBe(['email' => true, 'whatsapp' => false]);
    expect(app(AvisoTitularCanais::class)->ativos(99))->toBe(['email' => false, 'whatsapp' => false]);
    $json = json_decode((string) DB::table('business')->where('id', 98)->value('common_settings'), true);
    expect($json['enable_lot_number'])->toBe(1);
});

it('aviso-envio 10 · --todos (uso do agendador) só envia em negócio com canal ligado', function () {
    app(AvisoTitularCanais::class)->definir(98, true, null);
    $id = avisarTitularArquivo(98, avisarTitularContato(98, $this->user98));

    $this->artisan('arquivos:avisar-titulares', ['--todos' => true])->assertSuccessful();

    Mail::assertSent(AvisoTitularMail::class, 1);
    expect(DB::table('arquivos')->where('id', $id)->value('titular_avisado_at'))->not->toBeNull();

    // Desligado de novo: o agendador não toca o negócio, mesmo com arquivo novo na janela.
    app(AvisoTitularCanais::class)->definir(98, false, false);
    $outro = avisarTitularArquivo(98, avisarTitularContato(98, $this->user98, ['email' => 'outro@example.test']));

    $this->artisan('arquivos:avisar-titulares', ['--todos' => true])->assertSuccessful();

    Mail::assertSent(AvisoTitularMail::class, 1);
    expect(DB::table('arquivos')->where('id', $outro)->value('titular_avisado_at'))->toBeNull();
});

it('aviso-envio 11 · agendado diário 10:00 BRT com --todos, só em live', function () {
    $evento = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'arquivos:avisar-titulares --todos'));

    expect($evento)->not->toBeNull();
    expect($evento->expression)->toBe('0 10 * * *');
    expect($evento->timezone)->toBe('America/Sao_Paulo');
    expect($evento->runsInEnvironment('live'))->toBeTrue();
    expect($evento->runsInEnvironment('testing'))->toBeFalse();
});
