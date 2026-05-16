<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Cms\Entities\CmsSiteDetail;

uses(Tests\TestCase::class);

/**
 * Testa que a home pública (`/`) NÃO mistura site_details entre tenants.
 *
 * CmsSiteDetail hoje é tabela global (logo, headline, descrição landing
 * oimpresso.com). Quando suportar multi-business (US-CMS-003), home biz=1
 * deve carregar config biz=1 e home biz=99 deve carregar config biz=99 —
 * sem vazamento de logo/headline/depoimentos entre tenants.
 *
 * Esta suite documenta o gap (5/30 em D1, 0/15 em D3 na auditoria 2026-05-16)
 * e fica `markTestSkipped` até US-CMS-003 adicionar `business_id` em
 * `cms_site_details`. Padrão validado em outros módulos.
 *
 * NUNCA usar biz=4 (ROTA LIVRE) — ADR 0101.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/Cms/SPEC.md US-CMS-003
 */

const BIZ_WAGNER_HOME = 1;
const BIZ_FICTICIO_HOME = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: schema UltimatePOS MySQL requerido (ADR 0101)'
        );
    }
    if (! Schema::hasTable('cms_site_details')) {
        $this->markTestSkipped('cms_site_details table missing — rode migrate');
    }
    if (! Schema::hasColumn('cms_site_details', 'business_id')) {
        $this->markTestSkipped(
            'GAP D3: cms_site_details ainda não tem business_id — US-CMS-003 pendente. '
            .'Este teste vira ativo quando a coluna + global scope existirem.'
        );
    }
});

function setBizSessionHome(int $businessId): void
{
    session(['user.business_id' => $businessId]);
}

it('site_details biz=1 não aparece em queries biz=99', function () {
    setBizSessionHome(BIZ_WAGNER_HOME);
    CmsSiteDetail::withoutGlobalScopes()->updateOrCreate( // SUPERADMIN: setup
        [
            'business_id' => BIZ_WAGNER_HOME,
            'site_key'    => 'home_headline_teste',
        ],
        [
            'site_value' => json_encode('Headline exclusivo biz=1'),
        ]
    );

    setBizSessionHome(BIZ_FICTICIO_HOME);
    $valor = CmsSiteDetail::getValue('home_headline_teste');

    expect($valor)->toBeNull();
})->afterEach(function () {
    CmsSiteDetail::withoutGlobalScopes()
        ->where('site_key', 'home_headline_teste')
        ->forceDelete();
});

it('home pública carrega config do tenant correto', function () {
    setBizSessionHome(BIZ_WAGNER_HOME);
    CmsSiteDetail::withoutGlobalScopes()->updateOrCreate( // SUPERADMIN: setup
        [
            'business_id' => BIZ_WAGNER_HOME,
            'site_key'    => 'home_titulo_teste',
        ],
        ['site_value' => json_encode('oimpresso WR2 Wagner')]
    );

    CmsSiteDetail::withoutGlobalScopes()->updateOrCreate( // SUPERADMIN: setup
        [
            'business_id' => BIZ_FICTICIO_HOME,
            'site_key'    => 'home_titulo_teste',
        ],
        ['site_value' => json_encode('Tenant fictício 99')]
    );

    setBizSessionHome(BIZ_WAGNER_HOME);
    $valorBiz1 = CmsSiteDetail::getValue('home_titulo_teste');
    expect($valorBiz1)->toBe('oimpresso WR2 Wagner');

    setBizSessionHome(BIZ_FICTICIO_HOME);
    $valorBiz99 = CmsSiteDetail::getValue('home_titulo_teste');
    expect($valorBiz99)->toBe('Tenant fictício 99');
})->afterEach(function () {
    CmsSiteDetail::withoutGlobalScopes()
        ->where('site_key', 'home_titulo_teste')
        ->forceDelete();
});

it('getSiteDetails não retorna chaves de outro tenant', function () {
    setBizSessionHome(BIZ_WAGNER_HOME);
    CmsSiteDetail::withoutGlobalScopes()->updateOrCreate( // SUPERADMIN: setup
        [
            'business_id' => BIZ_WAGNER_HOME,
            'site_key'    => 'chave_exclusiva_biz1_teste',
        ],
        ['site_value' => json_encode('valor biz1')]
    );

    setBizSessionHome(BIZ_FICTICIO_HOME);
    $details = CmsSiteDetail::getSiteDetails();

    expect($details)->not->toHaveKey('chave_exclusiva_biz1_teste');
})->afterEach(function () {
    CmsSiteDetail::withoutGlobalScopes()
        ->where('site_key', 'chave_exclusiva_biz1_teste')
        ->forceDelete();
});
