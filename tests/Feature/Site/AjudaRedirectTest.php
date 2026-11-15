<?php

declare(strict_types=1);

/**
 * "Ajuda" do topo e do rodapé do site público (SiteHeader / SiteFooter).
 *
 * Não existe central de ajuda pública: até 2026-11-14 o link apontava para /ajuda/ e
 * terminava em 404. Decisão [W] 2026-11-14: /ajuda leva ao Fale conosco, com 302 porque
 * a central vai existir.
 *
 * Sem tenant e sem banco (sqlite-safe).
 */
it('leva /ajuda ao Fale conosco com redirect temporário, sem login', function () {
    $this->assertGuest();

    $r = $this->get('/ajuda');

    $r->assertStatus(302);
    expect((string) $r->headers->get('Location'))->toEndWith('/c/contact-us');
});

it('topo e rodapé do site apontam para /ajuda, que tem rota', function () {
    $header = (string) file_get_contents(resource_path('js/Components/Site/SiteHeader.tsx'));
    $footer = (string) file_get_contents(resource_path('js/Components/Site/SiteFooter.tsx'));

    expect($header)->toContain("href: '/ajuda' }");
    expect($footer)->toContain("href: '/ajuda' }");
    // O destino antigo, com barra no fim, terminava em 404 em produção.
    expect($header)->not->toContain("'/ajuda/'");
    expect($footer)->not->toContain("'/ajuda/'");
});
