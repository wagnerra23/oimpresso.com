<?php

namespace Modules\Cms\Http\Controllers;

use App\Util\OtelHelper;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Modules\Cms\Entities\CmsSiteDetail;
use Modules\Cms\Http\Requests\StoreCmsSettingsRequest;
use App\Support\Privacy\PiiRedactor;

class SettingsController extends Controller
{
    protected $commonUtil;

    /**
     * PiiRedactor canônico (Modules/Jana) — D7.a LGPD em logs Cms admin
     * (site details podem conter `notifiable_email` / `mail_us` / `contact_us`).
     */
    protected PiiRedactor $piiRedactor;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(Util $commonUtil, PiiRedactor $piiRedactor)
    {
        $this->commonUtil = $commonUtil;
        $this->piiRedactor = $piiRedactor;
    }

    /**
     * Detalhes do site. Thread Cms/01 fase 4a: Inertia `Admin/SiteDetails/Index` com as seções
     * Aplicação, Contato, Redes sociais e Integrações. Estatísticas, FAQs, Chat e Botões seguem na
     * Blade (`?legado=1`) até a fase 4b — a gravação é por chave, então salvar uma não apaga a outra.
     *
     * @return \Inertia\Response|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        if (! request()->boolean('legado')) {
            return Inertia::render('Admin/SiteDetails/Index', [
                'detalhes' => Inertia::defer(fn () => $this->buildDetalhesPayload()),
            ]);
        }

        $details = CmsSiteDetail::getSiteDetails();
        $logo = CmsSiteDetail::getValue('logo', false);

        return view('cms::settings.index')
            ->with(compact('details', 'logo'));
    }

    /** Só as chaves das seções já migradas; formato = o que o Model grava (S1). */
    private function buildDetalhesPayload(): array
    {
        $d = CmsSiteDetail::getSiteDetails();
        $lista = fn ($v, int $n, array $campos) => collect(range(0, $n - 1))
            ->map(fn ($i) => collect($campos)->mapWithKeys(fn ($c) => [$c => (string) ($v[$i][$c] ?? '')])->all())
            ->all();

        return [
            'notifiable_email' => (string) ($d['notifiable_email'] ?? ''),
            'logo_url' => optional(CmsSiteDetail::getValue('logo', false))->logo_url,
            'contact_us' => $lista($d['contact_us'] ?? [], 3, ['label', 'num']),
            'mail_us' => $lista($d['mail_us'] ?? [], 2, ['label', 'email']),
            'follow_us' => collect(['facebook', 'instagram', 'linkedin', 'twitter', 'youtube'])
                ->mapWithKeys(fn ($r) => [$r => (string) ($d['follow_us'][$r] ?? '')])->all(),
            'google_analytics' => (string) ($d['google_analytics'] ?? ''),
            'fb_pixel' => (string) ($d['fb_pixel'] ?? ''),
            'custom_js' => (string) ($d['custom_js'] ?? ''),
            'custom_css' => (string) ($d['custom_css'] ?? ''),
            'meta_tags' => (string) ($d['meta_tags'] ?? ''),
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('cms::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * D8.c Wave 17: validation extraída pra StoreCmsSettingsRequest
     * (limites max em custom_js/custom_css agora obrigatórios — antes não havia
     * validação alguma, vide PR/runbook governance v3).
     *
     * @return Response
     */
    public function store(StoreCmsSettingsRequest $request)
    {
        //check if app is in demo & disable action
        $notAllowedInDemo = $this->commonUtil->notAllowedInDemo();
        if (! empty($notAllowedInDemo)) {
            return $notAllowedInDemo;
        }

        $business_id = request()->session()->get('user.business_id');

        try {
            DB::beginTransaction();

            $site_details = $request->only(['faqs', 'statistics', 'google_analytics', 'fb_pixel',
                'custom_js', 'custom_css', 'meta_tags', 'chat_widget', 'contact_us', 'mail_us',
                'follow_us', 'notifiable_email', 'btns', 'chat', ]);

            $logo = CmsSiteDetail::getValue('logo', false);
            $site_details['logo'] = $this->__uploadFile($request, $logo);

            // D9.a OTel — instrumenta save de site details (operação admin crítica).
            OtelHelper::spanBiz('cms.settings.save', function () use ($site_details) {
                CmsSiteDetail::createOrUpdateSiteDetails($site_details);
            }, ['keys_count' => count($site_details)]);

            DB::commit();

            // D9.b Log estruturado — mudança de config admin (audit trail).
            Log::info('cms.settings.saved', [
                'biz' => $business_id,
                'keys' => array_keys($site_details),
            ]);
            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Cms\Http\Controllers\SettingsController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) { // era `Exception` sem barra: no namespace do módulo não casava nada
            DB::rollBack();
            // D7.a LGPD — exception message pode conter email/telefone (settings carregam contact_us/mail_us).
            \Log::emergency('[cms.settings.error] '.$this->piiRedactor->redact(
                'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage()
            ));
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output);
        }
    }

    private function __uploadFile($request, $logo)
    {
        $file = $request->file('logo');
        $file_name = null;

        if (! empty($file) && ! empty($logo->logo_path)) {
            $this->__removeFile($logo->logo_path);
        }

        if (! empty($logo) && ! empty($logo->site_value)) {
            $file_name = json_decode($logo->site_value, true);
        }

        if (
            ! empty($file) &&
            (
                $file->getSize() <= config('constants.document_size_limit')
            )
        ) {
            $new_file_name = 'logo.'.$file->getClientOriginalExtension();
            if ($file->storeAs('/cms', $new_file_name)) {
                $file_name = $new_file_name;
            }
        }

        return $file_name;
    }

    private function __removeFile($img_path)
    {
        if (! empty($img_path) && file_exists($img_path)) {
            unlink($img_path);
        }
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return view('cms::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('cms::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
