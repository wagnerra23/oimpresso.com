<?php

declare(strict_types=1);

/**
 * A gaveta de 760px do /cliente monta dentro do `.cockpit` (prop `container` do `SheetContent`).
 *
 * Sem `container`, o portal do Radix monta no <body>, fora do `.cockpit`, onde moram a fonte e os
 * tokens do Design System. Medido em produção em 2026-11-21 (empresa 1, tema escuro, `design-diff`
 * nos dois lados): a gaveta saía na fonte do sistema (Segoe UI) no lugar da IBM Plex Sans e com 7
 * tokens do DS vazios. O conserto é o da gaveta da Fabricação (lápide §5 2026-10-19).
 *
 * Este arquivo é separado de propósito: o `ClienteIndexDrawer760CharterTest`, onde estes asserts
 * caberiam, está na quarentena da lane do Cliente (usa RefreshDatabase) e não roda em PR.
 *
 * O que este teste NÃO prova: o efeito no navegador (fonte e tokens). Nem o PHP nem o jsdom resolvem
 * `var(--…)` de folha de estilo; o efeito se mede depois do deploy com `design-diff --check-drawer`.
 */

test('a gaveta da lista de clientes monta o portal no .cockpit', function () {
    $tsx = file_get_contents(__DIR__.'/../../../resources/js/Pages/Cliente/Index.tsx');

    // O contêiner é o `.cockpit` da página, lido em layout effect (aberta na 1ª renderização por
    // `?contact_id=`, a gaveta já nasce no lugar certo).
    expect($tsx)
        ->toContain("useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), [])")
        // ... e chega no SheetContent da gaveta (o único `container={cockpit}` do arquivo).
        ->toContain('container={cockpit}');

    expect(substr_count($tsx, 'container={cockpit}'))->toBe(1);

    // A âncora da gaveta e o contêiner ficam no MESMO SheetContent: entre os dois só cabe o `side`.
    $ancora = strpos($tsx, 'data-contract="drawer-de-detalhe"');
    $container = strpos($tsx, 'container={cockpit}');
    expect($ancora)->not->toBeFalse()
        ->and($container)->toBeGreaterThan($ancora)
        ->and($container - $ancora)->toBeLessThan(80);
});
