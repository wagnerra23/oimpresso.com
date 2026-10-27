<?php

declare(strict_types=1);

// @covers-us US-CONSULTA-004
// @covers-us US-WA-004
// @covers-us US-WA-325

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Repair\Events\RepairStatusChanged;
use Modules\Repair\Tests\Support\JobSheetFixtures as Fx;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DespacharMensagemDoSistemaJob;
use Tests\Support\WithSeededTenant;

/**
 * UC-FLX-24 "Cliente acompanha o status" — ponta a ponta, do balcão ao portal.
 *
 * O caminho medido (2026-10-20):
 *   PUT /repair/job-sheet/{id} muda `status_id`
 *     → JobSheetController::update dispara RepairStatusChanged(os, nome do status)
 *     → Modules\Whatsapp\Listeners\NotifyRepairCustomer → NotificadorClienteWhatsapp (US-WA-319):
 *       mensagem na conversa do cliente no canal omnichannel + DespacharMensagemDoSistemaJob na fila
 *     → /consulta-os/buscar lê `status_id` (repair_statuses) e devolve o status novo.
 *
 * QUEM DECIDE O AVISO (decisão [W] 2026-10-21): a marca `repair_statuses.whatsapp_template_aviso`
 *   ('ready' | 'waiting_parts' | NULL), configurada em /repair/status, viaja no evento. O nome do
 *   status não decide mais. Até 2026-10-21 só os nomes LITERAIS 'ready'/'waiting_parts' avisavam,
 *   e um status chamado "Pronto para retirada" mudava o portal sem avisar (era o UC-COS-13).
 *   `is_completed_status` não serve de sinal: vale "Pronto" no kanban e "Entregue" no startPipeline.
 *
 * O outro limite (transição pela FSM não avisa) está no RepairFsmActionControllerTest,
 * que monta o pipeline em SQLite.
 *
 * TENANT: seededTenant() = 98 contra seededSupportClientTenant() = 99 (ADR 0358). Nunca 4.
 * Roda só em MySQL (schema UltimatePOS) — na lane sqlite pula pelo guard das fixtures.
 *
 * @see resources/js/Pages/ConsultaOs/Index.casos.md (UC-COS-12, UC-COS-13, UC-COS-31, UC-COS-32)
 * @see memory/requisitos/ConsultaOs/SPEC.md (US-CONSULTA-004)
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

beforeEach(function () {
    // Só liga a limpeza do afterEach quando o teste de fato roda: na lane sqlite ele é pulado
    // e as tabelas do UltimatePOS (contacts, repair_*) não existem.
    $GLOBALS['flx24_ativo'] = false;
    if (($motivo = Fx::motivoDeSkip()) !== null) {
        $this->markTestSkipped($motivo);
    }
    if (! Schema::hasColumn('channels', 'handles_repair_status')) {
        $this->markTestSkipped('Tabela channels sem handles_repair_status — módulo Whatsapp não migrado.');
    }
    if (! Event::hasListeners(RepairStatusChanged::class)) {
        $this->markTestSkipped('Nenhum listener de RepairStatusChanged — módulo Whatsapp desligado neste ambiente.');
    }

    $GLOBALS['flx24_ativo'] = true;
    $GLOBALS['flx24_telefones'] = [];
    // Teto dos status antes do teste: o que nascer acima dele é fixture deste arquivo.
    $GLOBALS['flx24_status_teto'] = (int) DB::table('repair_statuses')->max('id');
});

afterEach(function () {
    if (! ($GLOBALS['flx24_ativo'] ?? false)) {
        return;
    }
    // O tenant 98 é compartilhado (CT 100 e a lane verticais-pest, que não recria o banco
    // entre arquivos): número de WhatsApp, OS, status e clientes fictícios não podem ficar.
    // Medido 2026-10-21: o status "Recebido" vazio que sobrava daqui virava o "primeiro
    // status pendente" do RepairDashboardContratoTest (UC-RDSH-02) e mudava a contagem dele.
    if (! empty($GLOBALS['flx24_telefones'] ?? [])) {
        $conversas = DB::table('conversations')->whereIn('channel_id', $GLOBALS['flx24_telefones'])->pluck('id');
        DB::table('messages')->whereIn('conversation_id', $conversas)->delete();
        DB::table('conversations')->whereIn('id', $conversas)->delete();
        DB::table('channels')->whereIn('id', $GLOBALS['flx24_telefones'])->delete();
    }

    $contatos = DB::table('contacts')->where('name', 'like', '%FLX-24%')->pluck('id');
    if ($contatos->isNotEmpty()) {
        DB::table('repair_job_sheets')->whereIn('contact_id', $contatos)->delete();
        DB::table('contacts')->whereIn('id', $contatos)->delete();
    }

    $teto = (int) ($GLOBALS['flx24_status_teto'] ?? PHP_INT_MAX);
    DB::table('repair_statuses')
        ->where('id', '>', $teto)
        ->whereIn('name', ['Recebido', 'ready', 'Pronto para retirada', 'Aguardando peça'])
        ->whereNotIn('id', DB::table('repair_job_sheets')->select('status_id')->whereNotNull('status_id'))
        ->delete();
});

/** Canal de WhatsApp do tenant marcado para aviso de OS. */
function flx24Telefone(int $businessId): int
{
    $id = (int) Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $businessId,
        'channel_uuid' => (string) Str::uuid(),
        'label' => 'Balcao FLX-24',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'status' => 'active',
        'channel_health' => 'healthy',
        'handles_repair_status' => true,
        'handles_outbound_default' => false,
        'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ])->id;
    $GLOBALS['flx24_telefones'][] = $id;

    return $id;
}

