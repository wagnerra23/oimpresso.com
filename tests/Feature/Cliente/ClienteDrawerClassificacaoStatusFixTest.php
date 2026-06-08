<?php

declare(strict_types=1);

/**
 * Wagner 2026-05-27 -- bug Classificacao Status select VAZIO mesmo apos
 * PATCH bem-sucedido.
 *
 * Bateria exaustiva descobriu: backend retornava `contact_status:"active"`
 * mas drawer mostrava dropdown vazio. Mismatch dupla:
 *   1. Chave: ClassificacaoTab lia `contact.status` (alias legacy PT-BR),
 *      backend ContactController payload rows envia `contact_status` (canon EN UPOS)
 *   2. Valor enum: STATUS_OPTIONS tinha PT-BR `'ativo'/'inativo'/'bloqueado'`,
 *      backend persistiu EN `'active'/'inactive'/'blocked'`
 *
 * Plus: handleStatusChange enviava `performSave('status', ...)`. Validator
 * backend so aceita `contact_status` -> silent no-op (mesmo bug aliases PT-BR
 * que afetou Daniela #1773).
 *
 * Fix:
 *   - useState init: `normalizeStatus(contact.contact_status ?? contact.status) || 'active'`
 *   - useEffect resync: idem
 *   - STATUS_OPTIONS canon EN (active/inactive/blocked)
 *   - normalizeStatus map alias PT-BR -> EN (cadastros pre-canon)
 *   - handleStatusChange: performSave('contact_status', v, prev) -- canon backend
 *
 * GUARDs estruturais file_get_contents.
 */

// ─── GUARD 1: ContactInfo aceita contact_status canon EN ─────────────────

test('GUARD 1 — ContactInfo declara contact_status (canon EN) + status (alias legado)', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/ClassificacaoTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // Tipo aceita ambos EN + PT-BR (alias).
        ->toContain("status?: 'ativo' | 'inativo' | 'bloqueado' | 'active' | 'inactive' | 'blocked'")
        ->toContain("contact_status?: 'active' | 'inactive' | 'blocked'");
});

// ─── GUARD 2: STATUS_OPTIONS usa canon EN ────────────────────────────────

test('GUARD 2 — STATUS_OPTIONS usa canon EN (active/inactive/blocked)', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/ClassificacaoTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("value: 'active', label: 'Ativo'")
        ->toContain("value: 'inactive', label: 'Inativo'")
        ->toContain("value: 'blocked', label: 'Bloqueado'");
});

// ─── GUARD 3: normalizeStatus mapeia PT-BR alias -> EN canon ─────────────

test('GUARD 3 — normalizeStatus function mapeia alias PT-BR -> EN canon', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/ClassificacaoTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('function normalizeStatus')
        ->toContain('ativo: \'active\'')
        ->toContain('inativo: \'inactive\'')
        ->toContain('bloqueado: \'blocked\'');
});

// ─── GUARD 4: useState init + useEffect resync usam normalizeStatus ──────

test('GUARD 4 — useState/useEffect leem contact_status (com fallback status) normalizado', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/ClassificacaoTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('normalizeStatus(contact.contact_status ?? contact.status) || \'active\'');
});

// ─── GUARD 5: handleStatusChange envia chave canon contact_status ────────

test('GUARD 5 — handleStatusChange envia chave canon backend (contact_status, nao status)', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/ClassificacaoTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("performSave('contact_status', v, prev)")
        ->not->toContain("performSave('status', v, prev)");
});
