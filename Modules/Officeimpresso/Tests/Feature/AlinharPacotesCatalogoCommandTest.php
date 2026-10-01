<?php

declare(strict_types=1);

use App\System;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;

uses(Tests\TestCase::class);

/**
 * `officeimpresso:alinhar-pacotes-catalogo` — pré-requisito do redirect do QR.
 *
 * Decisão [W] 2026-10-01 (D3, 2ª rodada, "pode ajustar primeiro"): quem tem
 * `officeimpresso_module` precisa ganhar `productcatalogue_module` ANTES de o QR do
 * Officeimpresso redirecionar pro ProductCatalogue, senão leva 403.
 *
 * O comando varre TODOS os pacotes. No CT 100 a base persiste entre runs e é clone de
 * prod (§5 2026-09-18: mutação que escreve vira lixo do próximo run), por isso cada
 * caso roda dentro de uma transação revertida — nada do que ele grava sobrevive ao
 * teste. Os asserts olham só as fixtures criadas aqui, nunca a contagem global.
 *
 * Tenant: `seededTenant()` (fictício 98, ADR 0358) — nunca biz=4.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS necessário (ADR 0101).');
    }

    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function oiCatalogoPacote(array $permissoes): Package
{
    return Package::create([
        'name'               => 'Pacote alinhar-catalogo test '.uniqid(),
        'description'        => 'fixture AlinharPacotesCatalogoCommandTest',
        'location_count'     => 1,
        'user_count'         => 5,
        'product_count'      => 100,
        'invoice_count'      => 1000,
        'interval'           => 'months',
        'interval_count'     => 1,
        'trial_days'         => 0,
        'price'              => 0,
        'is_active'          => 1,
        'sort_order'         => 999,
        'is_private'         => 1,
        'is_one_time'        => 0,
        'custom_permissions' => $permissoes,
    ]);
}

function oiCatalogoInscricao(int $businessId, int $packageId, array $detalhes, string $fim): Subscription
{
    return Subscription::create([
        'business_id'     => $businessId,
        'package_id'      => $packageId,
        'paid_via'        => 'offline',
        'start_date'      => now()->toDateString(),
        'end_date'        => $fim,
        'trial_end_date'  => null,
        'status'          => 'approved',
        'package_price'   => 0,
        'package_details' => $detalhes,
        'created_id'      => 1,
    ]);
}

it('pacotes 1 · --dry-run lista o pacote e a inscrição e NÃO grava', function () {
    $biz = $this->seededTenant();
    $pacote = oiCatalogoPacote(['officeimpresso_module' => 1]);
    $inscricao = oiCatalogoInscricao($biz->id, $pacote->id, ['name' => $pacote->name, 'officeimpresso_module' => 1], now()->addYear()->toDateString());

    $this->artisan('officeimpresso:alinhar-pacotes-catalogo', ['--dry-run' => true])
        ->expectsOutputToContain($pacote->name)
        ->expectsOutputToContain('--dry-run: NADA foi gravado.')
        ->assertSuccessful();

    expect($pacote->fresh()->custom_permissions)->not->toHaveKey('productcatalogue_module');
    expect($inscricao->fresh()->package_details)->not->toHaveKey('productcatalogue_module');
});

it('pacotes 2 · aplica a chave no pacote e na inscrição vigente, preservando as outras chaves', function () {
    $biz = $this->seededTenant();
    $pacote = oiCatalogoPacote(['officeimpresso_module' => 1, 'crm_module' => 1]);
    $inscricao = oiCatalogoInscricao($biz->id, $pacote->id, [
        'name' => $pacote->name,
        'officeimpresso_module' => 1,
        'chave_so_da_inscricao' => 1,
    ], now()->addYear()->toDateString());

    $this->artisan('officeimpresso:alinhar-pacotes-catalogo')->assertSuccessful();

    expect($pacote->fresh()->custom_permissions)->toBe([
        'officeimpresso_module' => 1, 'crm_module' => 1, 'productcatalogue_module' => 1,
    ]);
    expect($inscricao->fresh()->package_details)->toBe([
        'name' => $pacote->name, 'officeimpresso_module' => 1,
        'chave_so_da_inscricao' => 1, 'productcatalogue_module' => 1,
    ]);
});

it('pacotes 3 · depois de aplicar, o gate do ProductCatalogue libera o business', function () {
    $biz = $this->seededTenant();
    $pacote = oiCatalogoPacote(['officeimpresso_module' => 1]);
    oiCatalogoInscricao($biz->id, $pacote->id, ['name' => $pacote->name, 'officeimpresso_module' => 1], now()->addYears(50)->toDateString());

    // O gate consulta `auth()->user()->can('superadmin')` antes da inscrição: sem
    // usuário logado ele quebra, e com superadmin liberaria tudo (verde por vácuo).
    $this->actingAs(User::create([
        'business_id' => $biz->id,
        'first_name'  => 'OI',
        'surname'     => 'Pacotes',
        'username'    => 'oi_pacotes_'.uniqid(),
        'email'       => 'oi_pacotes_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]));

    // Sem `superadmin_version` na tabela `system`, `hasThePermissionInSubscription`
    // devolve true para QUALQUER chave (Superadmin "não instalado"). É o estado da
    // base fresca do CI — medido: a pré-condição abaixo caiu nele. Marca como
    // instalado dentro da transação (revertida no afterEach).
    if (empty(System::getProperty('superadmin_version'))) {
        System::addProperty('superadmin_version', '1.0');
    }

    // Pré-condição anti-vácuo: a inscrição desta fixture é a ativa e ainda nega.
    expect(Subscription::active_subscription($biz->id)->package_id)->toBe($pacote->id);
    expect((bool) app(ModuleUtil::class)->hasThePermissionInSubscription($biz->id, 'productcatalogue_module'))->toBeFalse();

    $this->artisan('officeimpresso:alinhar-pacotes-catalogo')->assertSuccessful();

    expect((bool) app(ModuleUtil::class)->hasThePermissionInSubscription($biz->id, 'productcatalogue_module'))->toBeTrue();
});

it('pacotes 4 · não toca pacote sem officeimpresso, inscrição vencida nem quem já tem o catálogo', function () {
    $biz = $this->seededTenant();
    $semOi = oiCatalogoPacote(['crm_module' => 1]);
    $jaTem = oiCatalogoPacote(['officeimpresso_module' => 1, 'productcatalogue_module' => 1]);
    $vencida = oiCatalogoInscricao($biz->id, $semOi->id, ['officeimpresso_module' => 1], now()->subDay()->toDateString());

    $this->artisan('officeimpresso:alinhar-pacotes-catalogo')->assertSuccessful();

    expect($semOi->fresh()->custom_permissions)->toBe(['crm_module' => 1]);
    expect($jaTem->fresh()->custom_permissions)->toBe(['officeimpresso_module' => 1, 'productcatalogue_module' => 1]);
    expect($vencida->fresh()->package_details)->toBe(['officeimpresso_module' => 1]);
});

it('pacotes 5 · idempotente: a segunda execução não encontra nada a fazer', function () {
    $biz = $this->seededTenant();
    $pacote = oiCatalogoPacote(['officeimpresso_module' => 1]);
    oiCatalogoInscricao($biz->id, $pacote->id, ['officeimpresso_module' => 1], now()->addYear()->toDateString());

    $this->artisan('officeimpresso:alinhar-pacotes-catalogo')->assertSuccessful();

    // A base pode ter outros pacotes pendentes (no CT 100 é clone de prod), então o
    // contrato é sobre ESTA fixture: ela não volta a ser listada e o valor não muda.
    $this->artisan('officeimpresso:alinhar-pacotes-catalogo', ['--dry-run' => true])
        ->doesntExpectOutputToContain($pacote->name)
        ->assertSuccessful();

    expect($pacote->fresh()->custom_permissions)->toBe(['officeimpresso_module' => 1, 'productcatalogue_module' => 1]);
});
