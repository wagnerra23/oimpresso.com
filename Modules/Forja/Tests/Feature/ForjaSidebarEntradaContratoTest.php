<?php

declare(strict_types=1);

use App\System;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Forja · UC-FORJA-03 — a entry "Forja" aparece na sidebar e leva ao cockpit.
 *
 * Contrato (fonte, não o código):
 *   - UC-FORJA-03 em Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.casos.md:
 *     "Forja aparece na sidebar e leva ao cockpit".
 *   - Decisão [W] 2026-09-08 (#7038): a Forja é ITEM do grupo PLATAFORMA, como no protótipo
 *     (`prototipo-ui/cowork/Wagner/data.jsx`, grupo PLATAFORMA, entry `projects` label "Forja")
 *     — link único (dropdown com submenu é o AP19 da ADR 0180), SEM ghosts e SEM atalho.
 *     Isto SUPERSEDE a redação antiga do UC (dropdown, `G F`, ghosts 1:1), reconciliada no
 *     casos.md no mesmo PR deste teste.
 *
 * POR QUE O CAMINHO REAL, e não `DataController::modifyAdminMenu()` direto: o que o UC
 * promete é o que o React recebe. A requisição passa pelo `AdminSidebarMenu`, que só chama
 * o `modifyAdminMenu` de módulo INSTALADO (`ModuleUtil::getModuleData` →
 * `isModuleInstalled`), e o `ShellMenuBuilder` serializa o resultado em `shell.menu`.
 * Invocar o DataController direto pularia exatamente o portão que decide se a entry existe.
 *
 * O ESTADO DE INSTALAÇÃO É O DE PRODUÇÃO, de propósito. O `InstallController` da Forja grava
 * `system.projectmgmt_version` (fachada legacy, ADR 0088) e nunca `forja_version`. Medido em
 * produção em 2026-10-07 (HEAD 6b0cd71f8f, `php artisan tinker`, leitura apenas):
 * `system` tem `projectmgmt_version=0.1` e NÃO tem `forja_version`;
 * `ModuleUtil::isModuleInstalled('Forja')` devolve `false`. O caso abaixo monta esse mesmo
 * estado (grava a row que o installer grava, remove a que ele não grava — tudo dentro da
 * transação). Semear `forja_version` aqui deixaria o teste verde escondendo o defeito de
 * produção — é a fixture que mente (§5 2026-08-24).
 *
 * Usuário: superadmin (o `modifyAdminMenu` aceita `can('superadmin')` sem assinatura) +
 * `jana.mcp.usage.all` (o construtor do ForjaController exige pra abrir /forja).
 * Tenant fictício 98 (ADR 0358) — NUNCA biz=4. Stack exige MySQL: em sqlite PULA (LC-13).
 *
 * @see Modules\Forja\Http\Controllers\DataController::modifyAdminMenu
 * @see app/Utils/ModuleUtil.php (isModuleInstalled · getModuleData)
 */

function forjaSidebarExigeSchema(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: AdminSidebarMenu + ModuleUtil exigem schema MySQL (ADR 0358).');
    }
    foreach (['users', 'permissions', 'system'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            test()->markTestSkipped("Tabela {$tabela} ausente — rode com DB_CONNECTION=mysql.");
        }
    }
}

/** Instalação como o InstallController da Forja deixa (e como produção está). */
function forjaSidebarInstalacaoDeProducao(): void
{
    if (! System::getProperty('projectmgmt_version')) {
        System::addProperty('projectmgmt_version', '0.1');
    }
    DB::table('system')->where('key', 'forja_version')->delete();
}

function forjaSidebarUsuario(): User
{
    forjaSidebarExigeSchema();

    $business = test()->seededTenant(); // biz=98 fictício (ADR 0358)
    $user = test()->usuarioComPermissoes(['jana.mcp.usage.all'], $business);
    $user->forceFill(['user_type' => 'user', 'allow_login' => 1])->save();
    $user->givePermissionTo(Permission::findOrCreate('superadmin', 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    session(['user.business_id' => $business->id, 'business.id' => $business->id, 'user.id' => $user->id]);

    return User::findOrFail($user->id);
}

it('UC-FORJA-03 · a entry "Forja" chega ao shell.menu como link único do grupo plataforma e leva ao cockpit', function () {
    $user = forjaSidebarUsuario();
    forjaSidebarInstalacaoDeProducao();

    $resp = $this->actingAs($user)->get('/forja');
    $resp->assertOk();

    $menu = (array) data_get($resp->viewData('page'), 'props.shell.menu', []);
    // Controle positivo: o menu foi serializado — senão o "não achei a Forja" mediria o vácuo.
    expect($menu)->not->toBeEmpty('shell.menu chegou vazio — o assert não estaria medindo nada');

    $forja = array_values(array_filter($menu, static fn ($i) => ($i['label'] ?? null) === 'Forja'));

    expect($forja)->toHaveCount(1,
        'a sidebar tem de ter UMA entry "Forja" (decisão [W] 2026-09-08: item do grupo PLATAFORMA; '.
        'duas portas pra mesma tela é o que a #7038 desfez). Zero = o AdminSidebarMenu não chamou o '.
        'DataController da Forja — ModuleUtil::isModuleInstalled("Forja") procura `forja_version`, '.
        'e o InstallController (e produção) só tem `projectmgmt_version`.'
    );

    $entry = $forja[0];
    expect($entry['href'] ?? null)->toBe('/forja');
    expect($entry['group'] ?? null)->toBe('plataforma');
    expect($entry)->not->toHaveKey('children');
    expect($entry)->not->toHaveKey('ghosts');
    expect($entry)->not->toHaveKey('shortcut');

    // "leva ao cockpit": o href resolve pra landing do hub, a Triagem.
    expect(Route::getRoutes()->match(Request::create('/forja', 'GET'))->getName())->toBe('forja.triagem');
});
