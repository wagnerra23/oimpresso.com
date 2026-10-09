<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Superadmin\Entities\SuperadminFrontendPage;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-005

/**
 * Contrato da tela `/superadmin/frontend-pages?tela=nova` — thread Superadmin/08 (Blade → Inertia).
 * UCs: Modules/Superadmin/Resources/js/Pages/superadmin/Paginas/Index.casos.md
 *
 * A tabela é global (sem business_id). Cada caso cria páginas com slug único e apaga no fim.
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /superadmin/frontend-pages requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('superadmin_frontend_pages') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
    config(['constants.administrator_usernames' => 'pag_superadmin_test']);
});

afterEach(function () {
    if (Schema::hasTable('superadmin_frontend_pages')) {
        SuperadminFrontendPage::where('slug', 'like', 'uc-sapag-%')->delete();
    }
});

/** Tenant fictício. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_PAG = 98;

const ROTA_PAG = '/superadmin/frontend-pages';

function pagUsuario(string $username, bool $superadmin): User
{
    Business::firstOrCreate(['id' => BIZ_PAG], ['name' => 'Tenant fictício páginas', 'currency_id' => 1]);

    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username . '@test.local', 'password' => bcrypt('secret'),
        'business_id' => BIZ_PAG, 'first_name' => 'Pag', 'last_name' => 'Teste',
    ]);

    if ($superadmin) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']));
    } else {
        $user->syncRoles([]);
        $user->syncPermissions([]);
    }

    return $user;
}

function pagCriar(string $sufixo, array $extra = []): SuperadminFrontendPage
{
    return SuperadminFrontendPage::create($extra + [
        'title' => 'Página fictícia ' . $sufixo, 'slug' => 'uc-sapag-' . $sufixo . '-' . uniqid(),
        'content' => '<p>Texto <b>institucional</b></p>', 'menu_order' => 7, 'is_shown' => 1,
    ]);
}

/** Cabeçalhos de uma visita Inertia (o controller decide a resposta por X-Inertia). */
function pagInertia(): array
{
    return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request())];
}

it('UC-SAPAG-01 · com ?tela=nova responde Inertia; sem a chave segue a Blade', function () {
    $su = pagUsuario('pag_superadmin_test', true);

    $this->actingAs($su)->get(ROTA_PAG . '?tela=nova')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('superadmin/Paginas/Index'));

    $this->actingAs($su)->get(ROTA_PAG)->assertOk()->assertViewIs('superadmin::pages.index');
});

it('UC-SAPAG-02 · usuário comum é barrado enquanto o superadmin passa', function () {
    $barrado = $this->actingAs(pagUsuario('pag_comum_test', false))->get(ROTA_PAG . '?tela=nova');
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(pagUsuario('pag_superadmin_test', true))->get(ROTA_PAG . '?tela=nova')->assertOk();
});

it('UC-SAPAG-03 · a lista traz a página com resumo sem marcação', function () {
    $p = pagCriar('lista');

    $resposta = $this->actingAs(pagUsuario('pag_superadmin_test', true))->get(ROTA_PAG . '?tela=nova', pagInertia() + [
        'X-Inertia-Partial-Data' => 'paginas',
        'X-Inertia-Partial-Component' => 'superadmin/Paginas/Index',
    ]);
    $resposta->assertOk();

    $item = collect((array) $resposta->json('props.paginas'))->firstWhere('id', $p->id);
    expect($item)->not->toBeNull();
    expect($item['slug'])->toBe($p->slug);
    expect($item['ordem'])->toBe(7);
    expect($item['visivel'])->toBeTrue();
    expect($item['resumo'])->toBe('Texto institucional');
});

it('UC-SAPAG-04 · slug repetido é recusado no campo e não duplica', function () {
    $p = pagCriar('dup');

    $this->actingAs(pagUsuario('pag_superadmin_test', true))
        ->post(ROTA_PAG, ['title' => 'Outra', 'slug' => $p->slug, 'content' => 'x', 'menu_order' => 1], pagInertia())
        ->assertSessionHasErrors('slug');

    expect(SuperadminFrontendPage::where('slug', $p->slug)->count())->toBe(1);
});

it('UC-SAPAG-05 · editar pela tela nova grava e volta para ?tela=nova', function () {
    $p = pagCriar('edita');

    $resposta = $this->actingAs(pagUsuario('pag_superadmin_test', true))
        ->put(ROTA_PAG . '/' . $p->id, [
            'title' => 'Título novo UC-SAPAG-05', 'slug' => $p->slug, 'content' => 'y', 'menu_order' => 3, 'is_shown' => 0,
        ], pagInertia());

    $resposta->assertRedirect();
    expect((string) $resposta->headers->get('Location'))->toContain('tela=nova');

    $gravado = SuperadminFrontendPage::find($p->id);
    expect($gravado->title)->toBe('Título novo UC-SAPAG-05');
    expect((int) $gravado->is_shown)->toBe(0);
    expect((int) $gravado->menu_order)->toBe(3);
});

it('UC-SAPAG-06 · excluir pela tela nova remove a página e volta para ?tela=nova', function () {
    $p = pagCriar('apaga');

    $resposta = $this->actingAs(pagUsuario('pag_superadmin_test', true))
        ->delete(ROTA_PAG . '/' . $p->id, [], pagInertia());

    $resposta->assertRedirect();
    expect((string) $resposta->headers->get('Location'))->toContain('tela=nova');
    expect(SuperadminFrontendPage::whereKey($p->id)->exists())->toBeFalse();
});
