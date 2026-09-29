<?php

declare(strict_types=1);

use App\Facades\Menu;
use App\Services\LegacyMenuAdapter;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(PontoTestCase::class);

/**
 * Contrato da tela REP-P no celular (`/ponto/mobile` · Ponto/Mobile/Index) — thread 06.
 *
 * UCs de `resources/js/Pages/Ponto/Mobile/Index.casos.md`, derivados da tabela EARS §C da
 * thread + ADR 0383 (sem biometria) + W5 (GPS fraco é recusa). NÃO do `.tsx`.
 * A API por trás (`/ponto/api`, Passport) tem os seus casos no Wave28MobileMarcacaoTest;
 * aqui é a MESMA lógica servida sob sessão web, que é o caminho da tela.
 *
 * @covers-us US-PONTO-001
 *
 * Tier 0: tenant fictício 98 (ADR 0358). Transação revertida por caso — `ponto_marcacoes`
 * recusa DELETE por trigger (Portaria MTP 671/2021).
 */

const RPM_BIZ = 98;

function rpmUsuario(bool $comColaborador = true, bool $comAcessoModulo = true): User
{
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'RPM teste', 'username' => 'rpm_' . uniqid(), 'password' => 'x',
        'business_id' => RPM_BIZ, 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($comColaborador) {
        DB::table('ponto_colaborador_config')->insert([
            'business_id' => RPM_BIZ, 'user_id' => $userId, 'matricula' => 'RPM-' . uniqid(),
            'controla_ponto' => true, 'admissao' => '2020-01-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $u = User::findOrFail($userId);
    if ($comAcessoModulo) {
        Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
        $u->givePermissionTo('ponto.access');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => RPM_BIZ, 'business.id' => RPM_BIZ]);

    return $u;
}

function rpmPayload(array $extra = []): array
{
    return array_merge([
        'tipo' => Marcacao::TIPO_ENTRADA, 'lat' => -28.336, 'lng' => -48.926, 'accuracy' => 15,
        'device_uuid' => 'rpm-device', 'timestamp_device' => now()->toIso8601String(),
    ], $extra);
}

function rpmMarcacoesDo(User $u): int
{
    $colab = DB::table('ponto_colaborador_config')->where('user_id', $u->id)->value('id');

    return DB::table('ponto_marcacoes')->where('colaborador_config_id', $colab)->count();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + FK + triggers exigem MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('ponto_marcacoes') || ! DB::table('business')->where('id', RPM_BIZ)->exists()) {
        $this->markTestSkipped('Schema do Ponto ou tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
    config()->set('pontowr2.geofence.business_' . RPM_BIZ, null);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-REPP-00: a aba "REP-P (celular)" é a 10ª do header, aponta pra /ponto/mobile, e a tela abre', function () {
    $fonte = file_get_contents(base_path('Modules/Ponto/Http/Controllers/DataController.php'));
    $iColab = strpos($fonte, "'key' => 'colaboradores'");
    $iMobile = strpos($fonte, "'key' => 'mobile'");
    $iImp = strpos($fonte, "'key' => 'importacoes'", (int) $iColab);
    expect($iMobile)->not->toBeFalse();
    expect($iColab < $iMobile && $iMobile < $iImp)->toBeTrue();
    expect($fonte)->toContain("'href' => '/ponto/mobile'");

    $this->actingAs(rpmUsuario());
    $r = $this->inertiaGet('/ponto/mobile');
    $this->assertInertiaComponent($r, 'Ponto/Mobile/Index');
    $r->assertJsonPath('props.pode_ver_modulo', true);
});

it('UC-REPP-01: bater ponto grava REP_P no meu cadastro com NSR do servidor, e aparece em Hoje ao recarregar', function () {
    $u = rpmUsuario();
    $this->actingAs($u);

    $r = $this->postJson('/ponto/mobile/marcar', rpmPayload())->assertStatus(201);
    expect((int) $r->json('marcacao.nsr'))->toBeGreaterThan(0);

    $m = DB::table('ponto_marcacoes')->where('id', $r->json('marcacao.id'))->first();
    expect((int) $m->business_id)->toBe(RPM_BIZ);
    expect($m->dispositivo_id)->toBe('mobile:rpm-device');
    expect(rpmMarcacoesDo($u))->toBe(1);

    $this->inertiaGet('/ponto/mobile')->assertJsonCount(1, 'props.marcacoes_hoje')
        ->assertJsonPath('props.marcacoes_hoje.0.tipo', Marcacao::TIPO_ENTRADA)
        ->assertJsonPath('props.marcacoes_hoje.0.nsr', (int) $m->nsr);
});

it('UC-REPP-02: GPS acima de 500 m → 422 com a mensagem do serviço e nada gravado', function () {
    $u = rpmUsuario();
    $this->actingAs($u);

    $this->postJson('/ponto/mobile/marcar', rpmPayload(['accuracy' => 900]))
        ->assertStatus(422)->assertJsonPath('erro', 'validacao_falhou');
    expect(rpmMarcacoesDo($u))->toBe(0);
});

it('UC-REPP-03: fora do geofence grava e volta revisar=true', function () {
    config()->set('pontowr2.geofence.business_' . RPM_BIZ, ['lat' => -27.595, 'lng' => -48.548, 'raio_metros' => 500.0]);
    $u = rpmUsuario();
    $this->actingAs($u);

    $this->postJson('/ponto/mobile/marcar', rpmPayload())->assertStatus(201)->assertJsonPath('marcacao.revisar', true);
    expect(rpmMarcacoesDo($u))->toBe(1);
});

it('UC-REPP-04: sem cadastro de ponto a tela vem com colaborador nulo e marcar → 403', function () {
    $this->actingAs(rpmUsuario(false));

    $this->inertiaGet('/ponto/mobile')->assertJsonPath('props.colaborador', null)->assertJsonCount(0, 'props.marcacoes_hoje');
    $this->postJson('/ponto/mobile/marcar', rpmPayload())->assertStatus(403)->assertJsonPath('erro', 'sem_colaborador');
});

it('UC-REPP-05: GUARD ADR 0383 — o fonte da tela não usa câmera, não captura imagem e não oferece "mesmo assim"', function () {
    $fonte = file_get_contents(base_path('resources/js/Pages/Ponto/Mobile/Index.tsx'));
    expect($fonte)->not->toContain('getUserMedia');
    expect($fonte)->not->toContain('capture=');
    expect(strtolower($fonte))->not->toContain('selfie');
    expect(strtolower($fonte))->not->toContain('mesmo assim');
    // Controle positivo: a leitura achou a tela certa (senão os `not` passam vazios).
    expect($fonte)->toContain('/ponto/mobile/marcar');
});

it('UC-REPP-08: colaborador SEM ponto.access abre a tela e bate o ponto, sem o cabeçalho do módulo ([W] 2026-09-29)', function () {
    $u = rpmUsuario(true, false);
    $this->actingAs($u);
    expect($u->can('ponto.access'))->toBeFalse();

    $r = $this->inertiaGet('/ponto/mobile');
    $this->assertInertiaComponent($r, 'Ponto/Mobile/Index');
    $r->assertJsonPath('props.pode_ver_modulo', false);
    expect($r->json('props.colaborador'))->not->toBeNull();

    $this->postJson('/ponto/mobile/marcar', rpmPayload())->assertStatus(201);
    expect(rpmMarcacoesDo($u))->toBe(1);

    // O resto do módulo segue fechado pra ele.
    $this->get('/ponto/espelho')->assertForbidden();
});

/** Itens do menu que chegam ao browser — pelo consumidor real (LegacyMenuAdapter), como o MenuGhostsContratoTest. */
function rpmRotulosDoMenu(User $u): array
{
    Menu::make('admin-sidebar-menu', function ($m) {});
    test()->actingAs($u);
    session(['user.business_id' => RPM_BIZ, 'business.id' => RPM_BIZ]);
    (new Modules\Ponto\Http\Controllers\DataController())->modifyAdminMenu();

    return collect((new LegacyMenuAdapter())->build())->pluck('label')->map(fn ($l) => (string) $l)->all();
}

it('UC-REPP-09: o menu mostra "Bater ponto" (→ /ponto/mobile) a quem tem cadastro de ponto — e não a quem não tem ([W] 2026-09-29)', function () {
    // superadmin passa o gate de assinatura do módulo (ModuleUtil) sem depender do pacote do
    // tenant 98 — o mesmo caminho do MenuGhostsContratoTest. O que se mede é a condição nova.
    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $colab = rpmUsuario(true, false);
    $colab->givePermissionTo('superadmin');
    $semCadastro = rpmUsuario(false, false);
    $semCadastro->givePermissionTo('superadmin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $rotulos = rpmRotulosDoMenu($colab->fresh());
    expect($rotulos)->toContain('Bater ponto');
    $item = collect((new LegacyMenuAdapter())->build())->firstWhere('label', 'Bater ponto');
    expect((string) ($item['href'] ?? $item['url'] ?? ''))->toContain('/ponto/mobile');

    expect(rpmRotulosDoMenu($semCadastro->fresh()))->not->toContain('Bater ponto');
});
