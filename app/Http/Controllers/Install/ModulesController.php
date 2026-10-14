<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Utils\ModuleUtil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Module;
use ZipArchive;
use Illuminate\Support\Facades\Artisan;

class ModulesController extends Controller
{
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ModuleUtil  $moduleUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * /manage-modules — o caminho antigo do gerenciador. A tela AdminLTE saiu (playbook
     * modulos/04, PR-8); a tela viva é /modulos (ModuleManagementController).
     *
     * A rota GET FICA, como redirecionamento, porque os InstallController de módulo voltam
     * para cá por `action([ModulesController::class, 'index'])` depois de instalar ou
     * desinstalar (contado em 2026-10-14: trait ConfirmaInstalacaoPorPost,
     * install-module.blade.php e 6 InstallController em Modules/*). Sem esta rota, `action()`
     * lança e o install termina em 500. O `reflash()` leva o `status` que o instalador gravou
     * até /modulos, que é onde ele é mostrado.
     */
    public function index(): RedirectResponse
    {
        session()->reflash();

        return redirect()->route('modules.index', [], 301);
    }

    /**
     * A URL tem rota POST registrada, servida por um InstallController de módulo?
     * Pergunta ao router (não a uma lista à mão): 405/404 ou rota de outro controller = false.
     */
    public static function aceitaPost(string $url): bool
    {
        if ($url === '' || $url === '#') {
            return false;
        }

        try {
            $rota = app('router')->getRoutes()->match(Request::create($url, 'POST'));
        } catch (\Throwable $e) {
            return false;
        }

        $controller = ltrim(\Illuminate\Support\Str::before($rota->getActionName(), '@'), '\\');

        return ! $rota->isFallback
            && str_starts_with($controller, 'Modules\\')
            && str_ends_with($controller, '\\InstallController');
    }

    public function regenerate()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            Artisan::call('module:publish');
            Artisan::call('passport:install --force');
            // Artisan::call('scribe:generate');

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            $output = ['success' => 1,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * Upload the module.
     */
    public function uploadModule(Request $request)
    {
        $notAllowed = $this->moduleUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {

            //get zipped file
            $module = $request->file('module');
            $module_name = $module->getClientOriginalName();
            $module_name = str_replace('.zip', '', $module_name);

            //check if uploaded file is valid or not and and if not redirect back
            if ($module->getMimeType() != 'application/zip') {
                $output = ['success' => false,
                    'msg' => __('lang_v1.pls_upload_valid_zip_file'),
                ];

                return redirect()->back()->with(['status' => $output]);
            }

            //check if 'Modules' folder exist or not, if not exist create
            $path = '../Modules';
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }

            //extract the zipped file in given path
            $zip = new ZipArchive();
            if ($zip->open($module) === true) {
                $zip->extractTo($path.'/');
                $zip->close();

                //Needs improvement

                // if(!(file_exists($path . '/' . $module_name . '/composer.json')
                //     && file_exists($path . '/' . $module_name . '/module.json')
                //     && file_exists($path . '/' . $module_name . '/Config/config.php'))){
                //         \File::deleteDirectory($path . '/' . $module_name);
                // }
            }

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (Exception $e) {
            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }
}
