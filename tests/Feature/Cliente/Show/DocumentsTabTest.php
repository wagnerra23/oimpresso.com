<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de canon-source (DocumentsTab.tsx estrutura/endpoints) contra fonte-da-verdade móvel — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

// Wave D — US-CRM-066 Tab Documents & Note
// Restrição Tier 0 ADR 0093: DocumentAndNoteController filtra business_id global scope.
// notable_type='App\Contact' é polimórfico canon.

test('DocumentsTab.tsx — estrutura mínima componente', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('export default function DocumentsTab')
        ->toContain('data-testid="documents-tab-root"')
        ->not->toContain(': any');
});

test('DocumentsTab.tsx — upload anexo (input + endpoint canon)', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('data-testid="documents-file-input"')
        ->toContain('data-testid="documents-upload-btn"')
        ->toContain("'/post-document-upload'")
        ->toContain('multiple')
        ->toContain('X-CSRF-TOKEN');
});

test('DocumentsTab.tsx — delete anexo (DELETE polimórfico)', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    // Template literal `/note-documents/${docId}` — busca substring sem aspas
    expect($contents)
        ->toContain('handleDelete')
        ->toContain('/note-documents/')
        ->toContain('_method')
        ->toContain('DELETE')
        ->toContain('notable_id')
        ->toContain('notable_type');
});

test('DocumentsTab.tsx — notable_type App\\Contact polimórfico', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    // notable_type='App\Contact' string em TS escaped App\\Contact
    expect($contents)
        ->toContain("'App\\\\Contact'");
});

test('DocumentsTab.tsx — textarea notes autosave 1500ms debounce', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('data-testid="notes-textarea"')
        ->toContain('data-testid="notes-heading-input"')
        ->toContain('data-testid="notes-autosave-status"')
        ->toContain('1500')
        ->toContain('autosave')
        ->toContain("'saving'")
        ->toContain("'saved'")
        ->toContain("'error'");
});

test('DocumentsTab.tsx — empty state PT-BR + permissions gate', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('Nenhum anexo. Envie comprovantes, contratos, fotos.')
        ->toContain('permissions.upload')
        ->toContain('permissions.delete_document')
        ->toContain('permissions.edit_note')
        ->toContain('data-testid="documents-empty"');
});

test('DocumentsTab.tsx — dark mode tokens', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('dark:text-rose-400')
        ->toContain('dark:text-emerald-400')
        ->toContain('bg-background')
        ->toContain('border-border');
});
