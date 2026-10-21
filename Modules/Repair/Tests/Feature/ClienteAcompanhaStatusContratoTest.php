<?php

declare(strict_types=1);

// @covers-us US-CONSULTA-004

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Repair\Events\RepairStatusChanged;
use Modules\Repair\Tests\Support\JobSheetFixtures as Fx;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;
use Tests\Support\WithSeededTenant;

/**
 * UC-FLX-24 "Cliente acompanha o status" — ponta a ponta, do balcão ao portal.
 *
 * O caminho medido (2026-10-20):
 *   PUT /repair/job-sheet/{id} muda `status_id`
 *     → JobSheetController::update dispara RepairStatusChanged(os, nome do status)
 *     → Modules\Whatsapp\Listeners\NotifyRepairCustomer enfileira SendWhatsappMessageJob
 *     → /consulta-os/buscar lê `status_id` (repair_statuses) e devolve o status novo.
 *
 * O LIMITE que este arquivo trava (estado de hoje, não desejo):
 *   o listener só reconhece os nomes LITERAIS 'ready' e 'waiting_parts'. O nome do status é
 *   escrito por cada empresa ("Pronto para retirada"), então com nome em português o aviso
 *   não sai — o portal muda, o WhatsApp não. Não há no `repair_statuses` um sinal limpo
 *   para "este status avisa como pronto": `is_completed_status` é usado como "Pronto" no
 *   kanban (ProducaoOficinaController::findStatusForColumn) e como "Entregue" no
 *   startPipeline (RepairFsmActionController), e mandar "seu aparelho está pronto" para
 *   quem já retirou é mensagem errada. Decidir o sinal é produto ([W]).
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
    if (($motivo = Fx::motivoDeSkip()) !== null) {
        $this->markTestSkipped($motivo);
    }
    if (! Schema::hasTable('whatsapp_business_phones')) {
        $this->markTestSkipped('Tabela whatsapp_business_phones ausente — módulo Whatsapp não migrado.');
    }
    if (! Event::hasListeners(RepairStatusChanged::class)) {
        $this->markTestSkipped('Nenhum listener de RepairStatusChanged — módulo Whatsapp desligado neste ambiente.');
    }

    $GLOBALS['flx24_telefones'] = [];
});

afterEach(function () {
    // O tenant 98 é compartilhado no CT 100: o número de WhatsApp fictício não pode ficar
    // para outros testes do mesmo tenant.
    if (! empty($GLOBALS['flx24_telefones'] ?? [])) {
        DB::table('whatsapp_business_phones')->whereIn('id', $GLOBALS['flx24_telefones'])->delete();
    }
});

/** Número de WhatsApp do tenant que atende aviso de OS, com os dois templates. */
function flx24Telefone(int $businessId): int
{
    $id = (int) DB::table('whatsapp_business_phones')->insertGetId([
        'business_id' => $businessId,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Balcao FLX-24',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => 1,
        'handles_outbound_default' => 0,
        'template_repair_ready_name' => 'repair_status_ready',
        'template_repair_waiting_parts_name' => 'repair_status_waiting_parts',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $GLOBALS['flx24_telefones'][] = $id;

    return $id;
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
    // Nome literal que o listener reconhece hoje (ver o limite no UC-COS-13).
    $statusNovo = Fx::status((int) $biz->id, 'ready');
    $osId = Fx::os((int) $biz->id, $contato, $statusInicial, (int) $user->id);
    $numero = (string) DB::table('repair_job_sheets')->where('id', $osId)->value('job_sheet_no');
    $telefone = flx24Telefone((int) $biz->id);

    // A outra empresa tem OS, cliente e número de WhatsApp próprios — nada dela pode sair aqui.
    $donoVizinho = Fx::usuario((int) $vizinho->id, false);
    $contatoVizinho = Fx::cliente((int) $vizinho->id, (int) $donoVizinho->id, 'Cliente Vizinho FLX-24');
    DB::table('contacts')->where('id', $contatoVizinho)->update(['mobile' => '+5547900000099']);
    $statusVizinho = Fx::status((int) $vizinho->id, 'ready');
    $osVizinha = Fx::os((int) $vizinho->id, $contatoVizinho, $statusVizinho, (int) $donoVizinho->id);
    $numeroVizinho = (string) DB::table('repair_job_sheets')->where('id', $osVizinha)->value('job_sheet_no');
    flx24Telefone((int) $vizinho->id);

    Bus::fake([SendWhatsappMessageJob::class]);

    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $statusNovo,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-FLX24',
    ]);

    expect((int) DB::table('repair_job_sheets')->where('id', $osId)->value('status_id'))->toBe($statusNovo);

    // Aviso: exatamente um, do número desta empresa, para o celular deste cliente.
    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 1);
    Bus::assertDispatched(SendWhatsappMessageJob::class, function ($job) use ($biz, $telefone, $osId) {
        return $job->businessId === (int) $biz->id
            && $job->whatsappBusinessPhoneId === $telefone
            && $job->to === '+5547900000098'
            && $job->payload['name'] === 'repair_status_ready'
            && (string) $job->payload['params']['repair_id'] === (string) $osId;
    });

    // Portal: a busca pelo nº da OS devolve só esta OS, já com o status novo.
    $corpo = flx24Portal($this, $numero);
    expect($corpo['found'])->toBeTrue();
    expect($corpo['ordens'])->toHaveCount(1);
    expect($corpo['ordens'][0]['numero'])->toBe($numero);
    expect($corpo['ordens'][0]['status']['nome'])->toBe('ready');
    expect(json_encode($corpo))->not->toContain($numeroVizinho);
    expect(json_encode($corpo))->not->toContain('Cliente Vizinho FLX-24');
});

