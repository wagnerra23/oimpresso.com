<?php

namespace Modules\Cms\Services;

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
        return [
            'testimonials' => $this->cmsUtil->getPageByType('testimonial'),
            'page' => $this->cmsUtil->getPageByLayout('home'),
            'faqs' => CmsSiteDetail::getValue('faqs'),
            'statistics' => CmsSiteDetail::getValue('statistics'),
        ];
    }

    /**
     * Lista de posts (type=blog) habilitados, ordenados por priority asc.
     *
     * Usado por CmsController@getBlogList → Inertia::render('Site/Blogs').
     */
    public function getBlogList()
    {
        return CmsPage::where('type', 'blog')
            ->where('is_enabled', 1)
            ->orderBy('priority', 'asc')
            ->get();
    }

    /**
     * Single blog post por id (com type=blog + is_enabled=1).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException quando id inexistente/desabilitado
     */
    public function findBlogPost(int $id): CmsPage
    {
        return CmsPage::where('type', 'blog')
            ->where('is_enabled', 1)
            ->findOrFail($id);
    }

    /**
     * Conteúdo de página estática (contact/about/etc) por layout key.
     */
    public function getPageByLayout(string $layout)
    {
        return $this->cmsUtil->getPageByLayout($layout);
    }
}
