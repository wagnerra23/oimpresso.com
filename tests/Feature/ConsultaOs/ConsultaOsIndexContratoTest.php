<?php

declare(strict_types=1);

// @covers-us US-CONSULTA-001

use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;
use Modules\ConsultaOs\Repositories\RepairConsultaOsRepository;
use Modules\Repair\Entities\JobSheet;

/**
 * Contrato da tela pública /consulta-os — resources/js/Pages/ConsultaOs/Index.casos.md.
 *
 * Os UC derivam do charter (Index.charter.md: Goals, Non-Goals, Anti-hooks), nunca do .tsx.
 * Cada `it()` cita o UC que defende (casos-gate G-2).
 *
 * US-CONSULTA-001 (2026-10-02): a busca lê as folhas de OS reais do Repair. O schema mínimo
 * é criado em SQLite in-memory, no idioma que Modules/Repair/Tests/Feature/
 * PortalStatusReparoSemVazamentoTest.php usava até o cutover de 2026-10-27. Tenants fictícios 98 e 99 (ADR 0358) — nunca o 4.
 * Fora de SQLite o teste pula (cria tabelas; não pode rodar contra banco persistente).
 *
 * Decisão [W] 2026-11-17 — "empresa na URL, celular só com série": a busca é
 * /consulta-os/{codigo}/buscar e só olha a empresa do código. Os códigos fictícios das empresas 98 e
 * 99 ficam em consulta_os_empresas (EMPRESA_98 / EMPRESA_99), criada por consultaOsEmpresas().
 *
 * Asserts de ausência usam assertArrayNotHasKey / not->toContain com 1 argumento, nunca
 * `->not->toHaveKey($k, $msg)` (proibicoes §5 2026-09-22, LC-31).
 */

const CONSULTA_OS_TABELAS = [
    'activity_log', 'repair_job_sheets', 'transactions', 'contacts',
    'repair_statuses', 'brands', 'repair_device_models', 'categories', 'consulta_os_empresas', 'users',
];

/** Códigos públicos fictícios (formato EmpresaPublica::FORMATO) das empresas 98 e 99. */
const CONSULTA_OS_EMPRESA_98 = 'emprsa98bb';
const CONSULTA_OS_EMPRESA_99 = 'emprsa99bb';

/** Chaves que o payload público de cada OS pode ter — e nenhuma outra. */
const CONSULTA_OS_CHAVES_OS = [
    'numero', 'marca', 'aparelho', 'modelo', 'serie', 'status', 'previsao_entrega', 'atividades',
];

const CONSULTA_OS_CHAVES_ATIVIDADE = ['data', 'acao', 'por', 'nota', 'conclusao_de', 'conclusao_para'];

/** Valores gravados em colunas internas que nunca podem aparecer na resposta. */
const CONSULTA_OS_SEGREDOS = ['CUSTO-INTERNO-777', 'SENHA-APARELHO-1234', 'DEFEITO-INTERNO', 'Cliente Sigiloso', '11122233344'];

// A guarda mora no beforeEach (forma que o sqlite-test-corruptors reconhece): o arquivo cria
// tabelas e grava linhas, então fora de SQLite in-memory nenhum caso pode rodar.
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Cria schema mínimo do Repair — só em SQLite in-memory.');
    }
});

function consultaOsSemear(): void
{
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

/** Tabela-ponte com o código público das empresas 98 e 99 (idempotente). */
function consultaOsEmpresas(): void
{
    if (! Schema::hasTable('consulta_os_empresas')) {
        Schema::create('consulta_os_empresas', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id')->unique();
            $t->string('codigo', 16)->unique();
            $t->timestamps();
        });
    }
    foreach ([98 => CONSULTA_OS_EMPRESA_98, 99 => CONSULTA_OS_EMPRESA_99] as $biz => $codigo) {
        DB::table('consulta_os_empresas')->updateOrInsert(['business_id' => $biz], ['codigo' => $codigo]);
    }
}

/** Busca pública pelo link da empresa (98 ou 99). */
function consultaOsBuscar(array $q, int $biz): \Illuminate\Testing\TestResponse
{
    consultaOsEmpresas();
    $codigo = $biz === 99 ? CONSULTA_OS_EMPRESA_99 : CONSULTA_OS_EMPRESA_98;

    return test()->getJson("/consulta-os/{$codigo}/buscar?".http_build_query($q));
}

