<?php

declare(strict_types=1);

use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Connector\Http\Controllers\Api\LicencaComputadorController;
use Modules\Officeimpresso\Entities\Licenca_Computador;

uses(Tests\TestCase::class);

/**
 * Officeimpresso thread 02 (L2) — o servidor para de GRAVAR `senha`/`contra_senha`
 * que o desktop Delphi manda em LICENCIAMENTO.
 *
 * Contrato (memory/reference/contrato-delphi-inviolavel.md): o desktop continua
 * mandando os campos e a requisição não pode falhar nem mudar de formato. O
 * `salvar-equipamento/{business_id}` devolve o model em JSON e esse JSON sempre
 * ecoou os dois campos — a ordem de chaves abaixo é a do código antes da thread
 * (ordem das atribuições em saveEquipamento + created_at/id do INSERT).
 *
 * Tier 0 (ADR 0093): tenant 98 (ADR 0358) × 1, nunca biz=4. Hermético na lane
 * sqlite :memory: (cria a tabela só se ausente e a derruba no afterEach).
 */
defined('SEG_BIZ_A') || define('SEG_BIZ_A', 98);
defined('SEG_BIZ_B') || define('SEG_BIZ_B', 1);

beforeEach(function () {
    $this->criadas = [];

    if (! Schema::hasTable('licenca_computador')) {
        Schema::create('licenca_computador', function ($t) {
            $t->increments('id');
            $t->integer('business_id')->nullable();
            foreach (['hd', 'user_win', 'tipodeacesso', 'conexao', 'usuario', 'senha', 'sistema_operacional',
                'ip_interno', 'antivirus', 'pasta_instalacao', 'versao_exe', 'versao_banco', 'backup_automatico',
                'paf', 'processador', 'memoria', 'velocidade_conexao', 'impressora_fiscal', 'leitor_barras',
                'gera_mensalidade', 'hostname', 'liberado', 'serial', 'contra_senha', 'oculto', 'motivo',
                'caminho_banco', 'descricao', 'sistema'] as $coluna) {
                $t->string($coluna)->nullable();
            }
            $t->boolean('bloqueado')->default(false);
            $t->double('valor')->nullable();
            foreach (['data', 'dt_ultima_assistencia', 'dt_validade', 'dt_ultimo_acesso', 'dt_cadastro'] as $coluna) {
                $t->timestamp($coluna)->nullable();
            }
            $t->timestamps();
        });
        $this->criadas[] = 'licenca_computador';
    }

    try {
        activity()->disableLogging();
    } catch (\Throwable) {
        // activitylog ausente: nada a desligar
    }

    Carbon::setTestNow('2026-10-01 10:00:00');
    $this->hd = 'HD-SEGREDO-' . uniqid();
    $this->ctl = app(LicencaComputadorController::class);
});

afterEach(function () {
    Carbon::setTestNow();
    DB::table('licenca_computador')->where('hd', $this->hd)->delete(); // SUPERADMIN: cleanup

    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse($this->criadas) as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
});

function segredoPayload(string $hd): array
{
    return [
        'HD' => $hd, 'DESCRICAO' => 'SEGREDO-USR', 'USUARIO' => 'adm',
        'SENHA' => 'S3nh4Desk', 'SERIAL' => 'SER-1', 'CONTRA_SENHA' => 'CTR-999',
        'VERSAO_EXE' => '1.0.1474', 'SISTEMA' => 'WR Comercial',
    ];
}

it('INSERT: segredo do payload não vai pro banco e a resposta mantém o formato', function () {
    $equip = $this->ctl->saveEquipamento(new Request(segredoPayload($this->hd)), SEG_BIZ_A);
    expect($equip)->toBeInstanceOf(Licenca_Computador::class);

    $linha = DB::table('licenca_computador')->where('hd', $this->hd)->first();
    expect($linha->senha)->toBeNull();
    expect($linha->contra_senha)->toBeNull();
    expect($linha->usuario)->toBe('adm');

    $json = Router::toResponse(Request::create('/'), $equip)->getContent();
    $corpo = json_decode($json, true);

    expect(array_keys($corpo))->toBe([
        'business_id', 'hd', 'user_win', 'liberado', 'motivo', 'bloqueado', 'conexao', 'usuario', 'senha',
        'sistema_operacional', 'ip_interno', 'antivirus', 'pasta_instalacao', 'versao_exe', 'versao_banco',
        'dt_ultima_assistencia', 'backup_automatico', 'paf', 'processador', 'memoria', 'velocidade_conexao',
        'impressora_fiscal', 'leitor_barras', 'gera_mensalidade', 'hostname', 'dt_validade', 'serial',
        'contra_senha', 'valor', 'caminho_banco', 'descricao', 'sistema', 'dt_cadastro', 'updated_at',
        'dt_ultimo_acesso', 'created_at', 'id',
    ]);
    expect($corpo['senha'])->toBe('S3nh4Desk');
    expect($corpo['contra_senha'])->toBe('CTR-999');
    expect($equip->isDirty())->toBeFalse();
});

