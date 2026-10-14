<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Cms\Entities\CmsPage;

uses(Tests\TestCase::class);

// @covers-us US-CMS-001
// @covers-us US-CMS-005

/**
 * Contrato das 4 telas públicas Site/* do Cms. UCs de Modules/Cms/Resources/js/Pages/Site/*.casos.md
 * (fontes: SPEC Cms US-CMS-001/005 + charters — não o .tsx). `cms_pages` é global (US-CMS-002
 * pendente); toda escrita em transação revertida (§5 2026-09-18). SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o site público lê o schema MySQL do CMS.');
    }
    if (! Schema::hasTable('cms_pages')) {
        $this->markTestSkipped('Schema ausente — rode migrations primeiro.');
    }
    DB::beginTransaction(); // nada fica no banco compartilhado do CT 100
});

afterEach(fn () => DB::rollBack());

/** Requisição como o Inertia faz; versão do próprio middleware (valor suposto dá 409). */
function sitePubHeaders(array $extra = []): array
{
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    return array_merge(['X-Inertia' => 'true', 'X-Inertia-Version' => $versao, 'X-Requested-With' => 'XMLHttpRequest'], $extra);
}

function sitePubPost(array $attrs): CmsPage
{
    return CmsPage::create(array_merge(['type' => 'blog', 'content' => '<p>Texto do post de teste</p>', 'is_enabled' => 1], $attrs));
}

it('UC-SHOME-01 · / abre sem login, na tela Site/Home', function () {
    $this->assertGuest();
    $r = $this->withHeaders(sitePubHeaders())->get('/');

    $r->assertOk();
    expect($r->json('component'))->toBe('Site/Home');
});

it('UC-SHOME-02 · as 4 seções do CMS chegam diferidas, e chegam quando pedidas', function () {
    $secoes = ['page', 'testimonials', 'faqs', 'statistics'];

    $inicial = $this->withHeaders(sitePubHeaders())->get('/');
    $inicial->assertOk();
    $diferidas = collect($inicial->json('deferredProps') ?? [])->flatten()->all();
    foreach ($secoes as $s) {
        expect($diferidas)->toContain($s);
        expect(array_key_exists($s, (array) $inicial->json('props')))->toBeFalse();
    }

    $parcial = $this->withHeaders(sitePubHeaders([
        'X-Inertia-Partial-Component' => 'Site/Home',
        'X-Inertia-Partial-Data' => implode(',', $secoes),
    ]))->get('/');
    $parcial->assertOk();
    foreach ($secoes as $s) {
        expect(array_key_exists($s, (array) $parcial->json('props')))->toBeTrue();
    }
});

it('UC-SPAGE-01 · abre a página pelo título na URL, sem login', function () {
    CmsPage::create(['type' => 'page', 'title' => 'Termos sitepub contrato', 'content' => '<p>Termos</p>', 'is_enabled' => 1]);

    $this->assertGuest();
    $r = $this->withHeaders(sitePubHeaders())->get('/c/page/termos-sitepub-contrato');

    $r->assertOk();
    expect($r->json('component'))->toBe('Site/Page');
    expect($r->json('props.page.title'))->toBe('Termos sitepub contrato');
});

it('UC-SPAGE-02 · título que não existe dá 404', function () {
    $this->withHeaders(sitePubHeaders())
        ->get('/c/page/sitepub-pagina-que-nao-existe-'.uniqid())
        ->assertNotFound();
});

it('UC-SPAGE-03 · o conteúdo da página sai sanitizado', function () {
    CmsPage::create([
        'type' => 'page',
        'title' => 'Sobre sitepub xss',
        'content' => '<p>Texto legitimo da pagina</p><script>alert(1)</script>',
        'is_enabled' => 1,
    ]);

    $r = $this->withHeaders(sitePubHeaders())->get('/c/page/sobre-sitepub-xss');
    $r->assertOk();
    $conteudo = (string) $r->json('props.page.content');

    expect($conteudo)->toContain('Texto legitimo da pagina');
    expect(stripos($conteudo, '<script'))->toBeFalse();
});

it('UC-SBLOG-01 · /c/blogs abre sem login, com a lista de posts', function () {
    $this->assertGuest();
    $r = $this->withHeaders(sitePubHeaders())->get('/c/blogs');

    $r->assertOk();
    expect($r->json('component'))->toBe('Site/Blogs');
    expect($r->json('props.posts'))->toBeArray();
});

it('UC-SBLOG-02 · só post publicado aparece na vitrine', function () {
    $publicado = sitePubPost(['title' => 'Sitepub publicado']);
    $desligado = sitePubPost(['title' => 'Sitepub desligado', 'is_enabled' => 0]);

    $ids = collect($this->withHeaders(sitePubHeaders())->get('/c/blogs')->json('props.posts'))->pluck('id')->all();

    expect($ids)->toContain($publicado->id);
    expect(in_array($desligado->id, $ids, true))->toBeFalse();
});

it('UC-SBLOG-03 · a ordem é a prioridade do editor, não a data', function () {
    $segundo = sitePubPost(['title' => 'Sitepub prioridade 2', 'priority' => 2]);
    $primeiro = sitePubPost(['title' => 'Sitepub prioridade 1', 'priority' => 1]);

    $ids = collect($this->withHeaders(sitePubHeaders())->get('/c/blogs')->json('props.posts'))->pluck('id')->values()->all();

    $posPrimeiro = array_search($primeiro->id, $ids, true);
    $posSegundo = array_search($segundo->id, $ids, true);
    expect($posPrimeiro)->not->toBeFalse();
    expect($posSegundo)->not->toBeFalse();
    expect($posPrimeiro)->toBeLessThan($posSegundo);
});

it('UC-SBPOST-01 · abre o post pela URL, sem login', function () {
    $post = sitePubPost(['title' => 'Sitepub leitura']);

    $this->assertGuest();
    $r = $this->withHeaders(sitePubHeaders())->get('/c/blog/sitepub-leitura-'.$post->id);

    $r->assertOk();
    expect($r->json('component'))->toBe('Site/BlogPost');
    expect($r->json('props.post.title'))->toBe('Sitepub leitura');
});

it('UC-SBPOST-02 · id inexistente ou post desligado dá 404', function () {
    $maior = (int) CmsPage::max('id');
    $this->withHeaders(sitePubHeaders())
        ->get('/c/blog/sitepub-nao-existe-'.($maior + 1000))
        ->assertNotFound();

    $desligado = sitePubPost(['title' => 'Sitepub rascunho', 'is_enabled' => 0]);
    $this->withHeaders(sitePubHeaders())
        ->get('/c/blog/sitepub-rascunho-'.$desligado->id)
        ->assertNotFound();
});

it('UC-SBPOST-03 · o corpo do post sai sanitizado', function () {
    $post = sitePubPost([
        'title' => 'Sitepub xss post',
        'content' => '<p>Corpo legitimo do post</p><script>alert(1)</script>',
    ]);

    $r = $this->withHeaders(sitePubHeaders())->get('/c/blog/sitepub-xss-post-'.$post->id);
    $r->assertOk();
    $corpo = (string) $r->json('props.post.content');

    expect($corpo)->toContain('Corpo legitimo do post');
    expect(stripos($corpo, '<script'))->toBeFalse();
});