/** Linhas por tabela que o portal lê — para provar que nenhuma busca grava (UC-COS-06). */
function consultaOsContagens(): array
{
    $contagem = [];
    foreach (['repair_job_sheets', 'activity_log', 'transactions', 'contacts'] as $tabela) {
        $contagem[$tabela] = DB::table($tabela)->count();
    }

    return $contagem;
}

/**
 * Registros de log com a mensagem pedida, capturados pelo evento MessageLogged
 * (o controller escreve em Log::channel(...) e Log::error(...); os dois disparam o evento).
 *
 * @return \ArrayObject<int, MessageLogged>
 */
function consultaOsCapturarLogs(): \ArrayObject
{
    $logs = new \ArrayObject();
    Event::listen(MessageLogged::class, function (MessageLogged $e) use ($logs) {
        if (str_starts_with($e->message, 'consultaos.')) {
            $logs[] = $e;
        }
    });

    return $logs;
}

/** Visita Inertia de verdade: o @inertiajs/core manda X-Inertia E X-Requested-With. */
function consultaOsVisitar(string $url): \Illuminate\Testing\TestResponse
{
    // A versão vem do próprio HandleInertiaRequests::version() — senão o middleware devolve 409.
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    return test()->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $versao,
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get($url);
}

it('UC-COS-01 portal abre sem login e renderiza a tela ConsultaOs/Index', function () {
    $this->assertGuest();
    consultaOsEmpresas();

    $response = consultaOsVisitar('/consulta-os/'.CONSULTA_OS_EMPRESA_98);

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'ConsultaOs/Index');
    // Props: se a busca por celular está ligada (config do Repair) e o código da URL. Nada de dado de OS.
    expect(array_keys($response->json('props') ?? []))->toContain('buscaPorCelular');
    expect($response->json('props.buscaPorCelular'))->toBeBool();
    $response->assertJsonPath('props.empresa', CONSULTA_OS_EMPRESA_98);
});

it('UC-COS-02 busca pelo nº da OS devolve o status daquela OS', function () {
    consultaOsSemear();

    $a = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B'], 99);
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
    $b = consultaOsBuscar(['tipo' => 'invoice_no', 'numero' => 'VND-98-1'], 98);
    $b->assertOk()->assertJsonPath('ordens.0.status.nome', 'Em reparo');
});

it('UC-COS-03 OS não encontrada devolve 404 só com found=false, sem pista nem texto técnico', function () {
    consultaOsSemear();

    $response = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'NAO-EXISTE-1'], 98);

    $response->assertNotFound();
    $response->assertExactJson(['found' => false]);
});

it('UC-COS-04 [T0] payload público é a whitelist — nada interno, financeiro ou do cliente', function () {
    consultaOsSemear();

    $response = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B'], 99);
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
    // O código público da empresa também não volta no corpo (ele já está na URL).
    expect($corpo)->not->toContain(CONSULTA_OS_EMPRESA_99);
    $this->assertArrayNotHasKey('business_id', $os);
    $this->assertArrayNotHasKey('estimated_cost', $os);
    $this->assertArrayNotHasKey('contact_id', $os);
});

it('UC-COS-05 sem critério válido não há busca nem lista', function () {
    consultaOsSemear();

    consultaOsBuscar([], 99)->assertStatus(422);
    consultaOsBuscar(['tipo' => 'job_sheet_no'], 99)->assertStatus(422);
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => ''], 99)->assertStatus(422);
    consultaOsBuscar(['numero' => 'OS-B'], 99)->assertStatus(422);
    consultaOsBuscar(['tipo' => 'qualquer', 'numero' => 'OS-B'], 99)->assertStatus(422);
    // Só o nº de série, sem tipo nem número, não lista nada.
    consultaOsBuscar(['serie' => 'SERIE-B'], 99)->assertStatus(422);

    // Defesa em profundidade: o repositório recusa sozinho, sem depender do FormRequest.
    $repo = app(RepairConsultaOsRepository::class);
    expect($repo->buscar(99, 'qualquer', 'OS-B'))->toBe([]);
    expect($repo->buscar(99, '', 'OS-B'))->toBe([]);
    expect($repo->buscar(99, 'job_sheet_no', '   '))->toBe([]);
    // Sem empresa também não há busca.
    expect($repo->buscar(0, 'job_sheet_no', 'OS-B'))->toBe([]);
    // Controle: com critério válido o mesmo repositório acha.
    expect($repo->buscar(99, 'job_sheet_no', 'OS-B'))->toHaveCount(1);
});

