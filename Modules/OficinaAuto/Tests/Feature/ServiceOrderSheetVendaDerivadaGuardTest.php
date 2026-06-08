<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Pest GUARDs estruturais — ADR 0192 · Integração Vendas × Oficina (OficinaAuto).
 *
 * Rodam SEMPRE (não dependem de schema DB · sobrevivem a refactor).
 * Garantem que VendaDerivadaCard shared existe + OficinaAuto está cabeado.
 *
 * Pareado com ServiceOrderSheetVendaDerivadaTest.php (feature tests · skipam em SQLite).
 *
 * Refs:
 *  - resources/js/Components/shared/VendaDerivadaCard.tsx (shared cross-módulo)
 *  - resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderSheet.tsx (consumidor)
 *  - Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php (backend payload)
 *  - memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 */

const SHARED_VENDA_CARD_TSX = 'resources/js/Components/shared/VendaDerivadaCard.tsx';
const OFICINA_SHEET_TSX = 'resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderSheet.tsx';
const OFICINA_CONTROLLER_PHP = 'Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php';

function vendaDerivadaGuardReadFile(string $relativePath): string
{
    // __DIR__ resolve relativo ao próprio teste — funciona em worktree OU root
    // (base_path() resolveria pro root sempre, quebrando lookup em worktrees novas).
    $worktreeRoot = realpath(__DIR__.'/../../../../');
    return file_get_contents($worktreeRoot.DIRECTORY_SEPARATOR.$relativePath);
}

it('GUARD: VendaDerivadaCard shared existe + exporta interfaces canon ADR 0192', function () {
    $src = vendaDerivadaGuardReadFile(SHARED_VENDA_CARD_TSX);
    expect($src)
        ->toContain('export interface VendaItem {')
        ->toContain('export interface VendaItemsSummary {')
        ->toContain('export interface VendaFiscal {')
        ->toContain('export interface VendaDerivada {')
        ->toContain('export default function VendaDerivadaCard(');
});

it('GUARD: OficinaAuto ServiceOrderSheet importa VendaDerivadaCard do shared', function () {
    $src = vendaDerivadaGuardReadFile(OFICINA_SHEET_TSX);
    expect($src)
        ->toContain("from '@/Components/shared/VendaDerivadaCard'")
        ->toContain('venda_derivada?: VendaDerivada | null;')
        ->toContain('{data.venda_derivada && <VendaDerivadaCard venda={data.venda_derivada} />}');
});

it('GUARD: OficinaAuto ServiceOrderController eager-loads transaction + entrega venda_derivada', function () {
    $src = vendaDerivadaGuardReadFile(OFICINA_CONTROLLER_PHP);
    expect($src)
        ->toContain("'transaction:id,business_id,invoice_no,final_total,transaction_date'")
        ->toContain("'venda_derivada' => \$order->transaction")
        ->toContain('private function shapeVendaDerivada(\\App\\Transaction $t): array')
        ->toContain("'invoice_no'       => (string) (\$t->invoice_no ?? \$t->id)");
});
