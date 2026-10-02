<?php

declare(strict_types=1);

// @covers-us US-CONSULTA-001

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\ConsultaOs\Repositories\RepairConsultaOsRepository;
use Modules\Repair\Entities\JobSheet;

/**
 * Contrato da tela pública /consulta-os — resources/js/Pages/ConsultaOs/Index.casos.md.
 *
 * Os UC derivam do charter (Index.charter.md: Goals, Non-Goals, Anti-hooks), nunca do .tsx.
 * Cada `it()` cita o UC que defende (casos-gate G-2).
 *
 * US-CONSULTA-001 (2026-10-02): a busca lê as folhas de OS reais do Repair. O schema mínimo
 * é criado em SQLite in-memory, no idioma de Modules/Repair/Tests/Feature/
 * PortalStatusReparoSemVazamentoTest.php. Tenants fictícios 98 e 99 (ADR 0358) — nunca o 4.
 * Fora de SQLite o teste pula (cria tabelas; não pode rodar contra banco persistente).
 *
 * Asserts de ausência usam assertArrayNotHasKey / not->toContain com 1 argumento, nunca
 * `->not->toHaveKey($k, $msg)` (proibicoes §5 2026-09-22, LC-31).
 */

const CONSULTA_OS_TABELAS = [
    'activity_log', 'repair_job_sheets', 'transactions', 'contacts',
    'repair_statuses', 'brands', 'repair_device_models', 'categories',
];

/** Chaves que o payload público de cada OS pode ter — e nenhuma outra. */
const CONSULTA_OS_CHAVES_OS = [
    'numero', 'marca', 'aparelho', 'modelo', 'serie', 'status', 'previsao_entrega', 'atividades',
];

const CONSULTA_OS_CHAVES_ATIVIDADE = ['data', 'acao', 'por', 'nota', 'conclusao_de', 'conclusao_para'];

/** Valores gravados em colunas internas que nunca podem aparecer na resposta. */
const CONSULTA_OS_SEGREDOS = ['CUSTO-INTERNO-777', 'SENHA-APARELHO-1234', 'DEFEITO-INTERNO', 'Cliente Sigiloso', '11122233344'];

function consultaOsSqlite(): void
{
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('Cria schema mínimo do Repair — só em SQLite in-memory.');
    }
}

