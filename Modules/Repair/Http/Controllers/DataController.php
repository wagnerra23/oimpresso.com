<?php

namespace Modules\Repair\Http\Controllers;

use App\Brands;
use App\Category;
use App\Utils\ModuleUtil;
use App\Warranty;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Menu;
use Modules\Repair\Concerns\TelaReactPorEmpresa;
use Modules\Repair\Entities\DeviceModel;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;
use Modules\Repair\Utils\RepairUtil;

class DataController extends Controller
{
    use TelaReactPorEmpresa;

    /**
     * Sets sell fields from module
     *
     * @param  array  $data
     * @return obj
     */
    public function after_sale_saved($data)
    {
        $transaction = $data['transaction'];
        $input = $data['input'];

        $repairUtil = new RepairUtil();

        $repair_module_fields = [
            'repair_brand_id',
            'repair_device_id',
            'repair_model_id',
            'repair_serial_no',
            'repair_security_pwd',
            'repair_security_pattern',
            'repair_defects',
            'repair_status_id',
            'repair_warranty_id',
            'repair_job_sheet_id',
        ];

        foreach ($repair_module_fields as $field) {
            if (isset($input[$field])) {
                $transaction->$field = $input[$field];
            }
        }
        if (isset($input['repair_checklist'])) {
            $transaction->repair_checklist = json_encode($input['repair_checklist']);
        }

        // As datas só mudam quando o formulário as envia. O PDV de reparo sempre envia (vazio =
        // limpar); a edição pela lista de vendas (Sells/Edit React e sell.edit Blade) não envia
        // nenhum campo de reparo — antes, salvar por ali zerava entrega e conclusão (UC-SEDIT-11).
        // uf_datetime_input aceita ISO (o que o PDV React manda) e, fora disso, o formato de
        // data da empresa (o que o PDV Blade manda). Antes era só uf_date: o React mandava
        // DD/MM fixo, que numa empresa m/d/Y trocava dia e mês ou falhava.
        if (array_key_exists('repair_completed_on', $input)) {
            $transaction->repair_completed_on = ! empty($input['repair_completed_on']) ? $repairUtil->uf_datetime_input($input['repair_completed_on']) : null;
        }
        if (array_key_exists('repair_due_date', $input)) {
            $transaction->repair_due_date = ! empty($input['repair_due_date']) ? $repairUtil->uf_datetime_input($input['repair_due_date']) : null;
        }

        $transaction->save();

        return $transaction;
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
                'value' => 'repair.create',
                'label' => __('repair::lang.add_invoice'),
                'default' => false,
            ],
            [
                'value' => 'repair.update',
                'label' => __('repair::lang.edit_invoice'),
                'default' => false,
            ],
            [
                'value' => 'repair.view',
                'label' => __('repair::lang.view_all_invoice'),
                'default' => false,
                'is_radio' => true,
                'radio_input_name' => 'repair_invoice_view',
            ],
            [
                'value' => 'repair.view_own',
                'label' => __('repair::lang.view_own_invoice'),
                'default' => false,
                'is_radio' => true,
                'radio_input_name' => 'repair_invoice_view',
            ],
            [
                'value' => 'repair.delete',
                'label' => __('repair::lang.delete_invoice'),
                'default' => false,
            ],
            [
                'value' => 'repair_status.update',
                'label' => __('repair::lang.change_invoice_status'),
                'default' => false,
            ],
            [
                'value' => 'repair_status.access',
                'label' => __('repair::lang.access_job_sheet_status'),
                'default' => false,
            ],
            [
                'value' => 'job_sheet.create',
                'label' => __('repair::lang.add_job_sheet'),
                'default' => false,
            ],
            [
                'value' => 'job_sheet.edit',
                'label' => __('repair::lang.edit_job_sheet'),
                'default' => false,
            ],
            [
                'value' => 'job_sheet.delete',
                'label' => __('repair::lang.delete_job_sheet'),
                'default' => false,
            ],
            [
                'value' => 'job_sheet.view_assigned',
                'label' => __('repair::lang.view_assigned_job_sheet'),
                'default' => false,
                'is_radio' => true,
                'radio_input_name' => 'job_sheet_view',
            ],
            [
                'value' => 'job_sheet.view_all',
                'label' => __('repair::lang.view_all_job_sheet'),
                'default' => false,
                'is_radio' => true,
                'radio_input_name' => 'job_sheet_view',
            ],

        ];
    }

    public function superadmin_package()
    {
        return [
            [
                'name' => 'repair_module',
                'label' => __('repair::lang.repair_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Abas do topo das telas da Assistência técnica (ModuleTabs) e sub-itens do menu lateral.
     *
     * [W] 2026-11-06: o sidebar e as abas mostram o mesmo. Ordem e rótulos do protótipo
     * (`repair-page.jsx`, barra "Áreas da assistência técnica"). "Portal do cliente" é o
     * ConsultaOs público (D-PORTAL, [W] 2026-10-02) e entrou como aba em 2026-11-27 — ver abaDoPortal().
     *
     * `recarrega: true` quando a tela, para esta empresa, ainda responde o Blade: o `<Link>` do
     * Inertia não abre Blade e mostrava a janela de erro com o HTML dentro (Configurações,
     * medido em produção 2026-11-27). Com `recarrega` a aba navega com link comum, como o menu
     * lateral. A decisão é a mesma do controller da tela (`mwartEnabled`), para não divergir.
     *
     * @return array<int, array{key: string, label: string, href: string, recarrega?: bool}>
     */
    public function abasDoRepair(int $business_id): array
    {
        $aba = fn (string $key, string $label, string $href, ?string $tela = null): array => ($tela === null || $this->mwartEnabled($tela, $business_id))
            ? ['key' => $key, 'label' => $label, 'href' => $href]
            : ['key' => $key, 'label' => $label, 'href' => $href, 'recarrega' => true];

        return array_values(array_filter([
            $aba('painel', 'Painel', '/repair/dashboard', 'repair_dashboard_index'),
            // Produção da oficina não tem Blade: é sempre a tela React.
            $aba('producao', 'Produção', '/repair/producao-oficina'),
            $aba('folhas', 'Folhas de OS', '/repair/job-sheet', 'repair_job_sheet_index'),
            $aba('reparos', 'Reparos', '/repair/repair', 'repair_index'),
            $aba('status', 'Status', '/repair/status', 'repair_status_index'),
            $aba('modelos', 'Modelos', '/repair/device-models', 'repair_device_models_index'),
            $this->abaDoPortal($business_id),
            $aba('config', 'Configurações', '/repair/repair-settings', 'repair_settings_index'),
        ]));
    }

    /**
     * Aba "Portal do cliente": o link público do ConsultaOs desta empresa (`/consulta-os/{codigo}`).
     *
     * Só LÊ o código: o menu monta em toda página e não pode gravar. Se a empresa ainda não tem
     * código, a aba leva à configuração da Assistência, que cria o código e mostra o link (decisão
     * [W] 2026-11-18). Sem o ConsultaOs registrado a aba não aparece. É página pública fora do
     * shell, por isso sempre `recarrega`.
     *
     * @return array{key: string, label: string, href: string, recarrega: bool}|null
     */
    private function abaDoPortal(int $business_id): ?array
    {
        // Mesmo jeito da RepairSettingsController::linkDoPortal: classe por nome, sem `use`, para a
        // Assistência seguir funcionando sem o ConsultaOs.
        $empresaPublica = \Modules\ConsultaOs\Services\EmpresaPublica::class;
        if ($business_id <= 0 || ! class_exists($empresaPublica) || ! \Illuminate\Support\Facades\Route::has('consulta-os.empresa')) {
            return null;
        }

        $href = $empresaPublica::linkExistente($business_id) ?? '/repair/repair-settings';

        return ['key' => 'portal', 'label' => 'Portal do cliente', 'href' => $href, 'recarrega' => true];
    }

    /**
     * Adds Repair menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_repair_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'repair_module');

        $background_color = '';
        if (config('app.env') == 'demo') {
            $background_color = '#bc8f8f !important';
        }

        if ($is_repair_enabled && (auth()->user()->can('superadmin') || auth()->user()->can('repair.view') || auth()->user()->can('job_sheet.view_assigned') || auth()->user()->can('job_sheet.view_all'))) {
            $ghosts = $this->abasDoRepair((int) $business_id);
            Menu::modify('admin-sidebar-menu', function ($menu) use ($background_color, $ghosts) {
                // ADR 0180 Fase 4 Wave B (2026-05-21): entry principal declara
                // atalho kbd + primary action + ghosts tabs.
                //  - `shortcut` G O → atalho overlay (Fase 8)
                //  - `primary`     → "Nova OS" via SellPosController (sub_type=repair)
                //  - `ghosts`      → sub-views da tela /repair/* (Dashboard, OS, Job Sheets,
                //    Producao Oficina Kanban, Status, Settings)
                //
                // hrefs absolutos (LegacyMenuAdapter::toRelative espera path string).
                $menu->url(
                    action([\Modules\Repair\Http\Controllers\DashboardController::class, 'index']),
                    'Assistência técnica', // rótulo do protótipo (data.jsx, PRODUÇÃO)
                    [
                        'icon'     => 'fa fas fa-wrench',
                        'active'   => request()->segment(1) == 'repair',
                        'style'    => 'background-color:'.$background_color,
                        'shortcut' => 'G O',
                        'primary'  => [
                            'label'    => 'Nova OS',
                            // /sells/pos/create não existe (404 em prod, medido 2026-10-01);
                            // /pos/create é a rota do SellPosController que lê ?sub_type=.
                            'href'     => '/pos/create?sub_type=repair',
                            'shortcut' => 'N',
                        ],
                        // As abas do topo das telas leem ESTA lista (ModuleTabs) — ver abasDoRepair().
                        'ghosts'   => $ghosts,
                    ]
                )
                ->order(24);
            });
        }
    }

    /**
     * Returns view/js path with required extra data.
     * for pos screen
     *
     * @return array
     */
    public function get_pos_screen_view($params)
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_repair_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'repair_module');

        if ($is_repair_enabled && (! is_null($params['sub_type']) && $params['sub_type'] == 'repair')) {
            $repairUtil = new RepairUtil();
            $repair_settings = $repairUtil->getRepairSettings($business_id);

            $default_status = '';
            if (! empty($repair_settings['default_status'])) {
                $default_status = $repair_settings['default_status'];
            }

            $repair_statuses = RepairStatus::getRepairSatuses($business_id);
            $device_models = DeviceModel::forDropdown($business_id);
            $brands = Brands::forDropdown($business_id);
            $devices = Category::forDropdown($business_id, 'device');

            $warranties = [];
            if (request()->session()->get('business.common_settings.enable_product_warranty')) {
                $warranties = Warranty::forDropdown($business_id);
            }

            $job_sheet = [];
            $parts = [];
            if (isset($params['job_sheet_id'])) {
                // OS de outro business (ou apagada) não é encontrada pelo where business_id.
                // Antes, getPartsUsed() rodava sobre null e o PDV caía em 500; agora a venda
                // abre sem OS de origem (UC-S07, Sells/Create.casos.md).
                $job_sheet = JobSheet::where('business_id', $business_id)
                        ->find($params['job_sheet_id']) ?? [];

                $parts = ! empty($job_sheet) ? $job_sheet->getPartsUsed() : [];
            }

            return  [
                'view_path' => 'repair::repair.partials.repair_pos',
                'view_data' => [
                    'repair_statuses' => $repair_statuses,
                    'default_status' => $default_status,
                    'device_models' => $device_models,
                    'brands' => $brands,
                    'devices' => $devices,
                    'warranties' => $warranties,
                    'job_sheet' => $job_sheet,
                    'repair_settings' => $repair_settings,
                    'parts' => $parts,
                ],
                'module_js_path' => 'repair::layouts.partials.javascripts',
                'module_css_path' => 'repair::job_sheet.tagify_css',
                'go_back_url' => action([\Modules\Repair\Http\Controllers\RepairController::class, 'index']),
                'transaction_sub_type' => 'repair',
            ];
        } else {
            return [];
        }
    }

    /**
     * Function to add repair module taxonomies
     *
     * @return array
     */
    public function addTaxonomies()
    {
        $module_util = new ModuleUtil();
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $module_util->hasThePermissionInSubscription($business_id, 'repair_module'))) {
            return [
                'device' => [],
            ];
        }

        return [
            'device' => [
                'taxonomy_label' => __('repair::lang.device'),
                'heading' => __('repair::lang.devices'),
                'sub_heading' => __('repair::lang.manage_device'),
                'enable_taxonomy_code' => false,
                'enable_sub_taxonomy' => false,
            ],
        ];
    }

    /**
     * Returns view/js path with required extra data.
     * for product
     *
     * @return array
     */
    public function get_product_screen_top_view()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_repair_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'repair_module');

        if ($is_repair_enabled) {
            $device_models = DeviceModel::forDropdown($business_id);

            return [
                'view_path' => 'repair::device_model.partials.repair_product_screen',
                'view_data' => [
                    'device_models' => $device_models,
                ],
                'module_js_path' => 'repair::layouts.partials.javascripts',
            ];
        } else {
            return [];
        }
    }

    /**
     * Sets product fields from module
     * An input field with name "has_module_data" is required in the request to call this function
     *
     * @param  array  $data
     * @return obj
     */
    public function after_product_saved($data)
    {
        $product = $data['product'];
        $request = $data['request'];

        $repair_module_fields = [
            'repair_model_id',
        ];

        foreach ($repair_module_fields as $field) {
            if (! empty($request->get($field))) {
                $product->$field = $request->get($field);
            }
        }

        $product->save();

        return $product;
    }

    public function get_filters_for_list_product_screen()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_repair_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'repair_module');

        if ($is_repair_enabled) {
            $device_models = DeviceModel::forDropdown($business_id);

            return [
                'view_path' => 'repair::device_model.partials.list_product_filters',
                'view_data' => [
                    'device_models' => $device_models,
                ],
            ];
        } else {
            return [];
        }
    }
}
