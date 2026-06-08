<?php

declare(strict_types=1);

/**
 * Wagner 2026-06-08 — "exclusão de contato pela tela, precisa".
 *
 * Excluir contato pelo menu ⋮ da listagem (/cliente) via soft delete
 * DELETE /contacts/{id} (ContactController::destroy). O backend é a fonte de
 * verdade das travas; o front só confirma (AlertDialog) e trata {success,msg}.
 *
 * Estratégia structural file_get_contents (alinhado ClienteDrawerRowsCanonBrPayloadTest).
 *
 * Refs: ADR 0093 (multi-tenant Tier 0), ADR 0179 (drawer 760), charter v9.
 */

// ─── GUARD 1: backend expõe is_default no select + payload ──────────────────
// Sem is_default no payload, o front não consegue esconder "Excluir" do
// consumidor/fornecedor padrão (walk-in) → no-op confuso (destroy protege mas
// o usuário clica e "some" sem feedback claro).

test('GUARD 1 — buildClienteIndexCustomers select + payload incluem is_default', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain("'contacts.is_default'")
        ->toMatch("/'is_default'\\s*=>\\s*\\(bool\\)\\s*\\\$contact->is_default/");
});

// ─── GUARD 2: permissions prop do Inertia inclui `delete` ───────────────────
// Gate do "Excluir": customer.delete || supplier.delete (cobre aba Fornecedores).

test('GUARD 2 — permissions prop inclui delete (customer.delete || supplier.delete)', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toMatch("/'delete'\\s*=>\\s*auth\\(\\)->user\\(\\)->can\\('customer\\.delete'\\)\\s*\\|\\|\\s*auth\\(\\)->user\\(\\)->can\\('supplier\\.delete'\\)/");
});

// ─── GUARD 3: destroy() mantém as travas Tier 0 — sem regressão ─────────────
// (1) escopo business_id · (2) bloqueia se houver transação · (3) protege
// is_default · (4) ActivityLog LGPD · (5) desabilita login associado.

test('GUARD 3 — destroy() preserva travas (business_id + transação + is_default + ActivityLog)', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        // permissão exigida
        ->toContain("'supplier.delete'")
        ->toContain("'customer.delete'")
        // bloqueio por transação (venda/compra/OS)
        ->toContain("Transaction::where('business_id', \$business_id)")
        ->toContain("->where('contact_id', \$id)")
        ->toContain('if ($count == 0)')
        // escopo Tier 0 + proteção walk-in
        ->toContain("Contact::where('business_id', \$business_id)->findOrFail(\$id)")
        ->toContain('if (! $contact->is_default)')
        // auditoria LGPD + corte de login
        ->toContain("'contact_deleted'")
        ->toContain("->update(['allow_login' => 0])")
        ->toContain('$contact->delete()');
});

// ─── GUARD 4: Index.tsx — ClienteRow declara is_default ─────────────────────

test('GUARD 4 — Cliente/Index.tsx ClienteRow declara is_default', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('is_default?: boolean | null')
        // permissions prop tipada com delete
        ->toContain('delete: boolean');
});

// ─── GUARD 5: ActionsMenu — "Excluir" gated por canDelete && !is_default ────

test('GUARD 5 — ActionsMenu Excluir gated por canDelete e esconde is_default', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('canDelete: boolean')
        ->toContain('const showDelete = canDelete && !row.is_default')
        ->toContain('canDelete={props.permissions.delete}')
        ->toContain('onDelete={() => setDeleteTarget(row)}')
        // o item só renderiza quando showDelete
        ->toContain('{showDelete && (')
        ->toContain('Excluir');
});

// ─── GUARD 6: fluxo de exclusão — DELETE + CSRF + AJAX + confirmação + toast ─

test('GUARD 6 — confirmDelete usa DELETE /contacts/{id} com CSRF + AlertDialog + toast', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // endpoint canon + método + headers AJAX (destroy() só responde isLegacyAjax)
        ->toContain('/contacts/${deleteTarget.id}')
        ->toContain("method: 'DELETE'")
        ->toContain("'X-CSRF-TOKEN'")
        ->toContain("'X-Requested-With': 'XMLHttpRequest'")
        // confirmação destrutiva nunca em 1 clique
        ->toContain('<AlertDialog')
        ->toContain('Excluir contato?')
        // feedback + reload da lista pós-sucesso
        ->toContain('toast.success')
        ->toContain('toast.error')
        ->toContain("router.reload({ only: ['customers', 'kpis', 'tab_counts']");
});
