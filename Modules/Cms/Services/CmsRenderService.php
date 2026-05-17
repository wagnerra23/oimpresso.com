<?php

declare(strict_types=1);

namespace Modules\Cms\Services;

use App\Util\OtelHelper;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Entities\CmsSiteDetail;

/**
 * CmsRenderService — Wave 27 D9.a polish (2026-05-17).
 *
 * Thin service que consolida operações de renderização do site público que
 * ainda viviam em Controllers ou Utils. Introduzido pra:
 *
 *  1. **D9.a observability** — span por operação de leitura cara
 *     (`OtelHelper::spanBiz` zero-cost quando `otel.enabled=false`). Wave 25
 *     mostrou que score D9 sobe quando há ≥3 Services instrumentados
 *     (ModuleGradeService::dim9Observability mede `Modules/<X>/Services/`).
 *
 *  2. **SoC brutal** (ADR 0094 §5) — separar "leitura/render" (idempotente,
 *     cacheable) de "escrita/CRUD" (CmsPageService). Permite no futuro
 *     wrap em `Cache::remember(...)` sem mexer no Controller.
 *
 *  3. **Multi-tenant Tier 0** (ADR 0093) — quando US-CMS-002 entregar
 *     `business_id` em `cms_pages`, este service vira o ponto único pra
 *     resolver o site público por host/biz sem espalhar lógica.
 *
 * **Limitação conhecida — schema CMS atual:**
 * `cms_pages.business_id` AUSENTE intencionalmente (gap rastreado US-CMS-002).
 * Site público é GLOBAL — todos os businesses servem o mesmo conteúdo
 * institucional. Este service NÃO adiciona escopo de business; quando schema
 * mudar, atualizamos só aqui (Controllers permanecem thin).
 *
 * @see Modules\Cms\Services\SiteContentService (home/blog payload)
 * @see Modules\Cms\Services\CmsPageService (CRUD)
 * @see App\Util\OtelHelper
 * @see memory/decisions/0155-module-grade-v3.md D9.a
 */
class CmsRenderService
{
    /**
     * Resolve metadados <head> (SEO) de uma página pra inject server-side.
     *
     * Retorna array compatível com helper `<Head>` Inertia:
     *   ['title' => ..., 'description' => ..., 'tags' => [...]]
     *
     * Pega title da página + fallback pro site default + meta_description
     * limitada (Google trunca em 160 chars). Span:
     * `cms.render.meta_for_page`.
     *
     * @param  int  $pageId  CmsPage id
     * @return array{title: string, description: string, tags: array<int, string>}
     */
    public function metaForPage(int $pageId): array
    {
        return OtelHelper::spanBiz('cms.render.meta_for_page', function () use ($pageId) {
            $page = CmsPage::find($pageId);

            if ($page === null) {
                return $this->siteDefaultMeta();
            }

            return [
                'title'       => (string) ($page->title ?: $this->siteDefaultMeta()['title']),
                'description' => $this->truncateMeta((string) ($page->meta_description ?? '')),
                'tags'        => array_values(array_filter(
                    array_map('trim', explode(',', (string) ($page->tags ?? '')))
                )),
            ];
        }, ['page_id' => $pageId]);
    }

    /**
     * Resolve metadados default do site (fallback quando página não tem).
     * Span: `cms.render.site_default_meta`.
     *
     * @return array{title: string, description: string, tags: array<int, string>}
     */
    public function siteDefaultMeta(): array
    {
        return OtelHelper::spanBiz('cms.render.site_default_meta', function () {
            $title = (string) (CmsSiteDetail::getValue('site_title') ?? 'oimpresso');
            $desc  = $this->truncateMeta((string) (CmsSiteDetail::getValue('site_description') ?? ''));

            return [
                'title'       => $title,
                'description' => $desc,
                'tags'        => [],
            ];
        });
    }

    /**
     * Resolve snippets de tracking aprovados pra <head> (GA/Pixel/custom_js).
     *
     * Retorna apenas snippets NÃO vazios — caller faz `@inject` no template.
     * Span: `cms.render.tracking_snippets`.
     *
     * **Segurança:** este service NÃO sanitiza HTML — apenas LÊ valores já
     * salvos via `UpdateCmsSiteDetailsRequest` (que limita tamanho). Caller
     * tem responsabilidade de @raw apenas em contexto superadmin-approved.
     *
     * @return array<string, string>
     */
    public function trackingSnippets(): array
    {
        return OtelHelper::spanBiz('cms.render.tracking_snippets', function () {
            $snippets = [
                'google_analytics' => (string) (CmsSiteDetail::getValue('google_analytics') ?? ''),
                'fb_pixel'         => (string) (CmsSiteDetail::getValue('fb_pixel') ?? ''),
                'custom_js'        => (string) (CmsSiteDetail::getValue('custom_js') ?? ''),
                'custom_css'       => (string) (CmsSiteDetail::getValue('custom_css') ?? ''),
            ];

            return array_filter($snippets, fn ($v) => $v !== '');
        });
    }

    /**
     * Trunca meta_description ≤160 chars (limite SEO). Helper interno.
     */
    protected function truncateMeta(string $text): string
    {
        $text = trim(strip_tags($text));
        if (mb_strlen($text) <= 160) {
            return $text;
        }

        return mb_substr($text, 0, 157) . '...';
    }
}
