<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\Business;
use App\Services\AppLojas\AjustesDaEmpresa;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

/**
 * Ajustes da empresa do app das lojas — bloco `ajustes` de GET /api/app/inicio e a aba "App das lojas"
 * de /business/settings.
 *
 * Contrato: handoff `propostas-app/AJUSTES-DA-EMPRESA.md` (tabela das chaves e padrões) e
 * memory/requisitos/AppMobile/API-CONTRATO-v1.md §6.2: `ajustes` vem SEMPRE com todas as chaves;
 * sem chave gravada, vale o padrão; a gravação é em `business.pos_settings`. NÃO derivado do código —
 * a tabela de padrões abaixo é a do handoff, escrita à mão para o teste pegar quem mudar o mapa.
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 da lane. Transação revertida.
 */

const APP_AJ_BIZ = 98;
const APP_AJ_OUTRO = 2;

/** Os padrões da tabela de AJUSTES-DA-EMPRESA.md. */
const APP_AJ_PADROES = [
    'app_entrega_exige_assinatura' => false,
    'app_entrega_exige_localizacao' => false,
    'app_acoes_fsm_liberadas' => [],
    'app_anexos_camera' => true,
    'app_apontamento_exige_qr' => false,
    'app_orcamento_rapido' => false,
    'app_atendimento_atalhos' => true,
    'app_comissao_vendedor_ve_propria' => true,
    'app_biometria_login' => true,
];

function appAjPos(int $bizId, ?array $pos): void
{
    DB::table('business')->where('id', $bizId)->update(['pos_settings' => $pos === null ? null : json_encode($pos)]);
}

function appAjPosLido(int $bizId): array
{
    return json_decode((string) DB::table('business')->where('id', $bizId)->value('pos_settings'), true) ?: [];
}

/** Corpo mínimo que o postBusinessSettings aceita (mesmo do EmpresaBaselineTest). */
function appAjCorpo(Business $b, array $campos = []): array
{
    return array_merge([
        'name' => $b->name, 'currency_id' => $b->currency_id,
        'time_zone' => $b->time_zone ?: 'America/Sao_Paulo', 'fy_start_month' => $b->fy_start_month ?: 1,
        'accounting_method' => $b->accounting_method ?: 'fifo', 'date_format' => $b->date_format ?: 'd/m/Y',
        'time_format' => $b->time_format ?: '24', 'on_product_expiry' => 'keep_selling', 'custom_labels' => [],
    ], $campos);
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS exige MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_AJ_BIZ, APP_AJ_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    // Essentials fora do plano, como no AppPerfilMenuApiContratoTest: as áreas do Início ficam fixas.
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(false);
    app()->instance(ModuleUtil::class, $mu);
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-APPAJ-01: sem chave gravada, `ajustes` traz as 9 chaves com o padrão da tabela', function () {
    appAjPos(APP_AJ_BIZ, ['allow_overselling' => 1]);
    Passport::actingAs(User::factory()->create(['business_id' => APP_AJ_BIZ]), [], 'api');

    $ajustes = $this->getJson('/api/app/inicio')->assertOk()->json('ajustes');

    expect($ajustes)->toBe(APP_AJ_PADROES);
    // pos_settings nulo (empresa antiga) dá o mesmo resultado.
    appAjPos(APP_AJ_BIZ, null);
    expect($this->getJson('/api/app/inicio')->assertOk()->json('ajustes'))->toBe(APP_AJ_PADROES);
});

it('UC-APPAJ-02: o gravado em pos_settings vale; valor inválido volta ao padrão e a lista é limpa', function () {
    appAjPos(APP_AJ_BIZ, [
        'app_entrega_exige_assinatura' => '1',
        'app_biometria_login' => '0',
        'app_anexos_camera' => 'talvez',
        'app_acoes_fsm_liberadas' => ['marcar_pronto', 'marcar_pronto', '', 'Com Espaço', 7],
    ]);
    Passport::actingAs(User::factory()->create(['business_id' => APP_AJ_BIZ]), [], 'api');

    $ajustes = $this->getJson('/api/app/inicio')->assertOk()->json('ajustes');

    expect($ajustes['app_entrega_exige_assinatura'])->toBeTrue();
    expect($ajustes['app_biometria_login'])->toBeFalse();
    expect($ajustes['app_anexos_camera'])->toBeTrue();
    expect($ajustes['app_acoes_fsm_liberadas'])->toBe(['marcar_pronto']);
    expect(array_keys($ajustes))->toBe(array_keys(APP_AJ_PADROES));
});

