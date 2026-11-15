<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Cms\Entities\CmsSiteDetail;

uses(Tests\TestCase::class);

/**
 * Página pública de contato (`/c/contact-us`, Blade `cms::frontend.pages.contact_us`).
 *
 * Medido em produção em 2026-11-14: textos fixos em inglês ("Contact Us", "Send Message",
 * "Call Us", "All Rights Reserved"), `lang="en"` e os valores de exemplo do instalador
 * aparecendo como contato real (telefone `0000000000`, e-mail `*@example.com`).
 *
 * Os contatos vêm de Cms › Detalhes do site (`cms_site_details`, chaves `contact_us` e
 * `mail_us`). `cms_site_details` não tem business_id: é o site da instalação. Toda escrita
 * fica em transação revertida (§5 2026-09-18). SKIP em SQLite — leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a página lê o schema MySQL do CMS.');
    }
    if (! Schema::hasTable('cms_site_details')) {
        $this->markTestSkipped('Schema ausente — rode migrations primeiro.');
    }
    DB::beginTransaction();
});

afterEach(fn () => DB::rollBack());

it('a página de contato abre em português, sem login', function () {
    $html = $this->get('/c/contact-us')->assertOk()->getContent();

    expect($html)->toContain('lang="pt-BR"');
    expect($html)->toContain('Fale conosco');
    expect($html)->toContain('Enviar mensagem');
    expect($html)->toContain('Todos os direitos reservados');
    foreach (['Contact Us', 'SEND MESSAGE', 'Call <strong>Us</strong>', 'Mail <strong>Us</strong>', 'All Rights Reserved', 'Try For Free'] as $ingles) {
        expect(str_contains($html, $ingles))->toBeFalse("ainda em inglês: {$ingles}");
    }
});

it('valor de exemplo do instalador não aparece como contato, e as seções vazias somem', function () {
    CmsSiteDetail::createOrUpdateSiteDetails([
        'contact_us' => [['label' => 'Call', 'num' => '0000000000']],
        'mail_us' => [['label' => 'Contact', 'email' => 'contact@example.com']],
    ]);

    $html = $this->get('/c/contact-us')->assertOk()->getContent();

    expect(str_contains($html, '0000000000'))->toBeFalse('telefone de exemplo apareceu');
    expect(str_contains($html, 'example.com'))->toBeFalse('e-mail de exemplo apareceu');
    expect(str_contains($html, '<strong>Telefone</strong>'))->toBeFalse('título sem telefone apareceu');
    expect(str_contains($html, '<strong>E-mail</strong>'))->toBeFalse('título sem e-mail apareceu');
});

it('contato real cadastrado no Cms aparece com o título da seção', function () {
    CmsSiteDetail::createOrUpdateSiteDetails([
        'contact_us' => [['label' => 'Suporte', 'num' => '48 3333-1234']],
        'mail_us' => [['label' => 'Comercial', 'email' => 'comercial@contato-teste.com.br']],
    ]);

    $html = $this->get('/c/contact-us')->assertOk()->getContent();

    expect($html)->toContain('<strong>Telefone</strong>');
    expect($html)->toContain('48 3333-1234');
    expect($html)->toContain('<strong>E-mail</strong>');
    expect($html)->toContain('comercial@contato-teste.com.br');
});