it('UPDATE: equipamento já cadastrado mantém a coluna e a resposta ecoa o payload', function () {
    DB::table('licenca_computador')->insert([ // SUPERADMIN: fixture
        'business_id' => SEG_BIZ_A, 'hd' => $this->hd, 'user_win' => 'SEGREDO-USR',
        'senha' => 'ANTIGA', 'contra_senha' => 'CTR-ANTIGA', 'bloqueado' => 0,
    ]);

    $equip = $this->ctl->saveEquipamento(new Request(segredoPayload($this->hd)), SEG_BIZ_A);
    expect($equip)->toBeInstanceOf(Licenca_Computador::class);

    $linha = DB::table('licenca_computador')->where('hd', $this->hd)->first();
    expect($linha->senha)->toBe('ANTIGA');
    expect($linha->contra_senha)->toBe('CTR-ANTIGA');
    expect($linha->versao_exe)->toBe('1.0.1474');

    $corpo = json_decode(Router::toResponse(Request::create('/'), $equip)->getContent(), true);
    $chaves = array_keys($corpo);
    expect($chaves)->toContain('senha');
    expect($chaves)->toContain('contra_senha');
    expect($corpo['senha'])->toBe('S3nh4Desk');
    expect($corpo['contra_senha'])->toBe('CTR-999');
});

it('rota salvar-equipamento: aceita o payload com senha e não grava (usuário central)', function () {
    config(['connector.delphi_master_user_ids' => [987650001]]);
    $usuario = new User();
    $usuario->forceFill(['id' => 987650001, 'business_id' => SEG_BIZ_B]);

    if (! Schema::hasTable('business') || ! DB::table('business')->where('id', SEG_BIZ_A)->exists()) {
        $this->markTestSkipped('negócio 98 ausente nesta lane (o guard exige o negócio da URL)');
    }

    $request = Request::create('/connector/api/salvar-equipamento/' . SEG_BIZ_A, 'POST', segredoPayload($this->hd));
    $request->setUserResolver(fn () => $usuario);

    $equip = $this->ctl->saveEquipamentoRota($request, SEG_BIZ_A);

    expect($equip)->toBeInstanceOf(Licenca_Computador::class);
    expect(DB::table('licenca_computador')->where('hd', $this->hd)->value('senha'))->toBeNull();
});

it('cross-tenant: salvar no negócio 98 não toca o mesmo HD do negócio 1', function () {
    DB::table('licenca_computador')->insert([ // SUPERADMIN: fixture
        ['business_id' => SEG_BIZ_A, 'hd' => $this->hd, 'user_win' => 'SEGREDO-USR', 'senha' => 'A98', 'bloqueado' => 0, 'versao_exe' => 'velha'],
        ['business_id' => SEG_BIZ_B, 'hd' => $this->hd, 'user_win' => 'SEGREDO-USR', 'senha' => 'B1', 'bloqueado' => 0, 'versao_exe' => 'velha'],
    ]);

    $this->ctl->saveEquipamento(new Request(segredoPayload($this->hd)), SEG_BIZ_A);

    $a = DB::table('licenca_computador')->where('hd', $this->hd)->where('business_id', SEG_BIZ_A)->first();
    $b = DB::table('licenca_computador')->where('hd', $this->hd)->where('business_id', SEG_BIZ_B)->first();

    expect($a->senha)->toBe('A98');
    expect($a->versao_exe)->toBe('1.0.1474');
    expect($b->senha)->toBe('B1');
    expect($b->versao_exe)->toBe('velha');
});
