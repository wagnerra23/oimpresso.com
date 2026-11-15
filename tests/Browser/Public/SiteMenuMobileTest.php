<?php

declare(strict_types=1);

/**
 * Menu do site público no celular (SiteHeader — resources/js/Components/Site/SiteHeader.tsx).
 *
 * Medido em produção em 2026-11-15, a 375×812: abaixo de `md` o <nav> do topo fica com
 * display:none e não havia botão de menu — Preços, Ajuda e Contato só pelo rodapé.
 *
 * O teste mede o que o browser resolveu (getBoundingClientRect / getComputedStyle), nunca a
 * classe CSS: botão visível, painel fechado por padrão, links visíveis depois do toque,
 * alvos de toque com pelo menos 44×44 px (WCAG 2.5.5), Esc fecha.
 *
 * Página-alvo: /login — usa o SiteLayout (mesmo SiteHeader da home e de /pricing) e não
 * depende de dado de tenant.
 */
beforeEach(function () {
    // Mesmo realinhamento do AuthBridgeSmokeTest: o servidor do browser roda no processo do
    // teste, que o phpunit.xml põe em sqlite :memory: sem schema — e o /login lê `system`.
    // Aponta para o MySQL `oimpresso_test` (schema-squash) que o job já sobe.
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

function siteMenuMedida(): string
{
    return <<<'JS'
        () => {
            const vis = (el) => {
                if (!el) return false;
                const s = getComputedStyle(el), r = el.getBoundingClientRect();
                return s.display !== 'none' && s.visibility !== 'hidden' && r.width > 0 && r.height > 0;
            };
            const botao = document.querySelector('button[aria-controls="menu-site-mobile"]');
            const painel = document.getElementById('menu-site-mobile');
            const alvos = painel ? [...painel.querySelectorAll('a')].filter(vis) : [];
            const rb = botao ? botao.getBoundingClientRect() : null;
            return JSON.stringify({
                botaoVisivel: vis(botao),
                botaoW: rb ? Math.round(rb.width) : 0,
                botaoH: rb ? Math.round(rb.height) : 0,
                expandido: botao ? botao.getAttribute('aria-expanded') : null,
                painelVisivel: vis(painel),
                hrefs: alvos.map((a) => a.getAttribute('href')),
                menorAlvo: alvos.length ? Math.min(...alvos.map((a) => Math.round(a.getBoundingClientRect().height))) : 0,
            });
        }
    JS;
}

it('no celular (375x812) o menu abre com Preços, Ajuda e Contato, com alvos de toque de 44px', function () {
    $page = visit('/login')->resize(375, 812);

    $fechado = json_decode((string) $page->script(siteMenuMedida()), true);
    expect($fechado['botaoVisivel'])->toBeTrue()
        ->and($fechado['botaoW'])->toBeGreaterThanOrEqual(44)
        ->and($fechado['botaoH'])->toBeGreaterThanOrEqual(44)
        ->and($fechado['expandido'])->toBe('false')
        ->and($fechado['painelVisivel'])->toBeFalse();

    $page->click('button[aria-controls="menu-site-mobile"]');

    $aberto = json_decode((string) $page->script(siteMenuMedida()), true);
    expect($aberto['expandido'])->toBe('true')
        ->and($aberto['painelVisivel'])->toBeTrue()
        ->and($aberto['hrefs'])->toContain('/pricing')
        ->and($aberto['hrefs'])->toContain('/ajuda')
        ->and($aberto['hrefs'])->toContain('/c/contact-us')
        ->and($aberto['hrefs'])->toContain('/login')
        ->and($aberto['menorAlvo'])->toBeGreaterThanOrEqual(44);
});

it('no celular o Esc fecha o menu', function () {
    $page = visit('/login')->resize(375, 812);
    $page->click('button[aria-controls="menu-site-mobile"]');
    $page->script('() => document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }))');

    $depois = json_decode((string) $page->script(siteMenuMedida()), true);
    expect($depois['expandido'])->toBe('false')
        ->and($depois['painelVisivel'])->toBeFalse();
});

it('no desktop (1280) o botão de menu não aparece e os links do topo sim', function () {
    $page = visit('/login')->resize(1280, 800);

    $r = json_decode((string) $page->script(<<<'JS'
        () => {
            const b = document.querySelector('button[aria-controls="menu-site-mobile"]');
            const nav = document.querySelector('header nav[aria-label="Navegação principal"]');
            return JSON.stringify({
                botao: b ? getComputedStyle(b).display : 'ausente',
                nav: nav ? getComputedStyle(nav).display : 'ausente',
            });
        }
    JS), true);

    expect($r['botao'])->toBe('none')
        ->and($r['nav'])->not->toBe('none')
        ->and($r['nav'])->not->toBe('ausente');
});
