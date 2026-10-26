<?php

namespace App\Http\Controllers;

use App\Business;
use App\Currency;
use App\Notifications\TestEmailNotification;
use App\System;
use App\TaxRate;
use App\Unit;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\RestaurantUtil;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class BusinessController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | BusinessController
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new business/business as well as their
    | validation and creation.
    |
    */

    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $restaurantUtil;

    protected $moduleUtil;

    protected $mailDrivers;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(BusinessUtil $businessUtil, RestaurantUtil $restaurantUtil, ModuleUtil $moduleUtil)
    {
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;

        $this->theme_colors = [
            'primary' => 'Blue',
            // 'black' => 'Black',
            'purple' => 'Purple',
            'green' => 'Green',
            'red' => 'Red',
            'yellow' => 'Yellow',
            'orange' => 'Orange',
            'sky' => 'Sky',
            // 'blue-light' => 'Blue Light',
            // 'black-light' => 'Black Light',
            // 'purple-light' => 'Purple Light',
            // 'green-light' => 'Green Light',
            // 'red-light' => 'Red Light',
        ];

        $this->mailDrivers = [
            'smtp' => 'SMTP',
            // 'sendmail' => 'Sendmail',
            // 'mailgun' => 'Mailgun',
            // 'mandrill' => 'Mandrill',
            // 'ses' => 'SES',
            // 'sparkpost' => 'Sparkpost'
        ];
    }

    /**
     * Shows registration form
     *
     * @return \Illuminate\Http\Response
     */
    public function getRegister()
    {
        if (! config('constants.allow_registration')) {
            return redirect('/');
        }

        $currencies = $this->businessUtil->allCurrencies();

        $timezone_list = $this->businessUtil->allTimeZones();

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = __('business.months.'.$i);
        }

        $accounting_methods = $this->businessUtil->allAccountingMethods();
        $package_id = request()->package;

        $system_settings = System::getProperties(['superadmin_enable_register_tc', 'superadmin_register_tc'], true);

        return view('business.register', compact(
            'currencies',
            'timezone_list',
            'months',
            'accounting_methods',
            'package_id',
            'system_settings'
        ));
    }

    /**
     * Handles the registration of a new business and it's owner
     *
     * @return \Illuminate\Http\Response
     */
    public function postRegister(Request $request)
    {
        if (! config('constants.allow_registration')) {
            return redirect('/');
        }

        try {
            $validator = $request->validate(
                [
                    'name' => 'required|max:255',
                    'currency_id' => 'required|numeric',
                    'country' => 'required|max:255',
                    'state' => 'required|max:255',
                    'city' => 'required|max:255',
                    'zip_code' => 'required|max:255',
                    'landmark' => 'required|max:255',
                    'time_zone' => 'required|max:255',
                    'surname' => 'max:10',
                    'email' => 'sometimes|nullable|email|unique:users|max:255',
                    'first_name' => 'required|max:255',
                    'username' => 'required|min:4|max:255|unique:users',
                    'password' => 'required|min:4|max:255',
                    'fy_start_month' => 'required',
                    'accounting_method' => 'required',
                ],
                [
                    'name.required' => __('validation.required', ['attribute' => __('business.business_name')]),
                    'name.currency_id' => __('validation.required', ['attribute' => __('business.currency')]),
                    'country.required' => __('validation.required', ['attribute' => __('business.country')]),
                    'state.required' => __('validation.required', ['attribute' => __('business.state')]),
                    'city.required' => __('validation.required', ['attribute' => __('business.city')]),
                    'zip_code.required' => __('validation.required', ['attribute' => __('business.zip_code')]),
                    'landmark.required' => __('validation.required', ['attribute' => __('business.landmark')]),
                    'time_zone.required' => __('validation.required', ['attribute' => __('business.time_zone')]),
                    'email.email' => __('validation.email', ['attribute' => __('business.email')]),
                    'email.email' => __('validation.unique', ['attribute' => __('business.email')]),
                    'first_name.required' => __('validation.required', ['attribute' => __('business.first_name')]),
                    'username.required' => __('validation.required', ['attribute' => __('business.username')]),
                    'username.min' => __('validation.min', ['attribute' => __('business.username')]),
                    'password.required' => __('validation.required', ['attribute' => __('business.username')]),
                    'password.min' => __('validation.min', ['attribute' => __('business.username')]),
                    'fy_start_month.required' => __('validation.required', ['attribute' => __('business.fy_start_month')]),
                    'accounting_method.required' => __('validation.required', ['attribute' => __('business.accounting_method')]),
                ]
            );

            DB::beginTransaction();

            //Create owner.
            $owner_details = $request->only(['surname', 'first_name', 'last_name', 'username', 'email', 'password', 'language']);

            $owner_details['language'] = empty($owner_details['language']) ? config('app.locale') : $owner_details['language'];

            $user = User::create_user($owner_details);

            $business_details = $request->only(['name', 'start_date', 'currency_id', 'time_zone',
                'fy_start_month', 'accounting_method', 'tax_label_1', 'tax_number_1',
                'tax_label_2', 'tax_number_2', ]);

            $business_location = $request->only(['name', 'country', 'state', 'city', 'zip_code', 'landmark',
                'website', 'mobile', 'alternate_number', ]);

            //Create the business
            $business_details['owner_id'] = $user->id;
            if (! empty($business_details['start_date'])) {
                $business_details['start_date'] = Carbon::createFromFormat(config('constants.default_date_format'), $business_details['start_date'])->toDateString();
            }

            //upload logo
            $logo_name = $this->businessUtil->uploadFile($request, 'business_logo', 'business_logos', 'image');
            if (! empty($logo_name)) {
                $business_details['logo'] = $logo_name;
            }

            //default enabled modules
            $business_details['enabled_modules'] = ['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses'];

            $business = $this->businessUtil->createNewBusiness($business_details);

            //Update user with business id
            $user->business_id = $business->id;
            $user->save();

            $this->businessUtil->newBusinessDefaultResources($business->id, $user->id);
            $new_location = $this->businessUtil->addLocation($business->id, $business_location);

            //create new permission with the new location
            Permission::create(['name' => 'location.'.$new_location->id]);

            DB::commit();

            //Module function to be called after after business is created
            if (config('app.env') != 'demo') {
                $this->moduleUtil->getModuleData('after_business_created', ['business' => $business]);
            }

            //Process payment information if superadmin is installed & package information is present
            $is_installed_superadmin = $this->moduleUtil->isSuperadminInstalled();
            $package_id = $request->get('package_id', null);
            if ($is_installed_superadmin && ! empty($package_id) && (config('app.env') != 'demo')) {
                $package = \Modules\Superadmin\Entities\Package::find($package_id);
                if (! empty($package)) {
                    Auth::login($user);
                    return redirect()->route('register-pay', ['package_id' => $package_id]);
                }
            }

            $output = ['success' => 1,
                'msg' => __('business.business_created_succesfully'),
            ];

            return redirect('login')->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    /**
     * Handles the validation username
     *
     * @return \Illuminate\Http\Response
     */
    public function postCheckUsername(Request $request)
    {
        $username = $request->input('username');

        if (! empty($request->input('username_ext'))) {
            $username .= $request->input('username_ext');
        }

        $count = User::where('username', $username)->count();

        if ($count == 0) {
            echo 'true';
            exit;
        } else {
            echo 'false';
            exit;
        }
    }

    /**
     * Shows business settings form
     *
     * @return \Illuminate\View\View|\Inertia\Response
     */
    public function getBusinessSettings()
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $timezone_list = [];
        foreach ($timezones as $timezone) {
            $timezone_list[$timezone] = $timezone;
        }

        $business_id = request()->session()->get('user.business_id');
        $business = Business::where('id', $business_id)->first();

        $currencies = $this->businessUtil->allCurrencies();
        $tax_details = TaxRate::forBusinessDropdown($business_id);
        $tax_rates = $tax_details['tax_rates'];

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = __('business.months.'.$i);
        }

        $accounting_methods = [
            'fifo' => __('business.fifo'),
            'lifo' => __('business.lifo'),
        ];
        $commission_agent_dropdown = [
            '' => __('lang_v1.disable'),
            'logged_in_user' => __('lang_v1.logged_in_user'),
            'user' => __('lang_v1.select_from_users_list'),
            'cmsn_agnt' => __('lang_v1.select_from_commisssion_agents_list'),
        ];

        $units_dropdown = Unit::forDropdown($business_id, true);

        $date_formats = Business::date_formats();

        $shortcuts = json_decode($business->keyboard_shortcuts, true);

        $pos_settings = empty($business->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business->pos_settings, true);

        $email_settings = empty($business->email_settings) ? $this->businessUtil->defaultEmailSettings() : $business->email_settings;

        $sms_settings = empty($business->sms_settings) ? $this->businessUtil->defaultSmsSettings() : $business->sms_settings;

        $modules = $this->moduleUtil->availableModules();

        $theme_colors = $this->theme_colors;

        $mail_drivers = $this->mailDrivers;

        $allow_superadmin_email_settings = System::getProperty('allow_email_settings_to_businesses');

        $custom_labels = ! empty($business->custom_labels) ? json_decode($business->custom_labels, true) : [];

        $common_settings = ! empty($business->common_settings) ? $business->common_settings : [];

        $weighing_scale_setting = ! empty($business->weighing_scale_setting) ? $business->weighing_scale_setting : [];

        $payment_types = $this->moduleUtil->payment_types(null, false, $business_id);

        // `?classico=1` mantém a Blade alcançável com a flag ligada: abas que a Page ainda não desenhou abrem lá.
        if (! request()->boolean('classico') && app(\App\Services\FeatureFlagService::class)->isOn(self::FLAG_EMPRESA_V2, ['business_id' => $business_id])) {
            return \Inertia\Inertia::render('Configuracoes/Empresa/Index', [
                'corpo' => $this->corpoDoSalvar($business),
                'logo' => $business->logo ? asset('uploads/business_logos/'.$business->logo) : null,
                // Só QUAIS segredos estão gravados, nunca o valor: a tela mostra "gravada" e aceita digitar outro.
                'segredos_gravados' => array_merge(
                    array_values(array_filter(self::SEGREDOS_POS, fn ($k) => ! empty($pos_settings[$k]))),
                    array_map(fn ($k) => 'email.'.$k, array_filter(self::SEGREDOS_EMAIL, fn ($k) => ! empty(((array) $business->email_settings)[$k] ?? null))),
                    array_map(fn ($k) => 'sms.'.$k, array_values(array_filter(self::SEGREDOS_SMS, fn ($k) => ! empty(((array) $business->sms_settings)[$k] ?? null)))),
                ),
                'email_superadmin' => ! empty($allow_superadmin_email_settings),
                'app_lojas' => $this->ajustesAppLojas((array) $pos_settings, \App\Services\AppLojas\AjustesDaEmpresa::CHAVES),
                'opcoes' => \Inertia\Inertia::defer(fn () => [
                    'moedas' => $currencies, 'fusos' => $timezone_list, 'meses' => $months,
                    'metodos' => $accounting_methods, 'formatos_data' => $date_formats, 'unidades' => $units_dropdown,
                    'impostos' => $tax_rates, 'comissao' => $commission_agent_dropdown, 'pagamentos' => $payment_types,
                    'temas' => $theme_colors, 'drivers_email' => $mail_drivers,
                    'modulos' => array_map(fn ($m) => (string) ($m['name'] ?? ''), $modules),
                ]),
            ]);
        }

        return view('business.settings', compact('business', 'currencies', 'tax_rates', 'timezone_list', 'months', 'accounting_methods', 'commission_agent_dropdown', 'units_dropdown', 'date_formats', 'shortcuts', 'pos_settings', 'modules', 'theme_colors', 'email_settings', 'sms_settings', 'mail_drivers', 'allow_superadmin_email_settings', 'custom_labels', 'common_settings', 'weighing_scale_setting', 'payment_types'));
    }

    /**
     * Aba "App das lojas": os mesmos ajustes que a Blade `settings_app_lojas` desenha (bool e `na_tela`), com rótulo,
     * ajuda e o valor atual vindos de AjustesDaEmpresa — a tela não copia texto nem padrão.
     *
     * As chaves chegam por parâmetro com tipo largo: lido direto da constante, o PHPStan dá o `tipo === 'bool'` como
     * sempre verdadeiro hoje (a única chave de lista também está fora da tela), mas é o mesmo filtro da Blade.
     *
     * @param  array<string, array{tipo: string, rotulo: string, ajuda: string, na_tela: bool}>  $chaves
     * @return list<array{chave: string, rotulo: string, ajuda: string, valor: bool}>
     */
    private function ajustesAppLojas(array $pos, array $chaves): array
    {
        $valores = \App\Services\AppLojas\AjustesDaEmpresa::completar($pos);
        $saida = [];
        foreach ($chaves as $chave => $c) {
            if ($c['na_tela'] && $c['tipo'] === 'bool') {
                $saida[] = ['chave' => $chave, 'rotulo' => $c['rotulo'], 'ajuda' => $c['ajuda'], 'valor' => (bool) $valores[$chave]];
            }
        }

        return $saida;
    }

    /**
     * Flag do caminho React de /business/settings (thread sistema/playbook/08, F3). Fora do fallbackDefaults
     * do FeatureFlagService: nasce DESLIGADA, a Blade segue servindo. Ligar é decisão [W] (RUNBOOK-empresa).
     */
    private const FLAG_EMPRESA_V2 = 'useV2ConfiguracoesEmpresa';

    /** Chaves de `pos_settings` que são segredo: não vão para a tela React, e o salvar mantém o gravado quando faltam. */
    private const SEGREDOS_POS = ['stripe_secret_key', 'razor_pay_key_secret'];

    /**
     * Segredos de e-mail e SMS (F3-5a). Cabeçalho e parâmetro do SMS personalizado entram porque costumam levar o token
     * da API (ex.: `Authorization: Bearer …`). Mesma regra de SEGREDOS_POS: fora das props; ausente no envio = manter.
     */
    private const SEGREDOS_EMAIL = ['mail_password'];

    /**
     * As chaves de `common_settings` que os parciais da Blade (business/partials) desenham. Só elas são
     * substituídas pelo salvar; as demais são de outras features e não podem sumir quando alguém salva a tela.
     * EmpresaBaselineTest confere esta lista contra os parciais.
     */
    public const CHAVES_COMMON_DA_BLADE = ['is_enabled_export', 'default_credit_limit', 'enable_product_warranty',
        'enable_secondary_unit', 'is_product_image_required', 'enable_purchase_order', 'enable_purchase_requisition',
        'default_datatable_page_entries',
        // Aba Financeiro (settings_financeiro): a Blade manda o objeto inteiro, com `ativo` por hidden.
        'financeiro_regua_cobranca',
        // Aba Vendas (settings_sales): `ativo` (checkbox) + `nota_baixa` (select sempre presente) — objeto inteiro no POST.
        'sells_pesquisa_entrega'];

    private const SEGREDOS_SMS = ['nexmo_secret', 'twilio_token', 'header_val_1', 'header_val_2', 'header_val_3',
        'param_val_1', 'param_val_2', 'param_val_3', 'param_val_4', 'param_val_5', 'param_val_6', 'param_val_7', 'param_val_8',
        'param_val_9', 'param_val_10'];

    /**
     * O corpo que a tela React devolve INTEIRO ao postBusinessSettings, no formato que ele lê.
     *
     * O salvar é substituição total (EmpresaBaselineTest): checkbox, as chaves da Blade em `common_settings`, `enabled_modules`,
     * atalhos, `pos_settings` e os números com `num_uf` são sobrescritos quando faltam. Por isso todos vêm
     * daqui, com o valor gravado, e a Page só troca os campos da aba que desenha.
     *
     * Fora de propósito: `email_settings` e `sms_settings` (senha e segredo não vão para o HTML). O
     * `$request->only()` do salvar omite chave ausente, então eles ficam como estão. Idem os demais campos
     * do `only()` sem pós-processamento (prefixos, rótulos de imposto, balança…).
     *
     * Números que passam por `num_uf` vão como `1234,5000`: vírgula decimal e sem milhar é lido igual em
     * qualquer moeda. O `@num_format` da Blade usa o separador da moeda e a precisão do negócio, e por
     * isso arredonda a margem quando a precisão é 0 e lê "25.000" como 25000 em moeda de ponto decimal.
     */
    private function corpoDoSalvar(Business $b): array
    {
        // Sempre com uma casa: "0" seria `empty()` no salvar, que troca por 1 os campos de pontos.
        $numero = fn ($v) => $v === null ? '' : preg_replace('/0{1,3}$/', '', number_format((float) $v, 4, ',', ''));
        $json = fn ($v) => is_array($v) ? $v : (json_decode((string) $v, true) ?: []);
        $corpo = [
            'name' => $b->name, 'currency_id' => $b->currency_id, 'currency_symbol_placement' => $b->currency_symbol_placement,
            'time_zone' => $b->time_zone, 'fy_start_month' => $b->fy_start_month, 'accounting_method' => $b->accounting_method,
            'transaction_edit_days' => $b->transaction_edit_days, 'date_format' => $b->date_format, 'time_format' => $b->time_format,
            'currency_precision' => $b->currency_precision, 'quantity_precision' => $b->quantity_precision,
            'code_label_1' => $b->code_label_1, 'code_1' => $b->code_1, 'code_label_2' => $b->code_label_2, 'code_2' => $b->code_2,
            // Impostos · Produtos (F3-2). Estão no only() sem pós-processamento: vão para a Page poder editá-los.
            'tax_label_1' => $b->tax_label_1, 'tax_number_1' => $b->tax_number_1, 'tax_label_2' => $b->tax_label_2, 'tax_number_2' => $b->tax_number_2,
            // Vendas (F3-3a).
            'default_sales_tax' => $b->default_sales_tax, 'sell_price_tax' => $b->sell_price_tax,
            'item_addition_method' => $b->item_addition_method, 'sales_cmsn_agnt' => $b->sales_cmsn_agnt,
            'sku_prefix' => $b->sku_prefix, 'default_unit' => $b->default_unit, 'expiry_type' => $b->expiry_type, 'stop_selling_before' => $b->stop_selling_before,
            // uf_date() lê no formato do negócio que está na sessão — o mesmo que formata aqui.
            'start_date' => $b->start_date ? Carbon::parse($b->start_date)->format(session('business.date_format', $b->date_format ?: 'd/m/Y')) : '',
            'default_profit_percent' => $numero($b->default_profit_percent), 'default_sales_discount' => $numero($b->default_sales_discount),
            'amount_for_unit_rp' => $numero($b->amount_for_unit_rp), 'min_order_total_for_rp' => $numero($b->min_order_total_for_rp),
            'redeem_amount_per_unit_rp' => $numero($b->redeem_amount_per_unit_rp), 'min_order_total_for_redeem' => $numero($b->min_order_total_for_redeem),
            'on_product_expiry' => (string) $b->on_product_expiry, 'stock_expiry_alert_days' => $b->stock_expiry_alert_days,
            'enable_rp' => (bool) $b->enable_rp, 'enable_tooltip' => (bool) $b->enable_tooltip,
            'enable_product_expiry' => (bool) $b->enable_product_expiry, 'purchase_in_diff_currency' => (bool) $b->purchase_in_diff_currency,
            'purchase_currency_id' => $b->purchase_currency_id, 'p_exchange_rate' => (string) $b->p_exchange_rate,
            // Sem as chaves secretas de pagamento: props do Inertia ficam no HTML. O salvar as preserva quando faltam.
            'shortcuts' => $json($b->keyboard_shortcuts), 'pos_settings' => array_diff_key($json($b->pos_settings), array_flip(self::SEGREDOS_POS)),
            // Balança (F3-3b). Está no only(): vazio some do FormData e o salvar deixa como está.
            'weighing_scale_setting' => $json($b->weighing_scale_setting),
            // Sistema (F3-4). Os dois estão no only(): prefixo vazio some do FormData e fica como está.
            'theme_color' => $b->theme_color, 'ref_no_prefixes' => $json($b->ref_no_prefixes),
            // Fidelidade (F3-5b). Estão no only() sem pós-processamento; os quatro valores com num_uf já vão acima.
            'rp_name' => $b->rp_name, 'max_rp_per_order' => $b->max_rp_per_order, 'min_redeem_point' => $b->min_redeem_point,
            'max_redeem_point' => $b->max_redeem_point, 'rp_expiry_period' => $b->rp_expiry_period, 'rp_expiry_type' => $b->rp_expiry_type,
            // E-mail e SMS (F3-5a): sem os segredos. Vazio some do FormData e o only() deixa como está.
            'email_settings' => array_diff_key($json($b->email_settings), array_flip(self::SEGREDOS_EMAIL)),
            'sms_settings' => array_diff_key($json($b->sms_settings), array_flip(self::SEGREDOS_SMS)),
            'common_settings' => $json($b->common_settings), 'enabled_modules' => array_values($json($b->enabled_modules)),
            // Objeto vazio some do FormData e o salvar lê `custom_labels` sem checar: sem a chave, cai no catch.
            'custom_labels' => $json($b->custom_labels) ?: ['payments' => ['custom_pay_1' => '']],
        ];
        foreach (['enable_editing_product_from_purchase', 'enable_inline_tax', 'enable_brand', 'enable_category', 'enable_sub_category',
            'enable_price_tax', 'enable_purchase_status', 'enable_lot_number', 'enable_racks', 'enable_row', 'enable_position', 'enable_sub_units'] as $c) {
            $corpo[$c] = (bool) $b->{$c};
        }

        return $corpo;
    }

    /**
     * Updates business settings
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function postBusinessSettings(Request $request)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->businessUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            $business_details = $request->only(['name', 'start_date', 'currency_id', 'tax_label_1', 'tax_number_1', 'tax_label_2', 'tax_number_2', 'default_profit_percent', 'default_sales_tax', 'default_sales_discount', 'sell_price_tax', 'sku_prefix', 'time_zone', 'fy_start_month', 'accounting_method', 'transaction_edit_days', 'sales_cmsn_agnt', 'item_addition_method', 'currency_symbol_placement', 'on_product_expiry',
                'stop_selling_before', 'default_unit', 'expiry_type', 'date_format',
                'time_format', 'ref_no_prefixes', 'theme_color', 'email_settings',
                'sms_settings', 'rp_name', 'amount_for_unit_rp',
                'min_order_total_for_rp', 'max_rp_per_order',
                'redeem_amount_per_unit_rp', 'min_order_total_for_redeem',
                'min_redeem_point', 'max_redeem_point', 'rp_expiry_period',
                'rp_expiry_type', 'custom_labels', 'weighing_scale_setting',
                'code_label_1', 'code_1', 'code_label_2', 'code_2', 'currency_precision', 'quantity_precision', ]);

            if (! empty($request->input('enable_rp')) && $request->input('enable_rp') == 1) {
                $business_details['enable_rp'] = 1;
            } else {
                $business_details['enable_rp'] = 0;
            }

            $business_details['amount_for_unit_rp'] = ! empty($business_details['amount_for_unit_rp']) ? $this->businessUtil->num_uf($business_details['amount_for_unit_rp']) : 1;
            $business_details['min_order_total_for_rp'] = ! empty($business_details['min_order_total_for_rp']) ? $this->businessUtil->num_uf($business_details['min_order_total_for_rp']) : 1;
            $business_details['redeem_amount_per_unit_rp'] = ! empty($business_details['redeem_amount_per_unit_rp']) ? $this->businessUtil->num_uf($business_details['redeem_amount_per_unit_rp']) : 1;
            $business_details['min_order_total_for_redeem'] = ! empty($business_details['min_order_total_for_redeem']) ? $this->businessUtil->num_uf($business_details['min_order_total_for_redeem']) : 1;

            $business_details['default_profit_percent'] = ! empty($business_details['default_profit_percent']) ? $this->businessUtil->num_uf($business_details['default_profit_percent']) : 0;

            $business_details['default_sales_discount'] = ! empty($business_details['default_sales_discount']) ? $this->businessUtil->num_uf($business_details['default_sales_discount']) : 0;

            if (! empty($business_details['start_date'])) {
                $business_details['start_date'] = $this->businessUtil->uf_date($business_details['start_date']);
            }

            if (! empty($request->input('enable_tooltip')) && $request->input('enable_tooltip') == 1) {
                $business_details['enable_tooltip'] = 1;
            } else {
                $business_details['enable_tooltip'] = 0;
            }

            $business_details['enable_product_expiry'] = ! empty($request->input('enable_product_expiry')) && $request->input('enable_product_expiry') == 1 ? 1 : 0;
            if ($business_details['on_product_expiry'] == 'keep_selling') {
                $business_details['stop_selling_before'] = null;
            }

            $business_details['stock_expiry_alert_days'] = ! empty($request->input('stock_expiry_alert_days')) ? $request->input('stock_expiry_alert_days') : 30;

            //Check for Purchase currency
            if (! empty($request->input('purchase_in_diff_currency')) && $request->input('purchase_in_diff_currency') == 1) {
                $business_details['purchase_in_diff_currency'] = 1;
                $business_details['purchase_currency_id'] = $request->input('purchase_currency_id');
                $business_details['p_exchange_rate'] = $request->input('p_exchange_rate');
            } else {
                $business_details['purchase_in_diff_currency'] = 0;
                $business_details['purchase_currency_id'] = null;
                $business_details['p_exchange_rate'] = 1;
            }

            //upload logo
            $logo_name = $this->businessUtil->uploadFile($request, 'business_logo', 'business_logos', 'image');
            if (! empty($logo_name)) {
                $business_details['logo'] = $logo_name;
            }

            $checkboxes = ['enable_editing_product_from_purchase',
                'enable_inline_tax',
                'enable_brand', 'enable_category', 'enable_sub_category', 'enable_price_tax', 'enable_purchase_status',
                'enable_lot_number', 'enable_racks', 'enable_row', 'enable_position', 'enable_sub_units', ];
            foreach ($checkboxes as $value) {
                $business_details[$value] = ! empty($request->input($value)) && $request->input($value) == 1 ? 1 : 0;
            }

            $business_id = request()->session()->get('user.business_id');
            $business = Business::where('id', $business_id)->first();

            //Update business settings
            if (! empty($business_details['logo'])) {
                $business->logo = $business_details['logo'];
            } else {
                unset($business_details['logo']);
            }

            //System settings
            $shortcuts = $request->input('shortcuts');
            $business_details['keyboard_shortcuts'] = json_encode($shortcuts);

            //pos_settings
            $pos_settings = $request->input('pos_settings');
            // A Blade sempre manda as duas chaves; a tela React não as recebe nem as manda. Ausente = manter o gravado.
            $gravado = json_decode((string) $business->pos_settings, true) ?: [];
            foreach (self::SEGREDOS_POS as $segredo) {
                if (! array_key_exists($segredo, (array) $pos_settings) && array_key_exists($segredo, $gravado)) {
                    $pos_settings[$segredo] = $gravado[$segredo];
                }
            }
            // E-mail e SMS: o only() grava o objeto que chegar inteiro. A Blade sempre manda todos os campos (os @if só
            // escondem por classe); a tela React não manda os segredos. Ausente = manter o gravado.
            foreach (['email_settings' => self::SEGREDOS_EMAIL, 'sms_settings' => self::SEGREDOS_SMS] as $campo => $chaves) {
                if (! isset($business_details[$campo]) || ! is_array($business_details[$campo])) {
                    continue;
                }
                $gravadoCampo = (array) ($business->{$campo} ?? []);
                foreach ($chaves as $segredo) {
                    if (! array_key_exists($segredo, $business_details[$campo]) && array_key_exists($segredo, $gravadoCampo)) {
                        $business_details[$campo][$segredo] = $gravadoCampo[$segredo];
                    }
                }
            }
            // Ajustes do app das lojas: a aba que não edita uma chave `app_*` não a apaga, e o valor gravado é normalizado.
            $pos_settings = \App\Services\AppLojas\AjustesDaEmpresa::aoSalvar((array) $pos_settings, $gravado);
            $default_pos_settings = $this->businessUtil->defaultPosSettings();
            foreach ($default_pos_settings as $key => $value) {
                if (! isset($pos_settings[$key])) {
                    $pos_settings[$key] = $value;
                }
            }
            $business_details['pos_settings'] = json_encode($pos_settings);

            $business_details['custom_labels'] = json_encode($business_details['custom_labels']);

            // A Blade só desenha as chaves de CHAVES_COMMON_DA_BLADE; o resto do JSON é de outras features e fica.
            // As da Blade seguem substituídas: checkbox desmarcado não vem no POST e tem de sair.
            $enviado = (array) ($request->input('common_settings') ?: []);
            $outrasFeatures = array_diff_key((array) ($business->common_settings ?? []), array_flip(self::CHAVES_COMMON_DA_BLADE));
            $business_details['common_settings'] = $enviado + $outrasFeatures;

            //Enabled modules
            $enabled_modules = $request->input('enabled_modules');
            $business_details['enabled_modules'] = ! empty($enabled_modules) ? $enabled_modules : null;
            $business->fill($business_details);
            $business->save();

            //update session data
            $request->session()->put('business', $business);

            //Update Currency details
            $currency = Currency::find($business->currency_id);
            $request->session()->put('currency', [
                'id' => $currency->id,
                'code' => $currency->code,
                'symbol' => $currency->symbol,
                'thousand_separator' => $currency->thousand_separator,
                'decimal_separator' => $currency->decimal_separator,
            ]);

            //update current financial year to session
            $financial_year = $this->businessUtil->getCurrentFinancialYear($business->id);
            $request->session()->put('financial_year', $financial_year);

            $output = ['success' => 1,
                'msg' => __('business.settings_updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('business/settings')->with('status', $output);
    }

    /**
     * Handles the validation email
     *
     * @return \Illuminate\Http\Response
     */
    public function postCheckEmail(Request $request)
    {
        $email = $request->input('email');

        $query = User::where('email', $email);

        if (! empty($request->input('user_id'))) {
            $user_id = $request->input('user_id');
            $query->where('id', '!=', $user_id);
        }

        $exists = $query->exists();
        if (! $exists) {
            echo 'true';
            exit;
        } else {
            echo 'false';
            exit;
        }
    }

    public function getEcomSettings()
    {
        try {
            $api_token = request()->header('API-TOKEN');
            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $settings = Business::where('id', $api_settings->business_id)
                        ->value('ecom_settings');

            $settings_array = ! empty($settings) ? json_decode($settings, true) : [];

            if (! empty($settings_array['slides'])) {
                foreach ($settings_array['slides'] as $key => $value) {
                    $settings_array['slides'][$key]['image_url'] = ! empty($value['image']) ? url('uploads/img/'.$value['image']) : '';
                }
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($settings_array);
    }

    /**
     * Handles the testing of email configuration
     *
     * @return \Illuminate\Http\Response
     */
    public function testEmailConfiguration(Request $request)
    {
        try {
            $email_settings = $request->input();

            $data['email_settings'] = $email_settings;
            \Notification::route('mail', $email_settings['mail_from_address'])
            ->notify(new TestEmailNotification($data));

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.email_tested_successfully'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return $output;
    }

    /**
     * Handles the testing of sms configuration
     *
     * @return \Illuminate\Http\Response
     */
    public function testSmsConfiguration(Request $request)
    {
        try {
            $sms_settings = $request->input();

            $data = [
                'sms_settings' => $sms_settings,
                'mobile_number' => $sms_settings['test_number'],
                'sms_body' => 'This is a test SMS',
            ];
            if (! empty($sms_settings['test_number'])) {
                $response = $this->businessUtil->sendSms($data);
            } else {
                $response = __('lang_v1.test_number_is_required');
            }

            $output = [
                'success' => 1,
                'msg' => $response,
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return $output;
    }
}
