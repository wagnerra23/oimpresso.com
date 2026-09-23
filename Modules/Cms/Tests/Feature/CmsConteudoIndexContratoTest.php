<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Cms\Entities\CmsPage;

uses(Tests\TestCase::class);

// @covers-us US-CMS-004

/**
 * Contrato da lista `/cms/cms-page` — thread Cms/01, fase 1 (Blade → Inertia).
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Cms/Resources/js/Pages/Admin/Content/Index.casos.md
 *   prototipo-ui/cowork/Wagner/cowork-inbox/cms/CMS-F1-2026-08-19.md §2/§3
 *
 * `cms_pages` é global (sem business_id — ADR 0093 §superadmin). O tenant 98 só dá o
 * `business_id` do usuário de teste. ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o painel do CMS requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('cms_pages') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema ausente — rode migrations primeiro.');
    }

    // O middleware `superadmin` compara o USERNAME com constants.administrator_usernames.
    config(['constants.administrator_usernames' => 'cms_superadmin_test']);
});

afterEach(function () {
    CmsPage::where('title', 'like', 'Contrato cms01 %')->delete();
});

const BIZ_CMS = 98;
const ROTA_CMS = '/cms/cms-page';

function cmsUsuario(string $username): User
{
    Business::firstOrCreate(['id' => BIZ_CMS], ['name' => 'Tenant fictício cms', 'currency_id' => 1]);

    return User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local',
        'password' => bcrypt('secret'),
        'business_id' => BIZ_CMS,
        'first_name' => 'Cms',
        'last_name' => 'Teste',
    ]);
}

function cmsLista(string $tipo): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $resposta = test()->actingAs(cmsUsuario('cms_superadmin_test'))->get(ROTA_CMS.'?type='.$tipo, [
        'X-Inertia' => 'true',
        // O browser real manda os DOIS (lápide §5 2026-09-08): sem este header o teste
        // passaria num controller que se desvia por request()->ajax().
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'paginas',
        'X-Inertia-Partial-Component' => 'Admin/Content/Index',
    ]);

    $resposta->assertOk();

    return (array) $resposta->json('props.paginas');
}

it('UC-CMS-01 · a lista responde Inertia com a ordem de priority e vazio no fim', function () {
    CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 sem ordem', 'content' => 'x', 'is_enabled' => 1, 'priority' => null]);
    CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 segunda', 'content' => 'x', 'is_enabled' => 1, 'priority' => 2]);
    CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 primeira', 'content' => 'x', 'is_enabled' => 0, 'priority' => 1]);

    $this->actingAs(cmsUsuario('cms_superadmin_test'))
        ->get(ROTA_CMS.'?type=page')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Admin/Content/Index')->where('tipo', 'page'));

    $titulos = collect(cmsLista('page'))->pluck('titulo')->filter(fn ($t) => str_starts_with($t, 'Contrato cms01'))->values()->all();

    expect($titulos)->toBe(['Contrato cms01 primeira', 'Contrato cms01 segunda', 'Contrato cms01 sem ordem']);
});

it('UC-CMS-02 · usuário sem superadmin é barrado enquanto o superadmin passa', function () {
    $barrado = $this->actingAs(cmsUsuario('cms_admin_negocio_test'))->get(ROTA_CMS);
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(cmsUsuario('cms_superadmin_test'))->get(ROTA_CMS)->assertOk();
});

it('UC-CMS-03 · visitante sem sessão vai para o login', function () {
    $this->get(ROTA_CMS)->assertRedirect();
});

it('UC-CMS-20 · a linha diz situação, sistema e descrição sem enum cru', function () {
    CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 sistema', 'content' => 'x', 'is_enabled' => 1, 'layout' => 'home', 'meta_description' => '']);
    CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 livre', 'content' => 'x', 'is_enabled' => 0, 'meta_description' => 'descrição']);

    $linhas = collect(cmsLista('page'))->keyBy('titulo');

    expect($linhas['Contrato cms01 sistema']['sistema'])->toBeTrue()
        ->and($linhas['Contrato cms01 sistema']['sem_descricao'])->toBeTrue()
        ->and($linhas['Contrato cms01 sistema']['publicada'])->toBeTrue()
        ->and($linhas['Contrato cms01 livre']['sistema'])->toBeFalse()
        ->and($linhas['Contrato cms01 livre']['sem_descricao'])->toBeFalse()
        ->and($linhas['Contrato cms01 livre']['publicada'])->toBeFalse()
        ->and($linhas['Contrato cms01 livre']['endereco'])->toBe('/c/page/contrato-cms01-livre');
});

it('UC-CMS-21 · tipo fora do domínio cai em page e a aba de blog só lista blog', function () {
    CmsPage::create(['type' => 'blog', 'title' => 'Contrato cms01 post', 'content' => 'x', 'is_enabled' => 1]);

    $this->actingAs(cmsUsuario('cms_superadmin_test'))
        ->get(ROTA_CMS.'?type=banner')
        ->assertInertia(fn (AssertableInertia $p) => $p->where('tipo', 'page'));

    $blog = collect(cmsLista('blog'))->firstWhere('titulo', 'Contrato cms01 post');
    $pagina = collect(cmsLista('page'))->firstWhere('titulo', 'Contrato cms01 post');

    expect($blog)->not->toBeNull()
        ->and($blog['endereco'])->toBe('/c/blog/contrato-cms01-post-'.$blog['id'])
        ->and($pagina)->toBeNull();
});

// ── Fase 2 · editor em drawer (RUNBOOK-admin-content.md) ─────────────────────

it('UC-CMS-04 · criar sem título devolve erro no campo e não grava nada', function () {
    $antes = CmsPage::count();

    $this->actingAs(cmsUsuario('cms_superadmin_test'))
        ->post(ROTA_CMS, ['type' => 'page', 'content' => 'Contrato cms01 sem titulo'])
        ->assertSessionHasErrors('title');

    expect(CmsPage::count())->toBe($antes);
});

it('UC-CMS-05 · descrição vazia vira os 160 primeiros caracteres do conteúdo em texto puro', function () {
    $longo = '<p>Olá <b>mundo</b></p><script>x()</script>'.str_repeat(' palavra', 60);

    $this->actingAs(cmsUsuario('cms_superadmin_test'))
        ->post(ROTA_CMS, ['type' => 'page', 'title' => 'Contrato cms01 meta', 'content' => $longo, 'meta_description' => ''])
        ->assertSessionHasNoErrors();

    $meta = (string) CmsPage::where('title', 'Contrato cms01 meta')->value('meta_description');

    expect(mb_strlen($meta))->toBe(160)
        ->and(str_starts_with($meta, 'Olá mundo'))->toBeTrue()
        ->and(str_contains($meta, '<'))->toBeFalse()
        ->and(str_contains($meta, 'x()'))->toBeFalse();
});

it('UC-CMS-23 · editar sem enviar a descrição preserva a que já estava gravada', function () {
    $p = CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 preserva', 'content' => 'corpo novo', 'is_enabled' => 1, 'meta_description' => 'digitada à mão']);

    // A Blade de edição não tem o campo: a requisição chega SEM a chave.
    $this->actingAs(cmsUsuario('cms_superadmin_test'))
        ->put(ROTA_CMS.'/'.$p->id, ['type' => 'page', 'title' => 'Contrato cms01 preserva', 'content' => 'outro corpo', 'is_enabled' => 1])
        ->assertSessionHasNoErrors();

    expect($p->fresh()->meta_description)->toBe('digitada à mão');

    // E vazia de propósito → deriva do conteúdo enviado.
    $this->actingAs(cmsUsuario('cms_superadmin_test'))
        ->put(ROTA_CMS.'/'.$p->id, ['type' => 'page', 'title' => 'Contrato cms01 preserva', 'content' => 'outro corpo', 'meta_description' => ''])
        ->assertSessionHasNoErrors();

    expect($p->fresh()->meta_description)->toBe('outro corpo');
});

it('UC-CMS-22 · o drawer recebe a linha pedida, e só por partial reload', function () {
    $p = CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 editor', 'content' => '<p>corpo</p>', 'is_enabled' => 0, 'layout' => 'home']);

    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $parcial = $this->actingAs(cmsUsuario('cms_superadmin_test'))->get(ROTA_CMS.'?type=page&editar='.$p->id, [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'editando',
        'X-Inertia-Partial-Component' => 'Admin/Content/Index',
    ])->assertOk();

    expect($parcial->json('props.editando.conteudo'))->toBe('<p>corpo</p>')
        ->and($parcial->json('props.editando.layout'))->toBe('home')
        ->and($parcial->json('props.editando.publicada'))->toBeFalse();

    // Carga inicial NÃO calcula o editor (Inertia::optional).
    $this->actingAs(cmsUsuario('cms_superadmin_test'))
        ->get(ROTA_CMS.'?type=page&editar='.$p->id)
        ->assertInertia(fn (AssertableInertia $pg) => $pg->missing('editando'));
});

// ── Fase 2b · destaques da página inicial (caminho A, [W] 2026-09-23) ────────

it('UC-CMS-08 · só a página inicial traz os destaques para o drawer', function () {
    $home = CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 home', 'content' => 'x', 'layout' => 'home']);
    $livre = CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 sem destaques', 'content' => 'x']);
    \Modules\Cms\Entities\CmsPageMeta::create(['cms_page_id' => $home->id, 'meta_key' => 'feature',
        'meta_value' => json_encode(['title' => 'Seção', 'description' => '', 'content' => [['icon' => '📦', 'title' => 'Estoque', 'description' => 'd']]])]);

    $editor = function (int $id) {
        $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return test()->actingAs(cmsUsuario('cms_superadmin_test'))->get(ROTA_CMS.'?type=page&editar='.$id, [
            'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Data' => 'editando', 'X-Inertia-Partial-Component' => 'Admin/Content/Index',
        ])->json('props.editando');
    };

    try {
        expect($editor($home->id)['destaques']['content'][0]['title'])->toBe('Estoque')
            ->and($editor($livre->id)['destaques'])->toBeNull();
    } finally {
        \Modules\Cms\Entities\CmsPageMeta::where('cms_page_id', $home->id)->delete();
    }
});

it('UC-CMS-24 · salvar os destaques grava o registro que a home pública lê', function () {
    $home = CmsPage::create(['type' => 'page', 'title' => 'Contrato cms01 home grava', 'content' => 'x', 'layout' => 'home']);
    $meta = \Modules\Cms\Entities\CmsPageMeta::create(['cms_page_id' => $home->id, 'meta_key' => 'feature', 'meta_value' => '{}']);

    try {
        $this->actingAs(cmsUsuario('cms_superadmin_test'))->put(ROTA_CMS.'/'.$home->id, [
            'type' => 'page', 'title' => 'Contrato cms01 home grava', 'content' => 'x', 'is_enabled' => 1,
            'meta' => ['feature' => ['id' => $meta->id, 'title' => 'Tudo num lugar', 'description' => 'texto',
                'content' => [['icon' => '🧾', 'title' => 'NF-e', 'description' => 'emite']]]],
        ])->assertSessionHasNoErrors();

        $gravado = json_decode((string) $meta->fresh()->meta_value, true);

        expect($gravado['title'])->toBe('Tudo num lugar')
            ->and($gravado['content'][0]['title'])->toBe('NF-e')
            ->and(\Modules\Cms\Entities\CmsPageMeta::where('cms_page_id', $home->id)->count())->toBe(1);
    } finally {
        \Modules\Cms\Entities\CmsPageMeta::where('cms_page_id', $home->id)->delete();
    }
});

it('UC-CMS-25 · a migration troca só o seed em inglês, e deixa conteúdo editado em paz', function () {
    $homeId = DB::table('cms_pages')->where('type', 'page')->where('layout', 'home')->value('id');
    if ($homeId === null) {
        $this->markTestSkipped('sem página layout=home no ambiente.');
    }

    $migration = require base_path('Modules/Cms/Database/Migrations/2026_09_23_180000_cms_home_destaques_troca_seed_pelo_conteudo_do_site.php');
    $seed = json_encode(['id' => '2', 'title' => 'Features to skyrocket 🚀 your business growth', 'description' => '<p>x</p>',
        'content' => [['icon' => 'fas fa-cloud', 'title' => 'Access Anywhere!', 'description' => 'd']]]);
    $editado = json_encode(['title' => 'Escrito pelo Wagner', 'content' => [['icon' => '✨', 'title' => 'Access Anywhere!', 'description' => 'd']]]);
    $gravado = fn () => json_decode((string) DB::table('cms_page_metas')->where('cms_page_id', $homeId)->where('meta_key', 'feature')->value('meta_value'), true);

    // Escrita dentro de transação revertida: o banco do CT 100 persiste entre runs (§5 2026-09-18).
    DB::beginTransaction();
    try {
        DB::table('cms_page_metas')->updateOrInsert(['cms_page_id' => $homeId, 'meta_key' => 'feature'], ['meta_value' => $seed]);
        $migration->up();
        expect($gravado()['title'])->toBe('Oito módulos. Uma plataforma.')
            ->and(count($gravado()['content']))->toBe(8)
            ->and($gravado()['content'][0]['icon'])->toBe('📐');

        DB::table('cms_page_metas')->where('cms_page_id', $homeId)->where('meta_key', 'feature')->update(['meta_value' => $editado]);
        $migration->up();
        expect($gravado()['title'])->toBe('Escrito pelo Wagner');
    } finally {
        DB::rollBack();
    }
});
