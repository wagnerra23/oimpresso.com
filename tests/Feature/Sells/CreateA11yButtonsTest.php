<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Pages/Sells/Create.tsx (feature "a11y-buttons").
 *
 * CRITÉRIO DE PRONTO:
 *   As PILLS de filtro de seção (Dados / Produtos / Pagamento / Resumo / Mais opções)
 *   E o BOTÃO DE REMOVER PRODUTO (linha do carrinho) DEVEM usar o <Button> do shadcn
 *   (@/Components/ui/button) — NÃO <button> HTML cru.
 *
 *   Motivo a11y: <Button> shadcn carrega focus-visible ring + estados de foco
 *   consistentes; <button> cru perde o focus ring/aria do design system, prejudicando
 *   navegação por teclado e leitores de tela.
 *
 * Estilo: teste estrutural (lê o source via file_get_contents + expect->toContain/toMatch),
 *   igual a SaleSheetComponentTest.php e CustomerAutoApplyOnSelectTest.php.
 *
 * Estado-alvo = "pronto": pills e remover via <Button> shadcn; ZERO <button> cru no arquivo.
 * Multi-tenant: teste de source puro — não toca Model/query (sem business_id envolvido).
 * Smoke real fica pro CT 100 (ADR 0101 biz=1). Aqui só travamos a estrutura do source.
 */

const A11Y_PAGE_PATH = 'resources/js/Pages/Sells/Create.tsx';

function readA11yPage(): string
{
    return file_get_contents(base_path(A11Y_PAGE_PATH));
}

// === Pré-condição: import do Button shadcn ===

it('a11y-buttons — Create.tsx importa Button do shadcn (@/Components/ui/button)', function () {
    $src = readA11yPage();
    expect($src)->toContain("import { Button } from '@/Components/ui/button'");
});

// === Pills de filtro de seção usam <Button> shadcn ===

it('a11y-buttons — as pills de seção usam <Button> shadcn (não <button> cru)', function () {
    $src = readA11yPage();

    // Recorta a região do <nav> de pills (aria-label="Seções do cadastro")
    $navStart = strpos($src, 'aria-label="Seções do cadastro"');
    expect($navStart)->not->toBeFalse();
    // Volta pro início da tag <nav> e pega ~1600 chars (cobre o .map das pills)
    $regionStart = strrpos(substr($src, 0, $navStart), '<nav');
    $region = substr($src, $regionStart, 1600);

    // A região renderiza <Button ...> (shadcn) pra cada aba.
    expect($region)->toContain('<Button');
    // E NÃO usa <button> HTML cru dentro do map de pills.
    expect($region)->not->toMatch('/<button[\s>]/');
});

it('a11y-buttons — pill ativa usa variant=default e inativa variant=ghost (estado de foco shadcn)', function () {
    $src = readA11yPage();
    $navStart = strpos($src, 'aria-label="Seções do cadastro"');
    $region = substr($src, $navStart, 1400);

    expect($region)->toMatch("/variant=\\{isActive \\? 'default' : 'ghost'\\}/");
});

it('a11y-buttons — pill marca aria-current quando ativa (acessibilidade de navegação)', function () {
    $src = readA11yPage();
    $navStart = strpos($src, 'aria-label="Seções do cadastro"');
    $region = substr($src, $navStart, 1400);

    expect($region)->toContain('aria-current');
});

// === Botão de remover produto usa <Button> shadcn ===

it('a11y-buttons — o botão de remover produto usa <Button> shadcn (não <button> cru)', function () {
    $src = readA11yPage();

    // Recorta a região em torno do handler de remoção da linha de produto.
    $hitStart = strpos($src, 'onClick={() => handleRemoveProduct(idx)}');
    expect($hitStart)->not->toBeFalse();
    // Pega contexto antes+depois pra cobrir a abertura da tag.
    $regionStart = max(0, $hitStart - 300);
    $region = substr($src, $regionStart, 600);

    // O remover é um <Button> shadcn (variant ghost / size icon), não <button> cru.
    expect($region)->toContain('<Button');
    expect($region)->not->toMatch('/<button[\s>]/');
});

it('a11y-buttons — remover produto tem aria-label descritivo (leitor de tela) + ícone Trash2', function () {
    $src = readA11yPage();
    $hitStart = strpos($src, 'onClick={() => handleRemoveProduct(idx)}');
    $region = substr($src, max(0, $hitStart - 300), 600);

    expect($region)->toMatch('/aria-label=\{`Remover \$\{p\.name\}`\}/');
    expect($region)->toContain('Trash2');
});

// === Anti-regressão global: nenhum <button> HTML cru no arquivo inteiro ===

it('a11y-buttons — Create.tsx NÃO contém nenhum <button> HTML cru (tudo via <Button> shadcn)', function () {
    $src = readA11yPage();

    // Bloqueia abertura de tag <button ...> ou <button> cru em qualquer ponto do arquivo.
    // <Button> (PascalCase, shadcn) NÃO casa com este padrão (regex é case-sensitive
    // e exige o 'b' minúsculo logo após '<').
    expect($src)->not->toMatch('/<button[\s>\/]/');
});
