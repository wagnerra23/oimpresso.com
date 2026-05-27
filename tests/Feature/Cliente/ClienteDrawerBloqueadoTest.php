<?php

declare(strict_types=1);

/**
 * ADR 0195 Bucket A — campo `bloqueado` integrado no drawer 760 (Classificacao tab).
 *
 * Demonstracao do agente cliente-drawer-integrar — Wagner 2026-05-27.
 *
 * Estrategia: structural file_get_contents (alinhada ClienteDrawerRowsCanonBrPayloadTest).
 *
 * Refs:
 *   - ADR 0195 (extensao contacts pra absorver PESSOAS legacy)
 *   - ADR 0179 (drawer 760)
 *   - Migration 2026_05_27_120000_extend_contacts_bucket_a_legacy_absorption
 *   - Contact.php cast `'bloqueado' => 'bool'` (linha 76)
 */

// ─── GUARD 1: Backend validator + shape ───────────────────────────────────

test('GUARD 1 — ClienteAutosaveController classificacao validator + shape com bloqueado', function () {
    $path = __DIR__ . '/../../../Modules/Crm/Http/Controllers/ClienteAutosaveController.php';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        // Validator aceita campo.
        ->toContain("'bloqueado' => ['nullable', 'boolean']")
        // shapeContactResponse retorna campo cast bool.
        ->toMatch("/'bloqueado'\\s*=>\\s*\\(bool\\)\\s*\\(\\\$contact->bloqueado/");
});

// ─── GUARD 2: Payload buildClienteIndexCustomers ──────────────────────────

test('GUARD 2 — ContactController buildClienteIndexCustomers select + payload com bloqueado graceful', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        // hasColumn graceful pra ambiente pre-migration Wave 2026-05-27.
        ->toContain("Schema::hasColumn('contacts', 'bloqueado')")
        ->toContain("'contacts.bloqueado'")
        ->toMatch("/\\\$payload\\['bloqueado'\\]\\s*=/");
});

// ─── GUARD 3: Cliente/Index.tsx ClienteRow declara bloqueado ──────────────

test('GUARD 3 — Cliente/Index.tsx ClienteRow declara bloqueado', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('bloqueado?: boolean | null');
});

// ─── GUARD 4: ClassificacaoTab — interface + state + JSX toggle ───────────

test('GUARD 4 — ClassificacaoTab.tsx tem interface + useState + handler + JSX toggle', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/ClassificacaoTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // Interface declara campo.
        ->toContain('bloqueado?: boolean | null')
        // useState inicializa do contact prop.
        ->toContain('useState<boolean>(Boolean(contact.bloqueado))')
        // useEffect resync ao mudar contact.id.
        ->toContain('setBloqueado(Boolean(contact.bloqueado))')
        // Handler dispara performSave.
        ->toContain('handleBloqueadoToggle')
        ->toContain("performSave('bloqueado'")
        // JSX toggle com aria-label semantico.
        ->toContain('Bloquear cobrança/venda')
        ->toContain('id="cl-bloqueado"')
        // Rollback case.
        ->toContain("field === 'bloqueado'");
});

// ─── GUARD 5: Cast Eloquent (regressao guard) ─────────────────────────────

test('GUARD 5 — Contact.php tem cast bloqueado bool (anti-regressao)', function () {
    $path = __DIR__ . '/../../../app/Contact.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'bloqueado' => 'bool'");
});
