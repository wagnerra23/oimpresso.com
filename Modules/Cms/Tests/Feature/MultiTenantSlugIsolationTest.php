<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Cms\Entities\CmsPage;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 de slugs CMS.
 *
 * ADR 0093: global scope business_id IRREVOGÁVEL.
 * Slug biz=1 NÃO pode aparecer em queries no contexto biz=99 e vice-versa —
 * vazamento de conteúdo entre tenants viola isolamento.
 *
 * CONTEXTO IMPORTANTE (gap conhecido):
 * As tabelas `cms_pages` e `cms_site_details` HOJE não possuem coluna
 * `business_id` — foram criadas como tabelas globais pro landing
 * oimpresso.com (ver migrations 2022_08_04 e 2022_09_10).
 *
 * Esta suite documenta o gap D3 (multi-tenant) catalogado na auditoria
 * 2026-05-16: enquanto a coluna não existir, os testes ficam `markTestSkipped`
 * com mensagem clara, virando alarme passivo até US-CMS-002 (adicionar
 * business_id + global scope) ser entregue. Padrão validado em outros
 * módulos com gap (Modules/ComunicacaoVisual em Sprint 1).
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) — conforme ADR 0101.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício, sem dados).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/requisitos/Cms/SPEC.md US-CMS-002 (adicionar business_id)
 */

const BIZ_WAGNER_CMS = 1;
const BIZ_FICTICIO_CMS = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: schema UltimatePOS MySQL requerido (ADR 0101)'
        );
    }
    if (! Schema::hasTable('cms_pages')) {
        $this->markTestSkipped('cms_pages table missing — rode Modules/Cms migrate');
    }
    if (! Schema::hasColumn('cms_pages', 'business_id')) {
        $this->markTestSkipped(
            'GAP D3: cms_pages ainda não tem business_id — US-CMS-002 pendente. '
            .'Este teste vira ativo quando a migration adicionar coluna + global scope.'
        );
    }
});

function setBizSessionCms(int $businessId): void
{
    session(['user.business_id' => $businessId]);
}

it('slug CmsPage biz=1 não aparece com session biz=99', function () {
    setBizSessionCms(BIZ_WAGNER_CMS);
    $page = CmsPage::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id' => BIZ_WAGNER_CMS,
        'type'        => 'page',
        'title'       => 'Pagina Privada Biz1 Teste',
        'content'     => '<p>Conteudo secreto biz=1</p>',
        'is_enabled'  => 1,
    ]);

    setBizSessionCms(BIZ_FICTICIO_CMS);
    $resultado = CmsPage::where('id', $page->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    CmsPage::withoutGlobalScopes()
        ->where('title', 'Pagina Privada Biz1 Teste')
        ->forceDelete();
});

it('slug CmsPage biz=1 aparece com session biz=1', function () {
    setBizSessionCms(BIZ_WAGNER_CMS);
    $page = CmsPage::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id' => BIZ_WAGNER_CMS,
        'type'        => 'page',
        'title'       => 'Pagina Publica Biz1 Teste',
        'content'     => '<p>Conteudo publico biz=1</p>',
        'is_enabled'  => 1,
    ]);

    setBizSessionCms(BIZ_WAGNER_CMS);
    $resultado = CmsPage::where('id', $page->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->title)->toBe('Pagina Publica Biz1 Teste');
})->afterEach(function () {
    CmsPage::withoutGlobalScopes()
        ->where('title', 'Pagina Publica Biz1 Teste')
        ->forceDelete();
});

it('lista getEnabledPages biz=99 não vê páginas biz=1', function () {
    setBizSessionCms(BIZ_WAGNER_CMS);
    CmsPage::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id' => BIZ_WAGNER_CMS,
        'type'        => 'page',
        'title'       => 'Termos Biz1 Teste',
        'content'     => '<p>Termos exclusivos biz=1</p>',
        'is_enabled'  => 1,
        'priority'    => 10,
    ]);

    setBizSessionCms(BIZ_FICTICIO_CMS);
    $pages = CmsPage::getEnabledPages('page');

    $titulos = $pages->pluck('title')->toArray();
    expect($titulos)->not->toContain('Termos Biz1 Teste');
})->afterEach(function () {
    CmsPage::withoutGlobalScopes()
        ->where('title', 'Termos Biz1 Teste')
        ->forceDelete();
});

it('count getPagesCount não vaza contagem entre tenants', function () {
    setBizSessionCms(BIZ_WAGNER_CMS);
    CmsPage::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id' => BIZ_WAGNER_CMS,
        'type'        => 'page',
        'title'       => 'Contagem Biz1 Teste',
        'content'     => '<p>x</p>',
        'is_enabled'  => 1,
    ]);

    setBizSessionCms(BIZ_FICTICIO_CMS);
    $countBiz99 = CmsPage::where('title', 'Contagem Biz1 Teste')->count();

    expect($countBiz99)->toBe(0);
})->afterEach(function () {
    CmsPage::withoutGlobalScopes()
        ->where('title', 'Contagem Biz1 Teste')
        ->forceDelete();
});

it('rota pública /c/page/{slug} biz=99 retorna 404 quando slug é de biz=1', function () {
    setBizSessionCms(BIZ_WAGNER_CMS);
    CmsPage::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id' => BIZ_WAGNER_CMS,
        'type'        => 'page',
        'title'       => 'Slug Privado Biz1 Teste',
        'content'     => '<p>Vazaria se sem isolamento</p>',
        'is_enabled'  => 1,
    ]);

    // Simula contexto biz=99 servindo a rota pública
    setBizSessionCms(BIZ_FICTICIO_CMS);
    $response = $this->get('/c/page/slug-privado-biz1-teste');

    // 404 esperado — slug não pertence ao tenant que serve a request
    expect($response->status())->toBe(404);
})->afterEach(function () {
    CmsPage::withoutGlobalScopes()
        ->where('title', 'Slug Privado Biz1 Teste')
        ->forceDelete();
});
