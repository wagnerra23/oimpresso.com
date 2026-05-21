<?php

declare(strict_types=1);

// Onda Final.A — Contact picker header Show
// Teste estrutural: garante componente existe + Show.tsx integra + Controller injeta dropdown.

test('ContactPicker.tsx — estrutura mínima componente', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/ContactPicker.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('export default function ContactPicker')
        ->toContain('data-testid="contact-picker-trigger"')
        ->toContain('data-testid="contact-picker-search"')
        ->toContain('data-testid="contact-picker-dropdown"')
        ->toContain('data-testid="contact-picker-empty"')
        ->toContain('router.visit')
        ->not->toContain(': any');
});

test('ContactPicker.tsx — busca + cap 50 + handle current', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/ContactPicker.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('useMemo')
        ->toContain('toLowerCase')
        ->toContain('.slice(0, 50)')
        ->toContain('isCurrent');
});

test('Cliente/Show.tsx — integra ContactPicker no header', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/Show.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("import ContactPicker")
        ->toContain('<ContactPicker')
        ->toContain('contact_dropdown')
        ->toContain('data="contact_dropdown"');
});

test('ContactController — Show injeta contact_dropdown defer com scope business_id', function () {
    $path = __DIR__ . '/../../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'contact_dropdown' => Inertia::defer")
        ->toContain("contacts.business_id")
        ->toContain("'customer', 'both'");
});
