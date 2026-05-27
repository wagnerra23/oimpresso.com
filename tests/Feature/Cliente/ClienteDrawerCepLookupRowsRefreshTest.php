<?php

declare(strict_types=1);

/**
 * Wagner 2026-05-27 -- bug Endereco lookup CEP NAO ATUALIZAVA drawer ao reabrir.
 *
 * Sintoma: user clica Buscar CEP → 5 PATCHes 200 OK → fecha drawer → reabre
 * mesmo cliente → campos voltam aos VELHOS (logradouro, complemento, bairro,
 * cidade ainda dados pre-lookup).
 *
 * Raiz: EnderecoTab nao tinha `onContactUpdated` callback (IdentificacaoTab
 * tinha). Backend persistia OK (resposta PATCH com dados novos) mas
 * `rows.find()` Inertia cache mantinha snapshot velho.
 *
 * Fix: EnderecoTab aceita prop `onContactUpdated` + acumula campos do lookup
 * em batch + chama callback ao final → parent atualiza draftContact direto.
 *
 * GUARDs estruturais (file_get_contents, sem DB).
 */

// ─── GUARD 1: EnderecoTab declara onContactUpdated ────────────────────────

test('GUARD 1 — EnderecoTab interface declara onContactUpdated callback', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('onContactUpdated?: (patched: Record<string, unknown>) => void')
        // Destructured no componente principal.
        ->toContain('export default function EnderecoTab({ contact, onSaved, onContactUpdated');
});

// ─── GUARD 2: handleCepLookup acumula contactPatch + chama callback ──────

test('GUARD 2 — handleCepLookup acumula contactPatch batch e chama onContactUpdated ao final', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // Cada campo persistido tambem entra no batch contactPatch.
        ->toContain('contactPatch.address_line_1 = novoLogr')
        ->toContain('contactPatch.address_line_2 = novoComplemento')
        ->toContain('contactPatch.neighborhood = novoBairro')
        ->toContain('contactPatch.city = novaCidade')
        ->toContain('contactPatch.state = novaUf')
        // Callback chamado ao final (so se houver patch).
        ->toContain('onContactUpdated?.(contactPatch)');
});

// ─── GUARD 3: Index.tsx repassa onContactUpdated pro EnderecoTab ─────────

test('GUARD 3 — Cliente/Index.tsx repassa onContactUpdated pro EnderecoTab', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toMatch("/<EnderecoTab\\s+key=\\{enderecoVersion\\}\\s+contact=\\{contact\\}\\s+onContactUpdated=\\{onContactUpdated\\}/");
});
