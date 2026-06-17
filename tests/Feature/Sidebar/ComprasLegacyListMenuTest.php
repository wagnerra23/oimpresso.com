<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar.

/**
 * Anti-regressão — entry legacy "Lista de compras" no sidebar.
 *
 * Bug 2026-06-17 (cliente ROTA LIVRE / Guilherme): o dropdown "Compras" do
 * sidebar legacy mostrava só "Adicionar compra" + "Lista de retornos de compra",
 * sem caminho pra consulta de compras (/purchases). Causa: a remoção incondicional
 * do item em 2026-05-22 (achando que a entry canônica "Compras" → /compras já
 * substituía), mas essa entry só aparece com `compras_module` ON. Pros businesses
 * com a flag OFF, isso apagou o ÚNICO caminho de menu pra lista.
 *
 * Contrato travado: o item "Lista de compras" → /purchases existe no
 * AdminSidebarMenu, GATED por `! in_array('compras_module', $enabled_modules)`
 * (some só quando o módulo canônico está ON — espelha isModuleEnabled do
 * Modules/Compras DataController, evitando 2 "Compras" no sidebar).
 *
 * Assertion source-level (file_get_contents) seguindo o pattern de
 * Biz4RotaLivreSidebarTest — não precisa boot/DB (testes rodam no CT 100).
 *
 * Refs:
 *   - R-COM-301 (memory/requisitos/Compras/SPEC.md): flag controla VISIBILIDADE
 *     do entry, NÃO bloqueia /purchases.
 *   - app/Http/Middleware/AdminSidebarMenu.php (Purchase dropdown)
 */
describe('Anti-regressão sidebar — entry "Lista de compras" legacy', function () {
    $middleware = dirname(__DIR__, 3) . '/app/Http/Middleware/AdminSidebarMenu.php';

    it('AdminSidebarMenu re-publica "Lista de compras" → /purchases gated por compras_module', function () use ($middleware) {
        $src = file_get_contents($middleware);

        // O item existe e aponta pro PurchaseController@index (tela legacy /purchases)
        expect($src)->toContain("__('purchase.list_purchase')");
        expect($src)->toContain('\App\Http\Controllers\PurchaseController::class, \'index\'');

        // Gate canon: some SÓ quando o módulo canônico Compras está ON
        expect($src)->toContain("! in_array('compras_module', \$enabled_modules)");

        // O closure do dropdown precisa receber $enabled_modules pra avaliar o gate
        expect($src)->toContain('function ($sub) use ($common_settings, $enabled_modules)');
    });

    it('marcador da remoção antiga sumiu (não pode voltar a comentar o bloco)', function () use ($middleware) {
        $src = file_get_contents($middleware);

        // A remoção de 2026-05-22 deixava este marcador no comentário do bloco morto.
        expect($src)->not->toContain('entry "List Purchase" → /purchases REMOVIDA');
    });
});
