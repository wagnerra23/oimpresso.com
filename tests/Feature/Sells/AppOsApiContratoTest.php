<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\Contract\AutosaveContractRunner;

/**
 * API de Ordens de serviço do app das lojas (tela 07) — GET /api/app/os, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §11.1 e §11.2. Mesmo universo do quadro web
 * da Oficina: etapas não-terminais do processo `oficina_mecanica_os`, na ordem do ERP; OS de
 * mecânica sem pipeline conta na etapa inicial; terminal fica fora. NÃO derivado do controller.
 *
 * Tier 0 (ADR 0093): OS de OUTRO business nunca aparece. Controle positivo em par: a OS do
 * próprio business APARECE — senão o "não aparece" seria verde por vácuo.
 */
uses(DatabaseTransactions::class);

/** Processo da oficina com as 6 etapas de quadro + as terminais, no business. @return array<string,int> */
function appOsProcesso(int $bizId): array
{
    $proc = DB::table('sale_processes')->where('business_id', $bizId)->where('key', 'oficina_mecanica_os')->value('id')
        ?? DB::table('sale_processes')->insertGetId([
            'business_id' => $bizId, 'key' => 'oficina_mecanica_os', 'name' => 'Oficina — OS de Mecânica',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $etapas = [
        'recepcao' => [0, 'Recepção', false], 'em_diagnostico' => [1, 'Diagnóstico', false],
        'aguardando_aprovacao' => [2, 'Aguardando aprovação', false], 'aguardando_pecas' => [3, 'Aguardando peças', false],
        'em_execucao' => [4, 'Em execução', false], 'pronto_retirada' => [5, 'Pronto p/ retirar', false],
        'entregue' => [6, 'Entregue', true],
        'cancelado' => [7, 'Cancelado', true],
        'garantia_acionada' => [8, 'Garantia acionada', true],
    ];
    $ids = [];
    foreach ($etapas as $key => [$ordem, $nome, $terminal]) {
        $ids[$key] = DB::table('sale_process_stages')->where('process_id', $proc)->where('key', $key)->value('id')
            ?? DB::table('sale_process_stages')->insertGetId([
                'process_id' => $proc, 'key' => $key, 'name' => $nome, 'sort_order' => $ordem,
                'is_initial' => $ordem === 0, 'is_terminal' => $terminal,
                'created_at' => now(), 'updated_at' => now(),
            ]);
    }

    return $ids;
}

function appOsCriar(int $bizId, ?int $stageId, ?int $contactId = null, string $tipo = 'mecanica'): int
{
    $veiculo = DB::table('vehicles')->insertGetId([
        'business_id' => $bizId, 'plate' => 'TST' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('service_orders')->insertGetId([
        'business_id' => $bizId, 'vehicle_id' => $veiculo, 'contact_id' => $contactId,
        'order_type' => $tipo, 'status' => 'aberta', 'current_stage_id' => $stageId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['service_orders', 'vehicles', 'oficina_service_order_items', 'sale_processes', 'sale_process_stages'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = $ctx['business'];
    $this->user = $ctx['user'];
    $this->outroBiz = \App\Business::where('id', '!=', $this->biz->id)->first();
    if (! $this->outroBiz) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }

    // Módulo no pacote do business (Camada 1) quando o Superadmin está instalado; sem ele o
    // ModuleUtil libera todo módulo e o pacote não entra na conta.
    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $this->biz->id, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode(['oficina_auto_module' => 1]), 'created_id' => $this->user->id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    Permission::firstOrCreate(['name' => 'oficinaauto.service_order.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.service_order.view');

    $this->etapas = appOsProcesso((int) $this->biz->id);
    Passport::actingAs($this->user, [], 'api');
});

it('sem token responde 401', function () {
    $this->app['auth']->forgetGuards();
    $this->withHeaders(['Accept' => 'application/json'])->get('/api/app/os')->assertStatus(401);
});

it('sem a permissão de ver OS responde 403 sem_permissao', function () {
    $this->user->revokePermissionTo('oficinaauto.service_order.view');
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->getJson('/api/app/os')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});

it('lista a OS com número, etapa na ordem do ERP, valor dos itens e travada', function () {
    $os = appOsCriar((int) $this->biz->id, $this->etapas['aguardando_pecas']);
    DB::table('oficina_service_order_items')->insert([
        ['business_id' => $this->biz->id, 'service_order_id' => $os, 'tipo' => 'peca', 'descricao' => 'Bieleta',
            'quantidade' => 2, 'valor_unitario' => 210, 'valor_total' => 420, 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => $this->biz->id, 'service_order_id' => $os, 'tipo' => 'mao_obra', 'descricao' => 'Troca',
            'quantidade' => 1, 'valor_unitario' => 330, 'valor_total' => 330, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $r = $this->getJson('/api/app/os')->assertOk();
    $item = collect($r->json('itens'))->firstWhere('id', $os);

    expect($item)->not->toBeNull();
    expect($item['numero'])->toBe('OS-' . str_pad((string) $os, 5, '0', STR_PAD_LEFT));
    expect($item['veiculo'])->toBe('Caminhão');
    expect((float) $item['valor'])->toBe(750.0);
    expect($item['etapa'])->toBe(['chave' => 'aguardando_pecas', 'rotulo' => 'Aguardando peças', 'indice' => 4, 'total_etapas' => 6]);
    expect($item['travada'])->toBeTrue();
    expect(collect($r->json('etapas'))->pluck('chave')->all())
        ->toBe(['recepcao', 'em_diagnostico', 'aguardando_aprovacao', 'aguardando_pecas', 'em_execucao', 'pronto_retirada']);
    expect($r->json('travadas'))->toBeGreaterThanOrEqual(1);
});

it('OS sem item vem com valor null; OS de mecânica sem pipeline cai na Recepção', function () {
    $semPipeline = appOsCriar((int) $this->biz->id, null);

    $item = collect($this->getJson('/api/app/os?etapa=recepcao')->assertOk()->json('itens'))->firstWhere('id', $semPipeline);
    expect($item)->not->toBeNull();
    expect($item['valor'])->toBeNull();
    expect($item['etapa']['chave'])->toBe('recepcao');
    expect($item['etapa']['indice'])->toBe(1);
});

it('OS em etapa terminal fica fora da lista e do total', function () {
    $ativa = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);
    $entregue = appOsCriar((int) $this->biz->id, $this->etapas['entregue']);

    $r = $this->getJson('/api/app/os')->assertOk();
    $ids = collect($r->json('itens'))->pluck('id');
    expect($ids)->toContain($ativa);
    expect($ids)->not->toContain($entregue);
    expect($r->json('total'))->toBe(array_sum(collect($r->json('etapas'))->pluck('total')->all()));
});

it('etapa mais avançada vem primeiro', function () {
    $recepcao = appOsCriar((int) $this->biz->id, $this->etapas['recepcao']);
    $pronta = appOsCriar((int) $this->biz->id, $this->etapas['pronto_retirada']);

    $ids = collect($this->getJson('/api/app/os')->assertOk()->json('itens'))->pluck('id')->all();
    expect(array_search($pronta, $ids, true))->toBeLessThan(array_search($recepcao, $ids, true));
});

it('OS de OUTRO business não aparece', function () {
    $minha = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);
    $outrasEtapas = appOsProcesso((int) $this->outroBiz->id);
    $alheia = appOsCriar((int) $this->outroBiz->id, $outrasEtapas['em_execucao']);

    $ids = collect($this->getJson('/api/app/os?etapa=em_execucao')->assertOk()->json('itens'))->pluck('id');
    expect($ids)->toContain($minha);
    expect($ids)->not->toContain($alheia);
});

it('a área oficina aparece no /inicio para quem vê OS', function () {
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->toContain('oficina');
});

// ── Tela 03 — GET /api/app/os/{id} (contrato §11.2) ─────────────────────────────

it('detalhe traz local, veículo, observações, vistoria, itens por tipo e totais do ERP', function () {
    $os = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);
    DB::table('service_orders')->where('id', $os)->update([
        'box_label' => 'Elevador 1', 'notes' => 'Barulho na suspensão', 'mileage_at_service' => 48312,
    ]);
    DB::table('oficina_service_order_items')->insert([
        ['business_id' => $this->biz->id, 'service_order_id' => $os, 'tipo' => 'peca', 'descricao' => 'Bieleta',
            'quantidade' => 2, 'valor_unitario' => 210, 'valor_total' => 420, 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => $this->biz->id, 'service_order_id' => $os, 'tipo' => 'mao_obra', 'descricao' => 'Troca',
            'quantidade' => 1.5, 'valor_unitario' => 220, 'valor_total' => 330, 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => $this->biz->id, 'service_order_id' => $os, 'tipo' => 'servico_terceiro', 'descricao' => 'Alinhamento',
            'quantidade' => 1, 'valor_unitario' => 80, 'valor_total' => 80, 'created_at' => now(), 'updated_at' => now()],
    ]);
    if (Schema::hasTable('oa_inspection_items')) {
        DB::table('oa_inspection_items')->insert([
            'business_id' => $this->biz->id, 'service_order_id' => $os, 'categoria' => 'suspensao',
            'descricao' => 'Bieleta folgada', 'severity' => 'critico', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $r = $this->getJson('/api/app/os/' . $os)->assertOk();

    expect($r->json('local'))->toBe('Elevador 1');
    expect($r->json('etapa'))->toBe(['chave' => 'em_execucao', 'rotulo' => 'Em execução', 'indice' => 5, 'total_etapas' => 6, 'terminal' => false]);
    expect($r->json('veiculo.km'))->toBe(48312);
    expect($r->json('veiculo.descricao'))->toBe('Caminhão');
    expect($r->json('observacoes'))->toBe('Barulho na suspensão');
    expect(collect($r->json('itens'))->pluck('tipo')->all())->toBe(['peca', 'mao_obra', 'servico_terceiro']);
    expect((float) $r->json('totais.pecas'))->toBe(420.0);
    expect((float) $r->json('totais.mao_de_obra'))->toBe(330.0);
    expect((float) $r->json('totais.terceiros'))->toBe(80.0);
    expect((float) $r->json('totais.total'))->toBe(830.0);
    if (Schema::hasTable('oa_inspection_items')) {
        expect($r->json('vistoria.critico'))->toBe(1);
    }
    expect($r->json('fotos_laudo'))->toBe(0);
});

it('detalhe de OS terminal vem com indice null e terminal true', function () {
    $os = appOsCriar((int) $this->biz->id, $this->etapas['entregue']);

    $etapa = $this->getJson('/api/app/os/' . $os)->assertOk()->json('etapa');
    expect($etapa['chave'])->toBe('entregue');
    expect($etapa['indice'])->toBeNull();
    expect($etapa['terminal'])->toBeTrue();
});

it('detalhe de OS fora do pipeline da oficina vem com etapa null', function () {
    $os = appOsCriar((int) $this->biz->id, null, null, 'manutencao');

    $r = $this->getJson('/api/app/os/' . $os)->assertOk();
    expect($r->json('etapa'))->toBeNull();
    expect($r->json('travada'))->toBeFalse();
    expect($r->json('cliente'))->toBeNull();
});

it('detalhe de OS de OUTRO business responde 404', function () {
    $minha = appOsCriar((int) $this->biz->id, $this->etapas['recepcao']);
    $alheia = appOsCriar((int) $this->outroBiz->id, null);

    $this->getJson('/api/app/os/' . $minha)->assertOk();
    $this->getJson('/api/app/os/' . $alheia)->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
});

it('o filtro de etapa filtra só os itens; total, travadas e contagem por etapa não mudam', function () {
    appOsCriar((int) $this->biz->id, $this->etapas['aguardando_pecas']);
    $emExecucao = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);

    $todas = $this->getJson('/api/app/os')->assertOk();
    $filtrada = $this->getJson('/api/app/os?etapa=aguardando_pecas')->assertOk();

    expect($filtrada->json('total'))->toBe($todas->json('total'));
    expect($filtrada->json('travadas'))->toBe($todas->json('travadas'));
    expect($filtrada->json('etapas'))->toBe($todas->json('etapas'));
    expect(collect($filtrada->json('itens'))->pluck('etapa.chave')->unique()->all())->toBe(['aguardando_pecas']);
    expect(collect($filtrada->json('itens'))->pluck('id'))->not->toContain($emExecucao);
});

// ── Tela 08 — GET /api/app/veiculos (contrato §11.3) ────────────────────────────

it('veículos: lista o meu com tipo, ano, reboque, cor, dono e o maior km conhecido', function () {
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');
    $dono = DB::table('contacts')->where('business_id', $this->biz->id)->value('id');

    $placa = 'APP' . random_int(1000, 9999);
    $v = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => $placa, 'secondary_plate' => 'REB1A23',
        'vehicle_type' => 'cavalo', 'color' => 'Branco', 'manufacture_year' => 2019, 'model_year' => 2020,
        'mileage_at_entry' => 40000, 'contact_id' => $dono, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('service_orders')->insert([
        'business_id' => $this->biz->id, 'vehicle_id' => $v, 'order_type' => 'mecanica', 'status' => 'aberta',
        'mileage_at_service' => 48312, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $item = collect($this->getJson('/api/app/veiculos?q=' . $placa)->assertOk()->json('itens'))->firstWhere('id', $v);
    expect($item)->not->toBeNull();
    expect($item['placa'])->toBe($placa);
    expect($item['placa_secundaria'])->toBe('REB1A23');
    expect($item['descricao'])->toBe('Cavalo (truck-cabine)');
    expect($item['ano'])->toBe('2019/2020');
    expect($item['cor'])->toBe('Branco');
    expect($item['km'])->toBe(48312);
    expect($item['cliente'])->toBe($dono ? DB::table('contacts')->where('id', $dono)->value('name') : null);
});

it('veículos: sem oficinaauto.vehicle.view responde 403; veículo de OUTRO business não aparece', function () {
    $this->getJson('/api/app/veiculos')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');
    $sufixo = (string) random_int(100, 999);
    $meu = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => 'MEU' . $sufixo, 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $alheio = DB::table('vehicles')->insertGetId([
        'business_id' => $this->outroBiz->id, 'plate' => 'OUT' . $sufixo, 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $ids = collect($this->getJson('/api/app/veiculos?q=' . $sufixo)->assertOk()->json('itens'))->pluck('id');
    expect($ids)->toContain($meu);
    expect($ids)->not->toContain($alheio);
});

// ── Tela 08 — GET /api/app/veiculos/{id}/os (contrato api/tela-08-veiculos.md, histórico) ──

/** Veículo do business com permissão de ver veículos já dada ao usuário do teste. */
function appOsVeiculoComPermissao(object $t): int
{
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $t->user->givePermissionTo('oficinaauto.vehicle.view');

    return (int) DB::table('vehicles')->insertGetId([
        'business_id' => $t->biz->id, 'plate' => 'HIS' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appOsDoVeiculo(int $bizId, int $veiculo, ?int $stageId, string $tipo, string $entrada): int
{
    return (int) DB::table('service_orders')->insertGetId([
        'business_id' => $bizId, 'vehicle_id' => $veiculo, 'order_type' => $tipo, 'status' => 'aberta',
        'current_stage_id' => $stageId, 'entered_at' => $entrada, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('histórico do veículo: todas as OS dele, da entrada mais nova para a mais antiga, com etapa e valor do ERP', function () {
    $v = appOsVeiculoComPermissao($this);
    $biz = (int) $this->biz->id;
    $entregue = appOsDoVeiculo($biz, $v, $this->etapas['entregue'], 'mecanica', now()->subDays(10)->toDateTimeString());
    $fora = appOsDoVeiculo($biz, $v, null, 'manutencao', now()->subDays(5)->toDateTimeString());
    $semPipeline = appOsDoVeiculo($biz, $v, null, 'mecanica', now()->subDays(2)->toDateTimeString());
    DB::table('oficina_service_order_items')->insert([
        ['business_id' => $biz, 'service_order_id' => $entregue, 'tipo' => 'peca', 'descricao' => 'Bieleta',
            'quantidade' => 2, 'valor_unitario' => 210, 'valor_total' => 420, 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => $biz, 'service_order_id' => $entregue, 'tipo' => 'mao_obra', 'descricao' => 'Troca',
            'quantidade' => 1, 'valor_unitario' => 330, 'valor_total' => 330, 'created_at' => now(), 'updated_at' => now()],
    ]);
    // OS de outro veículo do mesmo business não entra.
    $outroVeiculo = appOsCriar($biz, $this->etapas['recepcao']);

    $itens = $this->getJson("/api/app/veiculos/{$v}/os")->assertOk()->json('itens');

    expect(array_column($itens, 'os_id'))->toBe([$semPipeline, $fora, $entregue]);
    expect(array_column($itens, 'os_id'))->not->toContain($outroVeiculo);
    expect(array_column($itens, 'etapa_rotulo'))->toBe(['Recepção', null, 'Entregue']);
    // JSON grava 750.0 como 750: compara como número (null continua null).
    expect(array_map(fn ($x) => $x === null ? null : (float) $x, array_column($itens, 'valor')))->toBe([null, null, 750.0]);
    expect($itens[2]['numero'])->toBe('OS-' . str_pad((string) $entregue, 5, '0', STR_PAD_LEFT));
    expect($itens[2]['data'])->toBe(now()->subDays(10)->toDateString());
});

it('histórico do veículo: sem oficinaauto.vehicle.view responde 403; veículo de OUTRO business responde 404', function () {
    $alheio = (int) DB::table('vehicles')->insertGetId([
        'business_id' => $this->outroBiz->id, 'plate' => 'OUT' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->getJson("/api/app/veiculos/{$alheio}/os")->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    $meu = appOsVeiculoComPermissao($this);
    $this->getJson("/api/app/veiculos/{$meu}/os")->assertOk();
    $this->getJson("/api/app/veiculos/{$alheio}/os")->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
});

it('histórico do veículo: OS de OUTRO business apontando para o meu veículo não aparece', function () {
    $v = appOsVeiculoComPermissao($this);
    $minha = appOsDoVeiculo((int) $this->biz->id, $v, $this->etapas['recepcao'], 'mecanica', now()->toDateTimeString());
    $alheia = appOsDoVeiculo((int) $this->outroBiz->id, $v, null, 'mecanica', now()->toDateTimeString());

    $ids = array_column($this->getJson("/api/app/veiculos/{$v}/os")->assertOk()->json('itens'), 'os_id');
    expect($ids)->toContain($minha);
    expect($ids)->not->toContain($alheia);
});

// ── Histórico: o que foi feito em cada OS (pedido [W] 2026-10-06, sessão App Onda D) ──
// Contrato: cada OS do histórico traz `itens` [{tipo, descricao, quantidade}] na ordem da OS,
// SEM valor por item (o total já vai em `valor`), no máximo 20, e `itens_total` com o total.

function appOsItem(int $bizId, int $os, string $tipo, string $descricao, float $qtd = 1, ?string $excluido = null): void
{
    DB::table('oficina_service_order_items')->insert([
        'business_id' => $bizId, 'service_order_id' => $os, 'tipo' => $tipo, 'descricao' => $descricao,
        'quantidade' => $qtd, 'valor_unitario' => 10, 'valor_total' => 10 * $qtd,
        'deleted_at' => $excluido, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('histórico do veículo: cada OS traz serviços e peças na ordem da OS, com tipo do ERP e sem valor por item; item excluído fica fora', function () {
    $v = appOsVeiculoComPermissao($this);
    $biz = (int) $this->biz->id;
    $os = appOsDoVeiculo($biz, $v, $this->etapas['entregue'], 'mecanica', now()->subDay()->toDateTimeString());
    $vazia = appOsDoVeiculo($biz, $v, $this->etapas['recepcao'], 'mecanica', now()->toDateTimeString());
    appOsItem($biz, $os, 'mao_obra', 'Troca de pastilha');
    appOsItem($biz, $os, 'peca', 'Pastilha dianteira', 2);
    appOsItem($biz, $os, 'peca', 'Disco removido', 1, now()->toDateTimeString());
    appOsItem($biz, $os, 'servico_terceiro', 'Retífica');

    $lista = collect($this->getJson("/api/app/veiculos/{$v}/os")->assertOk()->json('itens'))->keyBy('os_id');

    $feitos = $lista[$os]['itens'];
    expect(array_column($feitos, 'descricao'))->toBe(['Troca de pastilha', 'Pastilha dianteira', 'Retífica']);
    expect(array_column($feitos, 'tipo'))->toBe(['mao_obra', 'peca', 'servico_terceiro']);
    expect(array_map(fn ($q) => (float) $q, array_column($feitos, 'quantidade')))->toBe([1.0, 2.0, 1.0]);
    foreach ($feitos as $f) {
        expect(array_keys($f))->toBe(['tipo', 'descricao', 'quantidade']);
    }
    expect($lista[$os]['itens_total'])->toBe(3);
    // O total da OS continua em `valor` (soma sem o excluído): 10 + 20 + 10.
    expect((float) $lista[$os]['valor'])->toBe(40.0);
    // OS sem item: lista vazia e total 0.
    expect($lista[$vazia]['itens'])->toBe([]);
    expect($lista[$vazia]['itens_total'])->toBe(0);
});

it('histórico do veículo: item de OUTRO business apontando para a minha OS não aparece nem conta', function () {
    $v = appOsVeiculoComPermissao($this);
    $biz = (int) $this->biz->id;
    $os = appOsDoVeiculo($biz, $v, $this->etapas['recepcao'], 'mecanica', now()->toDateTimeString());
    appOsItem($biz, $os, 'peca', 'Peça minha');
    appOsItem((int) $this->outroBiz->id, $os, 'peca', 'Peça alheia');

    $item = collect($this->getJson("/api/app/veiculos/{$v}/os")->assertOk()->json('itens'))->firstWhere('os_id', $os);

    expect(array_column($item['itens'], 'descricao'))->toBe(['Peça minha']);
    expect($item['itens_total'])->toBe(1);
});

it('histórico do veículo: no máximo 20 itens por OS, os primeiros da OS, e itens_total com o total', function () {
    $v = appOsVeiculoComPermissao($this);
    $biz = (int) $this->biz->id;
    $os = appOsDoVeiculo($biz, $v, $this->etapas['recepcao'], 'mecanica', now()->toDateTimeString());
    for ($n = 1; $n <= 23; $n++) {
        appOsItem($biz, $os, 'peca', 'Item ' . $n);
    }

    $item = collect($this->getJson("/api/app/veiculos/{$v}/os")->assertOk()->json('itens'))->firstWhere('os_id', $os);

    expect($item['itens'])->toHaveCount(20);
    expect($item['itens'][0]['descricao'])->toBe('Item 1');
    expect($item['itens'][19]['descricao'])->toBe('Item 20');
    expect($item['itens_total'])->toBe(23);
});

// ── Tela 03 — avançar etapa: GET /os/{id} traz `acoes`; POST /os/{id}/acoes/{chave} ─────────

/** Ações da fixture (idempotente pela chave única stage_id+key). Devolve o id da ação. */
function appOsAcao(int $stageId, string $chave, string $rotulo, int $alvo, bool $critica = false, ?string $efeito = null): int
{
    $dados = [
        'label' => $rotulo, 'target_stage_id' => $alvo, 'requires_confirmation' => $critica,
        'side_effect_class' => $efeito, 'event_class' => null, 'updated_at' => now(),
    ];
    if (Schema::hasColumn('sale_stage_actions', 'is_critical')) {
        $dados['is_critical'] = $critica;
    }
    DB::table('sale_stage_actions')->updateOrInsert(['stage_id' => $stageId, 'key' => $chave], $dados + ['created_at' => now()]);

    return (int) DB::table('sale_stage_actions')->where('stage_id', $stageId)->where('key', $chave)->value('id');
}

/** Liga as ações usadas nos casos abaixo e dá ao usuário a permissão de alterar OS. */
function appOsComAcoes(object $t, bool $comUpdate = true): void
{
    $e = $t->etapas;
    appOsAcao($e['recepcao'], 'iniciar_diagnostico', 'Iniciar diagnóstico', $e['em_diagnostico']);
    appOsAcao($e['em_execucao'], 'concluir_servico', 'Concluir serviço', $e['pronto_retirada'], true);
    appOsAcao($e['em_execucao'], 'cancelar_os', 'Cancelar OS', $e['cancelado'], true);
    appOsAcao($e['aguardando_aprovacao'], 'recusar_orcamento', 'Cliente recusou orçamento', $e['cancelado'], true);
    appOsAcao($e['pronto_retirada'], 'acionar_garantia', 'Acionar garantia', $e['garantia_acionada'], true);
    appOsAcao($e['pronto_retirada'], 'entregar', 'Entregar ao cliente', $e['entregue']);
    if ($comUpdate) {
        Permission::firstOrCreate(['name' => 'oficinaauto.service_order.update', 'guard_name' => 'web']);
        $t->user->givePermissionTo('oficinaauto.service_order.update');
    }
}

it('detalhe traz só as ações de avanço da etapa, com o gate; sem permissão de alterar, pode=false', function () {
    appOsComAcoes($this, false);
    $os = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);

    $acoes = $this->getJson('/api/app/os/' . $os)->assertOk()->json('acoes');
    // Avanço primeiro, depois a que encerra (cancelar_os), separadas por `tipo`.
    expect(array_column($acoes, 'chave'))->toBe(['concluir_servico', 'cancelar_os']);
    expect(array_column($acoes, 'tipo'))->toBe(['avanco', 'encerra']);
    expect($acoes[0]['critica'])->toBeTrue();
    expect($acoes[0]['pode'])->toBeFalse();
    // OS sem item: o gate do ERP barra a conclusão e diz o que falta.
    expect($acoes[0]['bloqueio'])->toContain('Orçamento com ≥ 1 item lançado');

    Permission::firstOrCreate(['name' => 'oficinaauto.service_order.update', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.service_order.update');
    expect($this->getJson('/api/app/os/' . $os)->assertOk()->json('acoes.0.pode'))->toBeTrue();
});

it('executar avança a OS pela FSM e devolve o detalhe já na etapa nova', function () {
    appOsComAcoes($this);
    $os = appOsCriar((int) $this->biz->id, $this->etapas['pronto_retirada']);

    $r = $this->postJson("/api/app/os/{$os}/acoes/entregar")->assertOk();
    expect($r->json('id'))->toBe($os);
    expect($r->json('etapa.chave'))->toBe('entregue');
    expect($r->json('etapa.terminal'))->toBeTrue();
    expect($r->json('acoes'))->toBe([]);
    expect((int) DB::table('service_orders')->where('id', $os)->value('current_stage_id'))->toBe($this->etapas['entregue']);
    // Trilha auditável da FSM (a mesma da web).
    expect(DB::table('sale_stage_history')->where('transaction_id', $os)->where('to_stage_id', $this->etapas['entregue'])->exists())->toBeTrue();
});

it('gate barra: 422 bloqueado e a OS não muda de etapa', function () {
    appOsComAcoes($this);
    $os = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);

    $this->postJson("/api/app/os/{$os}/acoes/concluir_servico")->assertStatus(422)->assertJsonPath('erro', 'bloqueado');
    expect((int) DB::table('service_orders')->where('id', $os)->value('current_stage_id'))->toBe($this->etapas['em_execucao']);
});

it('409 quando a ação não sai da etapa atual; 422 para ação que só a web faz; 403 sem permissão; 404 outra empresa', function () {
    appOsComAcoes($this, false);
    $minha = appOsCriar((int) $this->biz->id, $this->etapas['recepcao']);
    $emExecucao = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);
    $alheia = appOsCriar((int) $this->outroBiz->id, $this->etapas['recepcao']);

    $this->postJson("/api/app/os/{$minha}/acoes/iniciar_diagnostico")->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    Permission::firstOrCreate(['name' => 'oficinaauto.service_order.update', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.service_order.update');

    $this->postJson("/api/app/os/{$minha}/acoes/entregar")->assertStatus(409)->assertJsonPath('erro', 'etapa_mudou');
    // Ação fora das listas do app (ex.: o override do gate da web) segue 422.
    $this->postJson("/api/app/os/{$emExecucao}/acoes/override_gate")->assertStatus(422)->assertJsonPath('erro', 'nao_suportada');
    $this->postJson("/api/app/os/{$alheia}/acoes/iniciar_diagnostico")->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    // Controle positivo: na OS do próprio business a mesma ação avança.
    $this->postJson("/api/app/os/{$minha}/acoes/iniciar_diagnostico")->assertOk()->assertJsonPath('etapa.chave', 'em_diagnostico');
    expect((int) DB::table('service_orders')->where('id', $alheia)->value('current_stage_id'))->toBe($this->etapas['recepcao']);
});

it('ação com efeito colateral no banco não aparece nem executa pelo app', function () {
    appOsComAcoes($this);
    $e = $this->etapas;
    appOsAcao($e['pronto_retirada'], 'entregar', 'Entregar ao cliente', $e['entregue'], false, 'App\Fake\EfeitoQualquer');
    $os = appOsCriar((int) $this->biz->id, $e['pronto_retirada']);

    // A ação com efeito some; só sobra a que não tem (acionar garantia, nesta etapa).
    expect(array_column($this->getJson('/api/app/os/' . $os)->assertOk()->json('acoes'), 'chave'))->toBe(['acionar_garantia']);
    $this->postJson("/api/app/os/{$os}/acoes/entregar")->assertStatus(422)->assertJsonPath('erro', 'nao_suportada');
    expect((int) DB::table('service_orders')->where('id', $os)->value('current_stage_id'))->toBe($e['pronto_retirada']);
});

// ── Tela 07 — nova OS: POST /api/app/os + pode_criar; tela 08 — cliente_id ───────────────

function appOsPodeCriar(object $t): void
{
    Permission::firstOrCreate(['name' => 'oficinaauto.service_order.create', 'guard_name' => 'web']);
    $t->user->givePermissionTo('oficinaauto.service_order.create');
}

it('pode_criar acompanha a permissão de criar OS', function () {
    expect($this->getJson('/api/app/os')->assertOk()->json('pode_criar'))->toBeFalse();
    appOsPodeCriar($this);
    expect($this->getJson('/api/app/os')->assertOk()->json('pode_criar'))->toBeTrue();
});

it('nova OS nasce de mecânica na Recepção, ligada ao veículo, sem item nem venda', function () {
    appOsPodeCriar($this);
    $dono = DB::table('contacts')->where('business_id', $this->biz->id)->value('id');
    $v = (int) DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => 'NOV' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->postJson('/api/app/os', [
        'vehicle_id' => $v, 'contact_id' => $dono, 'mileage_at_service' => 48312,
        'box_label' => ' Elevador 1 ', 'notes' => 'Barulho na suspensão',
    ])->assertStatus(201);

    $id = (int) $r->json('id');
    expect($r->json('etapa.chave'))->toBe('recepcao');
    expect($r->json('local'))->toBe('Elevador 1');
    expect($r->json('veiculo.km'))->toBe(48312);
    expect($r->json('totais.total'))->toEqual(0);
    $os = DB::table('service_orders')->where('id', $id)->first();
    expect((int) $os->business_id)->toBe((int) $this->biz->id);
    expect($os->order_type)->toBe('mecanica');
    expect($os->status)->toBe('aberta');
    expect($os->transaction_id)->toBeNull();
    expect((int) DB::table('vehicles')->where('id', $v)->value('current_rental_id'))->toBe($id);
    expect(DB::table('oficina_service_order_items')->where('service_order_id', $id)->count())->toBe(0);
    // Aparece na lista da 07.
    expect(collect($this->getJson('/api/app/os')->assertOk()->json('itens'))->pluck('id'))->toContain($id);
});

it('nova OS: veículo ou cliente de OUTRA empresa é 422; sem permissão de criar é 403', function () {
    $antes = DB::table('service_orders')->count();
    $meu = (int) DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => 'MEU' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $alheio = (int) DB::table('vehicles')->insertGetId([
        'business_id' => $this->outroBiz->id, 'plate' => 'OUT' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $clienteAlheio = DB::table('contacts')->where('business_id', $this->outroBiz->id)->value('id');

    $this->postJson('/api/app/os', ['vehicle_id' => $meu])->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    appOsPodeCriar($this);
    $this->postJson('/api/app/os', [])->assertStatus(422)->assertJsonPath('campos.vehicle_id', 'Escolha o veículo.');
    $this->postJson('/api/app/os', ['vehicle_id' => $alheio])->assertStatus(422)->assertJsonPath('campos.vehicle_id', 'Veículo não encontrado.');
    if ($clienteAlheio) {
        $this->postJson('/api/app/os', ['vehicle_id' => $meu, 'contact_id' => $clienteAlheio])
            ->assertStatus(422)->assertJsonPath('campos.contact_id', 'Cliente não encontrado.');
    }
    expect(DB::table('service_orders')->count())->toBe($antes);
    // Controle positivo: com o veículo do próprio business cria.
    $this->postJson('/api/app/os', ['vehicle_id' => $meu])->assertStatus(201);
});

it('veículos trazem cliente_id do dono', function () {
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');
    $dono = (int) DB::table('contacts')->where('business_id', $this->biz->id)->value('id');
    if ($dono === 0) {
        $this->markTestSkipped('Lane sem contato no business.');
    }
    $placa = 'CLI' . random_int(1000, 9999);
    $v = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => $placa, 'vehicle_type' => 'caminhao', 'contact_id' => $dono,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $item = collect($this->getJson('/api/app/veiculos?q=' . $placa)->assertOk()->json('itens'))->firstWhere('id', $v);
    expect($item['cliente_id'])->toBe($dono);
});

// ── Tela 03 — encerrar pela FSM: cancelar_os e recusar_orcamento (pedido [W] 2026-10-05) ─────

it('cancelar com motivo encerra a OS pela FSM, grava o motivo na trilha e não toca status, veículo nem venda', function () {
    appOsComAcoes($this);
    $os = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);
    $veiculo = (int) DB::table('service_orders')->where('id', $os)->value('vehicle_id');
    DB::table('vehicles')->where('id', $veiculo)->update(['current_rental_id' => $os]);

    $r = $this->postJson("/api/app/os/{$os}/acoes/cancelar_os", ['motivo' => 'Cliente desistiu'])->assertOk();

    expect($r->json('etapa.chave'))->toBe('cancelado');
    expect($r->json('etapa.terminal'))->toBeTrue();
    expect($r->json('acoes'))->toBe([]);
    $linha = DB::table('service_orders')->where('id', $os)->first();
    expect((int) $linha->current_stage_id)->toBe($this->etapas['cancelado']);
    // Coluna legada intocada: o observer (venda automática / WhatsApp) só age quando ela muda.
    expect($linha->status)->toBe('aberta');
    expect($linha->transaction_id)->toBeNull();
    // Como na web: cancelar pela FSM da oficina não libera o veículo.
    expect((int) DB::table('vehicles')->where('id', $veiculo)->value('current_rental_id'))->toBe($os);
    $trilha = DB::table('sale_stage_history')->where('transaction_id', $os)
        ->where('to_stage_id', $this->etapas['cancelado'])->value('payload_snapshot');
    expect(json_decode((string) $trilha, true))->toMatchArray(['origem' => 'app', 'motivo' => 'Cliente desistiu']);
});

it('recusar orçamento aparece como encerra só em aguardando aprovação; sem motivo também vale', function () {
    appOsComAcoes($this);
    $os = appOsCriar((int) $this->biz->id, $this->etapas['aguardando_aprovacao']);

    $acoes = $this->getJson('/api/app/os/' . $os)->assertOk()->json('acoes');
    $recusar = collect($acoes)->firstWhere('chave', 'recusar_orcamento');
    expect($recusar)->not->toBeNull();
    expect($recusar['tipo'])->toBe('encerra');
    expect($recusar['critica'])->toBeTrue();

    $this->postJson("/api/app/os/{$os}/acoes/recusar_orcamento")->assertOk()->assertJsonPath('etapa.chave', 'cancelado');
});

it('motivo acima de 500 caracteres é 422 validacao e a OS não muda', function () {
    appOsComAcoes($this);
    $os = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);

    $this->postJson("/api/app/os/{$os}/acoes/cancelar_os", ['motivo' => str_repeat('a', 501)])
        ->assertStatus(422)->assertJsonPath('erro', 'validacao');
    expect((int) DB::table('service_orders')->where('id', $os)->value('current_stage_id'))->toBe($this->etapas['em_execucao']);
});

// ── Tela 03 — acionar garantia pelo app, com motivo obrigatório (pedido [W] 2026-10-05) ────────

it('acionar garantia aparece como encerra depois de cancelar, só em pronto p/ retirar, com destino e motivo obrigatório', function () {
    appOsComAcoes($this);
    $e = $this->etapas;
    appOsAcao($e['pronto_retirada'], 'cancelar_os', 'Cancelar OS', $e['cancelado'], true);
    $os = appOsCriar((int) $this->biz->id, $e['pronto_retirada']);

    $acoes = $this->getJson('/api/app/os/' . $os)->assertOk()->json('acoes');
    expect(array_column($acoes, 'chave'))->toBe(['entregar', 'cancelar_os', 'acionar_garantia']);
    expect(array_column($acoes, 'tipo'))->toBe(['avanco', 'encerra', 'encerra']);
    $garantia = $acoes[2];
    expect($garantia['critica'])->toBeTrue();
    expect($garantia['motivo_obrigatorio'])->toBeTrue();
    expect($garantia['destino'])->toBe(['chave' => 'garantia_acionada', 'rotulo' => 'Garantia acionada']);
    // Toda ação diz o destino; só a garantia exige motivo.
    expect($acoes[0]['destino'])->toBe(['chave' => 'entregue', 'rotulo' => 'Entregue']);
    expect($acoes[1]['destino'])->toBe(['chave' => 'cancelado', 'rotulo' => 'Cancelado']);
    expect($acoes[0]['motivo_obrigatorio'])->toBeFalse();
    expect($acoes[1]['motivo_obrigatorio'])->toBeFalse();

    // Fora de pronto p/ retirar ela não aparece.
    $emExecucao = appOsCriar((int) $this->biz->id, $e['em_execucao']);
    expect(array_column($this->getJson('/api/app/os/' . $emExecucao)->assertOk()->json('acoes'), 'chave'))
        ->not->toContain('acionar_garantia');
});

it('acionar garantia sem motivo é 422 validacao com a mensagem da garantia, e a OS não muda', function () {
    appOsComAcoes($this);
    $os = appOsCriar((int) $this->biz->id, $this->etapas['pronto_retirada']);

    foreach ([[], ['motivo' => null], ['motivo' => '']] as $corpo) {
        $this->postJson("/api/app/os/{$os}/acoes/acionar_garantia", $corpo)
            ->assertStatus(422)
            ->assertJsonPath('erro', 'validacao')
            ->assertJsonPath('campos.motivo', 'Informe o motivo da garantia.');
    }
    expect((int) DB::table('service_orders')->where('id', $os)->value('current_stage_id'))->toBe($this->etapas['pronto_retirada']);
    expect(DB::table('sale_stage_history')->where('transaction_id', $os)->exists())->toBeFalse();
});

it('acionar garantia com motivo encerra pela FSM, grava o motivo e não toca status, veículo, venda nem abre OS filha', function () {
    appOsComAcoes($this);
    $os = appOsCriar((int) $this->biz->id, $this->etapas['pronto_retirada']);
    $veiculo = (int) DB::table('service_orders')->where('id', $os)->value('vehicle_id');
    DB::table('vehicles')->where('id', $veiculo)->update(['current_rental_id' => $os]);
    $osAntes = DB::table('service_orders')->where('business_id', $this->biz->id)->count();

    $r = $this->postJson("/api/app/os/{$os}/acoes/acionar_garantia", ['motivo' => 'Barulho voltou na suspensão'])->assertOk();

    expect($r->json('etapa.chave'))->toBe('garantia_acionada');
    expect($r->json('etapa.terminal'))->toBeTrue();
    expect($r->json('acoes'))->toBe([]);
    $linha = DB::table('service_orders')->where('id', $os)->first();
    expect((int) $linha->current_stage_id)->toBe($this->etapas['garantia_acionada']);
    expect($linha->status)->toBe('aberta');
    expect($linha->transaction_id)->toBeNull();
    expect((int) DB::table('vehicles')->where('id', $veiculo)->value('current_rental_id'))->toBe($os);
    expect(DB::table('service_orders')->where('business_id', $this->biz->id)->count())->toBe($osAntes);
    $trilha = DB::table('sale_stage_history')->where('transaction_id', $os)
        ->where('to_stage_id', $this->etapas['garantia_acionada'])->value('payload_snapshot');
    expect(json_decode((string) $trilha, true))->toMatchArray(['origem' => 'app', 'motivo' => 'Barulho voltou na suspensão']);
});

it('cancelar e recusar seguem com motivo opcional', function () {
    appOsComAcoes($this);
    $os = appOsCriar((int) $this->biz->id, $this->etapas['em_execucao']);

    $this->postJson("/api/app/os/{$os}/acoes/cancelar_os")->assertOk()->assertJsonPath('etapa.chave', 'cancelado');
});

// ── Novo veículo pelo app: GET /api/app/veiculos/opcoes + POST /api/app/veiculos (pedido [W] 2026-10-05) ──

/** Ver e criar veículo (permissões da web). */
function appOsPodeCriarVeiculo(object $t): void
{
    foreach (['oficinaauto.vehicle.view', 'oficinaauto.vehicle.create'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $t->user->givePermissionTo($p);
    }
}

function appOsPlacaNova(): string
{
    return 'TZ' . chr(random_int(65, 90)) . random_int(1, 9) . chr(random_int(65, 90)) . random_int(10, 99);
}

it('novo veículo: opções trazem os tipos do ERP na ordem; pode_criar acompanha a permissão de criar', function () {
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');

    $tipos = $this->getJson('/api/app/veiculos/opcoes')->assertOk()->json('tipos');
    expect($tipos[0])->toBe(['chave' => 'caminhao', 'rotulo' => 'Caminhão']);
    expect(array_column($tipos, 'chave'))->toBe(array_keys(\App\Domain\Oficina\TiposVeiculo::ROTULOS));

    expect($this->getJson('/api/app/veiculos')->assertOk()->json('pode_criar'))->toBeFalse();
    appOsPodeCriarVeiculo($this);
    expect($this->getJson('/api/app/veiculos')->assertOk()->json('pode_criar'))->toBeTrue();
});

it('novo veículo: cria no business do token com a placa normalizada e devolve 201 no formato da lista', function () {
    appOsPodeCriarVeiculo($this);
    $dono = (int) DB::table('contacts')->where('business_id', $this->biz->id)->value('id');
    $placa = appOsPlacaNova();

    $r = $this->postJson('/api/app/veiculos', [
        'placa' => strtolower(substr($placa, 0, 3)) . '-' . substr($placa, 3), 'tipo' => 'cavalo',
        'ano_fabricacao' => 2019, 'ano_modelo' => 2020, 'cor' => 'Branco', 'km' => 48312,
        'renavam' => '12345678901', 'contact_id' => $dono,
    ])->assertStatus(201);

    expect($r->json('placa'))->toBe($placa);
    expect($r->json('descricao'))->toBe('Cavalo (truck-cabine)');
    expect($r->json('ano'))->toBe('2019/2020');
    expect($r->json('km'))->toBe(48312);
    expect($r->json('cliente_id'))->toBe($dono);
    $linha = DB::table('vehicles')->where('id', $r->json('id'))->first();
    expect((int) $linha->business_id)->toBe((int) $this->biz->id);
    expect($linha->plate)->toBe($placa);
    expect($linha->renavam)->toBe('12345678901');
    // Só o veículo: nenhuma OS nem venda nasce junto.
    expect(DB::table('service_orders')->where('vehicle_id', $linha->id)->exists())->toBeFalse();
});

it('novo veículo: placa em outro veículo ativo do business (principal ou reboque) é 422 com o id dele; excluído e de outra empresa não bloqueiam', function () {
    appOsPodeCriarVeiculo($this);
    $placa = appOsPlacaNova();
    $reboque = appOsPlacaNova();
    $ativo = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => substr($placa, 0, 3) . '-' . substr($placa, 3),
        'secondary_plate' => $reboque, 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->postJson('/api/app/veiculos', ['placa' => $placa, 'tipo' => 'caminhao'])->assertStatus(422)
        ->assertJsonPath('erro', 'validacao')
        ->assertJsonPath('campos.placa', 'Esta placa já está em outro veículo ativo.')
        ->assertJsonPath('veiculo_existente_id', $ativo);
    $this->postJson('/api/app/veiculos', ['placa' => appOsPlacaNova(), 'placa_secundaria' => $reboque, 'tipo' => 'semi_reboque'])
        ->assertStatus(422)->assertJsonPath('campos.placa_secundaria', 'Esta placa já está em outro veículo ativo.');
    expect(DB::table('vehicles')->where('business_id', $this->biz->id)->whereNull('deleted_at')
        ->whereIn('plate', [$placa, $reboque])->count())->toBe(0);

    // Excluído (soft delete) não bloqueia; placa igual em outra empresa também não.
    $livre = appOsPlacaNova();
    DB::table('vehicles')->insert([
        'business_id' => $this->biz->id, 'plate' => $livre, 'vehicle_type' => 'caminhao',
        'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('vehicles')->insert([
        'business_id' => $this->outroBiz->id, 'plate' => $livre, 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->postJson('/api/app/veiculos', ['placa' => $livre, 'tipo' => 'caminhao'])->assertStatus(201);
});

it('novo veículo: dono de OUTRA empresa é 422; sem placa ou tipo é 422; sem permissão de criar é 403', function () {
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');
    $this->postJson('/api/app/veiculos', ['placa' => appOsPlacaNova(), 'tipo' => 'caminhao'])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    appOsPodeCriarVeiculo($this);
    $alheio = (int) DB::table('contacts')->where('business_id', $this->outroBiz->id)->value('id');
    $placa = appOsPlacaNova();
    $this->postJson('/api/app/veiculos', ['placa' => $placa, 'tipo' => 'caminhao', 'contact_id' => $alheio])
        ->assertStatus(422)->assertJsonPath('campos.contact_id', 'Cliente não encontrado.');
    $this->postJson('/api/app/veiculos', ['placa' => '--', 'tipo' => 'foguete'])
        ->assertStatus(422)
        ->assertJsonPath('campos.placa', 'A placa do veículo é obrigatória.')
        ->assertJsonPath('campos.tipo', 'Tipo de veículo inválido.');
    expect(DB::table('vehicles')->where('plate', $placa)->exists())->toBeFalse();
});

// ── Consulta de placa pelo app: GET /api/app/veiculos/consulta-placa/{placa} (pedido [W] 2026-10-05) ──

it('consulta de placa: em teste (stub) devolve só dados técnicos no formato do cadastro, sem proprietário; NF* não encontrada', function () {
    appOsPodeCriarVeiculo($this);
    config()->set('oficina-auto.placa_lookup.driver', 'stub');
    \Illuminate\Support\Facades\Cache::flush();

    expect($this->getJson('/api/app/veiculos/opcoes')->assertOk()->json('consulta_placa'))->toBeTrue();

    $placa = appOsPlacaNova();
    $r = $this->getJson('/api/app/veiculos/consulta-placa/' . strtolower($placa))->assertOk();
    expect($r->json('encontrado'))->toBeTrue();
    expect(array_keys($r->json('dados')))->toBe(['placa', 'ano_fabricacao', 'ano_modelo', 'cor', 'chassi', 'renavam', 'marca_modelo']);
    expect($r->json('dados.placa'))->toBe($placa);

    $this->getJson('/api/app/veiculos/consulta-placa/NFA1B23')->assertOk()
        ->assertJsonPath('encontrado', false)
        ->assertJsonPath('mensagem', 'Nenhum dado encontrado para esta placa.');
});

it('consulta de placa: em produção sem fornecedor (stub) responde 503 sem_configuracao e opções avisam que não há consulta', function () {
    appOsPodeCriarVeiculo($this);
    config()->set('oficina-auto.placa_lookup.driver', 'stub');
    $env = app()['env'];
    app()['env'] = 'live';
    try {
        expect($this->getJson('/api/app/veiculos/opcoes')->assertOk()->json('consulta_placa'))->toBeFalse();
        $this->getJson('/api/app/veiculos/consulta-placa/' . appOsPlacaNova())->assertStatus(503)
            ->assertJsonPath('erro', 'sem_configuracao')
            ->assertJsonPath('mensagem', 'Consulta de placa não configurada.');
    } finally {
        app()['env'] = $env;
    }
});

it('consulta de placa: placa já em veículo ativo devolve o id dele sem consultar; inválida é 422; sem permissão de criar é 403', function () {
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');
    $this->getJson('/api/app/veiculos/consulta-placa/' . appOsPlacaNova())->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    appOsPodeCriarVeiculo($this);
    // Driver que falharia se fosse chamado: prova que a placa existente não gasta consulta.
    config()->set('oficina-auto.placa_lookup.driver', 'nenhum');
    $placa = appOsPlacaNova();
    $ativo = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => $placa, 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->getJson('/api/app/veiculos/consulta-placa/' . $placa)->assertOk()
        ->assertJsonPath('encontrado', false)
        ->assertJsonPath('veiculo_existente_id', $ativo);

    $this->getJson('/api/app/veiculos/consulta-placa/ABC12')->assertStatus(422)->assertJsonPath('erro', 'validacao');
});

// ── Web: cadastro e edição de veículo também recusam placa ativa repetida (decisão [W] 2026-10-05) ──

/** Roda as regras + o after() do FormRequest da web com o usuário do teste (sem passar pelo HTTP da web). */
function appOsValidaRequestVeiculo(object $t, string $classe, array $dados, ?object $veiculo = null): \Illuminate\Validation\Validator
{
    $req = $classe::create('/oficina-auto/veiculos', $veiculo ? 'PUT' : 'POST', $dados);
    $req->setContainer(app());
    $req->setUserResolver(fn () => $t->user);
    if ($veiculo !== null) {
        $rota = new \Illuminate\Routing\Route('PUT', 'oficina-auto/veiculos/{vehicle}', []);
        $rota->bind($req);
        $rota->setParameter('vehicle', $veiculo);
        $req->setRouteResolver(fn () => $rota);
    }
    $v = \Illuminate\Support\Facades\Validator::make($req->all(), $req->rules(), $req->messages());
    $req->withValidator($v);
    $v->passes();

    return $v;
}

it('web: cadastrar veículo com placa já em outro veículo ativo da empresa (principal ou reboque) é recusado; excluído e outra empresa não bloqueiam', function () {
    $placa = appOsPlacaNova();
    $reboque = appOsPlacaNova();
    DB::table('vehicles')->insert([
        'business_id' => $this->biz->id, 'plate' => strtolower($placa), 'secondary_plate' => $reboque,
        'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $classe = \Modules\OficinaAuto\Http\Requests\StoreVehicleRequest::class;

    $v = appOsValidaRequestVeiculo($this, $classe, ['plate' => substr($placa, 0, 3) . '-' . substr($placa, 3), 'vehicle_type' => 'caminhao']);
    expect($v->errors()->first('plate'))->toBe('Esta placa já está em outro veículo ativo.');
    $v = appOsValidaRequestVeiculo($this, $classe, ['plate' => appOsPlacaNova(), 'secondary_plate' => $reboque, 'vehicle_type' => 'semi_reboque']);
    expect($v->errors()->first('secondary_plate'))->toBe('Esta placa já está em outro veículo ativo.');

    $livre = appOsPlacaNova();
    DB::table('vehicles')->insert([
        ['business_id' => $this->biz->id, 'plate' => $livre, 'vehicle_type' => 'caminhao', 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => $this->outroBiz->id, 'plate' => $livre, 'vehicle_type' => 'caminhao', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
    expect(appOsValidaRequestVeiculo($this, $classe, ['plate' => $livre, 'vehicle_type' => 'caminhao'])->errors()->isEmpty())->toBeTrue();
});

it('web: editar veículo para uma placa de outro veículo ativo é recusado; manter a própria placa (mesmo se já duplicada antes da regra) continua editável', function () {
    $placaA = appOsPlacaNova();
    $placaB = appOsPlacaNova();
    $a = DB::table('vehicles')->insertGetId(['business_id' => $this->biz->id, 'plate' => $placaA, 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('vehicles')->insert(['business_id' => $this->biz->id, 'plate' => $placaB, 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);
    // Duplicata legada (nasceu antes da regra): mesma placa do A.
    DB::table('vehicles')->insert(['business_id' => $this->biz->id, 'plate' => $placaA, 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);
    $classe = \Modules\OficinaAuto\Http\Requests\UpdateVehicleRequest::class;
    $veiculoA = \Modules\OficinaAuto\Entities\Vehicle::withoutGlobalScopes()->findOrFail($a);

    $v = appOsValidaRequestVeiculo($this, $classe, ['plate' => $placaB, 'vehicle_type' => 'caminhao'], $veiculoA);
    expect($v->errors()->first('plate'))->toBe('Esta placa já está em outro veículo ativo.');

    // Mantendo a própria placa (e mudando só o km), passa — inclusive com a duplicata legada.
    $v = appOsValidaRequestVeiculo($this, $classe, ['plate' => $placaA, 'vehicle_type' => 'caminhao', 'mileage_at_entry' => 1000], $veiculoA);
    expect($v->errors()->isEmpty())->toBeTrue();
});

// ── Editar veículo pelo app: GET + PUT /api/app/veiculos/{id} (pedido [W] 2026-10-05) ──

/** Ver e editar veículo (permissões da web). */
function appOsPodeEditarVeiculo(object $t): void
{
    foreach (['oficinaauto.vehicle.view', 'oficinaauto.vehicle.update'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $t->user->givePermissionTo($p);
    }
}

it('editar veículo: detalhe traz os campos do formulário (tipo como chave, anos separados, km do cadastro) e pode_editar acompanha a permissão', function () {
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');
    $dono = (int) DB::table('contacts')->where('business_id', $this->biz->id)->value('id');
    $placa = appOsPlacaNova();
    $v = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => $placa, 'vehicle_type' => 'cavalo', 'manufacture_year' => 2019,
        'model_year' => 2020, 'color' => 'Branco', 'mileage_at_entry' => 40000, 'chassis' => '9BWZZZ377VT004251',
        'renavam' => '12345678901', 'contact_id' => $dono, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->getJson('/api/app/veiculos/' . $v)->assertOk();
    expect($r->json())->toMatchArray([
        'id' => $v, 'placa' => $placa, 'tipo' => 'cavalo', 'ano_fabricacao' => 2019, 'ano_modelo' => 2020,
        'cor' => 'Branco', 'km' => 40000, 'chassi' => '9BWZZZ377VT004251', 'renavam' => '12345678901',
        'contact_id' => $dono, 'pode_editar' => false,
    ]);
    expect($this->getJson('/api/app/veiculos')->assertOk()->json('pode_editar'))->toBeFalse();

    appOsPodeEditarVeiculo($this);
    expect($this->getJson('/api/app/veiculos/' . $v)->assertOk()->json('pode_editar'))->toBeTrue();
    expect($this->getJson('/api/app/veiculos')->assertOk()->json('pode_editar'))->toBeTrue();
});

it('editar veículo: PUT grava os campos, aceita km menor, não toca as OS existentes e devolve o item da lista', function () {
    appOsPodeEditarVeiculo($this);
    $placa = appOsPlacaNova();
    $v = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => $placa, 'vehicle_type' => 'caminhao', 'mileage_at_entry' => 50000,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $os = DB::table('service_orders')->insertGetId([
        'business_id' => $this->biz->id, 'vehicle_id' => $v, 'order_type' => 'mecanica', 'status' => 'aberta',
        'mileage_at_service' => 50100, 'contact_id' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $dono = (int) DB::table('contacts')->where('business_id', $this->biz->id)->value('id');
    $nova = appOsPlacaNova();

    $r = $this->putJson('/api/app/veiculos/' . $v, [
        'placa' => strtolower($nova), 'tipo' => 'cavalo', 'cor' => 'Azul', 'km' => 1000, 'contact_id' => $dono,
    ])->assertOk();

    expect($r->json('id'))->toBe($v);
    expect($r->json('placa'))->toBe($nova);
    $linha = DB::table('vehicles')->where('id', $v)->first();
    expect($linha->plate)->toBe($nova);
    expect($linha->vehicle_type)->toBe('cavalo');
    expect((int) $linha->mileage_at_entry)->toBe(1000);
    expect((int) $linha->contact_id)->toBe($dono);
    $osDepois = DB::table('service_orders')->where('id', $os)->first();
    expect((int) $osDepois->mileage_at_service)->toBe(50100);
    expect($osDepois->contact_id)->toBeNull();
});

it('editar veículo: trocar para placa de outro ativo é 422 com o id; manter a própria passa; outra empresa é 404; dono de outra empresa é 422; sem permissão é 403', function () {
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');
    $placaA = appOsPlacaNova();
    $placaB = appOsPlacaNova();
    $a = DB::table('vehicles')->insertGetId(['business_id' => $this->biz->id, 'plate' => $placaA, 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);
    $b = DB::table('vehicles')->insertGetId(['business_id' => $this->biz->id, 'plate' => $placaB, 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);
    $alheio = DB::table('vehicles')->insertGetId(['business_id' => $this->outroBiz->id, 'plate' => appOsPlacaNova(), 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);

    $this->putJson('/api/app/veiculos/' . $a, ['placa' => $placaA, 'tipo' => 'caminhao'])->assertStatus(403);

    appOsPodeEditarVeiculo($this);
    $this->putJson('/api/app/veiculos/' . $a, ['placa' => $placaB, 'tipo' => 'caminhao'])->assertStatus(422)
        ->assertJsonPath('campos.placa', 'Esta placa já está em outro veículo ativo.')
        ->assertJsonPath('veiculo_existente_id', $b);
    // Duplicata legada (nasceu antes da regra): manter a própria placa continua editável.
    DB::table('vehicles')->insert(['business_id' => $this->biz->id, 'plate' => $placaA, 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);
    $this->putJson('/api/app/veiculos/' . $a, ['placa' => $placaA, 'tipo' => 'caminhao', 'cor' => 'Verde'])->assertOk();

    $this->getJson('/api/app/veiculos/' . $alheio)->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    $this->putJson('/api/app/veiculos/' . $alheio, ['placa' => appOsPlacaNova(), 'tipo' => 'caminhao'])->assertStatus(404);
    $donoAlheio = (int) DB::table('contacts')->where('business_id', $this->outroBiz->id)->value('id');
    $this->putJson('/api/app/veiculos/' . $a, ['placa' => $placaA, 'tipo' => 'caminhao', 'contact_id' => $donoAlheio])
        ->assertStatus(422)->assertJsonPath('campos.contact_id', 'Cliente não encontrado.');
    expect((int) DB::table('vehicles')->where('id', $alheio)->value('business_id'))->toBe((int) $this->outroBiz->id);
});

// ── Excluir veículo pelo app: DELETE /api/app/veiculos/{id} (pedido [W] 2026-10-05) ──

/** Ver e excluir veículo (permissões da web). */
function appOsPodeExcluirVeiculo(object $t): void
{
    foreach (['oficinaauto.vehicle.view', 'oficinaauto.vehicle.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $t->user->givePermissionTo($p);
    }
}

it('excluir veículo: soft delete, some da lista, libera a placa; pode_excluir acompanha a permissão', function () {
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');
    $placa = appOsPlacaNova();
    $v = DB::table('vehicles')->insertGetId(['business_id' => $this->biz->id, 'plate' => $placa, 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);
    expect($this->getJson('/api/app/veiculos/' . $v)->assertOk()->json('pode_excluir'))->toBeFalse();
    $this->deleteJson('/api/app/veiculos/' . $v)->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    appOsPodeExcluirVeiculo($this);
    expect($this->getJson('/api/app/veiculos/' . $v)->assertOk()->json('pode_excluir'))->toBeTrue();
    $this->deleteJson('/api/app/veiculos/' . $v)->assertOk()->assertJsonPath('ok', true);

    // Soft delete: a linha continua no banco, com deleted_at.
    expect(DB::table('vehicles')->where('id', $v)->value('deleted_at'))->not->toBeNull();
    $this->getJson('/api/app/veiculos/' . $v)->assertStatus(404);
    expect(collect($this->getJson('/api/app/veiculos?q=' . $placa)->assertOk()->json('itens'))->pluck('id'))->not->toContain($v);
    // A placa fica livre para outro cadastro.
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.create', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.create');
    $this->postJson('/api/app/veiculos', ['placa' => $placa, 'tipo' => 'caminhao'])->assertStatus(201);
});

it('excluir veículo: com OS em andamento é 409 em_uso e nada muda; OS encerrada ou fora do processo não impede', function () {
    appOsPodeExcluirVeiculo($this);
    $e = $this->etapas;
    $v = DB::table('vehicles')->insertGetId(['business_id' => $this->biz->id, 'plate' => appOsPlacaNova(), 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);
    $osAberta = (int) DB::table('service_orders')->insertGetId([
        'business_id' => $this->biz->id, 'vehicle_id' => $v, 'order_type' => 'mecanica', 'status' => 'aberta',
        'current_stage_id' => $e['em_execucao'], 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('service_orders')->insert([
        'business_id' => $this->biz->id, 'vehicle_id' => $v, 'order_type' => 'mecanica', 'status' => 'aberta',
        'current_stage_id' => $e['entregue'], 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->deleteJson('/api/app/veiculos/' . $v)->assertStatus(409)
        ->assertJsonPath('erro', 'em_uso')
        ->assertJsonPath('os_abertas', 1)
        ->assertJsonPath('mensagem', 'Este veículo tem 1 OS em andamento. Encerre a OS antes de excluir.');
    expect(DB::table('vehicles')->where('id', $v)->value('deleted_at'))->toBeNull();

    // OS de mecânica ainda sem pipeline também conta (aparece na Recepção).
    DB::table('service_orders')->where('id', $osAberta)->update(['current_stage_id' => null]);
    $this->deleteJson('/api/app/veiculos/' . $v)->assertStatus(409)->assertJsonPath('os_abertas', 1);

    // Encerrada a OS, exclui.
    DB::table('service_orders')->where('id', $osAberta)->update(['current_stage_id' => $e['cancelado']]);
    $this->deleteJson('/api/app/veiculos/' . $v)->assertOk();
});

it('excluir veículo: de OUTRA empresa é 404 e não é tocado', function () {
    appOsPodeExcluirVeiculo($this);
    $alheio = DB::table('vehicles')->insertGetId(['business_id' => $this->outroBiz->id, 'plate' => appOsPlacaNova(), 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);

    $this->deleteJson('/api/app/veiculos/' . $alheio)->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    expect(DB::table('vehicles')->where('id', $alheio)->value('deleted_at'))->toBeNull();
});

// ── Histórico de km do veículo (pedido [W] 2026-10-05): km do cadastro + km de entrada de cada OS ──

it('histórico do veículo traz o km de entrada de cada OS e o km/data do cadastro', function () {
    $v = appOsVeiculoComPermissao($this);
    DB::table('vehicles')->where('id', $v)->update(['mileage_at_entry' => 40000, 'created_at' => '2026-01-10 08:00:00']);
    $velha = appOsDoVeiculo((int) $this->biz->id, $v, $this->etapas['entregue'], 'mecanica', '2026-03-01 09:00:00');
    $nova = appOsDoVeiculo((int) $this->biz->id, $v, $this->etapas['recepcao'], 'mecanica', '2026-06-01 09:00:00');
    DB::table('service_orders')->where('id', $velha)->update(['mileage_at_service' => 45200]);

    $r = $this->getJson("/api/app/veiculos/{$v}/os")->assertOk();

    expect($r->json('km_cadastro'))->toBe(40000);
    expect($r->json('cadastrado_em'))->toBe('2026-01-10');
    expect(array_column($r->json('itens'), 'os_id'))->toBe([$nova, $velha]);
    // Km da entrada de cada OS; null quando não foi anotado.
    expect(array_column($r->json('itens'), 'km'))->toBe([null, 45200]);
});

// ── Lembrete de revisão por km (decisão [W] 2026-10-06): proxima_revisao_km + filtro revisao=1 ──

it('revisão por km: cadastrar e editar gravam a próxima revisão; detalhe e lista devolvem o campo', function () {
    foreach (['oficinaauto.vehicle.view', 'oficinaauto.vehicle.create', 'oficinaauto.vehicle.update'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }
    $placa = appOsPlacaNova();

    $r = $this->postJson('/api/app/veiculos', ['placa' => $placa, 'tipo' => 'caminhao', 'km' => 40000, 'proxima_revisao_km' => 50000])
        ->assertStatus(201);
    $id = (int) $r->json('id');
    expect($r->json('proxima_revisao_km'))->toBe(50000);
    expect((int) DB::table('vehicles')->where('id', $id)->value('next_service_km'))->toBe(50000);

    $this->putJson('/api/app/veiculos/' . $id, ['placa' => $placa, 'tipo' => 'caminhao', 'km' => 40000, 'proxima_revisao_km' => 60000])->assertOk();
    expect($this->getJson('/api/app/veiculos/' . $id)->assertOk()->json('proxima_revisao_km'))->toBe(60000);

    // PUT SEM a chave (o app já em produção não conhece o campo) mantém a revisão marcada.
    $this->putJson('/api/app/veiculos/' . $id, ['placa' => $placa, 'tipo' => 'caminhao', 'cor' => 'Azul'])->assertOk();
    expect((int) DB::table('vehicles')->where('id', $id)->value('next_service_km'))->toBe(60000);
    // PUT com a chave em null apaga.
    $this->putJson('/api/app/veiculos/' . $id, ['placa' => $placa, 'tipo' => 'caminhao', 'proxima_revisao_km' => null])->assertOk();
    expect(DB::table('vehicles')->where('id', $id)->value('next_service_km'))->toBeNull();

    $this->postJson('/api/app/veiculos', ['placa' => appOsPlacaNova(), 'tipo' => 'caminhao', 'proxima_revisao_km' => -1])
        ->assertStatus(422)->assertJsonPath('campos.proxima_revisao_km', 'O km da próxima revisão não pode ser negativo.');
});

it('revisão por km: revisao=1 traz só os perto (até 1.000 km) ou atrasados, do mais atrasado ao que falta mais; a contagem não depende do filtro', function () {
    Permission::firstOrCreate(['name' => 'oficinaauto.vehicle.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('oficinaauto.vehicle.view');
    $base = $this->getJson('/api/app/veiculos')->assertOk()->json('revisao_proxima');
    $novo = fn (?int $km, ?int $prox) => DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz->id, 'plate' => appOsPlacaNova(), 'vehicle_type' => 'caminhao',
        'mileage_at_entry' => $km, 'next_service_km' => $prox, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $atrasado = $novo(52000, 50000);   // passou 2.000
    $perto = $novo(49500, 50000);      // faltam 500
    $longe = $novo(40000, 50000);      // faltam 10.000
    $semRevisao = $novo(90000, null);
    $proxBaixa = $novo(null, 300);     // km 0, próxima 300: dentro do aviso, e sem estourar a conta sem sinal
    // Km real vem também da OS: cadastro 30.000, OS com 49.200 → faltam 800.
    $pelaOs = $novo(30000, 50000);
    DB::table('service_orders')->insert([
        'business_id' => $this->biz->id, 'vehicle_id' => $pelaOs, 'order_type' => 'mecanica', 'status' => 'aberta',
        'mileage_at_service' => 49200, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $alheio = DB::table('vehicles')->insertGetId([
        'business_id' => $this->outroBiz->id, 'plate' => appOsPlacaNova(), 'vehicle_type' => 'caminhao',
        'mileage_at_entry' => 99999, 'next_service_km' => 1000, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->getJson('/api/app/veiculos?revisao=1')->assertOk();
    $ids = array_column($r->json('itens'), 'id');
    foreach ([$atrasado, $perto, $proxBaixa, $pelaOs] as $esperado) {
        expect($ids)->toContain($esperado);
    }
    foreach ([$longe, $semRevisao, $alheio] as $fora) {
        expect($ids)->not->toContain($fora);
    }
    // Do mais atrasado ao que falta mais: atrasado (+2.000) antes de perto (−500) antes de pela OS (−800).
    $pos = array_flip($ids);
    expect($pos[$atrasado])->toBeLessThan($pos[$perto]);
    expect($pos[$perto])->toBeLessThan($pos[$pelaOs]);
    expect($r->json('revisao_aviso_km'))->toBe(1000);
    // A contagem é a mesma com e sem o filtro, e conta só os 4 novos deste business.
    expect($r->json('revisao_proxima'))->toBe($base + 4);
    expect($this->getJson('/api/app/veiculos')->assertOk()->json('revisao_proxima'))->toBe($base + 4);
});

it('web: cadastro e edição aceitam a próxima revisão (km ≥ 0) e recusam negativo', function () {
    $store = \Modules\OficinaAuto\Http\Requests\StoreVehicleRequest::class;
    $ok = appOsValidaRequestVeiculo($this, $store, ['plate' => appOsPlacaNova(), 'vehicle_type' => 'caminhao', 'next_service_km' => 50000]);
    expect($ok->errors()->isEmpty())->toBeTrue();
    expect($ok->validated()['next_service_km'])->toBe(50000);
    $neg = appOsValidaRequestVeiculo($this, $store, ['plate' => appOsPlacaNova(), 'vehicle_type' => 'caminhao', 'next_service_km' => -5]);
    expect($neg->errors()->first('next_service_km'))->toBe('O km da próxima revisão não pode ser negativo.');

    $placa = appOsPlacaNova();
    $id = DB::table('vehicles')->insertGetId(['business_id' => $this->biz->id, 'plate' => $placa, 'vehicle_type' => 'caminhao', 'created_at' => now(), 'updated_at' => now()]);
    $veiculo = \Modules\OficinaAuto\Entities\Vehicle::withoutGlobalScopes()->findOrFail($id);
    $upd = appOsValidaRequestVeiculo($this, \Modules\OficinaAuto\Http\Requests\UpdateVehicleRequest::class, ['plate' => $placa, 'vehicle_type' => 'caminhao', 'next_service_km' => 61000], $veiculo);
    expect($upd->errors()->isEmpty())->toBeTrue();
    // O campo está nas regras da web: chega no validated() que o controller usa para gravar.
    expect($upd->validated()['next_service_km'])->toBe(61000);
});