it('UC-COS-06 as rotas públicas do portal são só leitura (GET) e nenhuma busca grava', function () {
    consultaOsSemear();
    config(['repair.enable_repair_check_using_mobile_num' => true]);

    $publicas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'consulta-os.'));

    // Anti-vácuo: as três rotas do charter existem (aviso sem empresa, tela da empresa, busca).
    expect($publicas->map->getName()->sort()->values()->all())
        ->toBe(['consulta-os.buscar', 'consulta-os.empresa', 'consulta-os.index']);

    foreach ($publicas as $rota) {
        expect(array_values(array_diff($rota->methods(), ['GET', 'HEAD'])))->toBe([]);
    }

    // Comportamento, não só o verbo (Anti-hook "NÃO grava dado de negócio nem altera a OS em GET"): uma busca que acha,
    // uma que não acha e uma recusada não mudam nenhuma linha das tabelas que o portal lê.
    $antes = consultaOsContagens();
    // Anti-vácuo: há o que gravar nessas tabelas (3 OS, 3 vendas, 6 atividades no seed).
    expect($antes['repair_job_sheets'])->toBe(3);
    expect($antes['activity_log'])->toBe(6);

    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B'], 99)->assertOk();
    consultaOsBuscar(['tipo' => 'invoice_no', 'numero' => 'VND-98-1'], 98)->assertOk();
    consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990003', 'serie' => 'SERIE-C'], 99)->assertOk();
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'NAO-EXISTE-1'], 98)->assertNotFound();
    consultaOsBuscar(['tipo' => 'qualquer', 'numero' => 'OS-B'], 99)->assertStatus(422);

    expect(consultaOsContagens())->toBe($antes);
});

it('UC-COS-07 anti-enumeração: throttle nas rotas e número fora do formato é recusado', function () {
    consultaOsSemear();

    foreach (['consulta-os.index', 'consulta-os.empresa', 'consulta-os.buscar', 'repair-status'] as $nome) {
        $middleware = Route::getRoutes()->getByName($nome)->gatherMiddleware();
        $temThrottle = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));
        expect($temThrottle)->toBeTrue();
    }

    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => "1' OR '1'='1"], 98)->assertStatus(422);
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => str_repeat('9', 21)], 98)->assertStatus(422);
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => '%'], 98)->assertStatus(422);
    // Controle: o formato real do nº de OS do Repair (com barra) é aceito.
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'JS2026/0001'], 98)->assertOk();
});

it('UC-COS-08 busca por nº da venda e por celular com série; o nº de série estreita', function () {
    consultaOsSemear();
    config(['repair.enable_repair_check_using_mobile_num' => true]);

    consultaOsBuscar(['tipo' => 'invoice_no', 'numero' => 'VND-99-1'], 99)
        ->assertOk()->assertJsonPath('ordens.0.numero', 'OS-B');
    consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990002', 'serie' => 'SERIE-B'], 99)
        ->assertOk()->assertJsonPath('ordens.0.numero', 'OS-B');

    // Segunda OS da empresa 98 com o mesmo nº (aparelho diferente): sem série vêm as duas.
    $agora = now();
    DB::table('repair_job_sheets')->insert([
        'business_id' => 98, 'contact_id' => 1, 'job_sheet_no' => 'JS2026/0001',
        'serial_no' => 'SERIE-A2', 'created_at' => $agora, 'updated_at' => $agora,
    ]);

    $ambas = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'JS2026/0001'], 98);
    expect($ambas->json('ordens'))->toHaveCount(2);

    $estreita = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'JS2026/0001', 'serie' => 'SERIE-A2'], 98);
    expect($estreita->json('ordens'))->toHaveCount(1);
    $estreita->assertJsonPath('ordens.0.serie', 'SERIE-A2');

    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B', 'serie' => 'SERIE-ERRADA'], 99)->assertNotFound();
});

