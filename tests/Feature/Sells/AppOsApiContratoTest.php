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

/** Processo da oficina com as 6 etapas de quadro + 1 terminal, no business. @return array<string,int> */
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
