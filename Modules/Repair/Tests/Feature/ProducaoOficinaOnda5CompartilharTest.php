<?php

declare(strict_types=1);

/**
 * Pest — Onda 5 follow-up (Worker 3 · 2026-05-25) · ADR 0192
 * Integração Vendas × Oficina — botão "Compartilhar" no drawer card.
 *
 * GUARD estrutural sobre `resources/js/Pages/Repair/ProducaoOficina/Index.tsx`
 * que garante que o handler `handleCompartilhar` permanece funcional e NÃO
 * regride pra placeholder no-op em refactors futuros.
 *
 * Implementação canônica:
 *  - Web Share API nativa (`navigator.share`) com `canShare` guard (Safari iOS quirk)
 *  - Fallback `navigator.clipboard.writeText()` + toast Sonner (pattern projeto)
 *  - `AbortError` tratado silenciosamente (user cancelou share-sheet)
 *  - Botão NÃO tem `title="Em breve"` (placeholder removido)
 *  - Botão tem `aria-label="Compartilhar venda V-NNNN"` (acessibilidade)
 *
 * Refs:
 *  - resources/js/Pages/Repair/ProducaoOficina/Index.tsx (VendaDerivadaCard)
 *  - resources/js/Pages/Repair/ProducaoOficina/Index.charter.md (Goals)
 *  - memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 *  - memory/requisitos/Repair/ProducaoOficina-r2-drawer-card-venda-derivada-visual-comparison.md
 */

uses(Tests\TestCase::class);

const PRODUCAO_OFICINA_INDEX_TSX = 'resources/js/Pages/Repair/ProducaoOficina/Index.tsx';

function ondaCincoCompartilharReadIndex(): string
{
    return file_get_contents(base_path(PRODUCAO_OFICINA_INDEX_TSX));
}

it('Onda 5 share: Index.tsx importa toast do sonner (pattern projeto)', function () {
    $src = ondaCincoCompartilharReadIndex();
    expect($src)->toContain("import { toast } from 'sonner';");
});

it('Onda 5 share: handleCompartilhar é async + usa Web Share API nativa', function () {
    $src = ondaCincoCompartilharReadIndex();
    expect($src)
        ->toContain('const handleCompartilhar = async () =>')
        ->toContain('navigator.share')
        ->toContain('navigator.canShare');
});

it('Onda 5 share: payload share inclui invoice_no + total BRL + url /sells/{id}', function () {
    $src = ondaCincoCompartilharReadIndex();
    expect($src)
        ->toContain('`${window.location.origin}/sells/${venda.id}`')
        ->toContain('`Venda #${venda.invoice_no}')
        ->toContain("style: 'currency'")
        ->toContain("currency: 'BRL'");
});

it('Onda 5 share: AbortError (user cancelou share-sheet) NÃO loga erro nem mostra toast', function () {
    $src = ondaCincoCompartilharReadIndex();
    expect($src)
        ->toContain("name === 'AbortError'")
        ->toContain("'AbortError') return");
});

it('Onda 5 share: fallback usa navigator.clipboard.writeText + toast.success', function () {
    $src = ondaCincoCompartilharReadIndex();
    expect($src)
        ->toContain('navigator.clipboard.writeText')
        ->toContain("toast.success('Link da venda copiado')");
});

it('Onda 5 share: fallback erro clipboard mostra toast.error gracioso', function () {
    $src = ondaCincoCompartilharReadIndex();
    expect($src)->toContain("toast.error('Não foi possível copiar o link')");
});

it('Onda 5 share: botão Compartilhar tem onClick={handleCompartilhar} (não no-op)', function () {
    $src = ondaCincoCompartilharReadIndex();
    expect($src)->toContain('onClick={handleCompartilhar}');
});

it('Onda 5 share: botão Compartilhar perdeu title="Em breve" placeholder', function () {
    $src = ondaCincoCompartilharReadIndex();
    expect($src)->not->toContain('title="Em breve · backlog wave futura"');
});

it('Onda 5 share: botão Compartilhar tem aria-label acessibilidade', function () {
    $src = ondaCincoCompartilharReadIndex();
    expect($src)->toContain('aria-label={`Compartilhar venda ${venda.invoice_no}`}');
});