it('UC-APPAJ-03: cross-tenant — o ajuste da empresa 98 não chega ao usuário da empresa 2, e vice-versa', function () {
    appAjPos(APP_AJ_BIZ, ['app_biometria_login' => false, 'app_orcamento_rapido' => true]);
    appAjPos(APP_AJ_OUTRO, ['app_entrega_exige_localizacao' => true]);

    Passport::actingAs(User::factory()->create(['business_id' => APP_AJ_OUTRO]), [], 'api');
    $doOutro = $this->getJson('/api/app/inicio')->assertOk()->json('ajustes');
    expect($doOutro['app_biometria_login'])->toBeTrue();
    expect($doOutro['app_orcamento_rapido'])->toBeFalse();
    // Controle positivo: o gravado da própria empresa chega.
    expect($doOutro['app_entrega_exige_localizacao'])->toBeTrue();

    Passport::actingAs(User::factory()->create(['business_id' => APP_AJ_BIZ]), [], 'api');
    $doBiz = $this->getJson('/api/app/inicio')->assertOk()->json('ajustes');
    expect($doBiz['app_biometria_login'])->toBeFalse();
    expect($doBiz['app_entrega_exige_localizacao'])->toBeFalse();
});

it('UC-APPAJ-04: salvar as configurações desliga ajuste de padrão ligado e não apaga a chave que a aba não edita', function () {
    $biz = Business::findOrFail(APP_AJ_BIZ);
    appAjPos(APP_AJ_BIZ, ['app_acoes_fsm_liberadas' => ['marcar_pronto'], 'app_entrega_exige_assinatura' => true]);
    $user = $this->usuarioComPermissoes(['business_settings.access'], $biz);
    $this->actingAs($user);
    session(['user.business_id' => $biz->id, 'user.id' => $user->id, 'business.id' => $biz->id]);

    // O que a aba manda: hidden 0 + checkbox 1 para as chaves bool; a lista não está na aba.
    $this->post('/business/update', appAjCorpo($biz, ['pos_settings' => [
        'app_biometria_login' => '0',
        'app_entrega_exige_assinatura' => '0',
        'app_apontamento_exige_qr' => '1',
    ]]))->assertSessionHas('status.success', 1);

    $pos = appAjPosLido(APP_AJ_BIZ);
    expect($pos['app_biometria_login'])->toBeFalse();
    expect($pos['app_entrega_exige_assinatura'])->toBeFalse();
    expect($pos['app_apontamento_exige_qr'])->toBeTrue();
    expect($pos['app_acoes_fsm_liberadas'])->toBe(['marcar_pronto']);
    // Caminho 2: a leitura central devolve o mesmo que foi gravado.
    $lido = AjustesDaEmpresa::paraEmpresa(APP_AJ_BIZ);
    expect($lido['app_biometria_login'])->toBeFalse();
    expect($lido['app_acoes_fsm_liberadas'])->toBe(['marcar_pronto']);
    expect($lido['app_anexos_camera'])->toBeTrue();
});

it('UC-APPAJ-05: a aba "App das lojas" aparece nas configurações com as chaves editáveis marcadas pelo gravado', function () {
    $biz = Business::findOrFail(APP_AJ_BIZ);
    appAjPos(APP_AJ_BIZ, ['app_biometria_login' => false]);
    $user = $this->usuarioComPermissoes(['business_settings.access'], $biz);
    $this->actingAs($user);
    session(['user.business_id' => $biz->id, 'user.id' => $user->id, 'business.id' => $biz->id]);
    // Mesmo ajuste de sessão do EmpresaBaselineTest: o @num_format da Blade lê session('currency').
    session(['currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);

    $html = $this->get('/business/settings?classico=1')->assertOk()->getContent();

    expect($html)->toContain('App das lojas');
    foreach (AjustesDaEmpresa::CHAVES as $chave => $c) {
        $presente = str_contains($html, 'name="pos_settings['.$chave.']"');
        expect($presente)->toBe($c['na_tela']);
    }
    // Padrão ligado aparece marcado; o desligado pela empresa, não.
    expect($html)->toMatch('/id="pos_settings_app_anexos_camera"[^>]*checked|checked[^>]*id="pos_settings_app_anexos_camera"/');
    expect($html)->not->toMatch('/id="pos_settings_app_biometria_login"[^>]*checked|checked[^>]*id="pos_settings_app_biometria_login"/');
});
