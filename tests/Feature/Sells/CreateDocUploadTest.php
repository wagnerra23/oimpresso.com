<?php

declare(strict_types=1);

/**
 * Pest test estrutural — paridade de upload de documento na VENDA.
 *
 * Feature "doc-upload": Sells/Create.tsx deve permitir anexar documento
 * à venda (campo `sell_document`), igual o Sells/Edit.tsx já faz.
 *
 * Hoje SÓ o Edit.tsx tem o input de upload (linhas ~955-985). O Create.tsx
 * NÃO tem nenhuma referência a `sell_document` — logo estes it() ficam
 * VERMELHOS até a feature ser implementada (test-first).
 *
 * Estado-alvo (espelha Edit.tsx exatamente):
 *   - <input type="file" id="sell_document"> com htmlFor casado
 *   - accept=".pdf,.csv,.zip,.doc,.docx,.jpg,.jpeg,.png"
 *   - guarda de 5MB: file.size > 5 * 1024 * 1024
 *   - setData('sell_document', file)
 *   - estado inicial sell_document: null no useForm
 *   - helper text "máx 5MB"
 *
 * Backend já aceita: TransactionUtil::uploadFile($request, 'sell_document', 'documents').
 * (Teste estrutural — lê o source com file_get_contents, não toca DB nem business_id.)
 */

const CREATE_PATH_DOCUP = 'resources/js/Pages/Sells/Create.tsx';
const EDIT_PATH_DOCUP = 'resources/js/Pages/Sells/Edit.tsx';

function readCreateDocUp(): string
{
    return file_get_contents(base_path(CREATE_PATH_DOCUP));
}

it('doc-upload — Create.tsx existe', function () {
    expect(file_exists(base_path(CREATE_PATH_DOCUP)))->toBeTrue();
});

// ─── Input de upload presente (paridade com Edit.tsx) ────────────────────────

it('doc-upload — Create.tsx tem input type=file id=sell_document', function () {
    $src = readCreateDocUp();
    expect($src)->toContain('type="file"');
    expect($src)->toContain('id="sell_document"');
});

it('doc-upload — Create.tsx casa Label htmlFor=sell_document com o input', function () {
    $src = readCreateDocUp();
    expect($src)->toContain('htmlFor="sell_document"');
});

it('doc-upload — Create.tsx aceita os MESMOS tipos do Edit (.pdf/.csv/.zip/.doc/.docx/.jpg/.png)', function () {
    $src = readCreateDocUp();
    expect($src)->toContain('accept=".pdf,.csv,.zip,.doc,.docx,.jpg,.jpeg,.png"');
});

// ─── Guarda de 5MB ───────────────────────────────────────────────────────────

it('doc-upload — Create.tsx valida tamanho máximo 5MB (5 * 1024 * 1024)', function () {
    $src = readCreateDocUp();
    expect($src)->toMatch('/file\.size\s*>\s*5\s*\*\s*1024\s*\*\s*1024/');
});

// ─── Wiring no useForm ───────────────────────────────────────────────────────

it('doc-upload — Create.tsx aplica o arquivo via setData(sell_document, file)', function () {
    $src = readCreateDocUp();
    expect($src)->toContain("setData('sell_document', file)");
});

it('doc-upload — Create.tsx inicializa sell_document como null no estado do form', function () {
    $src = readCreateDocUp();
    expect($src)->toMatch('/sell_document:\s*null/');
});

// ─── Helper text PT-BR (espelha Edit) ────────────────────────────────────────

it('doc-upload — Create.tsx mostra label "Anexar documento" + helper "máx 5MB"', function () {
    $src = readCreateDocUp();
    expect($src)->toContain('Anexar documento');
    expect($src)->toContain('máx 5MB');
});

// ─── Guarda anti-regressão da REFERÊNCIA (Edit.tsx mantém o campo) ────────────

it('doc-upload — referência: Edit.tsx ainda tem o input sell_document (não regrediu)', function () {
    $editSrc = file_get_contents(base_path(EDIT_PATH_DOCUP));
    expect($editSrc)->toContain('id="sell_document"');
    expect($editSrc)->toContain('accept=".pdf,.csv,.zip,.doc,.docx,.jpg,.jpeg,.png"');
});
