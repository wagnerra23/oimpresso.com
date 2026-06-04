<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Sells/Create.tsx confirmação ao Cancelar com carrinho
 * cheio (feature "cancel-confirm").
 *
 * Dor: o botão "Cancelar" hoje chama `router.visit('/sells')` direto (linha ~1629),
 *   saindo da tela e PERDENDO tudo o que já foi montado no carrinho (itens/notas)
 *   sem avisar. Larissa monta venda longa ao telefone — um clique errado em
 *   Cancelar joga fora o trabalho sem pergunta.
 *
 * Estado-alvo (o "pronto"):
 *   1. Novo handler `handleCancelClick` decide:
 *      - se `data.products.length > 0` → abre AlertDialog de confirmação
 *        (NÃO navega direto)
 *      - se carrinho vazio → navega direto pra /sells (sem fricção)
 *   2. Estado `cancelConfirm` (useState boolean) controla a abertura do dialog.
 *   3. AlertDialog shadcn (mesmo pattern do draft-recover já existente nesta tela)
 *      com 2 ações: ficar na tela (AlertDialogCancel) e descartar+sair
 *      (AlertDialogAction → router.visit('/sells')).
 *   4. O botão Cancelar do footer passa a usar `handleCancelClick`, NÃO mais o
 *      inline `() => router.visit('/sells')`.
 *
 * Como a feature ainda NÃO foi implementada, os it() abaixo ficam VERMELHOS agora
 * (test-first / TDD). Passam quando a feature existir.
 *
 * Padrão estrutural (igual SaleSheetComponentTest + CustomerAutoApplyOnSelectTest):
 *   lê o source com file_get_contents(base_path(...)) e faz expect()->toContain/toMatch.
 *   Não toca DB — mas vale a regra Tier 0: smoke real roda biz=1 (ADR 0101), nunca
 *   biz=4 (cliente ROTA LIVRE).
 */

const PAGE_PATH_CANCEL = 'resources/js/Pages/Sells/Create.tsx';

function readPageCancel(): string
{
    return file_get_contents(base_path(PAGE_PATH_CANCEL));
}

// === Handler de cancelar ===

it('cancel-confirm — Create.tsx tem handler handleCancelClick', function () {
    $src = readPageCancel();
    expect($src)->toContain('handleCancelClick');
    expect($src)->toMatch('/const\s+handleCancelClick\s*=/');
});

it('cancel-confirm — handleCancelClick checa data.products.length > 0', function () {
    $src = readPageCancel();
    $start = strpos($src, 'const handleCancelClick');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 700);
    expect($body)->toMatch('/data\.products\.length\s*>\s*0/');
});

it('cancel-confirm — handleCancelClick abre confirmação quando há itens (set state)', function () {
    $src = readPageCancel();
    $start = strpos($src, 'const handleCancelClick');
    $body = substr($src, $start, 700);
    // abre o dialog em vez de navegar direto
    expect($body)->toContain('setCancelConfirm(true)');
});

it('cancel-confirm — handleCancelClick navega direto pra /sells quando carrinho vazio', function () {
    $src = readPageCancel();
    $start = strpos($src, 'const handleCancelClick');
    $body = substr($src, $start, 700);
    expect($body)->toMatch("/router\.visit\(['\"]\/sells['\"]\)/");
});

// === Estado do dialog ===

it('cancel-confirm — Create.tsx declara estado cancelConfirm useState boolean', function () {
    $src = readPageCancel();
    expect($src)->toContain('cancelConfirm');
    expect($src)->toContain('setCancelConfirm');
    expect($src)->toMatch('/useState(<boolean>)?\(false\)/');
});

// === AlertDialog de confirmação ===

it('cancel-confirm — existe AlertDialog controlado por cancelConfirm', function () {
    $src = readPageCancel();
    expect($src)->toMatch('/<AlertDialog\s+open=\{cancelConfirm\}/');
    expect($src)->toContain('onOpenChange={setCancelConfirm}');
});

it('cancel-confirm — dialog tem ação destrutiva que descarta e sai pra /sells', function () {
    $src = readPageCancel();
    $start = strpos($src, 'open={cancelConfirm}');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 900);
    expect($body)->toContain('AlertDialogAction');
    expect($body)->toMatch("/router\.visit\(['\"]\/sells['\"]\)/");
});

it('cancel-confirm — dialog tem opção de ficar na tela (AlertDialogCancel)', function () {
    $src = readPageCancel();
    $start = strpos($src, 'open={cancelConfirm}');
    $body = substr($src, $start, 900);
    expect($body)->toContain('AlertDialogCancel');
});

// === Wiring no botão do footer ===

it('cancel-confirm — botão Cancelar do footer usa handleCancelClick', function () {
    $src = readPageCancel();
    expect($src)->toContain('onClick={handleCancelClick}');
});

it('cancel-confirm — botão Cancelar NÃO navega mais inline direto pra /sells', function () {
    $src = readPageCancel();
    // versão antiga inline (perde o carrinho sem perguntar) deve sumir
    expect($src)->not->toMatch("/onClick=\{\(\)\s*=>\s*router\.visit\(['\"]\/sells['\"]\)\}/");
});
