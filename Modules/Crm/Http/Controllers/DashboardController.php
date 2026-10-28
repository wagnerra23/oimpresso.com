<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Utils\ContactUtil;
use App\Services\FeatureFlagService;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Crm\Services\PortalContatoMenu;

class DashboardController extends Controller
{
    /**
     * Flag do Início do portal do contato em React (thread crm/playbook/18, F3). Convenção
     * `useV2<Modulo><Tela>`: sem a chave no GrowthBook cai no fallbackDefaults, que não a lista —
     * default OFF, a Blade segue servindo. Ligar é toggle (flag:set --biz=N), não deploy.
     *
     * @see memory/requisitos/Crm/RUNBOOK-portal-painel.md
     */
    private const FLAG_V2 = 'useV2CrmPortalPainel';

    protected $contactUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(ContactUtil $contactUtil, ModuleUtil $moduleUtil)
    {
        $this->contactUtil = $contactUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View|\Inertia\Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $crm_contact_id = auth()->user()->crm_contact_id;

        $contact = $this->contactUtil->getContactInfo($business_id, $crm_contact_id);
        // getContactInfo devolve null quando o contato do usuário não existe neste business
        // (antes vinha um Contact vazio e a tela abria em branco).
        if ($contact === null) {
            abort(404);
        }

        // `?classico=1` mantém a Blade alcançável com a flag ligada, até o cutover.
        if (! request()->boolean('classico') && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => (int) $business_id])) {
            return Inertia::render('Crm/Portal/Painel/Index', $this->painel($contact) + [
                // A casca do contato (D6) substitui a prop `shell` inteira: o menu e o resto do
                // shell do ERP não descem para quem é contato (UC-CRMPO-03).
                'shell' => ['portal' => $this->casca($contact)],
            ]);
        }

        return view('crm::dashboard.index')
            ->with(compact('contact'));
    }

    /**
     * Os blocos do Início seguem o tipo do contato, como a Blade: cliente vê vendas, fornecedor vê
     * compras, `both` vê os dois; abertura só quando existe. Valor só é MOSTRADO — os totais são os
     * de `ContactUtil::getContactInfo`, sem cálculo novo (a pagar = total − pago, como a Blade).
     */
    private function painel($contact): array
    {
        $bloco = fn ($total, $pago) => [
            'total' => $this->decimal($total), 'pago' => $this->decimal($pago), 'a_pagar' => $this->decimal($total - $pago),
            'total_f' => $this->contactUtil->num_f($total, true), 'pago_f' => $this->contactUtil->num_f($pago, true),
            'a_pagar_f' => $this->contactUtil->num_f($total - $pago, true),
        ];
        $ehCliente = in_array($contact->type, ['customer', 'both']);
        $ehFornecedor = in_array($contact->type, ['supplier', 'both']);
        $temAbertura = ! empty($contact->opening_balance) && (float) $contact->opening_balance != 0.0;

        return array_filter([
            'saudacao' => (string) (auth()->user()->first_name ?: $contact->name),
            'vendas' => $ehCliente ? $bloco((float) $contact->total_invoice, (float) $contact->invoice_received) : null,
            'compras' => $ehFornecedor ? $bloco((float) $contact->total_purchase, (float) $contact->purchase_paid) : null,
            'abertura' => $temAbertura ? $bloco((float) $contact->opening_balance, (float) $contact->opening_balance_paid) : null,
        ], fn ($v) => $v !== null);
    }

    private function casca($contact): array
    {
        $modulos = ! empty(session('business.enabled_modules')) ? session('business.enabled_modules') : [];

        return app(PortalContatoMenu::class)->casca($contact, $modulos);
    }

    private function decimal(float $v): string
    {
        return number_format($v, 2, '.', '');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
