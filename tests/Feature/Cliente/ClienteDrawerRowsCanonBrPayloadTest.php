<?php

declare(strict_types=1);

/**
 * Fix 2026-05-27 (Wagner reportou drawer Identificacao sem IE/CNPJ no Acme
 * Comercio Ltda biz=1).
 *
 * IdentificacaoTab espera `contact.ie` + `contact.cpf_cnpj_masked` + `contact.rg`
 * + `contact.nascimento` + `contact.cargo` no row. ContactController::
 * buildClienteIndexCustomers nao enviava — drawer abria com placeholders
 * mesmo com dado no banco.
 *
 * Estrategia structural file_get_contents (alinhado ClienteListagemTurbinadaTest).
 *
 * Refs: ADR 0178 (canon BR restaurado pos UPOS 6.7), ADR 0179 (drawer 760).
 */

// ─── GUARD 1: select cols inclui cpf_cnpj + inscricao_estadual + rg ────────

test('GUARD 1 — buildClienteIndexCustomers select inclui canon BR fields graceful', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        // hasColumn graceful pra ambiente pre-Wave 2026-05-21.
        ->toContain("Schema::hasColumn('contacts', 'cpf_cnpj')")
        ->toContain("'contacts.cpf_cnpj'")
        ->toContain("'contacts.inscricao_estadual'")
        ->toContain("'contacts.rg'")
        // Wave drawer 2026-05-22 — nascimento + cargo.
        ->toContain("Schema::hasColumn('contacts', 'cargo')")
        ->toContain("'contacts.nascimento'")
        ->toContain("'contacts.cargo'");
});

// ─── GUARD 2: payload row tem chaves drawer-friendly ──────────────────────

test('GUARD 2 — buildClienteIndexCustomers payload tem cpf_cnpj_masked + ie + rg + nascimento + cargo', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        // PII: cpf_cnpj_masked sempre passa por maskTaxNumber (LGPD).
        ->toMatch("/\\\$payload\\['cpf_cnpj_masked'\\]\\s*=\\s*\\\$this->maskTaxNumber/")
        // Fallback canon -> legacy UPOS pra cadastros pre-Wave 2026-05-21.
        ->toContain('cpf_cnpj ?? null')
        ->toContain('?? $contact->tax_number')
        // ie + rg + nascimento + cargo escapam o mask (sao livres, nao PII numerica).
        ->toMatch("/\\\$payload\\['ie'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['rg'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['nascimento'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['cargo'\\]\\s*=/");
});

// ─── GUARD 3: contrato frontend nao quebrou — IdentificacaoTab ainda le essas chaves

test('GUARD 3 — IdentificacaoTab.tsx ContactInfo declara ie + cpf_cnpj_masked', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('cpf_cnpj_masked?: string | null')
        ->toContain('ie?: string | null')
        ->toContain('rg?: string | null')
        ->toContain('nascimento?: string | null')
        ->toContain('cargo?: string | null')
        // useState inicializa do contact prop (autosave debounce).
        ->toContain('useState<string>(contact.ie ??')
        ->toContain('useState<string>(contact.cpf_cnpj_masked ??');
});

// ─── GUARD 4: Index.tsx ContactRow declara as chaves (contrato shared) ─────

test('GUARD 4 — Cliente/Index.tsx ClienteRow declara ie + cpf_cnpj_masked + rg + nascimento + cargo', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('cpf_cnpj_masked?: string | null')
        ->toContain('ie?: string | null')
        ->toContain('rg?: string | null')
        ->toContain('nascimento?: string | null')
        ->toContain('cargo?: string | null');
});
