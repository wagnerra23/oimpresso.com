<?php

declare(strict_types=1);

/**
 * Regressão Wagner 2026-06-08 — cadastro de empresa (cliente PJ) dava
 * "erro 500" ao salvar o CNPJ.
 *
 * Causa raiz: unique key `uk_contacts_biz_tax` (business_id + tax_number) cobre
 * inclusive linhas SOFT-DELETED. Wagner excluiu um contato e tentou cadastrar
 * outro com o mesmo CNPJ -> a linha arquivada ainda "segura" o CNPJ ->
 * `updateAndRespond()` fazia `$contact->update()` sem try/catch ->
 * `UniqueConstraintViolationException` (1062) virava 500 cru, sem dizer ao
 * usuário que já existe cadastro.
 *
 * Fix:
 *  1. Pre-check em identificacao() com withTrashed() — acha o holder mesmo
 *     arquivado e devolve 422 com mensagem clara (chave `doc` p/ o
 *     IdentificacaoTab exibir sob o campo + `tax_number` canon).
 *  2. Backstop try/catch em updateAndRespond() — nunca mais 500 cru.
 *
 * GUARDs estruturais (file_get_contents, sem DB) — mesmo padrão do
 * ClienteAutosaveAliasesPtBrTest.php.
 */

function readClienteAutosaveSource(): string
{
    return file_get_contents(
        __DIR__ . '/../../../Modules/Crm/Http/Controllers/ClienteAutosaveController.php'
    );
}

// ─── GUARD 1: pre-check de unicidade com withTrashed ──────────────────────

test('GUARD 1 — identificacao() faz pre-check de CNPJ duplicado com withTrashed()', function () {
    $src = readClienteAutosaveSource();

    expect($src)
        ->toContain('withTrashed()')
        ->toContain("->where('tax_number', \$validated['tax_number'])")
        // escopo Tier 0 — só o mesmo business (ADR 0093)
        ->toContain("->where('business_id', \$contact->business_id)")
        // não confunde o próprio contato com duplicata
        ->toContain("->where('id', '!=', \$contact->id)");
});

// ─── GUARD 2: mensagem distingue arquivado de ativo ───────────────────────

test('GUARD 2 — mensagem diferencia cadastro arquivado/excluído de ativo', function () {
    $src = readClienteAutosaveSource();

    expect($src)
        ->toContain('$existing->trashed()')
        ->toContain('arquivado/excluído com este CPF/CNPJ')
        ->toContain('Restaure o cadastro existente');
});

// ─── GUARD 3: erro devolvido sob `doc` (frontend) E `tax_number` (canon) ───

test('GUARD 3 — erro 422 sai sob doc + tax_number (IdentificacaoTab lê errors[doc])', function () {
    $src = readClienteAutosaveSource();

    // o pre-check devolve as duas chaves
    expect($src)->toMatch("/'errors' => \\['doc' => \\[\\\$msg\\], 'tax_number' => \\[\\\$msg\\]\\]/");
});

// ─── GUARD 4: backstop try/catch no save (anti-500 cru) ───────────────────

test('GUARD 4 — updateAndRespond() captura UniqueConstraintViolationException', function () {
    $src = readClienteAutosaveSource();

    // bloco do método
    preg_match('/private function updateAndRespond\(.*?(?=\n    (?:private|public|protected) function )/s', $src, $m);
    $block = $m[0] ?? '';

    expect($block)
        ->toContain('try {')
        ->toContain('$contact->update($data);')
        ->toContain('catch (\Illuminate\Database\UniqueConstraintViolationException $e)')
        ->toContain('Já existe um cadastro com este CPF/CNPJ neste negócio.');
});
