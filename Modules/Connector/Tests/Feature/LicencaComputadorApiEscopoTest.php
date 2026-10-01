<?php

declare(strict_types=1);

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Connector\Http\Controllers\Api\LicencaComputadorController;

uses(Tests\TestCase::class);

/**
 * Officeimpresso thread 01 (L1 + L7) — API de licença com escopo de negócio.
 *
 * Contrato: o token do negócio A não lê nem altera equipamento do negócio B
 * (index/show/update/destroy de Api\LicencaComputadorController), e o desktop
 * Delphi segue recebendo `S;…` em ProcessaDadosCliente (formato intocado).
 *
 * Tier 0 (ADR 0093). Tenant canônico 98 (ADR 0358), nunca biz=4. O "outro
 * negócio" é o 1, que o TestCase recompõe no MySQL.
 *
 * Hermético na lane sqlite :memory: (modules-pest, sem migrate): cria as
 * tabelas mínimas SÓ se ausentes e as derruba no afterEach — DDL só no sqlite
 * (dual-mode), para não corromper o MySQL persistente do nightly nem fazer
 * outro teste do mesmo processo deixar de pular por `Schema::hasTable`.
 */
defined('LIC_BIZ_A') || define('LIC_BIZ_A', 98);
defined('LIC_BIZ_B') || define('LIC_BIZ_B', 1);

beforeEach(function () {
    $this->criadas = [];

    if (! Schema::hasTable('licenca_computador')) {
        Schema::create('licenca_computador', function ($t) {
            $t->increments('id');
            $t->integer('business_id')->nullable();
            $t->string('hd')->nullable();
            $t->string('user_win')->nullable();
            $t->boolean('bloqueado')->default(false);
            $t->string('motivo')->nullable();
            $t->string('hostname')->nullable();
            $t->string('ip_interno')->nullable();
            $t->string('sistema')->nullable();
            $t->string('versao_exe')->nullable();
            $t->dateTime('dt_ultimo_acesso')->nullable();
            $t->timestamps();
        });
        $this->criadas[] = 'licenca_computador';
    }
    if (! Schema::hasTable('business')) {
        Schema::create('business', function ($t) {
            $t->increments('id');
            $t->boolean('officeimpresso_bloqueado')->default(false);
        });
        $this->criadas[] = 'business';
    }

    try {
        activity()->disableLogging();
    } catch (\Throwable) {
        // activitylog ausente: nada a desligar
    }

    $sufixo = uniqid();
    $this->hdA = 'HD-ESCOPO-A-' . $sufixo;
    $this->idA = DB::table('licenca_computador')->insertGetId([ // SUPERADMIN: fixture do teste
        'business_id' => LIC_BIZ_A, 'hd' => $this->hdA, 'user_win' => 'ESCOPO-A', 'bloqueado' => 0,
    ]);
    $this->idB = DB::table('licenca_computador')->insertGetId([ // SUPERADMIN: fixture do teste
        'business_id' => LIC_BIZ_B, 'hd' => 'HD-ESCOPO-B-' . $sufixo, 'user_win' => 'ESCOPO-B', 'bloqueado' => 0,
    ]);

    $usuario = new User();
    $usuario->id = 987654321;
    $usuario->business_id = LIC_BIZ_A;
    $this->actingAs($usuario);

    $this->ctl = app(LicencaComputadorController::class);
});

afterEach(function () {
    DB::table('licenca_computador')->whereIn('id', [$this->idA, $this->idB])->delete(); // SUPERADMIN: cleanup

    // Dual-mode: só derruba no sqlite :memory: (onde o próprio teste criou).
    // No MySQL persistente as tabelas são reais e nunca são tocadas.
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse($this->criadas) as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
});

it('index devolve só os equipamentos do negócio do token', function () {
    $ids = collect($this->ctl->index()->getData(true))->pluck('id')->all();

    expect($ids)->toContain($this->idA);
    expect(in_array($this->idB, $ids, true))->toBeFalse();
});

it('show de equipamento de outro negócio responde 404 (o próprio responde 200)', function () {
    expect($this->ctl->show($this->idA)->getStatusCode())->toBe(200);
    expect($this->ctl->show($this->idB)->getStatusCode())->toBe(404);
});

it('update de equipamento de outro negócio responde 404 e não altera nada', function () {
    $resposta = $this->ctl->update(new Request(['processador' => 'INVASOR']), $this->idB);

    expect($resposta->getStatusCode())->toBe(404);
    expect(DB::table('licenca_computador')->where('id', $this->idB)->value('business_id'))->toEqual(LIC_BIZ_B);
});

it('destroy de equipamento de outro negócio responde 404 e o registro continua', function () {
    expect($this->ctl->destroy($this->idB)->getStatusCode())->toBe(404);
    expect(DB::table('licenca_computador')->where('id', $this->idB)->exists())->toBeTrue();
});

it('token sem negócio não vê nenhum equipamento (escopo vazio, nunca whereNull)', function () {
    // A linha órfã (business_id NULL) só existe onde a coluna aceita NULL — a
    // tabela sqlite do teste. No MySQL ela é NOT NULL + FK, e o caso fica sem órfã.
    $orfa = in_array('licenca_computador', $this->criadas, true)
        ? DB::table('licenca_computador')->insertGetId(['business_id' => null, 'hd' => 'HD-ORFA-' . uniqid()]) // SUPERADMIN: fixture
        : null;

    try {
        $semNegocio = new User();
        $semNegocio->id = 987654322;
        $this->actingAs($semNegocio);

        expect($this->ctl->index()->getData(true))->toBe([]);
    } finally {
        DB::table('licenca_computador')->where('id', $orfa)->delete(); // SUPERADMIN: cleanup
    }
});

it('desktop com HD já cadastrado continua recebendo S; em ProcessaDadosCliente', function () {
    if (! DB::table('business')->where('id', LIC_BIZ_A)->exists()) {
        DB::table('business')->insert(['id' => LIC_BIZ_A, 'officeimpresso_bloqueado' => 0]); // SUPERADMIN: fixture sqlite
    }

    $request = Request::create('/connector/api/processa-dados-cliente', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['host' => 'HOST-ESCOPO', 'serial_hd' => $this->hdA, 'versao' => '1.0']));

    $resposta = $this->ctl->ProcessaDadosCliente($request);

    expect($resposta->getStatusCode())->toBe(200);
    expect($resposta->getContent())->toBe('S;Cliente e equipamento liberados');
});
