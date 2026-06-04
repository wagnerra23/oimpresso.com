<?php

declare(strict_types=1);

/**
 * Pest test estrutural (test-first) — Sells/Create.tsx checkbox "assinatura
 * recorrente" (is_recurring), paridade com Sells/Edit.tsx.
 *
 * CRITÉRIO DE PRONTO desta feature:
 *   O formulário de CRIAÇÃO de venda (Create.tsx) deve ter o MESMO checkbox
 *   is_recurring que já existe no formulário de EDIÇÃO (Edit.tsx) —
 *   "Assinatura recorrente" (Blade legacy `is_recurring`). Marca a venda como
 *   recorrente (gera próxima fatura automática).
 *
 * Espelha nomes EXATOS do Edit.tsx (lido na escrita deste teste):
 *   - estado useForm: `is_recurring: 0 as 0 | 1`
 *   - <Checkbox id="is_recurring" checked={data.is_recurring === 1} ...>
 *   - onCheckedChange={(c) => setData('is_recurring', c === true ? 1 : 0)}
 *   - <Label htmlFor="is_recurring">Assinatura recorrente</Label>
 *   - submit serializa `is_recurring: data.is_recurring ? 1 : 0`
 *   - tipo do form: `is_recurring: 0 | 1`
 *
 * ESTADO HOJE: VERMELHO. Create.tsx não tem nenhuma ocorrência de is_recurring
 * (verificado na escrita). Os it() abaixo falham até a feature ser implementada
 * e passam quando o checkbox de paridade for adicionado ao Create.tsx.
 *
 * Estilo estrutural canon (igual SaleSheetComponentTest + CustomerAutoApplyOnSelectTest):
 *   lê o source com file_get_contents(base_path(...)) + expect()->toContain/toMatch.
 *
 * Regras duras: biz=1 (ADR 0101) — teste estrutural não toca DB/tenant.
 */

const CREATE_PATH_IS_REC = 'resources/js/Pages/Sells/Create.tsx';

function readCreateIsRecurring(): string
{
    return file_get_contents(base_path(CREATE_PATH_IS_REC));
}

// === Estado do formulário (useForm) ===

it('is_recurring — Create.tsx declara campo is_recurring no estado useForm (default 0)', function () {
    $src = readCreateIsRecurring();
    expect($src)->toContain('is_recurring');
    // Default desmarcado, mesmo tipo do Edit.tsx: `is_recurring: 0 as 0 | 1`.
    expect($src)->toMatch('/is_recurring:\s*0\s+as\s+0\s*\|\s*1/');
});

it('is_recurring — tipo do form expõe is_recurring: 0 | 1 (paridade Edit.tsx)', function () {
    $src = readCreateIsRecurring();
    expect($src)->toMatch('/is_recurring:\s*0\s*\|\s*1/');
});

// === Checkbox no JSX (mesmos nomes/props do Edit.tsx) ===

it('is_recurring — Create.tsx renderiza <Checkbox id="is_recurring">', function () {
    $src = readCreateIsRecurring();
    expect($src)->toContain('id="is_recurring"');
    expect($src)->toContain('checked={data.is_recurring === 1}');
});

it('is_recurring — checkbox usa onCheckedChange setData 0/1 (igual Edit.tsx)', function () {
    $src = readCreateIsRecurring();
    expect($src)->toMatch(
        "/onCheckedChange=\\{\\(c\\)\\s*=>\\s*setData\\('is_recurring',\\s*c\\s*===\\s*true\\s*\\?\\s*1\\s*:\\s*0\\)\\}/"
    );
});

it('is_recurring — Label htmlFor="is_recurring" com texto "Assinatura recorrente"', function () {
    $src = readCreateIsRecurring();
    expect($src)->toContain('htmlFor="is_recurring"');
    expect($src)->toContain('Assinatura recorrente');
});

// === Serialização no submit (backend espera is_recurring 0/1) ===

it('is_recurring — submit serializa is_recurring como 1/0 (backend Blade legacy)', function () {
    $src = readCreateIsRecurring();
    expect($src)->toMatch('/is_recurring:\s*data\.is_recurring\s*\?\s*1\s*:\s*0/');
});
