<?php

namespace Modules\Officeimpresso\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

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
                'name' => 'officeimpresso_module',
                'label' => __('officeimpresso::lang.officeimpresso_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Adds Catalogue QR menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_officeimpresso_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'officeimpresso_module', 'superadmin_package');

        if ($is_officeimpresso_enabled) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                        action([\Modules\Officeimpresso\Http\Controllers\OfficeimpressoController::class, 'generateQr']),
                        __('officeimpresso::lang.catalogue_qr'),
                        ['icon' => 'fa fas fa-qrcode', 'active' => request()->segment(1) == 'product-catalogue', 'style' => config('app.env') == 'demo' ? 'background-color: #ff851b;' : '']
                    )
                ->order(95);
            });
        }
    }
}
