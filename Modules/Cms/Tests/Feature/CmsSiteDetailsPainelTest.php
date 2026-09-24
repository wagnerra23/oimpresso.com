<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Cms\Entities\CmsSiteDetail;

uses(Tests\TestCase::class);

// @covers-us US-CMS-004

/**
 * Contrato de `/cms/site-details` — thread Cms/01, fase 4a (Blade settings → Inertia).
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Cms/Resources/js/Pages/Admin/SiteDetails/Index.casos.md
 *   prototipo-ui/cowork/Wagner/cowork-inbox/cms/SiteDetails.casos.md
 *
 * `cms_site_details` é global (sem business_id). Toda escrita roda dentro de transação revertida:
 * o banco do CT 100 persiste entre runs (§5 2026-09-18). ⚠️ SKIP em SQLite — leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o painel do CMS requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('cms_site_details') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema ausente — rode migrations primeiro.');
    }

    config(['constants.administrator_usernames' => 'cmsd_superadmin_test']);
});

const ROTA_CMSD = '/cms/site-details';

function cmsdUsuario(string $username): User
{
    Business::firstOrCreate(['id' => 98], ['name' => 'Tenant fictício cms', 'currency_id' => 1]);

    return User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local',
        'password' => bcrypt('secret'),
        'business_id' => 98,
        'first_name' => 'Cmsd',
        'last_name' => 'Teste',
    ]);
}

/** Roda o bloco numa transação e desfaz — nada fica no banco compartilhado. */
function cmsdTransacao(callable $bloco): void
{
    DB::beginTransaction();
    try {
        $bloco();
    } finally {
        DB::rollBack();
    }
}

it('UC-CMSD-01 · abre em Inertia, e a tela anterior segue em ?legado=1', function () {
    $this->actingAs(cmsdUsuario('cmsd_superadmin_test'))
        ->get(ROTA_CMSD)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Admin/SiteDetails/Index'));

    $legado = $this->actingAs(cmsdUsuario('cmsd_superadmin_test'))->get(ROTA_CMSD.'?legado=1');
    $legado->assertOk();
    expect($legado->headers->get('X-Inertia'))->toBeNull();
});

it('UC-CMSD-07 · usuário comum é barrado enquanto o superadmin passa', function () {
    expect($this->actingAs(cmsdUsuario('cmsd_admin_negocio_test'))->get(ROTA_CMSD)->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(cmsdUsuario('cmsd_superadmin_test'))->get(ROTA_CMSD)->assertOk();
});

it('UC-CMSD-09 · o formato que o formulário sempre mandou (arrays) é aceito e gravado', function () {
    cmsdTransacao(function () {
        test()->actingAs(cmsdUsuario('cmsd_superadmin_test'))->post(ROTA_CMSD, [
            'notifiable_email' => 'contato@exemplo.test',
            'contact_us' => [['label' => 'WhatsApp', 'num' => '4899999999']],
            'mail_us' => [['label' => 'Comercial', 'email' => 'comercial@exemplo.test']],
            'follow_us' => ['instagram' => 'https://instagram.com/exemplo'],
            'statistics' => ['tagline' => 't', 'description' => 'd', 'content' => [['stats' => '10', 'title' => 'x']]],
            'faqs' => [['question' => 'P?', 'answer' => 'R.']],
        ])->assertSessionHasNoErrors();

        expect(CmsSiteDetail::getValue('contact_us')[0]['num'])->toBe('4899999999')
            ->and(CmsSiteDetail::getValue('follow_us')['instagram'])->toBe('https://instagram.com/exemplo')
            ->and(CmsSiteDetail::getValue('faqs')[0]['question'])->toBe('P?');
    });
});

it('UC-CMSD-02 · salvar as seções desta tela não apaga as que ficaram na anterior', function () {
    cmsdTransacao(function () {
        CmsSiteDetail::createOrUpdateSiteDetails(['faqs' => [['question' => 'Fica?', 'answer' => 'Fica.']]]);

        test()->actingAs(cmsdUsuario('cmsd_superadmin_test'))->post(ROTA_CMSD, [
            'notifiable_email' => 'outro@exemplo.test',
            'custom_css' => '.x{}',
        ])->assertSessionHasNoErrors();

        expect(CmsSiteDetail::getValue('faqs')[0]['question'])->toBe('Fica?')
            ->and(CmsSiteDetail::getValue('notifiable_email'))->toBe('outro@exemplo.test');
    });
});
