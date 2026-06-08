<?php

/**
 * US-FIN-036 (Onda 30) — PWA Financeiro
 *
 * Valida que manifest + sw + icons estão presentes em public/ com forma canônica.
 * Static assets servidos direto pelo web server (LiteSpeed/Apache/Nginx) — não
 * passam por roteamento Laravel; teste valida no FS + parse semântico.
 */

it('manifest.webmanifest existe em public/ com forma canônica', function () {
    $path = public_path('manifest.webmanifest');
    expect(file_exists($path))->toBeTrue('manifest.webmanifest deve existir em public/');

    $raw = file_get_contents($path);
    expect($raw)->not->toBeFalse();

    $manifest = json_decode($raw, true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE, 'manifest deve ser JSON válido');
    expect($manifest)->toBeArray();

    // Campos obrigatórios W3C Web App Manifest
    foreach (['name', 'short_name', 'start_url', 'display', 'icons'] as $field) {
        expect($manifest)->toHaveKey($field, "manifest deve ter '$field'");
    }

    expect($manifest['name'])->toBe('oimpresso Financeiro');
    expect($manifest['short_name'])->toBe('Financeiro');
    expect($manifest['start_url'])->toBe('/financeiro/unificado');
    expect($manifest['display'])->toBe('standalone');
    expect($manifest['theme_color'])->toBe('#1c1917'); // stone-900
    expect($manifest['background_color'])->toBe('#fafaf9'); // stone-50
    expect($manifest['lang'])->toBe('pt-BR');
});

it('manifest tem 3 icons (192, 512, 512 maskable)', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['icons'])->toBeArray()->toHaveCount(3);

    $sizes = array_map(fn ($icon) => $icon['sizes'], $manifest['icons']);
    expect($sizes)->toContain('192x192');
    expect($sizes)->toContain('512x512');

    // Deve ter pelo menos 1 maskable
    $purposes = array_map(fn ($icon) => $icon['purpose'] ?? 'any', $manifest['icons']);
    expect($purposes)->toContain('maskable');

    // Todos arquivos de icon existem em public/
    foreach ($manifest['icons'] as $icon) {
        $iconPath = public_path(ltrim($icon['src'], '/'));
        expect(file_exists($iconPath))->toBeTrue(
            "icon '{$icon['src']}' deve existir em public/"
        );
    }
});

it('service worker sw-financeiro.js existe em public/ com sanitização obrigatória', function () {
    $path = public_path('sw-financeiro.js');
    expect(file_exists($path))->toBeTrue('sw-financeiro.js deve existir em public/');

    $sw = file_get_contents($path);
    expect($sw)->not->toBeFalse();

    // Sanidade: cache versionado
    expect($sw)->toContain("'financeiro-v1'");

    // Tier 0 — rotas write nunca cacheadas (LGPD + segurança)
    expect($sw)->toContain('/baixar');
    expect($sw)->toContain('/aprovar');
    expect($sw)->toContain('/rejeitar');
    expect($sw)->toContain('/solicitar-aprovacao');
    expect($sw)->toContain('/conciliacao/upload');

    // Sanitização: cache-control private bloqueia put, set-cookie idem
    expect($sw)->toContain('cache-control');
    expect($sw)->toContain('private');
    expect($sw)->toContain('set-cookie');

    // Lifecycle moderno (skipWaiting + clients.claim)
    expect($sw)->toContain('skipWaiting');
    expect($sw)->toContain('clients.claim');
});

it('app.tsx registra service worker condicional a /financeiro/*', function () {
    $appTsx = file_get_contents(resource_path('js/app.tsx'));

    expect($appTsx)->toContain("serviceWorker' in navigator");
    expect($appTsx)->toContain('/sw-financeiro.js');
    expect($appTsx)->toContain("startsWith('/financeiro')");
});

it('PwaInstallBanner component existe e detecta standalone + dismiss 30d', function () {
    $banner = file_get_contents(resource_path('js/Components/shared/PwaInstallBanner.tsx'));

    expect($banner)->toContain('beforeinstallprompt');
    expect($banner)->toContain('display-mode: standalone');
    expect($banner)->toContain("startsWith('/financeiro')");
    expect($banner)->toContain('Instalar app');
    expect($banner)->toContain('Mais tarde');
    expect($banner)->toContain('pwa_install_banner_dismissed_until');
});
