<?php
// @covers-us US-COMVIS-007

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\ComunicacaoVisual\Services\ComprovanteInstalacao;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DespacharMensagemDoSistemaJob;
use Tests\Support\WithSeededTenant;

/**
 * Concluir a instalação manda ao cliente o link do protocolo por WhatsApp, com opt-in LGPD
 * (US-COMVIS-007 · UC-PCV-25 · 1d).
 *
 * Âncora: o UC-PCV-25 do protótipo da Produção ("Concluiu a instalação — protocolo com fotos e assinatura
 * enviado ao cliente"; "PDF … vai para o cliente por WhatsApp") e a regra LGPD do sistema:
 * `Contact::canReceiveWhatsappNotification()`. Contrato: resources/js/Pages/ComunicacaoVisual/Index.casos.md
 * UC-CVINST-17..19.
 *
 * Caminho real, como no InstalacaoAvisoClienteContratoTest: ComprovanteInstalacao::concluir → evento
 * InstalacaoConcluida → listener do Whatsapp → NotificadorClienteWhatsapp → mensagem na conversa do cliente
 * + DespacharMensagemDoSistemaJob na fila. Só o job é falso (Bus::fake).
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite ou sem as tabelas (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

const CVCONC_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasTable('cv_instalacao_comprovantes') || ! Schema::hasTable('arquivos')
        || ! Schema::hasColumn('contacts', 'whatsapp_consent')
        || ! Schema::hasColumn('channels', 'handles_outbound_default')) {
        $this->markTestSkipped('Requer MySQL com comprovante da CV, arquivos, consentimento em contacts e channels.handles_*.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Storage::fake(config('arquivos.disk_default', 'local'));
    Storage::fake('arquivos');
    Carbon::setTestNow('2026-11-19 11:10:00');
    Bus::fake([DespacharMensagemDoSistemaJob::class]);

    $this->instalador = \App\User::factory()->create(['business_id' => $this->biz->id, 'first_name' => 'Instalador', 'last_name' => 'Delta']);
    $this->fone98 = (int) Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $this->biz->id, 'channel_uuid' => (string) Str::uuid(), 'label' => 'CV teste',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW, 'status' => 'active', 'channel_health' => 'healthy',
        'handles_outbound_default' => true, 'display_identifier' => '+55 48 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ])->id;
});

afterEach(fn () => Carbon::setTestNow());

/** "Sem celular" é string vazia: `contacts.mobile` é NOT NULL no schema. */
function cvConcContato(int $biz, int $criadoPor, string $nome, string $mobile, ?bool $consente): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome, 'mobile' => $mobile,
        'whatsapp_consent' => $consente, 'contact_status' => 'active', 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Instalação agendada da empresa $biz, de uma OS cujo orçamento aponta para $contato. */
