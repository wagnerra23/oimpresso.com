<?php

declare(strict_types=1);

namespace Modules\Cms\Services;

use App\Util\OtelHelper;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Entities\CmsPageMeta;
use Modules\Cms\Repositories\CmsPageRepository;

/**
 * CmsPageService — orquestração CRUD de CmsPage (Wave 18 D4 saturação).
 *
 * Extraído de `CmsPageController` pra:
 *  - SoC brutal (ADR 0094 §5): Controller fica thin (HTTP only), Service orquestra,
 *    Repository encapsula query, Entity guarda comportamento intrínseco.
 *  - Permitir test isolado de regras de negócio sem boot full HTTP stack.
 *  - Centralizar log estruturado + OTel spans num único ponto (DRY).
 *
 * Multi-tenant Tier 0 (ADR 0093): created_by vem da session; quando cms_pages
 * ganhar `business_id` (US-CMS-002), Service passa a injetar businessId no
 * `create()` automaticamente — Controller continua thin.
 *
 * @see Modules\Cms\Http\Controllers\CmsPageController
 * @see Modules\Cms\Repositories\CmsPageRepository
 * @see Modules\Cms\Tests\Feature\CmsPageServiceTest
 */
class CmsPageService
{
    public function __construct(
        protected CmsPageRepository $pageRepo,
        protected Util $commonUtil,
    ) {}

    /**
     * Cria nova página/post a partir do payload validado (StoreCmsPageRequest).
     *
     * @param  array<string, mixed>  $input  campos já filtrados pelo FormRequest
     * @param  int|null  $createdBy  user id (session)
     * @param  Request|null  $request  pra upload de feature_image (legacy commonUtil)
     */
    public function criar(array $input, ?int $createdBy = null, ?Request $request = null): CmsPage
    {
        return OtelHelper::spanBiz('cms.service.page.criar', function () use ($input, $createdBy, $request) {
            $input['created_by'] = $createdBy;
            $input['is_enabled'] = ! empty($input['is_enabled']);

            if ($request !== null && $request->hasFile('feature_image')) {
                $input['feature_image'] = $this->commonUtil->uploadFile($request, 'feature_image', 'cms', 'image');
            }

            $page = CmsPage::create($input);

            Log::info('cms.page.created', [
                'biz' => session('user.business_id'),
                'page_id' => $page->id,
                'type' => $input['type'] ?? 'page',
                'created_by' => $createdBy,
            ]);

            return $page;
        }, [
            'page_type' => $input['type'] ?? 'page',
            'is_enabled' => (bool) ($input['is_enabled'] ?? false),
        ]);
    }

    /**
     * Atualiza página existente + sync de meta tags (CmsPageMeta).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, array<string, mixed>>|null  $metas
     */
    public function atualizar(int $id, array $input, ?array $metas = null, ?Request $request = null): CmsPage
    {
        return OtelHelper::spanBiz('cms.service.page.atualizar', function () use ($id, $input, $metas, $request) {
            $page = CmsPage::findOrFail($id);

            if ($request !== null && $request->hasFile('feature_image')) {
                $input['feature_image'] = $this->commonUtil->uploadFile($request, 'feature_image', 'cms', 'image');

                if (! empty($page->feature_image_path) && file_exists($page->feature_image_path)) {
                    @unlink($page->feature_image_path);
                }
            }

            $input['is_enabled'] = ! empty($input['is_enabled']);

            $page->update($input);

            if (! empty($metas)) {
                CmsPageMeta::updateOrCreateMetaForPage($metas, $id);
            }

            Log::info('cms.page.updated', [
                'biz' => session('user.business_id'),
                'page_id' => (int) $id,
                'is_enabled' => (bool) $input['is_enabled'],
            ]);

            return $page;
        }, [
            'page_id' => $id,
            'page_type' => $input['type'] ?? 'page',
            'has_meta' => ! empty($metas),
        ]);
    }

    /**
     * Remove página + arquivo físico de feature_image se existir.
     *
     * @return bool true em sucesso
     */
    public function remover(int $id, string $type): bool
    {
        return OtelHelper::spanBiz('cms.service.page.remover', function () use ($id, $type) {
            $page = CmsPage::where('type', $type)->findOrFail($id);

            if (! empty($page->feature_image_path) && file_exists($page->feature_image_path)) {
                @unlink($page->feature_image_path);
            }

            $deleted = (bool) $page->delete();

            Log::info('cms.page.deleted', [
                'biz' => session('user.business_id'),
                'page_id' => (int) $id,
                'type' => $type,
            ]);

            return $deleted;
        }, ['page_id' => $id, 'page_type' => $type]);
    }

    /**
     * Wrapper repository — exposto pra Controller chamar listagem admin.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CmsPage>
     */
    public function listarPorTipo(string $type)
    {
        return $this->pageRepo->porTipo($type);
    }
}
