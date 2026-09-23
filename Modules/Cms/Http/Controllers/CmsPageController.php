<?php

namespace Modules\Cms\Http\Controllers;

use App\Util\OtelHelper;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Entities\CmsPageMeta;
use Modules\Cms\Http\Requests\StoreCmsPageRequest;
use Modules\Cms\Http\Requests\UpdateCmsPageRequest;
use App\Support\Privacy\PiiRedactor;

class CmsPageController extends Controller
{
    protected $commonUtil;

    /**
     * PiiRedactor canônico — D7.a LGPD em logs (conteúdo CMS pode incluir
     * texto livre com email/telefone do autor/editor).
     */
    protected PiiRedactor $piiRedactor;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    protected \Modules\Cms\Services\SiteContentService $siteContent;

    public function __construct(Util $commonUtil, PiiRedactor $piiRedactor, \Modules\Cms\Services\SiteContentService $siteContent)
    {
        $this->commonUtil = $commonUtil;
        $this->piiRedactor = $piiRedactor;
        $this->siteContent = $siteContent;
    }

    /**
     * Lista do conteúdo do site (Inertia `Admin/Content/Index`).
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        // Thread Cms/01 — a lista sai do Blade (cms::page.index) e vira Inertia.
        // create/edit seguem Blade nesta fase (RUNBOOK-admin-content.md §Fases).
        // Tipo fora do domínio cai em `page`: a tela nunca mostra enum cru (A1 do F1).
        $tipo = in_array($request->get('type'), self::TIPOS, true) ? $request->get('type') : 'page';

        $contagens = CmsPage::whereIn('type', self::TIPOS)
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return Inertia::render('Admin/Content/Index', [
            'tipo' => $tipo,
            'contagens' => collect(self::TIPOS)->mapWithKeys(fn ($t) => [$t => (int) ($contagens[$t] ?? 0)]),
            'paginas' => Inertia::defer(fn () => $this->buildListaPayload($tipo)),
        ]);
    }

    /** Domínio real de `cms_pages.type` — nav.blade.php + CmsController (pedido [CC] §3.b). */
    private const TIPOS = ['page', 'blog', 'testimonial'];