it('UC-COS-09 [T0] mesmo nº de OS em duas empresas: cada link só vê a sua', function () {
    consultaOsSemear();

    // Anti-vácuo: o mesmo nº existe nas duas empresas.
    expect(DB::table('repair_job_sheets')->where('job_sheet_no', 'JS2026/0001')->pluck('business_id')->sort()->values()->all())
        ->toBe([98, 99]);

    $da98 = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'JS2026/0001'], 98);
    $da98->assertOk();
    expect(collect($da98->json('ordens'))->pluck('status.nome')->all())->toBe(['Em reparo']);
    expect($da98->getContent())->not->toContain('Aguardando peça');

    $da99 = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'JS2026/0001'], 99);
    $da99->assertOk();
    expect(collect($da99->json('ordens'))->pluck('status.nome')->all())->toBe(['Aguardando peça']);
    expect($da99->getContent())->not->toContain('Em reparo');

    // A OS-B só existe na 99: pelo link da 98 ela não aparece.
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B'], 98)->assertNotFound();
});

it('UC-COS-10 busca por celular desligada na config do Repair é recusada', function () {
    consultaOsSemear();
    config(['repair.enable_repair_check_using_mobile_num' => false]);

    consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990002', 'serie' => 'SERIE-B'], 99)->assertStatus(422);
    expect(app(RepairConsultaOsRepository::class)->buscar(99, 'mobile_num', '48999990002', 'SERIE-B'))->toBe([]);
    // Controle: os outros tipos seguem valendo.
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B'], 99)->assertOk();
});

it('UC-COS-11 o portal antigo /repair-status leva ao /consulta-os', function () {
    $this->assertGuest();

    $this->get('/repair-status')->assertRedirect(route('consulta-os.index'));
    // Cutover de 2026-10-27: o portal antigo saiu. O POST que ele usava (#8527) não existe mais.
    expect(Route::has('post-repair-status'))->toBeFalse();
    $this->post('/post-repair-status', ['search_type' => 'job_sheet_no', 'search_number' => 'OS-B'])
        ->assertNotFound();
});

it('UC-COS-59 [T0] nº da venda: cada link só acha a venda da própria empresa', function () {
    consultaOsSemear();
    $agora = now();
    $osDa98 = (int) DB::table('repair_job_sheets')->where('business_id', 98)->value('id');

    // O mesmo nº de venda nas duas empresas: a 98 também tem uma "VND-99-1", ligada à OS dela.
    DB::table('transactions')->insert([
        'business_id' => 98, 'repair_job_sheet_id' => $osDa98, 'invoice_no' => 'VND-99-1',
        'created_at' => $agora, 'updated_at' => $agora,
    ]);
    // Venda da 99 apontando para a OS da 98 (vínculo cruzado): não pode abrir a OS pelo link da 98.
    DB::table('transactions')->insert([
        'business_id' => 99, 'repair_job_sheet_id' => $osDa98, 'invoice_no' => 'VND-CRUZADA',
        'created_at' => $agora, 'updated_at' => $agora,
    ]);

    $da98 = consultaOsBuscar(['tipo' => 'invoice_no', 'numero' => 'VND-99-1'], 98);
    $da98->assertOk();
    expect(collect($da98->json('ordens'))->pluck('numero')->all())->toBe(['JS2026/0001']);
    $da98->assertJsonPath('ordens.0.status.nome', 'Em reparo');

    $da99 = consultaOsBuscar(['tipo' => 'invoice_no', 'numero' => 'VND-99-1'], 99);
    $da99->assertOk();
    expect(collect($da99->json('ordens'))->pluck('numero')->all())->toBe(['OS-B']);

    consultaOsBuscar(['tipo' => 'invoice_no', 'numero' => 'VND-CRUZADA'], 98)->assertNotFound();
    // A venda própria da 98 continua achando a OS dela (controle positivo).
    consultaOsBuscar(['tipo' => 'invoice_no', 'numero' => 'VND-98-1'], 98)->assertOk();
});