it('UC-COS-13: status com nome da empresa muda o portal mas hoje NÃO avisa pelo WhatsApp (limite do listener)', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente FLX-24 PT');
    DB::table('contacts')->where('id', $contato)->update(['mobile' => '+5547900000198']);
    $statusInicial = Fx::status((int) $biz->id, 'Recebido');
    $statusPronto = Fx::status((int) $biz->id, 'Pronto para retirada');
    DB::table('repair_statuses')->where('id', $statusPronto)->update(['is_completed_status' => 1]);
    $osId = Fx::os((int) $biz->id, $contato, $statusInicial, (int) $user->id);
    $numero = (string) DB::table('repair_job_sheets')->where('id', $osId)->value('job_sheet_no');
    flx24Telefone((int) $biz->id);

    Event::fake([RepairStatusChanged::class]);
    Bus::fake([SendWhatsappMessageJob::class]);

    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $statusPronto,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-FLX24-PT',
    ]);

    // O evento SAI, com o nome que a empresa escreveu...
    Event::assertDispatched(RepairStatusChanged::class, fn ($e) => $e->newStatus === 'Pronto para retirada');

    // ...e o listener, alimentado com esse mesmo evento, não enfileira nada.
    Event::dispatched(RepairStatusChanged::class)->each(
        fn (array $args) => app(\Modules\Whatsapp\Listeners\NotifyRepairCustomer::class)->handleEvent($args[0])
    );
    Bus::assertNotDispatched(SendWhatsappMessageJob::class);

    // O portal, que lê status_id, já mostra o status novo.
    $corpo = flx24Portal($this, $numero);
    expect($corpo['ordens'][0]['status']['nome'])->toBe('Pronto para retirada');
});

it('UC-COS-31: cliente que recusou WhatsApp não recebe o aviso; sem resposta (null) ainda recebe', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    $telefone = flx24Telefone((int) $biz->id);

    $recusou = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente FLX-24 Recusou');
    DB::table('contacts')->where('id', $recusou)->update(['mobile' => '+5547900000298', 'whatsapp_consent' => 0]);
    $semResposta = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente FLX-24 Sem Resposta');
    DB::table('contacts')->where('id', $semResposta)->update(['mobile' => '+5547900000398', 'whatsapp_consent' => null]);

    Bus::fake([SendWhatsappMessageJob::class]);
    $listener = app(\Modules\Whatsapp\Listeners\NotifyRepairCustomer::class);

    // Opt-out explícito (LGPD): nenhum job.
    $listener->handle((object) ['id' => 9801, 'business_id' => (int) $biz->id, 'contact_id' => $recusou], 'ready');
    Bus::assertNotDispatched(SendWhatsappMessageJob::class);

    // Controle: mesmo número, mesmo template, contato sem resposta de consentimento → avisa.
    $listener->handle((object) ['id' => 9802, 'business_id' => (int) $biz->id, 'contact_id' => $semResposta], 'ready');
    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 1);
    Bus::assertDispatched(SendWhatsappMessageJob::class, fn ($job) => $job->businessId === (int) $biz->id
        && $job->whatsappBusinessPhoneId === $telefone
        && $job->to === '+5547900000398');
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

    Bus::fake([SendWhatsappMessageJob::class]);
    $listener = app(\Modules\Whatsapp\Listeners\NotifyRepairCustomer::class);

    // OS da 98 com contact_id da 99: o número da 98 não pode escrever para o cliente da 99.
    $listener->handle((object) ['id' => 9803, 'business_id' => (int) $biz->id, 'contact_id' => $contatoVizinho], 'ready');
    Bus::assertNotDispatched(SendWhatsappMessageJob::class);

    // Controle: o cliente da própria empresa recebe.
    $listener->handle((object) ['id' => 9804, 'business_id' => (int) $biz->id, 'contact_id' => $proprio], 'ready');
    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 1);
    Bus::assertDispatched(SendWhatsappMessageJob::class, fn ($job) => $job->to === '+5547900000598');
});
