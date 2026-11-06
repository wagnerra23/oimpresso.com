<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Facades\Menu;
use Modules\Fiscal\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * Menu × permissão da rota (auditoria Fiscal 2026-11-05, lote 1.2).
 *
 * ÂNCORA (regra escrita, não o código): R-FISCAL-003 · memory/requisitos/Fiscal/SPEC.md —
 * gate POR SUB-FEATURE; sem `fiscal.nfe.view` o GET /fiscal/nfe é 403. Um item de menu
 * que aparece para quem a rota recusa é a mesma regra escrita duas vezes e discordando.
 *
 * Monta o menu DE VERDADE (App\Services\Menu, o mesmo builder do AdminSidebarMenu) e lê
 * as URLs publicadas — não procura texto no arquivo do DataController.
 *
 * Tenant 98 (fictício, ADR 0358). Nunca biz=4.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: usuário/papel Spatie exigem schema MySQL UltimatePOS (ADR 0358)');
    }
    if (! Schema::hasTable('roles')) {
        $this->markTestSkipped('roles ausente — schema UltimatePOS não migrado');
    }

    // Pacote ativo: este teste é sobre PERMISSÃO, o pacote tem teste próprio.
    $util = \Mockery::mock(ModuleUtil::class)->makePartial();
    $util->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $util);

    $this->b98 = $this->seededTenant();
});

/** URLs que o DataController do Fiscal publica para este usuário. */
function fiscalMenuUrls(\App\User $user, int $businessId): array
{
    test()->actingAs($user);
    session(['business.id' => $businessId, 'user.business_id' => $businessId]);

    Menu::destroy();
    Menu::create('admin-sidebar-menu', fn () => null);
    (new DataController())->modifyAdminMenu();

    return collect(Menu::instance('admin-sidebar-menu')->getItems())
        ->map(fn ($item) => parse_url($item->getUrl(), PHP_URL_PATH))
        ->filter(fn ($path) => str_starts_with((string) $path, '/fiscal'))
        ->values()
        ->all();
}

it('cada permissão de tela mostra só o item da sua rota', function (string $permissao, string $rota) {
    $u = $this->usuarioComPermissoes(['fiscal.access', $permissao], $this->b98);

    expect(fiscalMenuUrls($u, (int) $this->b98->id))->toBe([$rota]);
})->with([
    'Notas Fiscais' => ['fiscal.nfe.view', '/fiscal/nfe'],
    'Manifestação' => ['fiscal.dfe.manage', '/fiscal/dfe'],
    'Certificado' => ['fiscal.config.edit', '/fiscal/config'],
]);

it('só fiscal.access, sem permissão de tela nenhuma, não ganha item que daria 403', function () {
    $u = $this->usuarioComPermissoes(['fiscal.access'], $this->b98);

    expect(fiscalMenuUrls($u, (int) $this->b98->id))->toBe([]);
});

it('controle: com as três permissões de tela aparecem os três itens', function () {
    $u = $this->usuarioComPermissoes(
        ['fiscal.access', 'fiscal.nfe.view', 'fiscal.dfe.manage', 'fiscal.config.edit'],
        $this->b98
    );

    expect(fiscalMenuUrls($u, (int) $this->b98->id))
        ->toBe(['/fiscal/nfe', '/fiscal/dfe', '/fiscal/config']);
});