function consultaOsSemear(): void
{
    consultaOsSqlite();

    foreach (CONSULTA_OS_TABELAS as $tabela) {
        Schema::dropIfExists($tabela);
    }

    Schema::create('activity_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable();
        $t->text('description')->nullable();
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->string('subject_type')->nullable();
        $t->unsignedBigInteger('causer_id')->nullable();
        $t->string('causer_type')->nullable();
        $t->text('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->string('event')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->timestamps();
    });
    Schema::create('repair_job_sheets', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->integer('contact_id');
        $t->integer('status_id')->nullable();
        $t->integer('brand_id')->nullable();
        $t->integer('device_model_id')->nullable();
        $t->integer('device_id')->nullable();
        $t->string('job_sheet_no')->nullable();
        $t->string('serial_no')->nullable();
        $t->dateTime('delivery_date')->nullable();
        // Colunas internas — existem no Repair real e NÃO podem sair no portal.
        $t->string('estimated_cost')->nullable();
        $t->string('security_pwd')->nullable();
        $t->text('defects')->nullable();
        $t->timestamps();
    });
    Schema::create('transactions', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->integer('repair_job_sheet_id')->nullable();
        $t->string('invoice_no')->nullable();
        $t->timestamps();
    });
    Schema::create('contacts', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->string('name')->nullable();
        $t->string('mobile')->nullable();
        $t->string('tax_number')->nullable();
        $t->timestamps();
    });
    Schema::create('repair_statuses', function (Blueprint $t) {
        $t->increments('id');
        $t->integer('business_id');
        $t->string('name');
        $t->string('color')->nullable();
        $t->timestamps();
    });
    foreach (['brands', 'repair_device_models', 'categories'] as $tabela) {
        Schema::create($tabela, function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }

    $agora = now();
    $marca = DB::table('brands')->insertGetId(['name' => 'Marca X', 'created_at' => $agora, 'updated_at' => $agora]);
    $modelo = DB::table('repair_device_models')->insertGetId(['name' => 'Modelo Y', 'created_at' => $agora, 'updated_at' => $agora]);
    $aparelho = DB::table('categories')->insertGetId(['name' => 'Celular', 'created_at' => $agora, 'updated_at' => $agora]);

    // [biz, nº OS, celular, nº venda, status, cor, série]
    $linhas = [
        [98, 'JS2026/0001', '48999990001', 'VND-98-1', 'Em reparo', '#3366ff', 'SERIE-A'],
        [99, 'OS-B', '48999990002', 'VND-99-1', 'Pronto', '#22aa55', 'SERIE-B'],
        // Mesmo nº de OS em outra empresa (numeração é sequencial por empresa).
        [99, 'JS2026/0001', '48999990003', 'VND-99-2', 'Aguardando peça', '#aa8800', 'SERIE-C'],
    ];

    foreach ($linhas as [$biz, $os, $celular, $venda, $status, $cor, $serie]) {
        $contato = DB::table('contacts')->insertGetId([
            'business_id' => $biz, 'name' => 'Cliente Sigiloso', 'mobile' => $celular,
            'tax_number' => '11122233344', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $statusId = DB::table('repair_statuses')->insertGetId([
            'business_id' => $biz, 'name' => $status, 'color' => $cor, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $folha = DB::table('repair_job_sheets')->insertGetId([
            'business_id' => $biz, 'contact_id' => $contato, 'status_id' => $statusId,
            'brand_id' => $marca, 'device_model_id' => $modelo, 'device_id' => $aparelho,
            'job_sheet_no' => $os, 'serial_no' => $serie, 'delivery_date' => '2026-10-10 14:00:00',
            'estimated_cost' => 'CUSTO-INTERNO-777', 'security_pwd' => 'SENHA-APARELHO-1234',
            'defects' => 'DEFEITO-INTERNO', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        DB::table('transactions')->insert([
            'business_id' => $biz, 'repair_job_sheet_id' => $folha, 'invoice_no' => $venda,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        DB::table('activity_log')->insert([
            [
                'log_name' => 'default', 'description' => 'status_changed',
                'subject_type' => (new JobSheet)->getMorphClass(), 'subject_id' => $folha,
                'properties' => json_encode(['updated_status' => $status, 'update_note' => "Nota de {$os}"]),
                'business_id' => $biz, 'created_at' => $agora, 'updated_at' => $agora,
            ],
            [
                // Log automático do LogsActivity: carrega o campo interno `defects` em `attributes`.
                'log_name' => 'repair_job_sheet', 'description' => 'updated',
                'subject_type' => (new JobSheet)->getMorphClass(), 'subject_id' => $folha,
                'properties' => json_encode(['attributes' => ['defects' => 'DEFEITO-INTERNO']]),
                'business_id' => $biz, 'created_at' => $agora, 'updated_at' => $agora,
            ],
        ]);
    }
}

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (CONSULTA_OS_TABELAS as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
});

function consultaOsBuscar(array $q): \Illuminate\Testing\TestResponse
{
    return test()->getJson('/consulta-os/buscar?'.http_build_query($q));
}

it('UC-COS-01 portal abre sem login e renderiza a tela ConsultaOs/Index', function () {
    $this->assertGuest();

    // Visita Inertia de verdade: o @inertiajs/core manda X-Inertia E X-Requested-With.
    // A versão vem do próprio HandleInertiaRequests::version() — senão o middleware devolve 409.
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $versao,
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/consulta-os');

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'ConsultaOs/Index');
    // Única prop: se a busca por celular está ligada (config do Repair). Nada de dado de OS.
    expect(array_keys($response->json('props') ?? []))->toContain('buscaPorCelular');
    expect($response->json('props.buscaPorCelular'))->toBeBool();
});

it('UC-COS-02 busca pelo nº da OS devolve o status daquela OS', function () {
    consultaOsSemear();

    $a = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B']);
    $a->assertOk()->assertJsonPath('found', true);
    expect($a->json('ordens'))->toHaveCount(1);
    $a->assertJsonPath('ordens.0.numero', 'OS-B');
    $a->assertJsonPath('ordens.0.status.nome', 'Pronto');
    $a->assertJsonPath('ordens.0.status.cor', '#22aa55');
    $a->assertJsonPath('ordens.0.marca', 'Marca X');
    $a->assertJsonPath('ordens.0.modelo', 'Modelo Y');
    $a->assertJsonPath('ordens.0.aparelho', 'Celular');
    $a->assertJsonPath('ordens.0.serie', 'SERIE-B');
    $a->assertJsonPath('ordens.0.previsao_entrega', '2026-10-10T14:00:00');

    // Caso discriminante: outra OS devolve outro status (um status fixo passaria com uma só).
    $b = consultaOsBuscar(['tipo' => 'invoice_no', 'numero' => 'VND-98-1']);
    $b->assertOk()->assertJsonPath('ordens.0.status.nome', 'Em reparo');
});

it('UC-COS-03 OS não encontrada devolve 404 só com found=false, sem pista nem texto técnico', function () {
    consultaOsSemear();

    $response = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'NAO-EXISTE-1']);

    $response->assertNotFound();
    $response->assertExactJson(['found' => false]);
});

it('UC-COS-04 [T0] payload público é a whitelist — nada interno, financeiro ou do cliente', function () {
    consultaOsSemear();

    $response = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B']);
    $response->assertOk();

    // Controle positivo: o valor secreto existe no banco (senão a ausência abaixo é vácuo).
    expect(DB::table('repair_job_sheets')->where('job_sheet_no', 'OS-B')->value('estimated_cost'))
        ->toBe('CUSTO-INTERNO-777');

    $os = $response->json('ordens.0');
    expect(array_keys($os))->toBe(CONSULTA_OS_CHAVES_OS);
    expect(array_keys($os['status']))->toBe(['nome', 'cor']);
    expect($os['atividades'])->not->toBeEmpty();
    foreach ($os['atividades'] as $atividade) {
        expect(array_keys($atividade))->toBe(CONSULTA_OS_CHAVES_ATIVIDADE);
    }

    $corpo = $response->getContent();
    foreach (CONSULTA_OS_SEGREDOS as $segredo) {
        expect($corpo)->not->toContain($segredo);
    }
    $this->assertArrayNotHasKey('business_id', $os);
    $this->assertArrayNotHasKey('estimated_cost', $os);
    $this->assertArrayNotHasKey('contact_id', $os);
});

it('UC-COS-05 sem critério válido não há busca nem lista', function () {
    consultaOsSemear();

    consultaOsBuscar([])->assertStatus(422);
    consultaOsBuscar(['tipo' => 'job_sheet_no'])->assertStatus(422);
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => ''])->assertStatus(422);
    consultaOsBuscar(['numero' => 'OS-B'])->assertStatus(422);
    consultaOsBuscar(['tipo' => 'qualquer', 'numero' => 'OS-B'])->assertStatus(422);
    // Só o nº de série, sem tipo nem número, não lista nada.
    consultaOsBuscar(['serie' => 'SERIE-B'])->assertStatus(422);

    // Defesa em profundidade: o repositório recusa sozinho, sem depender do FormRequest.
    $repo = app(RepairConsultaOsRepository::class);
    expect($repo->buscar('qualquer', 'OS-B'))->toBe([]);
    expect($repo->buscar('', 'OS-B'))->toBe([]);
    expect($repo->buscar('job_sheet_no', '   '))->toBe([]);
    // Controle: com critério válido o mesmo repositório acha.
    expect($repo->buscar('job_sheet_no', 'OS-B'))->toHaveCount(1);
});

it('UC-COS-06 as rotas públicas do portal são só leitura (GET)', function () {
    $publicas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'consulta-os.'));

    // Anti-vácuo: as duas rotas do charter existem.
    expect($publicas->map->getName()->sort()->values()->all())
        ->toBe(['consulta-os.buscar', 'consulta-os.index']);

    foreach ($publicas as $rota) {
        expect(array_values(array_diff($rota->methods(), ['GET', 'HEAD'])))->toBe([]);
    }
});

