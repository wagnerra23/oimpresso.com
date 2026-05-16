<?php

namespace Modules\Cms\Http\Controllers;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Cms\Entities\CmsSiteDetail;
use Modules\Jana\Services\Privacy\PiiRedactor;

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
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $details = CmsSiteDetail::getSiteDetails();
        $logo = CmsSiteDetail::getValue('logo', false);

        return view('cms::settings.index')
            ->with(compact('details', 'logo'));
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
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
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

            CmsSiteDetail::createOrUpdateSiteDetails($site_details);

            DB::commit();
            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Cms\Http\Controllers\SettingsController::class, 'index'])
                ->with('status', $output);
        } catch (Exception $e) {
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
