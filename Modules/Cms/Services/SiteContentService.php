<?php

namespace Modules\Cms\Services;

use App\Util\OtelHelper;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Entities\CmsSiteDetail;
use Modules\Cms\Utils\CmsUtil;

/**
 * SiteContentService — thin service que agrega conteúdo do site público (home, page, blog).
 *
 * Extraído de CmsController/CmsPageController pra:
 *  - Isolar acesso a CmsPage + CmsSiteDetail num único ponto (D4.a SoC brutal — ADR 0094 §5).
 *  - Permitir test isolado por método (Pest) sem boot full HTTP stack.
 *  - Servir como camada candidata pra cache (Cache::remember) e Inertia::defer no futuro.
 *
 * Multi-tenant: CmsPage/CmsSiteDetail seguem o padrão UltimatePOS — query depende de
 * `business_id` quando aplicável. Métodos aqui são thin wrappers — global scope dos
 * Models continua sendo a fonte de verdade (ADR 0093 Tier 0 IRREVOGÁVEL).
 *
 * Skill `multi-tenant-patterns` (Tier A) — toda query nova adicionada aqui deve
 * herdar global scope dos Models, NUNCA usar `withoutGlobalScopes` sem comentário.
 *
 * Observabilidade (D9.a — Wave 17 governance): cada método público envolve seu corpo
 * em `OtelHelper::spanBiz(...)` (zero-cost quando `otel.enabled=false`). Wave 16
 * instrumentou os Controllers, mas `ModuleGradeService::dim9Observability()` mede
 * D9.a sobre `Modules/<X>/Services/` — instrumentar aqui é o que move o score.
 *
 * @see App\Util\OtelHelper
 * @see Modules\Governance\Services\ModuleGradeService::dim9Observability
 */
class SiteContentService
{
    public function __construct(protected CmsUtil $cmsUtil) {}

    /**
     * Payload completo da home pública (/c).
     *
     * Usado por CmsController@index → Inertia::render('Site/Home').
     * Candidato a Inertia::defer no futuro (skill `inertia-defer-default`)
     * caso testimonials/faqs/statistics fiquem caros (ainda thin hoje).
     *
     * @return array{testimonials: mixed, page: mixed, faqs: mixed, statistics: mixed}
     */
    public function getHomePayload(): array
    {
        return OtelHelper::spanBiz('cms.service.home_payload', function () {
            return [
                'testimonials' => $this->cmsUtil->getPageByType('testimonial'),
                'page' => $this->cmsUtil->getPageByLayout('home'),
                'faqs' => CmsSiteDetail::getValue('faqs'),
                'statistics' => CmsSiteDetail::getValue('statistics'),
            ];
        });
    }

    /**
     * Lista de posts (type=blog) habilitados, ordenados por priority asc.
     *
     * Usado por CmsController@getBlogList → Inertia::render('Site/Blogs').
     */
    public function getBlogList()
    {
        return OtelHelper::spanBiz('cms.service.blog_list', function () {
            return CmsPage::where('type', 'blog')
                ->where('is_enabled', 1)
                ->orderBy('priority', 'asc')
                ->get();
        });
    }

    /**
     * Single blog post por id (com type=blog + is_enabled=1).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException quando id inexistente/desabilitado
     */
    public function findBlogPost(int $id): CmsPage
    {
        return OtelHelper::spanBiz('cms.service.blog_find', function () use ($id) {
            return CmsPage::where('type', 'blog')
                ->where('is_enabled', 1)
                ->findOrFail($id);
        }, ['blog_post_id' => $id]);
    }

    /**
     * Conteúdo de página estática (contact/about/etc) por layout key.
     */
    public function getPageByLayout(string $layout)
    {
        return OtelHelper::spanBiz('cms.service.page_by_layout', function () use ($layout) {
            return $this->cmsUtil->getPageByLayout($layout);
        }, ['layout' => $layout]);
    }

    /**
     * Wave 28 D9 — render chrome do site público (cabeçalho + rodapé + site_details GLOBAL).
     *
     * Agrega CmsSiteDetail (chaves de configuração global do site oimpresso.com) num único
     * payload reaproveitável pelo Inertia shell (header/footer). Span dedicado isola
     * latência de leitura dos settings vs render principal da página (cms.page.render).
     *
     * Multi-tenant: CmsSiteDetail é GLOBAL (site oimpresso.com é único — US-CMS-002 schema
     * pendente IRREVOGÁVEL, ver Wave 26 D1 guard). NÃO scope business_id aqui.
     *
     * Wave 28 — adiciona o 5º span canon de SiteContentService (D9 saturation +1).
     *
     * @return array{site_name:?string, logo:?string, contact_email:?string, social:?string}
     */
    public function getRenderChromePayload(): array
    {
        return OtelHelper::spanBiz('cms.service.render_chrome', function () {
            return [
                'site_name'     => CmsSiteDetail::getValue('site_name'),
                'logo'          => CmsSiteDetail::getValue('logo'),
                'contact_email' => CmsSiteDetail::getValue('contact_email'),
                'social'        => CmsSiteDetail::getValue('social'),
            ];
        });
    }
}