it('UC-COS-07 anti-enumeração: throttle nas rotas e número fora do formato é recusado', function () {
    consultaOsSemear();

    foreach (['consulta-os.index', 'consulta-os.buscar', 'repair-status', 'post-repair-status'] as $nome) {
        $middleware = Route::getRoutes()->getByName($nome)->gatherMiddleware();
        $temThrottle = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));
        expect($temThrottle)->toBeTrue();
    }

    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => "1' OR '1'='1"])->assertStatus(422);
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => str_repeat('9', 21)])->assertStatus(422);
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => '%'])->assertStatus(422);
    // Controle: o formato real do nº de OS do Repair (com barra) é aceito.
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'JS2026/0001'])->assertOk();
});

it('UC-COS-08 busca por nº da venda e por celular; o nº de série só estreita', function () {
    consultaOsSemear();
    config(['repair.enable_repair_check_using_mobile_num' => true]);

    consultaOsBuscar(['tipo' => 'invoice_no', 'numero' => 'VND-99-1'])
        ->assertOk()->assertJsonPath('ordens.0.numero', 'OS-B');
    consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990002'])
        ->assertOk()->assertJsonPath('ordens.0.numero', 'OS-B');

    $ambas = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'JS2026/0001']);
    expect($ambas->json('ordens'))->toHaveCount(2);

    $estreita = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'JS2026/0001', 'serie' => 'SERIE-C']);
    expect($estreita->json('ordens'))->toHaveCount(1);
    $estreita->assertJsonPath('ordens.0.serie', 'SERIE-C');

    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B', 'serie' => 'SERIE-ERRADA'])->assertNotFound();
});

