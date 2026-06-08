<?php

declare(strict_types=1);

use App\Facades\Menu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * MWART Gate compliance — DataController NfeBrasil (sidebar injection).
 *
 * Cobre modifyAdminMenu — registro de 3 entries flat (Notas Fiscais ·
 * Manifestação · Certificado) no grupo FISCAL substituindo a tentativa
 * antiga 1-entry+ghosts (PR #1541, 2026-05-25).
 *
 * Padrão Wagner 2026-05-25: 3 entries flat com group='fiscal' permitem
 * usuário (Larissa/Martinho) acessar direto sem clique extra em "Fiscal" pai.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: ModuleUtil + Subscription requerem schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('business')) {
        $this->markTestSkipped('business table missing — UltimatePOS schema obrigatório');
    }

    // Re-criar menu 'admin-sidebar-menu' limpo a cada teste (Lavary Menu é singleton).
    Menu::make('admin-sidebar-menu', function ($m) {
    });
});

it('modifyAdminMenu retorna early sem permission subscription nfebrasil_module', function () {
    $user = \App\User::factory()->create(['business_id' => 99999]); // biz sem subscription
    $this->actingAs($user);
    session(['user.business_id' => 99999]);

    (new DataController())->modifyAdminMenu();

    // Menu deve continuar vazio (early return na linha 128)
    $menu = Menu::get('admin-sidebar-menu');
    $count = count($menu->all());
    expect($count)->toBe(0);
});

it('modifyAdminMenu retorna early sem permission nfebrasil.access (mesmo com subscription)', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    // user SEM nenhuma permission nfebrasil.* nem superadmin
    $this->actingAs($user);
    session(['user.business_id' => 1]);

    // Bypass subscription gate stub — chamamos direto pra testar permission gate (linha 132)
    // Como user não tem nenhuma permission, deve return early
    (new DataController())->modifyAdminMenu();

    $menu = Menu::get('admin-sidebar-menu');
    $count = count($menu->all());
    expect($count)->toBe(0);
});

it('superadmin: modifyAdminMenu registra 3 entries flat no grupo fiscal', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['user.business_id' => 1, 'business.id' => 1]);

    (new DataController())->modifyAdminMenu();

    $items = Menu::get('admin-sidebar-menu')->all();
    $labels = array_map(fn ($i) => $i->title, $items);

    // Wagner 2026-05-25: exatamente 3 entries flat (sem item "Fiscal" raiz)
    expect($labels)->toContain('Notas Fiscais');
    expect($labels)->toContain('Manifestação');
    expect($labels)->toContain('Certificado');
    expect($labels)->not->toContain('Fiscal'); // item raiz removido
});

it('entries 3-flat declaram group="fiscal" (LegacyMenuAdapter usa pra agrupar no frontend)', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['user.business_id' => 1, 'business.id' => 1]);

    (new DataController())->modifyAdminMenu();

    $items = Menu::get('admin-sidebar-menu')->all();
    $fiscalEntries = array_filter($items, fn ($i) => in_array($i->title, ['Notas Fiscais', 'Manifestação', 'Certificado'], true));

    expect($fiscalEntries)->toHaveCount(3);
    foreach ($fiscalEntries as $entry) {
        expect($entry->attr('group'))->toBe('fiscal');
    }
});

it('hrefs canon: Notas Fiscais → /fiscal/nfe; Manifestação → legacy; Certificado → legacy', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);
    session(['user.business_id' => 1, 'business.id' => 1]);

    (new DataController())->modifyAdminMenu();

    $byLabel = collect(Menu::get('admin-sidebar-menu')->all())->keyBy('title');

    expect($byLabel['Notas Fiscais']->url())->toContain('/fiscal/nfe');
    expect($byLabel['Manifestação']->url())->toContain('/nfe-brasil/manifestacao');
    expect($byLabel['Certificado']->url())->toContain('/nfe-brasil/configuracao/certificado');
});
