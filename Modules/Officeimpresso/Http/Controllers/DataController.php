<?php

namespace Modules\Officeimpresso\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
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

    public function modifyAdminMenu()
    {
        if (! auth()->check()) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->dropdown(
                __('officeimpresso::lang.officeimpresso_module'),
                function ($sub) {
                    $sub->url(
                        url('officeimpresso/licenca_computador'),
                        __('officeimpresso::lang.licencas'),
                        ['icon' => 'fa fas fa-laptop', 'active' => request()->segment(2) == 'licenca_computador']
                    );
                    $sub->url(
                        url('officeimpresso/computadores'),
                        __('officeimpresso::lang.computadores'),
                        ['icon' => 'fa fas fa-desktop', 'active' => request()->segment(2) == 'computadores']
                    );
                    $sub->url(
                        url('officeimpresso/businessall'),
                        __('officeimpresso::lang.clients'),
                        ['icon' => 'fa fas fa-users', 'active' => request()->segment(2) == 'businessall']
                    );
                },
                ['icon' => 'fa fas fa-key', 'active' => request()->segment(1) == 'officeimpresso']
            )->order(2);
        });
    }
}
