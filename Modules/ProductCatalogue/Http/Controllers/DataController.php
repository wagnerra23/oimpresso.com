<?php

namespace Modules\ProductCatalogue\Http\Controllers;

use Illuminate\Routing\Controller;

class DataController extends Controller
{
    /**
     * Defines module as a superadmin package.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'productcatalogue_module',
                'label' => __('productcatalogue::lang.productcatalogue_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no UI de Roles do UltimatePOS.
     *
     * Adicionado 2026-04-26 (audit DataController) pra permitir
     * delegação granular do acesso ao catálogo QR.
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'productcatalogue.access',
                'label' => __('productcatalogue::lang.productcatalogue_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Adds Catalogue QR menus — Wagner 2026-05-26: NO-OP.
     *
     * Catálogo QR NÃO é mais entry própria do sidebar. Virou GHOST do hub
     * Vendas (definido em app/Http/Middleware/AdminSidebarMenu.php dropdown
     * __('sale.sale'), ghost key='catalogue-qr' → /product-catalogue/catalogue-qr).
     *
     * Justificativa Wagner 2026-05-26:
     *  - Catálogo QR é um canal de venda (storefront público escaneado pelo
     *    cliente), conceitualmente subordinado ao hub Vendas — não entry
     *    paralela top-level no grupo COMERCIAL.
     *  - Acesso via PageHeader overflow [...] da tela /sells, ou URL direta,
     *    ou Cmd+K palette.
     *
     * Rotas /product-catalogue/catalogue-qr + /catalogue/{biz}/{loc} +
     * /show-catalogue/* CONTINUAM ativas — apenas a injeção sidebar foi
     * desligada.
     *
     * Histórico (ADR 0180 Fase 4 Wave A VENDER → 2026-05-26):
     *   2026-05-21 — Onda Wave A: declarou primary + ghosts próprios
     *   2026-05-26 — DESLIGADO sidebar (vira ghost de Vendas)
     */
    public function modifyAdminMenu()
    {
        // No-op intencional. Ver docblock acima.
    }
}
