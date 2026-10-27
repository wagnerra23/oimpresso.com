<?php
// @covers-us US-COMVIS-007
// @covers-us US-WA-325

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\ComunicacaoVisual\Services\AgendaInstalacao;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DespacharMensagemDoSistemaJob;
use Tests\Support\WithSeededTenant;

/**
 * Agendar a instalação avisa o cliente por WhatsApp, com opt-in LGPD (US-COMVIS-007 · UC-FLX-21).
 *
 * Âncora: UC-FLX-21 ("agenda equipe e veículo; avisa o cliente") e a regra LGPD do sistema:
 * `Contact::canReceiveWhatsappNotification()` — opt-out explícito bloqueia, null permite. Contrato:
 * resources/js/Pages/ComunicacaoVisual/Index.casos.md UC-CVINST-08..10.
 *
 * Caminho real: AgendaInstalacao::agendar → evento InstalacaoAgendada → listener do módulo Whatsapp
 * (registrado no provider) → NotificadorClienteWhatsapp (US-WA-319) → mensagem na conversa do cliente
 * no canal omnichannel + DespacharMensagemDoSistemaJob na fila. Só o job é falso (Bus::fake); evento,
 * listener e notificador são os de produção.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite ou sem a migration da agenda (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasColumn('cv_instalacoes', 'os_id')
        || ! Schema::hasColumn('contacts', 'whatsapp_consent')
        || ! Schema::hasColumn('channels', 'handles_outbound_default')) {
        $this->markTestSkipped('Requer MySQL com agenda da CV, consentimento em contacts e channels.handles_*.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Carbon::setTestNow('2026-10-20 08:00:00');
    Bus::fake([DespacharMensagemDoSistemaJob::class]);

    $this->instalador = \App\User::factory()->create(['business_id' => $this->biz->id, 'first_name' => 'Instalador', 'last_name' => 'Delta']);
    $this->fone98 = cvAvisoFone($this->biz->id);
    $this->fone99 = cvAvisoFone($this->outra->id);
    $this->cliente = cvAvisoContato($this->biz->id, $this->instalador->id, 'Maria Cliente', '48988887777', null);
    $this->os = cvAvisoOs($this->biz->id, $this->cliente);
});

afterEach(fn () => Carbon::setTestNow());

function cvAvisoFone(int $biz): int
{
    return (int) Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => 'CV teste',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW, 'status' => 'active', 'channel_health' => 'healthy',
        'handles_outbound_default' => true, 'display_identifier' => '+55 48 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ])->id;
}

/** "Sem celular" é string vazia: `contacts.mobile` é NOT NULL no schema (medido na lane MySQL). */
function cvAvisoContato(int $biz, int $criadoPor, string $nome, string $mobile, ?bool $consente): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome, 'mobile' => $mobile,
        'whatsapp_consent' => $consente, 'contact_status' => 'active', 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** OS da empresa $biz cujo orçamento aponta para o contato $contato (que pode ser de outra empresa). */
