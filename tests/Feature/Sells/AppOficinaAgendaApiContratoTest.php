<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;

/**
 * Agenda de revisão da Oficina no app das lojas — GET/POST /api/app/agendamentos,
 * POST /api/app/agendamentos/{id}/cancelar e POST /api/app/os com `agendamento_id`.
 *
 * Contrato: memory/requisitos/AppMobile/api/oficina-agenda.md (decisão [W] 2026-10-06 + padrões do
 * gerente da fila: mesmo horário permitido; dia passado = 422, hoje vale; permissões = criar OS).
 * NÃO derivado do controller.
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4. Todo "não aparece / não grava"
 * de outra empresa tem controle positivo em par — senão seria verde por vácuo.
 */
uses(DatabaseTransactions::class);

function agendaProcesso(int $bizId): void
{
    $proc = DB::table('sale_processes')->where('business_id', $bizId)->where('key', 'oficina_mecanica_os')->value('id')
        ?? DB::table('sale_processes')->insertGetId([
            'business_id' => $bizId, 'key' => 'oficina_mecanica_os', 'name' => 'Oficina — OS de Mecânica',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    if (! DB::table('sale_process_stages')->where('process_id', $proc)->where('key', 'recepcao')->exists()) {
        DB::table('sale_process_stages')->insert([
            'process_id' => $proc, 'key' => 'recepcao', 'name' => 'Recepção', 'sort_order' => 0,
            'is_initial' => true, 'is_terminal' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function agendaVeiculo(int $bizId, ?int $contactId = null): int
{
    return (int) DB::table('vehicles')->insertGetId([
        'business_id' => $bizId, 'contact_id' => $contactId, 'plate' => 'AGD' . random_int(1000, 9999),
        'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function agendaContato(int $bizId, string $nome, int $criadoPor): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $bizId, 'type' => 'customer', 'name' => $nome, 'contact_status' => 'active',
        'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Agendamento gravado direto no banco (para o lado 99 e para estados que a API não cria). */
function agendaGravar(int $bizId, int $veiculo, string $inicio, string $status = 'agendado'): int
{
    return (int) DB::table('oficina_agendamentos')->insertGetId([
        'business_id' => $bizId, 'vehicle_id' => $veiculo, 'inicio' => $inicio, 'status' => $status,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function agendaAmanha(string $hora = '09:00'): string
{
    return now()->addDay()->format('Y-m-d') . 'T' . $hora;
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['oficina_agendamentos', 'service_orders', 'vehicles', 'contacts', 'sale_processes', 'sale_process_stages'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    // PRÉ-CONDIÇÃO: empresas distintas, senão o cross-tenant é tautológico.
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->user = $this->usuarioComPermissoes(['oficinaauto.service_order.view', 'oficinaauto.service_order.create']);

    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $this->bizId, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode(['oficina_auto_module' => 1]), 'created_id' => $this->user->id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    agendaProcesso($this->bizId);

    Passport::actingAs($this->user, [], 'api');
});

it('sem token responde 401', function () {
    $this->app['auth']->forgetGuards();
    $this->withHeaders(['Accept' => 'application/json'])->get('/api/app/agendamentos')->assertStatus(401);
});

it('sem permissão de ver OS responde 403; só ver lista com pode_criar=false e não agenda', function () {
    $soVer = $this->usuarioComPermissoes(['oficinaauto.service_order.view']);
    Passport::actingAs($soVer, [], 'api');
    expect($this->getJson('/api/app/agendamentos')->assertOk()->json('pode_criar'))->toBeFalse();
    $this->postJson('/api/app/agendamentos', ['vehicle_id' => agendaVeiculo($this->bizId), 'inicio' => agendaAmanha()])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    $nada = $this->usuarioComPermissoes([]);
    Passport::actingAs($nada, [], 'api');
    $this->getJson('/api/app/agendamentos')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});

it('agenda veículo + cliente + dia e hora + observação; devolve o item e lista em ordem de início', function () {
    $dono = agendaContato($this->bizId, 'Transportes Agenda', (int) $this->user->id);
    $v = agendaVeiculo($this->bizId, $dono);

    $r = $this->postJson('/api/app/agendamentos', [
        'vehicle_id' => $v, 'contact_id' => $dono, 'inicio' => agendaAmanha('14:30'), 'observacao' => ' Revisão 50 mil ',
    ])->assertStatus(201);
    expect($r->json('inicio'))->toBe(agendaAmanha('14:30'));
    expect($r->json('veiculo.id'))->toBe($v);
    expect($r->json('veiculo.descricao'))->toBe('Caminhão');
    expect($r->json('cliente'))->toBe(['id' => $dono, 'nome' => 'Transportes Agenda']);
    expect($r->json('observacao'))->toBe('Revisão 50 mil');
    expect($r->json('status'))->toBe('agendado');
    expect($r->json('os_id'))->toBeNull();

    $linha = DB::table('oficina_agendamentos')->where('id', $r->json('id'))->first();
    expect((int) $linha->business_id)->toBe($this->bizId);
    expect((int) $linha->created_by)->toBe((int) $this->user->id);

    // Mesmo horário é permitido (mais de um box); mais cedo no mesmo dia vem antes.
    $this->postJson('/api/app/agendamentos', ['vehicle_id' => $v, 'inicio' => agendaAmanha('14:30')])->assertStatus(201);
    $cedo = $this->postJson('/api/app/agendamentos', ['vehicle_id' => $v, 'inicio' => agendaAmanha('08:00')])
        ->assertStatus(201)->json('id');

    $dia = now()->addDay()->format('Y-m-d');
    $lista = $this->getJson("/api/app/agendamentos?de={$dia}&ate={$dia}")->assertOk();
    expect($lista->json('pode_criar'))->toBeTrue();
    expect(array_column($lista->json('itens'), 'inicio'))
        ->toBe([agendaAmanha('08:00'), agendaAmanha('14:30'), agendaAmanha('14:30')]);
    expect($lista->json('itens.0.id'))->toBe($cedo);
});

it('validação por campo: veículo obrigatório, formato do início, dia passado recusado e hoje aceito', function () {
    $v = agendaVeiculo($this->bizId);
    $this->postJson('/api/app/agendamentos', [])->assertStatus(422)
        ->assertJsonPath('erro', 'validacao')
        ->assertJsonPath('campos.vehicle_id', 'Escolha o veículo.')
        ->assertJsonPath('campos.inicio', 'Informe o dia e a hora.');
    $this->postJson('/api/app/agendamentos', ['vehicle_id' => $v, 'inicio' => '10/10/2030 09:00'])
        ->assertStatus(422)->assertJsonPath('campos.inicio', 'Use o formato AAAA-MM-DDTHH:MM.');
    $ontem = now()->subDay()->format('Y-m-d') . 'T23:59';
    $this->postJson('/api/app/agendamentos', ['vehicle_id' => $v, 'inicio' => $ontem])
        ->assertStatus(422)->assertJsonPath('campos.inicio', 'O agendamento não pode ser num dia que já passou.');

    // O próprio dia vale, mesmo em hora que já passou (encaixe).
    $hojeCedo = now()->format('Y-m-d') . 'T00:00';
    $this->postJson('/api/app/agendamentos', ['vehicle_id' => $v, 'inicio' => $hojeCedo])->assertStatus(201);
});

it('Tier 0: veículo, cliente e agendamentos de OUTRA empresa nunca entram nem aparecem', function () {
    $antes = DB::table('oficina_agendamentos')->count();
    $meu = agendaVeiculo($this->bizId);
    $alheio = agendaVeiculo($this->outroBizId);
    $clienteAlheio = agendaContato($this->outroBizId, 'Cliente 99', (int) $this->user->id);

    $this->postJson('/api/app/agendamentos', ['vehicle_id' => $alheio, 'inicio' => agendaAmanha()])
        ->assertStatus(422)->assertJsonPath('campos.vehicle_id', 'Veículo não encontrado.');
    $this->postJson('/api/app/agendamentos', ['vehicle_id' => $meu, 'contact_id' => $clienteAlheio, 'inicio' => agendaAmanha()])
        ->assertStatus(422)->assertJsonPath('campos.contact_id', 'Cliente não encontrado.');
    expect(DB::table('oficina_agendamentos')->count())->toBe($antes);

    $doOutro = agendaGravar($this->outroBizId, $alheio, now()->addDay()->format('Y-m-d') . ' 10:00:00');
    $doMeu = agendaGravar($this->bizId, $meu, now()->addDay()->format('Y-m-d') . ' 10:00:00');
    $dia = now()->addDay()->format('Y-m-d');
    $ids = array_column($this->getJson("/api/app/agendamentos?de={$dia}&ate={$dia}")->assertOk()->json('itens'), 'id');
    expect($ids)->toContain($doMeu);       // controle positivo
    expect($ids)->not->toContain($doOutro);

    $this->postJson("/api/app/agendamentos/{$doOutro}/cancelar")->assertStatus(404);
    expect(DB::table('oficina_agendamentos')->where('id', $doOutro)->value('status'))->toBe('agendado');

    $osAntes = DB::table('service_orders')->count();
    $this->postJson('/api/app/os', ['vehicle_id' => $meu, 'agendamento_id' => $doOutro])
        ->assertStatus(422)->assertJsonPath('campos.agendamento_id', 'Agendamento não encontrado.');
    expect(DB::table('service_orders')->count())->toBe($osAntes);
    expect(DB::table('oficina_agendamentos')->where('id', $doOutro)->value('os_id'))->toBeNull();
});

it('cancela com motivo; cancelar de novo é 422 estado_invalido', function () {
    $id = $this->postJson('/api/app/agendamentos', ['vehicle_id' => agendaVeiculo($this->bizId), 'inicio' => agendaAmanha()])
        ->assertStatus(201)->json('id');

    $this->postJson("/api/app/agendamentos/{$id}/cancelar", ['motivo' => ' Cliente remarcou '])
        ->assertOk()->assertJsonPath('status', 'cancelado')->assertJsonPath('id', $id);
    expect(DB::table('oficina_agendamentos')->where('id', $id)->value('motivo_cancelamento'))->toBe('Cliente remarcou');

    $this->postJson("/api/app/agendamentos/{$id}/cancelar")
        ->assertStatus(422)->assertJsonPath('erro', 'estado_invalido')
        ->assertJsonPath('mensagem', 'Este agendamento já foi cancelado.');
});

it('Abrir OS com agendamento_id cria a OS e marca o agendamento atendido com os_id', function () {
    $v = agendaVeiculo($this->bizId);
    $ag = $this->postJson('/api/app/agendamentos', ['vehicle_id' => $v, 'inicio' => agendaAmanha()])
        ->assertStatus(201)->json('id');

    $os = (int) $this->postJson('/api/app/os', ['vehicle_id' => $v, 'agendamento_id' => $ag])->assertStatus(201)->json('id');

    $linha = DB::table('oficina_agendamentos')->where('id', $ag)->first();
    expect($linha->status)->toBe('atendido');
    expect((int) $linha->os_id)->toBe($os);
    expect((int) DB::table('service_orders')->where('id', $os)->value('business_id'))->toBe($this->bizId);
    $dia = now()->addDay()->format('Y-m-d');
    $item = collect($this->getJson("/api/app/agendamentos?de={$dia}&ate={$dia}")->json('itens'))->firstWhere('id', $ag);
    expect($item['status'])->toBe('atendido');
    expect($item['os_id'])->toBe($os);

    // Atendido não abre outra OS nem é cancelado.
    $osAntes = DB::table('service_orders')->count();
    $this->postJson('/api/app/os', ['vehicle_id' => $v, 'agendamento_id' => $ag])
        ->assertStatus(422)->assertJsonPath('campos.agendamento_id', 'Este agendamento não está mais aberto.');
    expect(DB::table('service_orders')->count())->toBe($osAntes);
    $this->postJson("/api/app/agendamentos/{$ag}/cancelar")
        ->assertStatus(422)->assertJsonPath('mensagem', 'Este agendamento já virou OS.');
});

it('Abrir OS recusa agendamento de outro veículo ou cancelado, sem criar OS', function () {
    $v = agendaVeiculo($this->bizId);
    $outroVeiculo = agendaVeiculo($this->bizId);
    $dia = now()->addDay()->format('Y-m-d') . ' 09:00:00';
    $deOutro = agendaGravar($this->bizId, $outroVeiculo, $dia);
    $cancelado = agendaGravar($this->bizId, $v, $dia, 'cancelado');
    $osAntes = DB::table('service_orders')->count();

    $this->postJson('/api/app/os', ['vehicle_id' => $v, 'agendamento_id' => $deOutro])
        ->assertStatus(422)->assertJsonPath('campos.agendamento_id', 'O agendamento é de outro veículo.');
    $this->postJson('/api/app/os', ['vehicle_id' => $v, 'agendamento_id' => $cancelado])
        ->assertStatus(422)->assertJsonPath('campos.agendamento_id', 'Este agendamento não está mais aberto.');
    expect(DB::table('service_orders')->count())->toBe($osAntes);
    expect(DB::table('oficina_agendamentos')->where('id', $deOutro)->value('status'))->toBe('agendado');

    // Controle positivo: sem agendamento a OS segue nascendo como antes.
    $this->postJson('/api/app/os', ['vehicle_id' => $v])->assertStatus(201);
});

it('GET valida as datas e recusa janela acima de 92 dias', function () {
    $this->getJson('/api/app/agendamentos?de=10/10/2030')->assertStatus(422)->assertJsonPath('campos.de', 'Use a data no formato AAAA-MM-DD.');
    $this->getJson('/api/app/agendamentos?de=2030-01-10&ate=2030-01-09')->assertStatus(422)->assertJsonPath('campos.ate', 'A data final é anterior à inicial.');
    $this->getJson('/api/app/agendamentos?de=2030-01-01&ate=2030-06-01')->assertStatus(422)->assertJsonPath('campos.ate', 'Período máximo de 92 dias.');
    $this->getJson('/api/app/agendamentos?de=2030-01-01&ate=2030-04-03')->assertOk();
});