it('UC-COS-09 [T0] busca cruza empresas: comportamento atual documentado (pendência [W])', function () {
    consultaOsSemear();

    // A OS "OS-B" é da empresa 99; buscar a "JS2026/0001" não a traz (o critério isola).
    $resposta = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'JS2026/0001']);
    $numeros = collect($resposta->json('ordens'))->pluck('numero')->unique()->values()->all();
    expect($numeros)->toBe(['JS2026/0001']);

    // O portal NÃO sabe a empresa do cliente: o mesmo nº em 98 e 99 volta das duas. É o
    // comportamento do /repair-status hoje. Fechar exige identificar a empresa na URL —
    // decisão do dono (US-CONSULTA-001, _saida-04). Quando decidir, ESTE assert inverte.
    $status = collect($resposta->json('ordens'))->pluck('status.nome')->sort()->values()->all();
    expect($status)->toBe(['Aguardando peça', 'Em reparo']);
});

it('UC-COS-10 busca por celular desligada na config do Repair é recusada', function () {
    consultaOsSemear();
    config(['repair.enable_repair_check_using_mobile_num' => false]);

    consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990002'])->assertStatus(422);
    expect(app(RepairConsultaOsRepository::class)->buscar('mobile_num', '48999990002'))->toBe([]);
    // Controle: os outros tipos seguem valendo.
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B'])->assertOk();
});

it('UC-COS-11 o portal antigo /repair-status leva ao /consulta-os', function () {
    $this->assertGuest();

    $this->get('/repair-status')->assertRedirect(route('consulta-os.index'));
    // A rota não mudou de nome nem ganhou irmã: o POST endurecido (#8527) segue no lugar.
    expect(Route::has('post-repair-status'))->toBeTrue();
});
