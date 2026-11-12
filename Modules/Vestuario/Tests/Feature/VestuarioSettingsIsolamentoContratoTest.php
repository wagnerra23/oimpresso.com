<?php

declare(strict_types=1);

// @covers-us US-VEST-020

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Vestuario\Entities\VestuarioSetting;
use Modules\Vestuario\Services\GradeCurvaService;
use Modules\Vestuario\Services\VestuarioSettingsResolver;
use Tests\Support\WithSeededTenant;

/**
 * Isolamento de `vestuario_settings` entre empresas, no MySQL (UC-VET-01, UC-VET-07).
 *
 *   UC-VET-01 ← CU-VEST-01 item 3  (a config resolvida é a da empresa da sessão; sem linha = defaults)
 *   UC-VET-07 ← CU-VEST-05 item 3  (qr_enabled de uma empresa não liga o QR da outra)
 *
 * Os casos que citavam esses UCs (UsVest020, VestuarioSetting*, GradeAvancada) pulam em sqlite e
 * nunca entraram na lane MySQL. Este arquivo roda lá.
 *
 * Por que o beforeEach apaga a tabela: o resolver usa `withoutGlobalScopes` e re-aplica o
 * `where('business_id')` à mão. Se alguém tirar esse where, `first()` devolve a linha de MENOR id.
 * Com a tabela limpa, a única linha é a da 98, e a 99 passa a ler a config da 98: o teste cai. Sem
 * a limpeza, uma linha antiga de outra empresa podia ser a de menor id e esconder a regressão.
 * O delete roda dentro da transação do DatabaseTransactions e volta no fim de cada caso.
 *
 * Tenant 98 (dono) × 99 (adversário), ADR 0358. NUNCA biz=4.
 *
 * @see memory/requisitos/Vestuario/SDD-tela-etiqueta-tag-v1.0.md §6 CU-VEST-01, CU-VEST-05
 * @see resources/js/Pages/Vestuario/Etiquetas/Index.casos.md
 * @see Modules/Vestuario/Services/VestuarioSettingsResolver.php
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('Requer MySQL com o schema do UltimatePOS.');
    }

    $this->dono = $this->seededTenant();
    $this->outra = $this->seededSupportClientTenant();

    DB::table('vestuario_settings')->delete();
    Cache::flush();

    $util = Mockery::mock(ModuleUtil::class)->makePartial();
    $util->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $util);

    $permissoes = ['vestuario.etiqueta.view', 'vestuario.etiqueta.create'];
    $this->userDono = $this->usuarioComPermissoes($permissoes, $this->dono);
    $this->userOutra = $this->usuarioComPermissoes($permissoes, $this->outra);

    $this->como = function (\App\User $u) {
        test()->flushHeaders();

        return test()->actingAs($u)->withSession(['user.business_id' => $u->business_id, 'user' => ['business_id' => $u->business_id, 'id' => $u->id]]);
    };

    // A empresa 98 configura uma etiqueta fora do padrão e liga o QR.
    $this->configDono = ['width_dots' => 600, 'height_dots' => 300, 'dpi' => 300, 'margin_dots' => 20, 'qr_enabled' => true];
    $resolver = app(VestuarioSettingsResolver::class)->forBusiness($this->dono->id);
    foreach ($this->configDono as $chave => $valor) {
        $resolver->set("etiqueta.{$chave}", $valor);
    }
});

const UC_VET_ISO_DEFAULTS = ['width_dots' => 400, 'height_dots' => 240, 'dpi' => 203, 'margin_dots' => 10, 'qr_enabled' => false];

function ucVetIsoLote(): array
{
    return ['items' => [['product_id' => 1, 'nome' => 'Peça', 'sku' => 'SKU-ISO-1']]];
}

it('UC-VET-01 — a tela da empresa 98 abre com a config que ela gravou (controle)', function () {
    ($this->como)($this->userDono)->get('/vestuario/etiquetas')->assertOk()
        ->assertInertia(function (Assert $p) {
            $p->component('Vestuario/Etiquetas/Index', false);
            foreach ($this->configDono as $chave => $valor) {
                $p->where("config.{$chave}", $valor);
            }
        });
});

it('UC-VET-01 — a empresa 99, sem linha própria, recebe os defaults e nada da 98 [T0]', function () {
    expect(DB::table('vestuario_settings')->where('business_id', $this->outra->id)->exists())->toBeFalse();

    ($this->como)($this->userOutra)->get('/vestuario/etiquetas')->assertOk()
        ->assertInertia(function (Assert $p) {
            foreach (UC_VET_ISO_DEFAULTS as $chave => $valor) {
                $p->where("config.{$chave}", $valor);
            }
        });
});

it('UC-VET-01 — o model só devolve a linha da empresa da sessão, e gravar na 99 não toca a 98 [T0]', function () {
    session(['user' => ['business_id' => $this->outra->id]]);

    // Anti-vácuo: a linha da 98 existe; sem o escopo ela aparece.
    expect(VestuarioSetting::withoutGlobalScopes()->where('business_id', $this->dono->id)->exists())->toBeTrue();
    expect(VestuarioSetting::query()->pluck('business_id')->all())->toBe([]);

    app(VestuarioSettingsResolver::class)->set('etiqueta.width_dots', 800);

    expect(VestuarioSetting::query()->pluck('business_id')->all())->toBe([$this->outra->id]);
    $dono = VestuarioSetting::withoutGlobalScopes()->where('business_id', $this->dono->id)->first();
    expect($dono->get('etiqueta.width_dots'))->toBe(600);
});

it('UC-VET-07 — o QR ligado na 98 sai no ZPL dela e não no da 99 [T0]', function () {
    $zplDono = ($this->como)($this->userDono)->post('/vestuario/etiquetas/lote/zpl', ucVetIsoLote())->assertOk()->getContent();
    $zplOutra = ($this->como)($this->userOutra)->post('/vestuario/etiquetas/lote/zpl', ucVetIsoLote())->assertOk()->getContent();

    expect($zplDono)->toContain('^BQ');
    expect($zplOutra)->toContain('^XA');
    expect($zplOutra)->not->toContain('^BQ');
});

it('UC-VET-01 — a curva de grade personalizada da 98 não aparece para a 99 [T0]', function () {
    app(VestuarioSettingsResolver::class)->forBusiness($this->dono->id)
        ->set('grades.curvas', ['so_da_98' => ['nome' => 'Só da 98', 'tamanhos' => ['P', 'M'], 'proporcao' => [1, 1]]]);

    $grade = app(GradeCurvaService::class);

    expect($grade->listarCurvas($this->dono->id))->toHaveKey('so_da_98');
    expect($grade->listarCurvas($this->outra->id))->not->toHaveKey('so_da_98');
    expect(fn () => $grade->calcularProporcao('so_da_98', 10, $this->outra->id))->toThrow(InvalidArgumentException::class);
});