/** Avisos que o notificador gravou (mensagens do sistema), com o canal e o número da conversa. */
function flx24Avisos(): array
{
    return DB::table('messages as m')->join('conversations as c', 'c.id', '=', 'm.conversation_id')
        ->where('m.direction', 'outbound')->where('m.sender_kind', 'system')
        ->whereIn('c.channel_id', $GLOBALS['flx24_telefones'] ?? [])
        ->orderBy('m.id')->get(['m.business_id', 'm.body', 'c.channel_id', 'c.customer_external_id'])->all();
}

/** Busca pública, sem login (o portal não tem sessão). */
function flx24Portal(object $t, string $numero): array
{
    auth()->guard('web')->logout();
    session()->flush();

    $resposta = $t->getJson('/consulta-os/buscar?'.http_build_query(['tipo' => 'job_sheet_no', 'numero' => $numero]));
    $resposta->assertOk();

    return $resposta->json();
}

it('UC-COS-12: mudar o status da OS avisa o cliente certo e o portal mostra o status novo', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();

    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente FLX-24');
    DB::table('contacts')->where('id', $contato)->update(['mobile' => '+5547900000098']);
    $statusInicial = Fx::status((int) $biz->id, 'Recebido');
    // A marca do status (whatsapp_template_aviso) decide o aviso; o nome aqui é só o rótulo.
    $statusNovo = Fx::status((int) $biz->id, 'ready');
    DB::table('repair_statuses')->where('id', $statusNovo)->update(['whatsapp_template_aviso' => 'ready']);
    $osId = Fx::os((int) $biz->id, $contato, $statusInicial, (int) $user->id);
    $numero = (string) DB::table('repair_job_sheets')->where('id', $osId)->value('job_sheet_no');
    $telefone = flx24Telefone((int) $biz->id);

    // A outra empresa tem OS, cliente e número de WhatsApp próprios — nada dela pode sair aqui.
    $donoVizinho = Fx::usuario((int) $vizinho->id, false);
    $contatoVizinho = Fx::cliente((int) $vizinho->id, (int) $donoVizinho->id, 'Cliente Vizinho FLX-24');
    DB::table('contacts')->where('id', $contatoVizinho)->update(['mobile' => '+5547900000099']);
    $statusVizinho = Fx::status((int) $vizinho->id, 'ready');
    DB::table('repair_statuses')->where('id', $statusVizinho)->update(['whatsapp_template_aviso' => 'ready']);
    $osVizinha = Fx::os((int) $vizinho->id, $contatoVizinho, $statusVizinho, (int) $donoVizinho->id);
    $numeroVizinho = (string) DB::table('repair_job_sheets')->where('id', $osVizinha)->value('job_sheet_no');
    flx24Telefone((int) $vizinho->id);

    Bus::fake([DespacharMensagemDoSistemaJob::class]);

    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $statusNovo,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-FLX24',
    ]);

    expect((int) DB::table('repair_job_sheets')->where('id', $osId)->value('status_id'))->toBe($statusNovo);

    // Aviso: exatamente um, pelo canal desta empresa, para o celular deste cliente, dizendo que está pronta.
    $avisos = flx24Avisos();
    expect($avisos)->toHaveCount(1);
    expect((int) $avisos[0]->business_id)->toBe((int) $biz->id)
        ->and((int) $avisos[0]->channel_id)->toBe($telefone)
        ->and($avisos[0]->customer_external_id)->toBe('+5547900000098')
        ->and($avisos[0]->body)->toContain("OS nº {$numero}")->toContain('pronta para retirada');
    Bus::assertDispatchedTimes(DespacharMensagemDoSistemaJob::class, 1);

    // Portal: a busca pelo nº da OS devolve só esta OS, já com o status novo.
    $corpo = flx24Portal($this, $numero);
    expect($corpo['found'])->toBeTrue();
    expect($corpo['ordens'])->toHaveCount(1);
    expect($corpo['ordens'][0]['numero'])->toBe($numero);
    expect($corpo['ordens'][0]['status']['nome'])->toBe('ready');
    expect(json_encode($corpo))->not->toContain($numeroVizinho);
    expect(json_encode($corpo))->not->toContain('Cliente Vizinho FLX-24');
});

