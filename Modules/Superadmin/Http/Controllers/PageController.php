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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\View|\Inertia\Response
     */
    public function index(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        // Tela React (thread Superadmin/08) atrás da chave `?tela=nova`. Sem ela, segue a Blade:
        // tornar a nova o padrão é cutover do [W] (MWART F5). RUNBOOK-paginas.md.
        if ($request->query('tela') === 'nova') {
            return Inertia::render('superadmin/Paginas/Index', [
                'paginas' => Inertia::defer(fn () => $this->paginasPayload()),
            ]);
        }

        $pages = SuperadminFrontendPage::orderBy('menu_order', 'asc')->get();

        return view('superadmin::pages.index')
            ->with(compact('pages'));
    }

    /**
     * Payload da tela nova. Tabela global do site (sem business_id — ADR 0093 §exceções
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

    /** Resposta da tela nova: slug repetido vira erro de campo; sucesso volta pra `?tela=nova`. */
    private function respostaInertia(array $output)
    {
        if (empty($output['success'])) {
            return back()->withErrors(['slug' => $output['msg']]);
        }

        return redirect()->to(action([self::class, 'index']) . '?tela=nova');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('superadmin::pages.create');
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
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        $page = SuperadminFrontendPage::findOrFail($id);

        return view('superadmin::pages.edit')->with(compact('page'));
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
