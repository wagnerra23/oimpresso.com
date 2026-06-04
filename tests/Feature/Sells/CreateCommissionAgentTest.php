<?php

declare(strict_types=1);

/**
 * Pest test estrutural (test-first) — Sells/Create.tsx deve RENDERIZAR o select
 * de comissionado/responsável em PARIDADE com Edit.tsx.
 *
 * Feature: commission-agent na tela de VENDA (Create).
 *
 * ESTADO HOJE (vermelho):
 *   - O state já tem `commission_agent_id` (linha ~189) mas a UI canônica de
 *     paridade NÃO aparece como no Edit.tsx.
 *   - O único select existente no Create está GATED por `hasCommissionAgent`
 *     (`{hasCommissionAgent && (...)}`, linha ~1485) e lê de `props.commissionAgents`
 *     em vez da lista de `users` que o Edit usa. Logo, quando não há agentes
 *     cadastrados o campo some — diferente do Edit, que sempre mostra o select
 *     "Responsável / comissionado" alimentado por `users`.
 *
 * ESTADO-ALVO (pronto = verde):
 *   Create.tsx renderiza o select de responsável/comissionado espelhando o Edit.tsx:
 *     - prop `users: OptionMap` (lista de usuários) — MESMO nome do Edit
 *     - <Label htmlFor="commission_agent"> "Responsável / comissionado"
 *     - SelectTrigger id="commission_agent" + aria-label "Responsável / comissionado"
 *     - itera os `users` pra montar os <SelectItem>
 *     - RENDERIZADO sem ficar escondido atrás de gate de lista vazia
 *       (`hasCommissionAgent` só com agentes cadastrados)
 *
 * Referência espelhada: resources/js/Pages/Sells/Edit.tsx (linhas ~899-920).
 *
 * Estilo: estrutural — lê o source com file_get_contents + expect()->toContain/toMatch,
 * igual a SaleSheetComponentTest.php e CustomerAutoApplyOnSelectTest.php.
 *
 * Tier 0: smoke/dados sempre biz=1 (ADR 0101) — aqui é teste estrutural de source,
 * não toca DB nem business_id, mas a regra fica registrada pra quem evoluir.
 */

const CREATE_PATH_CA = 'resources/js/Pages/Sells/Create.tsx';
const EDIT_PATH_CA = 'resources/js/Pages/Sells/Edit.tsx';

function readCreateCA(): string
{
    return file_get_contents(base_path(CREATE_PATH_CA));
}

function readEditCA(): string
{
    return file_get_contents(base_path(EDIT_PATH_CA));
}

// === Sanidade da referência (Edit) — deve passar HOJE (verde) ===

it('REF — Edit.tsx renderiza o select commission_agent com label Responsável / comissionado', function () {
    $src = readEditCA();
    expect($src)->toContain('htmlFor="commission_agent"');
    expect($src)->toContain('Responsável / comissionado');
});

// === Alvo no Create — VERMELHO hoje (feature ausente / fora de paridade) ===

it('ALVO — Create.tsx tem o Label "Responsável / comissionado" do select', function () {
    $src = readCreateCA();
    expect($src)->toContain('Responsável / comissionado');
});

it('ALVO — Create.tsx tem <Label htmlFor="commission_agent"> (mesmo id do Edit)', function () {
    $src = readCreateCA();
    expect($src)->toContain('htmlFor="commission_agent"');
});

it('ALVO — Create.tsx tem SelectTrigger id="commission_agent" (não só commission_agent_id)', function () {
    $src = readCreateCA();
    expect($src)->toMatch('/<SelectTrigger[^>]*id="commission_agent"/');
});

it('ALVO — Create.tsx aplica aria-label "Responsável / comissionado" no trigger', function () {
    $src = readCreateCA();
    expect($src)->toContain('aria-label="Responsável / comissionado"');
});

it('ALVO — o select de responsável é alimentado por users (não por commissionAgents)', function () {
    $src = readCreateCA();
    // Recorta o bloco do select de responsável (do Label até o fechamento próximo)
    // e exige que a fonte de dados seja `users`, igual ao Edit. Hoje o Create
    // alimenta o select por `props.commissionAgents` → vermelho.
    $start = strpos($src, 'htmlFor="commission_agent"');
    expect($start)->not->toBeFalse();
    $body = substr((string) $src, (int) $start, 700);
    expect($body)->toMatch('/\.users\b/');
    expect($body)->not->toContain('commissionAgents');
});

it('ALVO — Create.tsx itera users pra montar os SelectItem do responsável', function () {
    $src = readCreateCA();
    // Espelha o Edit: Object.entries((form.users ?? {}) ...).map(([id, name]) => <SelectItem ...>)
    expect($src)->toMatch('/Object\.entries\(\s*\(?\s*(props\.users|form\.users|users)/');
});

it('ALVO — o select de comissionado NÃO fica escondido atrás de gate de lista vazia (hasCommissionAgent)', function () {
    $src = readCreateCA();
    // No estado-alvo (paridade Edit) o campo sempre aparece. O gate atual
    // `{hasCommissionAgent && (` esconde o campo quando não há agentes — deve sumir.
    expect($src)->not->toContain('{hasCommissionAgent && (');
});