it('UC-COS-13: status com o nome que a empresa deu avisa pelo WhatsApp quando está marcado', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente FLX-24 PT');
    DB::table('contacts')->where('id', $contato)->update(['mobile' => '+5547900000198']);
    $statusInicial = Fx::status((int) $biz->id, 'Recebido');
    $statusPronto = Fx::status((int) $biz->id, 'Pronto para retirada');
    DB::table('repair_statuses')->where('id', $statusPronto)->update(['whatsapp_template_aviso' => 'ready']);
    $osId = Fx::os((int) $biz->id, $contato, $statusInicial, (int) $user->id);
    $numero = (string) DB::table('repair_job_sheets')->where('id', $osId)->value('job_sheet_no');
    $telefone = flx24Telefone((int) $biz->id);

    Bus::fake([DespacharMensagemDoSistemaJob::class]);

    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $statusPronto,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-FLX24-PT',
    ]);

    // O nome em português não atrapalha: a marca do status manda a mensagem de pronto.
    $avisos = flx24Avisos();
    expect($avisos)->toHaveCount(1);
    expect((int) $avisos[0]->channel_id)->toBe($telefone)
        ->and($avisos[0]->customer_external_id)->toBe('+5547900000198')
        ->and($avisos[0]->body)->toContain('pronta para retirada');

    $corpo = flx24Portal($this, $numero);
    expect($corpo['ordens'][0]['status']['nome'])->toBe('Pronto para retirada');
});

