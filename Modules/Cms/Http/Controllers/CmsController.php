<?php

namespace Modules\Cms\Http\Controllers;

use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Entities\CmsSiteDetail;
use Modules\Cms\Http\Requests\SubmitContactFormRequest;
use Modules\Cms\Notifications\NewLeadGeneratedNotification;
use Modules\Cms\Services\SiteContentService;
use Modules\Cms\Utils\CmsUtil;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Notification;

class CmsController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $cmsUtil;

    /**
     * Service de conteÃºdo do site pÃºblico (D4.a SoC brutal â€” ADR 0094 Â§5).
     */
    protected SiteContentService $siteContent;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(CmsUtil $cmsUtil, SiteContentService $siteContent)
    {
        $this->cmsUtil = $cmsUtil;
        $this->siteContent = $siteContent;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        // Wave 10 D6.a: 4 props per-key Inertia::defer â€” Site/Home pode renderizar shell
        // imediato e hidratar Testimonials/Faqs/SocialProof/Hero/FeatureGrid em segundo turno.
        // Pattern validado em ContactController@index (Cliente/Index â€” kpis+customers).
        //
        // Site/Home NÃƒO foi alvo do rollback PR #963 (que afetou sÃ³ Blogs/BlogPost/Page) â€”
        // se sintoma "undefined initial render" aparecer, frontend tem <Deferred> wrapper
        // com fallback={null} pra cada seÃ§Ã£o (resources/js/Pages/Site/Home.tsx).
        //
        // SoC: payload continua thin via SiteContentService (D4.a ADR 0094 Â§5).
        return Inertia::render('Site/Home', [
            'page' => Inertia::defer(fn () => $this->buildHomePagePayload()),
            'testimonials' => Inertia::defer(fn () => $this->buildHomeTestimonialsPayload()),
            'faqs' => Inertia::defer(fn () => $this->buildHomeFaqsPayload()),
            'statistics' => Inertia::defer(fn () => $this->buildHomeStatisticsPayload()),
        ]);
    }

    /**
     * Payload deferido da página layout=home (Hero + FeatureGrid).
     * Wrapper sobre SiteContentService — closure só executa quando Inertia pede.
     *
     * D9.a OTel — instrumenta render home pública (hot path, SEO/conversão).
     */
    private function buildHomePagePayload()
    {
        return OtelHelper::spanBiz('cms.home.render', function () {
            return $this->siteContent->getPageByLayout('home');
        }, ['layout' => 'home']);
    }

    /**
     * Payload deferido dos testimonials (type=testimonial).
     * Query independente â€” vai pra <Deferred data="testimonials"> no frontend.
     */
    private function buildHomeTestimonialsPayload()
    {
        return $this->cmsUtil->getPageByType('testimonial');
    }

    /**
     * Payload deferido das FAQs (CmsSiteDetail key=faqs).
     */
    private function buildHomeFaqsPayload()
    {
        return CmsSiteDetail::getValue('faqs');
    }

    /**
     * Payload deferido das statistics (CmsSiteDetail key=statistics).
     */
    private function buildHomeStatisticsPayload()
    {
        return CmsSiteDetail::getValue('statistics');
    }

    /**
     * VersÃ£o Blade legada da home (template UltimatePOS em inglÃªs).
     * Mantida atrÃ¡s de /old durante a transiÃ§Ã£o pra Inertia â€” remover apÃ³s validaÃ§Ã£o.
     */
    public function indexLegacy()
    {
        $testimonials = $this->cmsUtil->getPageByType('testimonial');
        $page = $this->cmsUtil->getPageByLayout('home');
        $faqs = CmsSiteDetail::getValue('faqs');
        $statistics = CmsSiteDetail::getValue('statistics');

        return view('cms::frontend.pages.home')
            ->with(compact('testimonials', 'faqs', 'statistics', 'page'));
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
        //
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

    public function getBlogList()
    {
        // ROLLBACK Wave L/W7 PR #963: Inertia::defer quebrava Pages (initial render undefined).
        return Inertia::render('Site/Blogs', [
            'posts' => $this->buildBlogListPayload(),
        ]);
    }

    /**
     * Payload deferido da lista de blogs (closure executada só quando frontend pede).
     *
     * D9.a OTel — instrumenta listagem pública de blogs (paginação/SEO).
     */
    private function buildBlogListPayload()
    {
        return OtelHelper::spanBiz('cms.blog.list', function () {
            return CmsPage::where('type', 'blog')
                        ->orderBy('priority', 'asc')
                        ->where('is_enabled', 1)
                        ->get();
        });
    }

    /** VersÃ£o Blade legada de /c/blogs â€” em /c/blogs/old durante a transiÃ§Ã£o. */
    public function getBlogListLegacy()
    {
        $blogs = CmsPage::where('type', 'blog')
                    ->orderBy('priority', 'asc')
                    ->where('is_enabled', 1)
                    ->get();

        return view('cms::frontend.blogs.index')
            ->with(compact('blogs'));
    }

    public function viewBlog(Request $request)
    {
        // Resolve id eager (precisa pra findOrFail throw 404 antes da defer).
        $id = $this->cmsUtil->findIdFromGivenUrl($request->url());

        // ROLLBACK Wave L/W7 PR #963: Inertia::defer quebrava Pages (initial render undefined).
        return Inertia::render('Site/BlogPost', [
            'post' => $this->buildBlogPostPayload($id),
        ]);
    }

    /**
     * Payload deferido do blog post individual.
     *
     * D9.a OTel — instrumenta render de blog post (hot path SEO).
     */
    private function buildBlogPostPayload(int $id)
    {
        return OtelHelper::spanBiz('cms.blog.view', function () use ($id) {
            return CmsPage::where('type', 'blog')
                        ->where('is_enabled', 1)
                        ->findOrFail($id);
        }, ['post_id' => $id]);
    }

    /** VersÃ£o Blade legada de /c/blog/{slug}-{id} â€” em /c/blog/{slug}-{id}/old. */
    public function viewBlogLegacy(Request $request)
    {
        $id = $this->cmsUtil->findIdFromGivenUrl($request->url());

        $blog = CmsPage::where('type', 'blog')
                    ->where('is_enabled', 1)
                    ->findOrFail($id);

        return view('cms::frontend.blogs.show')
            ->with(compact('blog'));
    }

    public function contactUs(Request $request)
    {
        $page = $this->cmsUtil->getPageByLayout('contact');

        return view('cms::frontend.pages.contact_us')
            ->with(compact('page'));
    }

    public function postContactForm(SubmitContactFormRequest $request, PiiRedactor $piiRedactor)
    {
        //check if app is in demo & disable action
        $notAllowedInDemo = $this->cmsUtil->notAllowedInDemo();
        if (! empty($notAllowedInDemo)) {
            return $notAllowedInDemo;
        }

        if ($request->ajax()) {
            try {
                // SubmitContactFormRequest ja validou + sanitizou (trim/honeypot)
                $lead_details = $request->validated();
                unset($lead_details['_gotcha']);

                $recipient = CmsSiteDetail::getValue('notifiable_email');

                // D9.a OTel — instrumenta dispatch de notificação de lead (HTTP externo: mail).
                if (! empty($recipient) && ! empty($lead_details['message'])) {
                    OtelHelper::spanBiz('cms.lead.notify', function () use ($recipient, $lead_details) {
                        Notification::route('mail', $recipient)
                            ->notify(new NewLeadGeneratedNotification($lead_details));
                    }, ['has_recipient' => true]);
                }

                // D7.a LGPD â€” log com PII redactada (CPF/CNPJ/email/telefone)
                // antes de gravar trace de lead capturado (skill multi-tenant + ADR 0094 Â§4).
                \Log::info('[cms.lead.captured] novo lead via formulÃ¡rio pÃºblico', [
                    'lead' => $piiRedactor->redactArray($lead_details),
                ]);

                $output = [
                    'success' => true,
                    'msg' => __('cms::lang.we_will_contact_soon'),
                ];
            } catch (Exception $e) {
                // PII redaction defensiva â€” exception pode carregar payload com email/telefone.
                \Log::emergency('[cms.lead.error] '.$piiRedactor->redact(
                    'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage()
                ));
                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