function cvConcInstalacao(int $biz, ?int $contato, int $quem): int
{
    $orc = DB::table('comvis_orcamentos')->insertGetId([
        'business_id' => $biz, 'numero' => 'ORC-'.substr(uniqid(), -10), 'contato_id' => $contato,
        'data_emissao' => '2026-11-10', 'status' => 'aprovado', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $os = DB::table('comvis_os')->insertGetId([
        'business_id' => $biz, 'orcamento_id' => $orc, 'numero' => 'CC-'.substr(uniqid(), -10),
        'status_etapa' => 'instalacao', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('cv_instalacoes')->insertGetId([
        'business_id' => $biz, 'os_id' => $os, 'status' => 'agendada', 'veiculo' => 'Fiorino ABC1D23',
        'equipe_user_ids_json' => json_encode([$quem]), 'data_agendada' => '2026-11-19 09:00:00',
        'data_fim' => '2026-11-19 11:00:00', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Foto antes, foto depois, assinatura e concluir — como o instalador faria pela API. */
function cvConcConcluir($t, int $inst): void
{
    $c = app(ComprovanteInstalacao::class);
    foreach (['foto_antes', 'foto_depois'] as $tipo) {
        $caminho = tempnam(sys_get_temp_dir(), 'cvc');
        file_put_contents($caminho, base64_decode(CVCONC_PNG));
        $c->foto($t->biz->id, $inst, $tipo, new UploadedFile($caminho, $tipo.'.png', 'image/png', null, true), $t->instalador->id);
    }
    $c->assinatura($t->biz->id, $inst, 'data:image/png;base64,'.CVCONC_PNG, 'Maria Cliente', true, $t->instalador->id);
    $c->concluir($t->biz->id, $inst);
}

/** Mensagens do sistema gravadas pelo notificador, das duas empresas. */
function cvConcEnviados($t): array
{
    return DB::table('messages as m')->join('conversations as c', 'c.id', '=', 'm.conversation_id')
        ->where('m.direction', 'outbound')->where('m.sender_kind', 'system')
        ->whereIn('m.business_id', [$t->biz->id, $t->outra->id])
        ->orderBy('m.id')->get(['m.business_id', 'm.body', 'c.channel_id', 'c.customer_external_id'])->all();
}

it('UC-CVINST-17 · concluir manda ao cliente a OS, a hora da conclusão e o link assinado do protocolo', function () {
    $cliente = cvConcContato($this->biz->id, $this->instalador->id, 'Maria Cliente', '48988887777', null);
    $inst = cvConcInstalacao($this->biz->id, $cliente, $this->instalador->id);

    cvConcConcluir($this, $inst);

    $enviados = cvConcEnviados($this);
    $numero = DB::table('comvis_os')->join('cv_instalacoes as i', 'i.os_id', '=', 'comvis_os.id')->where('i.id', $inst)->value('comvis_os.numero');
    expect($enviados)->toHaveCount(1);
    $m = $enviados[0];
    expect((int) $m->business_id)->toBe($this->biz->id)
        ->and((int) $m->channel_id)->toBe($this->fone98)
        ->and($m->customer_external_id)->toBe('+5548988887777')
        ->and($m->body)->toContain('Olá, Maria!')
        ->and($m->body)->toContain("OS {$numero} foi concluída em 19/11/2026 às 11:10")
        ->and($m->body)->toContain('por 7 dias')
        ->and($m->body)->toContain("/instalacao-cv/{$this->biz->id}/{$inst}/protocolo?expires=")
        ->and($m->body)->toContain('signature=')
        // não expõe quem instalou nem o veículo
        ->and($m->body)->not->toContain('Delta')
        ->and($m->body)->not->toContain('Fiorino');
});

it('UC-CVINST-18 · cliente que recusou WhatsApp, sem celular, ou OS sem cliente: conclui e não manda nada', function () {
    $recusou = cvConcContato($this->biz->id, $this->instalador->id, 'Recusou', '48911112222', false);
    $semCelular = cvConcContato($this->biz->id, $this->instalador->id, 'Sem celular', '', true);
    foreach ([$recusou, $semCelular, null] as $contato) {
        $inst = cvConcInstalacao($this->biz->id, $contato, $this->instalador->id);
        cvConcConcluir($this, $inst);
        expect(DB::table('cv_instalacoes')->where('id', $inst)->value('status'))->toBe('concluida');
    }
    expect(count(cvConcEnviados($this)))->toBe(0);

    // Controle: um cliente que aceita, com celular, recebe.
    cvConcConcluir($this, cvConcInstalacao($this->biz->id,
        cvConcContato($this->biz->id, $this->instalador->id, 'Aceita', '48922223333', true), $this->instalador->id));
    expect(count(cvConcEnviados($this)))->toBe(1);
});

it('UC-CVINST-19 · contato de outra empresa no orçamento não recebe o protocolo', function () {
    $daOutra = cvConcContato($this->outra->id, $this->instalador->id, 'Cliente Noventa', '48933334444', true);
    $inst = cvConcInstalacao($this->biz->id, $daOutra, $this->instalador->id);

    cvConcConcluir($this, $inst);

    expect(DB::table('cv_instalacoes')->where('id', $inst)->value('status'))->toBe('concluida')
        ->and(count(cvConcEnviados($this)))->toBe(0);
});