it('UC-COS-33: status sem marca não avisa, mesmo com o nome literal ready; a marca de aguardando peças manda a outra mensagem', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente FLX-24 Sem Marca');
    DB::table('contacts')->where('id', $contato)->update(['mobile' => '+5547900000698']);
    $statusInicial = Fx::status((int) $biz->id, 'Recebido');
    // Nome que antes avisava sozinho; sem a marca, hoje não avisa.
    $semMarca = Fx::status((int) $biz->id, 'ready');
    DB::table('repair_statuses')->where('id', $semMarca)->update(['whatsapp_template_aviso' => null]);
    $aguardando = Fx::status((int) $biz->id, 'Aguardando peça');
    DB::table('repair_statuses')->where('id', $aguardando)->update(['whatsapp_template_aviso' => 'waiting_parts']);
    $osId = Fx::os((int) $biz->id, $contato, $statusInicial, (int) $user->id);
    flx24Telefone((int) $biz->id);

    Bus::fake([DespacharMensagemDoSistemaJob::class]);

    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $semMarca,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-FLX24-SM',
    ]);
    expect((int) DB::table('repair_job_sheets')->where('id', $osId)->value('status_id'))->toBe($semMarca);
    expect(flx24Avisos())->toBe([]);

    // Controle: a mesma OS indo para um status marcado avisa, com a mensagem de aguardando peça.
    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $aguardando,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-FLX24-SM',
    ]);
    $avisos = flx24Avisos();
    expect($avisos)->toHaveCount(1);
    expect($avisos[0]->customer_external_id)->toBe('+5547900000698')
        ->and($avisos[0]->body)->toContain('aguardando peça');
});

it('UC-COS-31: cliente que recusou WhatsApp não recebe o aviso; sem resposta (null) ainda recebe', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    $telefone = flx24Telefone((int) $biz->id);

    $recusou = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente FLX-24 Recusou');
    DB::table('contacts')->where('id', $recusou)->update(['mobile' => '+5547900000298', 'whatsapp_consent' => 0]);
    $semResposta = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente FLX-24 Sem Resposta');
    DB::table('contacts')->where('id', $semResposta)->update(['mobile' => '+5547900000398', 'whatsapp_consent' => null]);

    Bus::fake([DespacharMensagemDoSistemaJob::class]);
    $listener = app(\Modules\Whatsapp\Listeners\NotifyRepairCustomer::class);

    // Opt-out explícito (LGPD): nenhum aviso.
    $listener->handle((object) ['id' => 9801, 'business_id' => (int) $biz->id, 'contact_id' => $recusou], 'ready');
    expect(flx24Avisos())->toBe([]);

    // Controle: mesmo canal, mesma mensagem, contato sem resposta de consentimento → avisa.
    $listener->handle((object) ['id' => 9802, 'business_id' => (int) $biz->id, 'contact_id' => $semResposta], 'ready');
    $avisos = flx24Avisos();
    expect($avisos)->toHaveCount(1);
    expect((int) $avisos[0]->business_id)->toBe((int) $biz->id)
        ->and((int) $avisos[0]->channel_id)->toBe($telefone)
        ->and($avisos[0]->customer_external_id)->toBe('+5547900000398');
});

it('UC-COS-32: OS que aponta para cliente de outra empresa não avisa ninguém', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();
    $user = Fx::usuario((int) $biz->id);
    flx24Telefone((int) $biz->id);

    $donoVizinho = Fx::usuario((int) $vizinho->id, false);
    $contatoVizinho = Fx::cliente((int) $vizinho->id, (int) $donoVizinho->id, 'Cliente Vizinho FLX-24 Cruzado');
    DB::table('contacts')->where('id', $contatoVizinho)->update(['mobile' => '+5547900000499', 'whatsapp_consent' => 1]);

    $proprio = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente FLX-24 Proprio');
    DB::table('contacts')->where('id', $proprio)->update(['mobile' => '+5547900000598', 'whatsapp_consent' => 1]);

    Bus::fake([DespacharMensagemDoSistemaJob::class]);
    $listener = app(\Modules\Whatsapp\Listeners\NotifyRepairCustomer::class);

    // OS da 98 com contact_id da 99: o número da 98 não pode escrever para o cliente da 99.
    $listener->handle((object) ['id' => 9803, 'business_id' => (int) $biz->id, 'contact_id' => $contatoVizinho], 'ready');
    expect(flx24Avisos())->toBe([]);

    // Controle: o cliente da própria empresa recebe.
    $listener->handle((object) ['id' => 9804, 'business_id' => (int) $biz->id, 'contact_id' => $proprio], 'ready');
    $avisos = flx24Avisos();
    expect($avisos)->toHaveCount(1)->and($avisos[0]->customer_external_id)->toBe('+5547900000598');
});