it('UC-COS-60 [T0] celular só com nº de série, e só dentro da empresa do link', function () {
    consultaOsSemear();
    config(['repair.enable_repair_check_using_mobile_num' => true]);

    // Sem série: 422, com a mensagem que a tela mostra.
    $sem = consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990002'], 99);
    $sem->assertStatus(422)->assertJsonValidationErrors('serie');
    expect(app(RepairConsultaOsRepository::class)->buscar(99, 'mobile_num', '48999990002'))->toBe([]);

    // Com série, pelo link da empresa certa: acha.
    consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990002', 'serie' => 'SERIE-B'], 99)
        ->assertOk()->assertJsonPath('ordens.0.numero', 'OS-B');
    // O mesmo celular e série pelo link da outra empresa: nada.
    consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990002', 'serie' => 'SERIE-B'], 98)->assertNotFound();

    // Contato da 99 ligado a uma OS da 98 (vínculo cruzado): o celular dele não abre a OS da 98.
    $osDa98 = DB::table('repair_job_sheets')->where('business_id', 98)->first();
    $contatoDa99 = (int) DB::table('contacts')->where('mobile', '48999990002')->value('id');
    DB::table('repair_job_sheets')->where('id', $osDa98->id)->update(['contact_id' => $contatoDa99]);
    consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990002', 'serie' => $osDa98->serial_no], 98)->assertNotFound();
});

it('UC-COS-61 [T0] a empresa vem da URL: sem código não há busca, código desconhecido dá 404', function () {
    consultaOsSemear();
    consultaOsEmpresas(); // a tabela-ponte existe antes da 1ª requisição (em produção vem da migration)

    // /consulta-os sem empresa: a tela abre, mas sem empresa para buscar.
    $aviso = consultaOsVisitar('/consulta-os');
    $aviso->assertOk()->assertJsonPath('component', 'ConsultaOs/Index');
    $aviso->assertJsonPath('props.empresa', null);

    // A busca antiga, sem empresa, não existe mais.
    $this->getJson('/consulta-os/buscar?'.http_build_query(['tipo' => 'job_sheet_no', 'numero' => 'OS-B']))
        ->assertNotFound();

    // Código no formato certo mas sem empresa: 404 na tela e na busca, só com found=false.
    $this->get('/consulta-os/semdna2345')->assertNotFound();
    $this->getJson('/consulta-os/semdna2345/buscar?'.http_build_query(['tipo' => 'job_sheet_no', 'numero' => 'OS-B']))
        ->assertNotFound()->assertExactJson(['found' => false]);

    // Controle positivo: o código da 99 acha a OS dela.
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B'], 99)->assertOk();
});

it('UC-COS-34 [T0] a resposta traz no máximo 20 OS, mesmo quando mais casam', function () {
    consultaOsSemear();

    // 25 OS da empresa 98 com o mesmo nº (ex.: celular compartilhado, nº reaproveitado).
    $agora = now();
    for ($i = 1; $i <= 25; $i++) {
        DB::table('repair_job_sheets')->insert([
            'business_id' => 98, 'contact_id' => 1, 'job_sheet_no' => 'LOTE-25',
            'serial_no' => "LOTE-SERIE-{$i}", 'created_at' => $agora, 'updated_at' => $agora,
        ]);
    }
    // Anti-vácuo: as 25 existem no banco.
    expect(DB::table('repair_job_sheets')->where('job_sheet_no', 'LOTE-25')->count())->toBe(25);

    $resposta = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'LOTE-25'], 98);
    $resposta->assertOk()->assertJsonPath('found', true);
    expect($resposta->json('ordens'))->toHaveCount(20);

    // O teto do charter é o mesmo número que o contrato do repositório declara.
    expect(ConsultaOsRepositoryInterface::LIMITE)->toBe(20);
    expect(app(RepairConsultaOsRepository::class)->buscar(98, 'job_sheet_no', 'LOTE-25'))->toHaveCount(20);
});

