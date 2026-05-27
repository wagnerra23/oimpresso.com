<?php

namespace Modules\Woocommerce\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

class DataController extends Controller
{
    public function dummy_data()
    {
        Artisan::call('db:seed', ['--class' => 'Modules\Woocommerce\Database\Seeders\AddDummySyncLogTableSeeder']);
    }

    public function superadmin_package()
    {
        return [
            [
                'name' => 'woocommerce_module',
                'label' => __('woocommerce::lang.woocommerce_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Defines user permissions for the module.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'woocommerce.syc_categories',
                'label' => __('woocommerce::lang.sync_product_categories'),
                'default' => false,
            ],
            [
                'value' => 'woocommerce.sync_products',
                'label' => __('woocommerce::lang.sync_products'),
                'default' => false,
            ],
            [
                'value' => 'woocommerce.sync_orders',
                'label' => __('woocommerce::lang.sync_orders'),
                'default' => false,
            ],
            [
                'value' => 'woocommerce.map_tax_rates',
                'label' => __('woocommerce::lang.map_tax_rates'),
                'default' => false,
            ],
            [
                'value' => 'woocommerce.access_woocommerce_api_settings',
                'label' => __('woocommerce::lang.access_woocommerce_api_settings'),
                'default' => false,
            ],

        ];
    }

    /**
     * Parses notification message from database.
     *
     * @return array
     */
    public function parse_notification($notification)
    {
        $notification_data = [];
        if ($notification->type ==
            'Modules\Woocommerce\Notifications\SyncOrdersNotification') {
            $msg = __('woocommerce::lang.orders_sync_notification');

            $notification_data = [
                'msg' => $msg,
                'icon_class' => 'fas fa-sync bg-light-blue',
                'link' => action([\App\Http\Controllers\SellController::class, 'index']),
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at->diffForHumans(),
            ];
        }

        return $notification_data;
    }

    /**
     * Returns product form part path with required extra data.
     *
     * @return array
     */
    public function product_form_part()
    {
        $path = 'woocommerce::woocommerce.partials.product_form_part';

        $business_id = request()->session()->get('user.business_id');

        $module_util = new ModuleUtil();
        $is_woo_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'woocommerce_module', 'superadmin_package');
        if ($is_woo_enabled) {
            return  [
                'template_path' => $path,
                'template_data' => [],
            ];
        } else {
            return [];
        }
    }

    /**
     * Returns products table extra columns for this module
     *
     * @return array
     */
    public function product_form_fields()
    {
        return ['woocommerce_disable_sync'];
    }

    /**
     * Adds Woocommerce menus — Wagner 2026-05-26: NO-OP.
     *
     * WooCommerce NÃO é mais entry própria do sidebar. Virou GHOST do hub
     * Vendas (definido em app/Http/Middleware/AdminSidebarMenu.php dropdown
     * __('sale.sale'), ghost key='woocommerce' → /woocommerce).
     *
     * Justificativa Wagner 2026-05-26:
     *  - WooCommerce é um canal de venda (sync com loja externa WordPress),
     *    conceitualmente subordinado ao hub Vendas — não entry paralela
     *    top-level no grupo COMERCIAL.
     *  - Acesso via PageHeader overflow [...] da tela /sells, ou URL direta
     *    /woocommerce, ou Cmd+K palette.
     *
     * Rotas /woocommerce/* CONTINUAM ativas (sync products/orders/categories
     * + map tax rates + API settings) — apenas a injeção sidebar foi
     * desligada. Permissions woocommerce.* continuam válidas no
     * WoocommerceController (Page render gate).
     *
     * Histórico:
     *   UltimatePOS original — entry top-level order 88 grupo COMERCIAL
     *   2026-05-26 — DESLIGADO sidebar (vira ghost de Vendas)
     */
    public function modifyAdminMenu()
    {
        // No-op intencional. Ver docblock acima.
    }
}
