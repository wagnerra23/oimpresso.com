<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Quem passa pelas portas do módulo — auditoria do Manufacturing, lote 2.1.
 *
 * Contrato: decisão B3 de [W] em 2026-11-09 — Configurações e Relatório usam
 * `manufacturing.access_production`, sem permissão nova; e a pilha canônica de rotas do
 * projeto inclui `CheckUserLogin` (`.claude/rules/modules.md`), que barra quem não é
 * usuário interno (`user_type != 'user'`) ou não pode entrar (`allow_login != 1`).
 *
 *  - UC-MCFG-05  abrir e gravar Configurações exige `access_production`;
 *  - UC-MCFG-06  contato com login de portal não entra nas rotas do módulo;
 *  - UC-REPORT-06 o relatório exige `access_production`, inclusive no ramo AJAX e no `?legacy=1`;
 *  - UC-RECIPE-22 perguntar se o produto tem receita exige acesso a receita ou a produção.
 *
 * O usuário entra na lista de administradores, o que resolve o pacote `manufacturing_module`
 * (Gate `superadmin`) sem conceder as permissões do recurso: o `Gate::before` só libera
 * `superadmin`, `backup` e `manage_modules` por essa lista. Assim o 403 só pode vir da
 * permissão testada. Cada caso negativo tem a âncora: o mesmo pedido, com a permissão, passa.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita.
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'mfg_recipes'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

/** Usuário da 98 na lista de administradores, com exatamente as permissões pedidas. */
function mfgGateUsuario(string $username, array $permissoes = [], string $tipo = 'user'): User
{
    mfgEmpNegocio(MFG_EMP_BIZ);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => MFG_EMP_BIZ, 'first_name' => 'Gate', 'last_name' => 'Fabricacao',
        'user_type' => $tipo, 'allow_login' => 1,
    ]);
    foreach ($permissoes as $p) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    config(['constants.administrator_usernames' => $user->username]);

    return $user;
}

function mfgGateSessao(): array
{
    return ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
}

describe('UC-MCFG-05 — Configurações exigem acesso à produção', function () {
    it('UC-MCFG-05 sem access_production, abrir e gravar dão 403 e nada é gravado', function () {
        $user = mfgGateUsuario('mfg_gate_cfg_sem', ['manufacturing.access_recipe']);
        DB::table('business')->where('id', MFG_EMP_BIZ)->update(['manufacturing_settings' => json_encode(['ref_no_prefix' => 'ANTES'])]);

        $this->actingAs($user)->withSession(mfgGateSessao())->get('/manufacturing/settings')->assertForbidden();
        $this->actingAs($user)->withSession(mfgGateSessao())->get('/manufacturing/settings?legacy=1')->assertForbidden();
        $this->actingAs($user)->withSession(mfgGateSessao())
            ->post('/manufacturing/settings', ['ref_no_prefix' => 'DEPOIS'])->assertForbidden();

        $gravada = json_decode((string) DB::table('business')->where('id', MFG_EMP_BIZ)->value('manufacturing_settings'), true);
        expect($gravada['ref_no_prefix'] ?? null)->toBe('ANTES');
    });

    it('UC-MCFG-05 âncora: com access_production a tela abre e o POST grava', function () {
        $user = mfgGateUsuario('mfg_gate_cfg_com', ['manufacturing.access_production']);

        $this->actingAs($user)->withSession(mfgGateSessao())->get('/manufacturing/settings')->assertOk();
        $this->actingAs($user)->withSession(mfgGateSessao())
            ->post('/manufacturing/settings', ['ref_no_prefix' => 'GATE98'])->assertRedirect();

        $gravada = json_decode((string) DB::table('business')->where('id', MFG_EMP_BIZ)->value('manufacturing_settings'), true);
        expect($gravada['ref_no_prefix'] ?? null)->toBe('GATE98');
    });
});

describe('UC-MCFG-06 — contato com login de portal não entra no módulo', function () {
    it('UC-MCFG-06 user_customer recebe 403 mesmo com a permissão; o usuário interno passa', function () {
        $cliente = mfgGateUsuario('mfg_gate_portal', ['manufacturing.access_production'], 'user_customer');
        $this->actingAs($cliente)->withSession(mfgGateSessao())->get('/manufacturing/settings')->assertForbidden();
        $this->actingAs($cliente)->withSession(mfgGateSessao())
            ->post('/manufacturing/settings', ['ref_no_prefix' => 'PORTAL'])->assertForbidden();

        $interno = mfgGateUsuario('mfg_gate_interno', ['manufacturing.access_production']);
        $this->actingAs($interno)->withSession(mfgGateSessao())->get('/manufacturing/settings')->assertOk();
    });
});

describe('UC-REPORT-06 — o relatório exige acesso à produção em todos os ramos', function () {
    it('UC-REPORT-06 sem access_production: tela, AJAX e ?legacy=1 dão 403', function () {
        $user = mfgGateUsuario('mfg_gate_rel_sem', ['manufacturing.access_recipe']);

        $this->actingAs($user)->withSession(mfgGateSessao())->get('/manufacturing/report')->assertForbidden();
        $this->actingAs($user)->withSession(mfgGateSessao())
            ->get('/manufacturing/report?start_date=2026-01-01&end_date=2026-12-31', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertForbidden();
        $this->actingAs($user)->withSession(mfgGateSessao())->get('/manufacturing/report?legacy=1')->assertForbidden();
    });

    it('UC-REPORT-06 âncora: com access_production os três ramos respondem', function () {
        $user = mfgGateUsuario('mfg_gate_rel_com', ['manufacturing.access_production']);

        $this->actingAs($user)->withSession(mfgGateSessao())->get('/manufacturing/report')->assertOk();
        $this->actingAs($user)->withSession(mfgGateSessao())
            ->get('/manufacturing/report?start_date=2026-01-01&end_date=2026-12-31', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonStructure(['total_production', 'total_production_cost', 'total_sold']);
        $this->actingAs($user)->withSession(mfgGateSessao())->get('/manufacturing/report?legacy=1')->assertOk();
    });
});

describe('UC-RECIPE-22 — perguntar se o produto tem receita exige acesso ao módulo', function () {
    it('UC-RECIPE-22 sem access_recipe nem access_production a resposta é 403; com elas, a resposta certa', function () {
        $dono = mfgGateUsuario('mfg_gate_existe_dono');
        [, $acabado] = mfgEmpReceita(MFG_EMP_BIZ, $dono->id, 'Insumo da pergunta');

        $sem = mfgGateUsuario('mfg_gate_existe_sem', ['manufacturing.add_recipe']);
        $this->actingAs($sem)->withSession(mfgGateSessao())->get('/manufacturing/is-recipe-exist/'.$acabado)->assertForbidden();

        $receita = mfgGateUsuario('mfg_gate_existe_rec', ['manufacturing.access_recipe']);
        $this->actingAs($receita)->withSession(mfgGateSessao())->get('/manufacturing/is-recipe-exist/'.$acabado)
            ->assertOk()->assertSeeText('1');

        $producao = mfgGateUsuario('mfg_gate_existe_prod', ['manufacturing.access_production']);
        $this->actingAs($producao)->withSession(mfgGateSessao())->get('/manufacturing/is-recipe-exist/'.$acabado)
            ->assertOk()->assertSeeText('1');
    });
});
