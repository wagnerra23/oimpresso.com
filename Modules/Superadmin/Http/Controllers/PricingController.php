<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Superadmin\Entities\Package;

class PricingController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        // PR2: hidratar Site/Pricing.tsx com $packages reais do DB
        // (atualmente os tiers estão hardcoded em PricingTiers.tsx pra acelerar a entrega)
        $packages = Package::listPackages(true);
        $permissions = $this->moduleUtil->getModuleData('superadmin_package');
        $permission_formatted = [];
        foreach ($permissions as $permission) {
            foreach ($permission as $details) {
                $permission_formatted[$details['name']] = $details['label'];
            }
        }

        return Inertia::render('Site/Pricing', [
            'packages' => $packages,
            'permissions' => $permission_formatted,
        ]);
    }

    /**
     * Versão Blade legada do /pricing (template UltimatePOS roxo "estilo 2010").
     * Mantida em /pricing/old durante a transição — remover após validação.
     */
    public function indexLegacy()
    {
        $packages = Package::listPackages(true);
        $permissions = $this->moduleUtil->getModuleData('superadmin_package');
        $permission_formatted = [];
        foreach ($permissions as $permission) {
            foreach ($permission as $details) {
                $permission_formatted[$details['name']] = $details['label'];
            }
        }

        return view('superadmin::pricing.index')
            ->with(compact('packages', 'permission_formatted'));
    }
}
