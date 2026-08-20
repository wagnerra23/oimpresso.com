<?php

namespace App\Http\Controllers;

use App\NotificationTemplate;
use App\Notifications\CustomerNotification;
use App\Utils\ModuleUtil;
use App\Utils\NotificationUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class NotificationTemplateController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $notificationUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, NotificationUtil $notificationUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->notificationUtil = $notificationUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('send_notification')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $grupos = $this->__grupos();

        $general_notifications = $this->__getTemplateDetails($grupos['general']);
        $customer_notifications = $this->__getTemplateDetails($grupos['customer']);
        $supplier_notifications = $this->__getTemplateDetails($grupos['supplier']);

        return view('notification_template.index')
                ->with(compact('customer_notifications', 'supplier_notifications', 'general_notifications'));
    }

    /**
     * Os 3 grupos de modelos que ESTA tela oferece — core + os que módulos injetam
     * via `notification_list`.
     *
     * Fonte única de propósito: o `index()` monta a tela a partir daqui e o `store()`
     * deriva daqui a lista de chaves aceitas (P2). Se as duas composições fossem
     * escritas separadamente, um modelo novo injetado por módulo apareceria na tela e
     * seria recusado ao salvar — ou pior, a whitelist envelheceria e voltaria a aceitar
     * qualquer coisa sem ninguém notar.
     *
     * @return array{general: array, customer: array, supplier: array}
     */
    private function __grupos()
    {
        $customer = NotificationTemplate::customerNotifications();
        foreach ($this->moduleUtil->getModuleData('notification_list', ['notification_for' => 'customer']) ?: [] as $extra) {
            $customer = array_merge($customer, $extra);
        }

        $supplier = NotificationTemplate::supplierNotifications();
        foreach ($this->moduleUtil->getModuleData('notification_list', ['notification_for' => 'supplier']) ?: [] as $extra) {
            $supplier = array_merge($supplier, $extra);
        }

        return [
            'general' => NotificationTemplate::generalNotifications(),
            'customer' => $customer,
            'supplier' => $supplier,
        ];
    }

    private function __getTemplateDetails($notifications)
    {
        $business_id = request()->session()->get('user.business_id');
        foreach ($notifications as $key => $value) {
            $notification_template = NotificationTemplate::getTemplate($business_id, $key);
            $notifications[$key]['subject'] = $notification_template['subject'];
            $notifications[$key]['email_body'] = $notification_template['email_body'];
            $notifications[$key]['sms_body'] = $notification_template['sms_body'];
            $notifications[$key]['whatsapp_text'] = $notification_template['whatsapp_text'];
            $notifications[$key]['auto_send'] = $notification_template['auto_send'];
            $notifications[$key]['auto_send_sms'] = $notification_template['auto_send_sms'];
            $notifications[$key]['auto_send_wa_notif'] = $notification_template['auto_send_wa_notif'];
            $notifications[$key]['cc'] = $notification_template['cc'];
            $notifications[$key]['bcc'] = $notification_template['bcc'];
        }

        return $notifications;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('send_notification')) {
            abort(403, 'Unauthorized action.');
        }

        $template_data = $request->input('template_data');
        $business_id = request()->session()->get('user.business_id');

        // POST sem `template_data` (ou com escalar): trata como "nada a gravar" e segue pro
        // mesmo fim do método. NÃO usar early-return aqui — o `store()` tem um `@return
        // Illuminate\Http\Response` no docblock que já não bate com o RedirectResponse real,
        // e essa divergência está no phpstan-baseline com `count: 1`. Um segundo `return
        // redirect()` faria a MESMA mensagem ocorrer 2×, e o ratchet reprova por contagem.
        // Corrigir o docblock seria o certo, mas muda o baseline — decisão separada.
        if (! is_array($template_data)) {
            $template_data = [];
        }

        // P2 — só as chaves que a PRÓPRIA tela oferece. Sem isto, `template_data[qualquer
        // _coisa]` num POST forjado cria linha com `template_for` arbitrário e polui a
        // tabela do negócio (achado A3).
        $permitidas = array_keys(array_merge(...array_values($this->__grupos())));
        $template_data = array_intersect_key($template_data, array_flip($permitidas));

        // P3 — validação server-side de cc/bcc. Antes disto só o `type="email"` do HTML
        // protegia: qualquer POST gravava o que chegasse (achado A2). R8 do charter: é UM
        // endereço por campo, não lista — se um dia virar lista, trocar por regra própria.
        $regras = [];
        foreach (array_keys($template_data) as $key) {
            $regras["template_data.{$key}.cc"] = 'nullable|email';
            $regras["template_data.{$key}.bcc"] = 'nullable|email';
        }
        if ($regras !== []) {
            $request->validate($regras);
        }

        foreach ($template_data as $key => $value) {
            NotificationTemplate::updateOrCreate(
                [
                    'business_id' => $business_id,
                    'template_for' => $key,
                ],
                [
                    'subject' => $value['subject'],
                    'email_body' => $value['email_body'],
                    'sms_body' => $value['sms_body'],
                    'whatsapp_text' => $value['whatsapp_text'],
                    'auto_send' => ! empty($value['auto_send']) ? 1 : 0,
                    'auto_send_sms' => ! empty($value['auto_send_sms']) ? 1 : 0,
                    'auto_send_wa_notif' => ! empty($value['auto_send_wa_notif']) ? 1 : 0,
                    'cc' => $value['cc'],
                    'bcc' => $value['bcc'],
                ]
            );
        }

        // P5 — o `redirect()->back()` mudo deixava o operador sem saber se gravou (achado A5).
        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('lang_v1.updated_success'),
        ]);
    }

    /**
     * P6 — "Enviar teste pra mim" (decisão D3).
     *
     * Manda o modelo escolhido para o e-mail do PRÓPRIO usuário logado, para ele conferir
     * como o texto chega. O destinatário NUNCA vem do request: aceitar destino do cliente
     * transformaria a rota num relay aberto autenticado. O throttle vive na rota
     * (`throttle:6,1`), não aqui, para valer também no 429 antes de tocar o controller.
     */
    public function test(Request $request)
    {
        if (! auth()->user()->can('send_notification')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $permitidas = array_keys(array_merge(...array_values($this->__grupos())));

        $validado = $request->validate([
            'template_for' => ['required', 'string', Rule::in($permitidas)],
            'canal' => 'required|in:email,sms,whatsapp',
        ]);

        $destino = auth()->user()->email;
        if ($validado['canal'] === 'email' && empty($destino)) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => __('lang_v1.no_email_found'),
            ]);
        }

        $modelo = NotificationTemplate::getTemplate($business_id, $validado['template_for']);

        // Sem transação de referência, as tags ficam literais — é o que se quer num teste:
        // o operador vê onde cada tag cai, não um valor inventado que não existe no negócio.
        $dados = $this->notificationUtil->replaceTags($business_id, [
            'email_body' => $modelo['email_body'] ?? '',
            'sms_body' => $modelo['sms_body'] ?? '',
            'subject' => $modelo['subject'] ?? '',
            'whatsapp_text' => $modelo['whatsapp_text'] ?? '',
        ], null);

        $dados['email_settings'] = request()->session()->get('business.email_settings');
        $dados['sms_settings'] = request()->session()->get('business.sms_settings');
        $dados['subject'] = '[teste] '.($dados['subject'] ?? '');

        if ($validado['canal'] === 'email') {
            Notification::route('mail', [$destino])->notify(new CustomerNotification($dados));
        }

        return redirect()->back()->with('status', [
            'success' => 1,
            'msg' => __('lang_v1.test_sent_to', ['destino' => $destino]),
        ]);
    }
}
