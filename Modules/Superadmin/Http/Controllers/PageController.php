<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Modules\Superadmin\Entities\SuperadminFrontendPage;
use Modules\Superadmin\Support\RedactsPiiInLogs;

class PageController extends Controller
{
    use RedactsPiiInLogs;

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Lista de páginas do site — tela React `superadmin/Paginas/Index` (cutover MWART F5,
     * 2026-10-27). Criar e editar são o drawer da própria tela: as Blades `pages.index`,
     * `pages.create` e `pages.edit` saíram, e as rotas `create`/`edit`/`show` do resource também.
     */
    public function index(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('superadmin/Paginas/Index', [
            'paginas' => Inertia::defer(fn () => $this->paginasPayload()),
        ]);
    }

    /**
     * Payload da tela. Tabela global do site (sem business_id — ADR 0093 §exceções
     * Superadmin). `resumo` vai sem tag: a lista não imprime o HTML da página.
     */
    private function paginasPayload(): array
    {
        return SuperadminFrontendPage::orderBy('menu_order', 'asc')->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'titulo' => (string) $p->title,
                'slug' => (string) $p->slug,
                'ordem' => (int) $p->menu_order,
                'visivel' => (int) $p->is_shown === 1,
                'resumo' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $p->content))), 160),
                'conteudo' => (string) $p->content,
            ])->values()->all();
    }

    /** Resposta da tela: slug repetido vira erro de campo; sucesso volta para a lista. */
    private function respostaInertia(array $output)
    {
        if (empty($output['success'])) {
            return back()->withErrors(['slug' => $output['msg']]);
        }

        return redirect()->action([self::class, 'index']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['title', 'slug', 'content', 'menu_order']);

            $input['slug'] = Str::slug($input['slug']);
            $input['is_shown'] = empty($request->input('is_shown')) ? 0 : 1;
            $input['menu_order'] = empty($input['menu_order']) ? 0 : $input['menu_order'];

            $is_slug_exists = SuperadminFrontendPage::where('slug', $input['slug'])->exists();
            if (! $is_slug_exists) {
                SuperadminFrontendPage::create($input);
                $output = ['success' => 1, 'msg' => __('lang_v1.success')];
            } else {
                $output = ['success' => 0, 'msg' => __('superadmin::lang.slug_already_exists')];
            }
        } catch (\Exception $e) {
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->logEmergencyRedacted($e, 'PageController');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if ($request->header('X-Inertia')) {
            return $this->respostaInertia($output);
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\PageController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function showPage($slug)
    {
        $page = SuperadminFrontendPage::where('slug', $slug)->first();

        if (! empty($page)) {
            return view('superadmin::pages.show')->with(compact('page'));
        } else {
            abort(404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['title', 'slug', 'content', 'menu_order']);

            $input['slug'] = Str::slug($input['slug']);
            $input['is_shown'] = empty($request->input('is_shown')) ? 0 : 1;

            $input['menu_order'] = empty($input['menu_order']) ? 0 : $input['menu_order'];
            $is_slug_exists = SuperadminFrontendPage::where('id', '!=', $id)
                                    ->where('slug', $input['slug'])
                                    ->exists();

            if (! $is_slug_exists) {
                SuperadminFrontendPage::where('id', $id)->update($input);
                $output = ['success' => 1, 'msg' => __('lang_v1.success')];
            } else {
                $output = ['success' => 0, 'msg' => __('superadmin::lang.slug_already_exists')];
            }
        } catch (\Exception $e) {
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->logEmergencyRedacted($e, 'PageController');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if ($request->header('X-Inertia')) {
            return $this->respostaInertia($output);
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\PageController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            SuperadminFrontendPage::where('id', $id)
                ->delete();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->logEmergencyRedacted($e, 'PageController');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if ($request->header('X-Inertia')) {
            return $this->respostaInertia($output);
        }

        return $output;
    }
}
