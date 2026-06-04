<?php

declare(strict_types=1);

/**
 * Pest test estrutural (test-first) — Sells/Create.tsx campo "nota interna da
 * equipe" (staff_note) separado da nota do cliente (additional_notes).
 *
 * FEATURE: staff-note no Create (paridade com Edit.tsx).
 *
 *   Hoje o Create.tsx só tem `additional_notes` (a nota que vira `sale_note`
 *   e aparece no recibo do cliente). O Edit.tsx já tem o campo separado
 *   `staff_note` — "Nota interna (equipe)" — observação visível só pra equipe,
 *   que NÃO aparece no recibo. O Create deve ganhar paridade.
 *
 * ESTADO-ALVO (o "pronto"), espelhado literalmente do Edit.tsx:
 *   1. Form default `staff_note: ''` no useForm.
 *   2. Label htmlFor="staff_note" com texto "Nota interna (equipe)".
 *   3. <Textarea id="staff_note"> wired com setData('staff_note', ...).
 *   4. Placeholder deixando claro que NÃO aparece no recibo.
 *   5. staff_note enviado no payload do submit (separado de additional_notes).
 *
 * Backend: TransactionUtil já persiste `staff_note` (usado pelo Edit).
 *
 * test-first: enquanto a feature NÃO existir no Create.tsx, estes it() ficam
 * VERMELHOS. Passam quando o campo for implementado. Multi-tenant não se
 * aplica (teste de source estático, não toca Model/query) — sem biz envolvido.
 */

const CREATE_PATH_STAFF_NOTE = 'resources/js/Pages/Sells/Create.tsx';
const EDIT_PATH_STAFF_NOTE = 'resources/js/Pages/Sells/Edit.tsx';

function readCreateStaffNote(): string
{
    return file_get_contents(base_path(CREATE_PATH_STAFF_NOTE));
}

function readEditStaffNote(): string
{
    return file_get_contents(base_path(EDIT_PATH_STAFF_NOTE));
}

// ─── Sanidade: a referência (Edit.tsx) já tem a feature ──────────────────────

it('referência: Edit.tsx já tem staff_note implementado (espelho do alvo)', function () {
    // Guard — se o Edit perder o staff_note, o "alvo" deste teste deixa de existir.
    $edit = readEditStaffNote();
    expect($edit)->toContain('staff_note');
    expect($edit)->toContain('Nota interna (equipe)');
});

// ─── Estado-alvo no Create.tsx (VERMELHO até implementar) ─────────────────────

it('Create.tsx declara staff_note nos defaults do useForm', function () {
    // Espelha Edit.tsx linha `staff_note: ''`.
    $src = readCreateStaffNote();
    expect($src)->toMatch("/staff_note:\s*''/");
});

it('Create.tsx tem Label "Nota interna (equipe)" com htmlFor="staff_note"', function () {
    // Texto e htmlFor literais do Edit.tsx — nota da EQUIPE, não do cliente.
    $src = readCreateStaffNote();
    expect($src)->toContain('htmlFor="staff_note"');
    expect($src)->toContain('Nota interna (equipe)');
});

it('Create.tsx tem Textarea id="staff_note" wired com setData', function () {
    // Campo separado de additional_notes, editável via setData('staff_note', ...).
    $src = readCreateStaffNote();
    expect($src)->toContain('id="staff_note"');
    expect($src)->toMatch("/setData\(\s*'staff_note'/");
});

it('Create.tsx placeholder do staff_note deixa claro que não vai pro recibo', function () {
    // Distingue staff_note (interna) da additional_notes (cliente/recibo).
    $src = readCreateStaffNote();
    expect($src)->toMatch('/placeholder=.*recibo/i');
});

it('Create.tsx envia staff_note no payload do submit (separado de additional_notes)', function () {
    // No build do payload, staff_note deve ir junto — sem reaproveitar d.notes.
    $src = readCreateStaffNote();
    expect($src)->toContain('staff_note: d.staff_note');
});

it('Create.tsx mantém staff_note distinto de additional_notes (não é alias)', function () {
    // Anti-regressão: additional_notes continua vindo de d.notes; staff_note tem
    // a própria origem (d.staff_note), provando que são dois campos separados.
    $src = readCreateStaffNote();
    expect($src)->toContain('additional_notes: d.notes');
    expect($src)->not->toMatch("/staff_note:\s*d\.notes/");
});