    /**
     * Linhas da lista. `priority` asc com vazio no fim (R6) — o `orderBy` cru do Blade
     * punha NULL primeiro no MySQL.
     */
    private function buildListaPayload(string $tipo): array
    {
        return CmsPage::where('type', $tipo)
            ->orderByRaw('priority IS NULL, priority ASC')
            ->get()
            ->map(fn (CmsPage $p) => [
                'id' => $p->id,
                'titulo' => $p->title,
                'prioridade' => $p->priority,
                'publicada' => (bool) $p->is_enabled,
                // R3: layout preenchido = página de sistema, sem excluir.
                'sistema' => ! empty($p->layout),
                'sem_descricao' => trim((string) $p->meta_description) === '',
                'criada_em' => optional($p->created_at)->toIso8601String(),
                'endereco' => match ($tipo) {
                    'page' => '/c/page/'.$p->slug,
                    'blog' => '/c/blog/'.$p->slug.'-'.$p->id,
                    default => null,
                },
            ])
            ->all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $post_type = $request->get('type', 'page');

        return view('cms::page.create')
            ->with(compact('post_type'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(StoreCmsPageRequest $request)
    {
        // Rules basicas (title obrigatorio, feature_image valida) em StoreCmsPageRequest::rules().
        //check if app is in demo & disable action
        $notAllowedInDemo = $this->commonUtil->notAllowedInDemo();
        if (! empty($notAllowedInDemo)) {
            return $notAllowedInDemo;
        }

        try {
            $input = $request->only(['title', 'content', 'meta_description',
                'tags', 'priority', 'type',
            ]);

            $input['created_by'] = $request->session()->get('user.id');

            $input['feature_image'] = $this->commonUtil->uploadFile($request, 'feature_image', 'cms', 'image');

            $input['is_enabled'] = ! empty($request->is_enabled);

            // D9.a OTel — instrumenta criação de página CMS (operação de escrita crítica).
            $page = OtelHelper::spanBiz('cms.page.create', function () use ($input) {
                return CmsPage::create($input);
            }, [
                'page_type' => $input['type'] ?? 'page',
                'is_enabled' => (bool) ($input['is_enabled'] ?? false),
            ]);

            // D9.b Log estruturado — evento de auditoria (não-crítico, sem span completo).
            Log::info('cms.page.created', [
                'biz' => session('user.business_id'),
                'page_id' => $page->id,
                'type' => $input['type'] ?? 'page',
                'created_by' => $input['created_by'] ?? null,
            ]);

            $output = ['success' => 1,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            // D7.a LGPD — exception pode carregar conteúdo CMS com PII em texto livre.
            \Log::emergency('[cms.page.error] '.$this->piiRedactor->redact(
                'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage()
            ));

            $output = ['success' => 0,
                'msg' => __('lang_v1.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'index'], ['type' => $input['type'] ?? 'page'])
            ->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function showPage($page_title)
    {
        $title = str_replace('-', ' ', $page_title);

        // Pre-check existência eager (404 deve vir antes de defer pra não vazar shell).
        $exists = CmsPage::where('title', $title)->exists();

        if (! $exists) {
            abort(404);
        }

        // ROLLBACK Wave L/W7 PR #963: Inertia::defer quebrava Pages (initial render undefined).
        return Inertia::render('Site/Page', [
            'page' => $this->buildPagePayload($title),
        ]);
    }

    /**
     * Payload deferido da página CMS (eager-load pageMeta).
     *
     * D9.a OTel — instrumenta render público (latência sensível pra SEO + UX).
     */
    private function buildPagePayload(string $title)
    {
        return OtelHelper::spanBiz('cms.page.render', function () use ($title) {
            $page = CmsPage::with(['pageMeta'])
                        ->where('title', $title)
                        ->first();

            if ($page === null) {
                return null;
            }

            // XSS: sanitiza o HTML in-place (Site/Page usa dangerouslySetInnerHTML).
            // getAttribute: coluna `content` é magic property Eloquent (PHPStan).
            $page->setAttribute('content', $this->siteContent->sanitizeHtml($page->getAttribute('content')));

            return $page;
        }, ['page_title' => $title]);
    }

    /** Versão Blade legada de /c/page/{slug} — em /c/page/{slug}/old. */
    public function showPageLegacy($page_title)
    {
        $title = str_replace('-', ' ', $page_title);

        $page = CmsPage::where('title', $title)
                    ->first();

        if (empty($page)) {
            abort(404);
        }

        return view('cms::frontend.pages.custom_view')
        ->with(compact('page'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $post_type = request()->get('type', 'page');

        $page = CmsPage::where('type', $post_type)
                    ->findOrFail($id);

        $page_meta = CmsPageMeta::where('cms_page_id', $id)
                        ->get()
                        ->keyBy('meta_key');

        return view('cms::page.edit')
            ->with(compact('page', 'post_type', 'page_meta'));
    }

    /**
     * Update the specified resource in storage.
     *
     * D8.c Wave 17: validation extraída pra UpdateCmsPageRequest.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(UpdateCmsPageRequest $request, $id)
    {
        //check if app is in demo & disable action
        $notAllowedInDemo = $this->commonUtil->notAllowedInDemo();
        if (! empty($notAllowedInDemo)) {
            return $notAllowedInDemo;
        }

        try {
            $input = $request->only(['title', 'content', 'meta_description',
                'tags', 'priority', 'type',
            ]);

            $page = CmsPage::findOrFail($id);

            if ($request->hasFile('feature_image')) {
                $input['feature_image'] = $this->commonUtil->uploadFile($request, 'feature_image', 'cms', 'image');
                //delete previous feature image from storage
                if (! empty($page->feature_image_path) && file_exists($page->feature_image_path)) {
                    unlink($page->feature_image_path);
                }
            }

            $input['is_enabled'] = ! empty($request->is_enabled);

            // D9.a OTel — instrumenta update de página + sync meta (operação crítica).
            OtelHelper::spanBiz('cms.page.update', function () use ($page, $input, $request, $id) {
                $page->update($input);

                if (! empty($request->input('meta'))) {
                    CmsPageMeta::updateOrCreateMetaForPage($request->input('meta'), $id);
                }
            }, [
                'page_id' => (int) $id,
                'page_type' => $input['type'] ?? 'page',
                'has_meta' => ! empty($request->input('meta')),
            ]);

            // D9.b Log estruturado — toggle is_enabled é evento relevante (LGPD Art. 37 audit).
            Log::info('cms.page.updated', [
                'biz' => session('user.business_id'),
                'page_id' => (int) $id,
                'is_enabled' => (bool) $input['is_enabled'],
            ]);

            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            // D7.a LGPD — exception pode carregar conteúdo CMS com PII em texto livre.
            \Log::emergency('[cms.page.error] '.$this->piiRedactor->redact(
                'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage()
            ));

            $output = ['success' => 0,
                'msg' => __('lang_v1.something_went_wrong'),
            ];
        }

        return redirect()
            ->back()
            ->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //check if app is in demo & disable action
        $notAllowedInDemo = $this->commonUtil->notAllowedInDemo();
        if (! empty($notAllowedInDemo)) {
            return $notAllowedInDemo;
        }

        if (request()->ajax()) {
            try {
                $post_type = request()->get('type');

                $page = CmsPage::where('type', $post_type)
                        ->findOrFail($id);

                // D9.a OTel — instrumenta delete (operação destrutiva crítica).
                OtelHelper::spanBiz('cms.page.delete', function () use ($page) {
                    if (! empty($page->feature_image_path) && file_exists($page->feature_image_path)) {
                        unlink($page->feature_image_path);
                    }

                    $page->delete();
                }, [
                    'page_id' => (int) $page->id,
                    'page_type' => (string) $post_type,
                ]);

                // D9.b Log estruturado — delete obrigatório (audit trail LGPD Art. 37/38).
                Log::info('cms.page.deleted', [
                    'biz' => session('user.business_id'),
                    'page_id' => (int) $id,
                    'type' => $post_type,
                ]);

                $output = ['success' => true,
                    'msg' => __('unit.deleted_success'),
                ];
            } catch (\Exception $e) {
                // D7.a LGPD — exception pode carregar conteúdo CMS com PII em texto livre.
            \Log::emergency('[cms.page.error] '.$this->piiRedactor->redact(
                'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage()
            ));

                $output = ['success' => false,
                    'msg' => '__("messages.something_went_wrong")',
                ];
            }

            return $output;
        }
    }
}
