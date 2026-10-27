<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Superadmin\Entities\SuperadminFrontendPage;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-005

/**
 * Página pública `/page/{slug}` (conteúdo das Páginas do Superadmin), sem login.
 * Desde 2026-10-27 renderiza a tela `Site/Page` (a mesma de `/c/page/{slug}`) em vez da Blade
 * `superadmin::pages.show`, e o conteúdo passa pelo HtmlSanitizer.
 *
 * Tabela global (sem business_id). Cada caso cria páginas `uc-sapub-*` e apaga no fim.
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /page/{slug} requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('superadmin_frontend_pages')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
});

afterEach(function () {
    if (Schema::hasTable('superadmin_frontend_pages')) {
        SuperadminFrontendPage::where('slug', 'like', 'uc-sapub-%')->delete();
    }
});

it('UC-SPAGE-04 · visitante sem login vê a página do Superadmin na tela Site/Page, com o HTML sem script', function () {
    $p = SuperadminFrontendPage::create([
        'title' => 'Termos fictícios UC-SAPUB',
        'slug' => 'uc-sapub-termos-' . uniqid(),
        'content' => '<p>Texto <b>legal</b></p><script>alert(1)</script>',
        'menu_order' => 1,
        'is_shown' => 1,
    ]);

    $this->get('/page/' . $p->slug)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Site/Page')
            ->where('page.id', (int) $p->id)
            ->where('page.title', 'Termos fictícios UC-SAPUB')
            ->where('page.content', fn ($html) => str_contains((string) $html, '<b>legal</b>')
                && ! str_contains((string) $html, '<script')));
});

it('UC-SPAGE-05 · /page/{slug} que não existe responde 404', function () {
    $this->get('/page/uc-sapub-nao-existe-' . uniqid())->assertNotFound();
});
