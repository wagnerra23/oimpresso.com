<?php

declare(strict_types=1);

it('rota /showcase/design-system está registrada', function () {
    $route = collect(\Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'showcase.design-system');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('showcase/design-system');
});

it('tokens enterprise existem em inertia.css', function () {
    $css = file_get_contents(base_path('resources/css/inertia.css'));

    expect($css)
        ->toContain('--font-sans')
        ->toContain('Inter')
        ->toContain('--text-display')
        ->toContain('--text-h1')
        ->toContain('--text-body')
        ->toContain('--text-caption')
        ->toContain('--color-success')
        ->toContain('--color-warning')
        ->toContain('--color-info')
        ->toContain('--color-surface-1')
        ->toContain('--color-surface-2')
        ->toContain('--shadow-sm')
        ->toContain('--shadow-lg')
        ->toContain('--radius-sm')
        ->toContain('--radius-xl')
        ->toContain('tabular-nums')
        ->toContain('shimmer');
});

it('dark mode preserva todos os tokens', function () {
    $css = file_get_contents(base_path('resources/css/inertia.css'));

    // dark { ... } block deve sobrescrever todos os tokens semânticos
    preg_match('/\.dark\s*\{(.*?)\n\}/s', $css, $m);

    expect($m[1] ?? '')
        ->toContain('--color-background')
        ->toContain('--color-foreground')
        ->toContain('--color-primary')
        ->toContain('--color-success')
        ->toContain('--color-surface-1');
});

it('4 novos componentes enterprise existem', function () {
    foreach (['kbd', 'spinner', 'empty', 'code-block'] as $name) {
        expect(file_exists(base_path("resources/js/Components/ui/{$name}.tsx")))
            ->toBeTrue("Componente {$name}.tsx faltando");
    }
});

it('Pricing.tsx usa novos tokens (text-display, text-body, surface-1)', function () {
    $tsx = file_get_contents(base_path('resources/js/Pages/Site/Pricing.tsx'));

    expect($tsx)
        ->toContain('var(--text-display)')
        ->toContain('text-body')
        ->toContain('bg-surface-1')
        ->toContain('shadow-xs');
});

it('CodeBlock tem botão copiar acessível', function () {
    $tsx = file_get_contents(base_path('resources/js/Components/ui/code-block.tsx'));

    expect($tsx)
        ->toContain('aria-label')
        ->toContain('Copiar')
        ->toContain('navigator.clipboard.writeText');
});

it('Spinner é acessível (role + sr-only)', function () {
    $tsx = file_get_contents(base_path('resources/js/Components/ui/spinner.tsx'));

    expect($tsx)
        ->toContain('role="status"')
        ->toContain('aria-live')
        ->toContain('sr-only');
});

it('Empty tem icon + title + description + action props', function () {
    $tsx = file_get_contents(base_path('resources/js/Components/ui/empty.tsx'));

    expect($tsx)
        ->toContain('icon?:')
        ->toContain('title?:')
        ->toContain('description?:')
        ->toContain('action?:');
});