it('UC-COS-35 [T0] a 31ª busca do mesmo IP no mesmo minuto recebe 429', function () {
    // IP só deste caso: o contador do throttle é por IP e não pode herdar de outro teste.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.31']);

    // Busca sem critério (422) também conta: o limite vale antes da validação.
    for ($i = 1; $i <= 30; $i++) {
        consultaOsBuscar([], 98)->assertStatus(422);
    }
    consultaOsBuscar([], 98)->assertStatus(429);
    // A tela do portal divide o mesmo limite.
    $this->get('/consulta-os/'.CONSULTA_OS_EMPRESA_98)->assertStatus(429);

    // Controle: outro IP, no mesmo minuto, ainda é atendido — o limite é por visitante.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.32']);
    consultaOsBuscar([], 98)->assertStatus(422);
});

it('UC-COS-36 cada busca registra log com IP truncado e número redigido, sem dado da OS', function () {
    consultaOsSemear();
    config(['repair.enable_repair_check_using_mobile_num' => true]);
    $logs = consultaOsCapturarLogs();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.57'])
        ->withHeaders(['User-Agent' => str_repeat('Navegador-de-teste/1.0 ', 10)]);

    consultaOsBuscar(['tipo' => 'mobile_num', 'numero' => '48999990002', 'serie' => 'SERIE-B'], 99)->assertOk();
    consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'NAO-EXISTE-1'], 98)->assertNotFound();

    $buscas = collect($logs)->filter(fn (MessageLogged $e) => $e->message === 'consultaos.busca_publica')->values();
    // Uma linha por busca, com o resultado de cada uma (caso discriminante: found × not_found).
    expect($buscas)->toHaveCount(2);
    expect($buscas->map(fn ($e) => $e->context['resultado'])->all())->toBe(['found', 'not_found']);

    $achou = $buscas[0]->context;
    expect($achou['tipo'])->toBe('mobile_num');
    expect($achou['ip_truncado'])->toBe('203.0.113.0');
    expect(strlen($achou['user_agent']))->toBeLessThanOrEqual(80);
    expect($achou['numero_redacted'])->not->toBe('48999990002');
    $this->assertArrayNotHasKey('business_id', $achou);

    // Nada identificável nem da OS em lugar nenhum do registro.
    $serializado = json_encode($buscas->map->context->all());
    foreach (['48999990002', '203.0.113.57', 'OS-B', 'Pronto', 'Cliente Sigiloso', 'SERIE-B'] as $proibido) {
        expect($serializado)->not->toContain($proibido);
    }
});

it('UC-COS-37 falha interna na busca devolve 500 só com found=false, sem detalhe técnico', function () {
    $logs = consultaOsCapturarLogs();
    $this->app->instance(ConsultaOsRepositoryInterface::class, new class implements ConsultaOsRepositoryInterface {
        public function buscar(int $businessId, string $tipo, string $numero, ?string $serie = null): array
        {
            throw new \RuntimeException('SQLSTATE-DETALHE-TECNICO-42');
        }
    });

    $resposta = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B'], 99);

    $resposta->assertStatus(500);
    $resposta->assertExactJson(['found' => false]);
    expect($resposta->getContent())->not->toContain('SQLSTATE-DETALHE-TECNICO-42');

    $falha = collect($logs)->firstWhere('message', 'consultaos.busca_falhou');
    expect($falha)->not->toBeNull();
    // Só o tipo do erro vai para o log, nunca a mensagem (pode carregar SQL ou dado).
    expect($falha->context['erro'])->toBe('RuntimeException');
    expect(json_encode($falha->context))->not->toContain('SQLSTATE-DETALHE-TECNICO-42');
    $auditoria = collect($logs)->firstWhere('message', 'consultaos.busca_publica');
    expect($auditoria->context['resultado'])->toBe('erro');
});

