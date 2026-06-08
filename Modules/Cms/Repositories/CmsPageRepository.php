<?php

declare(strict_types=1);

namespace Modules\Cms\Repositories;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Cms\Entities\CmsPage;

/**
 * CmsPageRepository — isolamento de queries Eloquent de CmsPage (Wave 18 D4 saturação).
 *
 * Antes (Wave 17): Controllers + Service chamavam `CmsPage::where(...)` direto.
 * Depois (Wave 18): Repository encapsula queries; Service chama Repository.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 * - As tabelas `cms_pages`/`cms_site_details` HOJE não possuem coluna `business_id`
 *   (gap conhecido US-CMS-002, ver `Tests/Feature/MultiTenantSlugIsolationTest.php`
 *   markTestSkipped). Quando a migration adicionar coluna, este Repository é o
 *   único ponto que precisa ganhar `where('business_id', $businessId)` — antes
 *   estava espalhado em 5 Controllers + 1 Service.
 * - SoC brutal (ADR 0094 §5): queries ficam aqui; orquestração no Service;
 *   I/O HTTP no Controller.
 *
 * Padrão validado em `Modules/Woocommerce/Repositories/WoocommerceSyncLogRepository.php`.
 *
 * Observabilidade (D9.a — Wave 17): cada método público envolve seu corpo em
 * `OtelHelper::spanBiz(...)` (zero-cost quando `otel.enabled=false`).
 *
 * @see Modules\Cms\Services\CmsPageService
 * @see Modules\Cms\Services\SiteContentService
 * @see App\Util\OtelHelper
 * @see Modules\Cms\Tests\Feature\CmsRepositoryTest
 */
class CmsPageRepository
{
    /**
     * Lista páginas por tipo, opcionalmente filtrando por status.
     *
     * @param  string  $type  page|post|banner|blog|testimonial
     * @param  bool|null  $onlyEnabled  null=todos, true=is_enabled=1, false=is_enabled=0
     * @return Collection<int, CmsPage>
     */
    public function porTipo(string $type, ?bool $onlyEnabled = null): Collection
    {
        return OtelHelper::spanBiz('cms.repo.por_tipo', function () use ($type, $onlyEnabled) {
            $query = $this->baseQuery()->where('type', $type)->orderBy('priority', 'asc');

            if ($onlyEnabled !== null) {
                $query->where('is_enabled', $onlyEnabled ? 1 : 0);
            }

            return $query->get();
        }, ['type' => $type, 'only_enabled' => $onlyEnabled]);
    }

    /**
     * Busca página pelo título (canonical slug-source).
     */
    public function porTitulo(string $title): ?CmsPage
    {
        return OtelHelper::spanBiz('cms.repo.por_titulo', function () use ($title) {
            return $this->baseQuery()->where('title', $title)->first();
        }, ['title' => $title]);
    }

    /**
     * Carrega página com pageMeta eager-loaded (anti-N+1 ADR 0094 §6).
     */
    public function porTituloComMeta(string $title): ?CmsPage
    {
        return OtelHelper::spanBiz('cms.repo.por_titulo_com_meta', function () use ($title) {
            return $this->baseQuery()
                ->with(['pageMeta'])
                ->where('title', $title)
                ->first();
        }, ['title' => $title]);
    }

    /**
     * Busca blog post habilitado por id (lança ModelNotFound se inexistente).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function blogHabilitado(int $id): CmsPage
    {
        return OtelHelper::spanBiz('cms.repo.blog_habilitado', function () use ($id) {
            return $this->baseQuery()
                ->where('type', 'blog')
                ->where('is_enabled', 1)
                ->findOrFail($id);
        }, ['id' => $id]);
    }

    /**
     * Conta páginas por tipo (admin dashboard widgets).
     */
    public function contarPorTipo(string $type, bool $onlyEnabled = true): int
    {
        return OtelHelper::spanBiz('cms.repo.contar_por_tipo', function () use ($type, $onlyEnabled) {
            $query = $this->baseQuery()->where('type', $type);

            if ($onlyEnabled) {
                $query->where('is_enabled', 1);
            }

            return $query->count();
        }, ['type' => $type, 'only_enabled' => $onlyEnabled]);
    }

    /**
     * Query base — ponto único pra adicionar `business_id` global scope quando
     * US-CMS-002 for entregue (hoje retorna query sem scope, equivalente ao
     * comportamento atual dos Controllers).
     *
     * @return Builder<CmsPage>
     */
    protected function baseQuery(): Builder
    {
        return CmsPage::query();
    }
}