function cvAvisoOs(int $biz, ?int $contato): int
{
    $orc = DB::table('comvis_orcamentos')->insertGetId([
        'business_id' => $biz, 'numero' => 'ORC-'.substr(uniqid(), -10), 'contato_id' => $contato,
        'data_emissao' => '2026-10-20', 'status' => 'aprovado', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('comvis_os')->insertGetId([
        'business_id' => $biz, 'orcamento_id' => $orc, 'numero' => 'AV-'.substr(uniqid(), -10),
        'status_etapa' => 'instalacao', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cvAvisoAgendar($t, int $os, string $inicio = '09:00', string $fim = '11:00'): void
{
    app(AgendaInstalacao::class)->agendar(
        $t->biz->id, $os, Carbon::parse("2026-10-21 {$inicio}"), Carbon::parse("2026-10-21 {$fim}"),
        [$t->instalador->id], 'Fiorino ABC1D23',
    );
}

/** Avisos gravados pelo notificador (mensagens do sistema), das duas empresas, com o canal e o número da conversa. */
function cvAvisoEnviados(): array
{
    return DB::table('messages as m')->join('conversations as c', 'c.id', '=', 'm.conversation_id')
        ->where('m.direction', 'outbound')->where('m.sender_kind', 'system')
        ->whereIn('m.business_id', [test()->biz->id, test()->outra->id])
        ->orderBy('m.id')->get(['m.business_id', 'm.body', 'c.channel_id', 'c.customer_external_id'])->all();
}

it('UC-CVINST-08 · agendar avisa o cliente pelo número de saída da empresa, com OS, dia e hora', function () {
    cvAvisoAgendar($this, $this->os);

    $jobs = cvAvisoEnviados();
    $numero = DB::table('comvis_os')->where('id', $this->os)->value('numero');
    expect($jobs)->toHaveCount(1);
    $job = $jobs[0];
    expect((int) $job->business_id)->toBe($this->biz->id)
        ->and((int) $job->channel_id)->toBe($this->fone98)
        ->and($job->customer_external_id)->toBe('+5548988887777')
        ->and($job->body)->toContain("OS {$numero}")
        ->and($job->body)->toContain('21/10/2026 às 09:00')
        ->and($job->body)->toContain('Olá, Maria!')
        // não expõe quem instala nem o veículo
        ->and($job->body)->not->toContain('Delta')
        ->and($job->body)->not->toContain('Fiorino');

    // Recusa por falta de janela não grava e não avisa.
    expect(fn () => cvAvisoAgendar($this, cvAvisoOs($this->biz->id, $this->cliente), '10:00', '12:00'))
        ->toThrow(\DomainException::class);
    expect(count(cvAvisoEnviados()))->toBe(1);
});

it('UC-CVINST-09 · cliente que recusou WhatsApp, sem celular, sem cliente ou sem número de saída ativo: não avisa', function () {
    $recusou = cvAvisoContato($this->biz->id, $this->instalador->id, 'Recusou', '48911112222', false);
    cvAvisoAgendar($this, cvAvisoOs($this->biz->id, $recusou), '07:00', '08:00');
    cvAvisoAgendar($this, cvAvisoOs($this->biz->id, cvAvisoContato($this->biz->id, $this->instalador->id, 'Sem celular', '', true)), '08:00', '09:00');
    cvAvisoAgendar($this, cvAvisoOs($this->biz->id, null), '11:00', '12:00');
    expect(count(cvAvisoEnviados()))->toBe(0);

    DB::table('channels')->where('id', $this->fone98)->update(['channel_health' => 'banned']);
    cvAvisoAgendar($this, $this->os, '13:00', '14:00');
    expect(count(cvAvisoEnviados()))->toBe(0);

    // Controle: o mesmo cliente, com canal de volta, recebe.
    DB::table('channels')->where('id', $this->fone98)->update(['channel_health' => 'healthy']);
    cvAvisoAgendar($this, $this->os, '15:00', '16:00');
    expect(count(cvAvisoEnviados()))->toBe(1);
});

it('UC-CVINST-10 · contato de outra empresa no orçamento não é avisado, e o número da outra empresa nunca é usado', function () {
    $daOutra = cvAvisoContato($this->outra->id, $this->instalador->id, 'Cliente Noventa', '48933334444', true);
    cvAvisoAgendar($this, cvAvisoOs($this->biz->id, $daOutra), '07:00', '08:00');
    expect(count(cvAvisoEnviados()))->toBe(0);

    // A 98 sem número próprio: a 99 tem número, e ele não serve.
    DB::table('channels')->where('id', $this->fone98)->update(['handles_outbound_default' => false]);
    cvAvisoAgendar($this, $this->os, '13:00', '14:00');
    expect(count(cvAvisoEnviados()))->toBe(0)
        ->and((int) DB::table('channels')->where('id', $this->fone99)->value('business_id'))->toBe($this->outra->id);
});
