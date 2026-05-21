<?php

declare(strict_types=1);

// Onda Final.C — Tab Pessoas de contato
// Teste estrutural: componente + integração + scope business_id + crm_contact_id.

test('PessoasContatoTab.tsx — estrutura mínima componente', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/PessoasContatoTab.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('export default function PessoasContatoTab')
        ->toContain('data-testid="persons-tab-root"')
        ->toContain('data-testid="persons-tab-empty"')
        ->toContain('data-testid="persons-tab-skeleton"')
        ->not->toContain(': any');
});

test('PessoasContatoTab.tsx — 5 colunas + Adicionar pessoa', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/PessoasContatoTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('>Nome<')
        ->toContain('>Usuário<')
        ->toContain('>E-mail<')
        ->toContain('>Departamento<')
        ->toContain('>Cargo<')
        ->toContain('Adicionar pessoa')
        ->toContain('/crm/contact-login/create?contact_id=');
});

test('Cliente/Show.tsx — integra PessoasContatoTab como 6ª tab', function () {
    $path = __DIR__ . '/../../../../resources/js/Pages/Cliente/Show.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("import PessoasContatoTab")
        ->toContain("'persons'")
        ->toContain("label: 'Pessoas'")
        ->toContain('<PessoasContatoTab')
        ->toContain('data="contact_persons"');
});

test('ContactController — Show injeta contact_persons defer scope business_id + crm_contact_id', function () {
    $path = __DIR__ . '/../../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'contact_persons' => Inertia::defer")
        ->toContain("crm_contact_id")
        ->toContain("'crm_department'")
        ->toContain("'crm_designation'");
});