it('UC-COS-62 quem mexeu na OS aparece como primeiro nome + inicial, nunca o nome completo', function () {
    consultaOsSemear();

    // Funcionário com tratamento, nome composto e sobrenome — o cadastro completo.
    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('surname')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });
    $funcionario = DB::table('users')->insertGetId([
        'surname' => 'Sra.', 'first_name' => 'Maria Aparecida', 'last_name' => 'Souzaplena',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $folha = DB::table('repair_job_sheets')->where('business_id', 99)->where('job_sheet_no', 'OS-B')->value('id');
    $alteradas = DB::table('activity_log')
        ->where('subject_id', $folha)->where('description', 'status_changed')
        ->update(['causer_type' => (new User)->getMorphClass(), 'causer_id' => $funcionario]);
    // Controle positivo: a troca de status da OS-B tem mesmo esse funcionário como autor.
    expect($alteradas)->toBe(1);

    $response = consultaOsBuscar(['tipo' => 'job_sheet_no', 'numero' => 'OS-B'], 99);
    $response->assertOk();

    $porQuem = collect($response->json('ordens.0.atividades'))->pluck('por')->filter()->values()->all();
    expect($porQuem)->toBe(['Maria S.']);

    $corpo = $response->getContent();
    foreach (['Souzaplena', 'Aparecida', 'Sra.'] as $parte) {
        expect($corpo)->not->toContain($parte);
    }

    // Sem sobrenome sai só o primeiro nome; sem nome nenhum, nada.
    expect(RepairConsultaOsRepository::nomePublico((new User)->forceFill(['first_name' => 'Joao'])))->toBe('Joao');
    expect(RepairConsultaOsRepository::nomePublico((new User)->forceFill(['first_name' => ''])))->toBeNull();
    expect(RepairConsultaOsRepository::nomePublico(null))->toBeNull();
});

/** Tabela-ponte vazia: nenhuma empresa tem código ainda (é o estado de produção antes do link nascer). */
function consultaOsEmpresasVazia(): void
{
    Schema::dropIfExists('consulta_os_empresas');
    Schema::create('consulta_os_empresas', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('business_id')->unique();
        $t->string('codigo', 16)->unique();
        $t->timestamps();
    });
}

it('UC-COS-63 o link da empresa nasce na 1ª vez que é pedido e depois é sempre o mesmo', function () {
    consultaOsEmpresasVazia();
    // Anti-vácuo: ninguém tem código antes.
    expect(DB::table('consulta_os_empresas')->count())->toBe(0);

    $primeiro = \Modules\ConsultaOs\Services\EmpresaPublica::linkDe(98);
    $segundo = \Modules\ConsultaOs\Services\EmpresaPublica::linkDe(98);

    expect($segundo)->toBe($primeiro);
    expect(DB::table('consulta_os_empresas')->where('business_id', 98)->count())->toBe(1);
    expect(DB::table('consulta_os_empresas')->count())->toBe(1);

    $codigo = (string) DB::table('consulta_os_empresas')->where('business_id', 98)->value('codigo');
    expect($codigo)->toMatch('/^'.\Modules\ConsultaOs\Services\EmpresaPublica::FORMATO.'$/');
    expect($primeiro)->toBe(route('consulta-os.empresa', ['empresa' => $codigo]));
    // O link resolve de volta para a mesma empresa.
    expect(\Modules\ConsultaOs\Services\EmpresaPublica::empresaDe($codigo))->toBe(98);
});

it('UC-COS-64 [T0] o link de uma empresa nunca é o de outra, nem abre a outra', function () {
    consultaOsEmpresasVazia();

    $link98 = \Modules\ConsultaOs\Services\EmpresaPublica::linkDe(98);
    $link99 = \Modules\ConsultaOs\Services\EmpresaPublica::linkDe(99);
    expect($link98)->not->toBe($link99);

    $codigo98 = (string) DB::table('consulta_os_empresas')->where('business_id', 98)->value('codigo');
    $codigo99 = (string) DB::table('consulta_os_empresas')->where('business_id', 99)->value('codigo');
    expect($link98)->not->toContain($codigo99);
    expect($link99)->not->toContain($codigo98);
    expect(\Modules\ConsultaOs\Services\EmpresaPublica::empresaDe($codigo98))->toBe(98);
    expect(\Modules\ConsultaOs\Services\EmpresaPublica::empresaDe($codigo99))->toBe(99);

    // Pedir de novo o link da 98 depois de criar o da 99 não troca nada.
    expect(\Modules\ConsultaOs\Services\EmpresaPublica::linkDe(98))->toBe($link98);
    expect(DB::table('consulta_os_empresas')->count())->toBe(2);
});
