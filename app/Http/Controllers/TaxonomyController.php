<?php

namespace App\Http\Controllers;

use App\Category;
use App\Utils\ModuleUtil;
use App\Services\FeatureFlagService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class TaxonomyController extends Controller
{
    /** Tela React das listas dos módulos (RUNBOOK-taxonomias). `?classico=1` mantém a Blade. */
    private const FLAG_V2 = 'useV2Taxonomias';

    /**
     * Tipos atendidos pela tela React e onde cada item é usado (tabela, coluna). O uso é contado sempre
     * no business_id da sessão. Categoria de produto fica de fora: tem tela própria em /units.
     */
    private const USO_POR_TIPO = [
        'source' => ['contacts', 'crm_source'],
        'life_stage' => ['contacts', 'crm_life_stage'],
        'followup_category' => ['crm_schedules', 'followup_category_id'],
        'hrm_department' => ['users', 'essentials_department_id'],
        'hrm_designation' => ['users', 'essentials_designation_id'],
    ];

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
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $category_type = request()->get('type');
        if ($category_type == 'product' && ! auth()->user()->can('category.view') && ! auth()->user()->can('category.create')) {
            abort(403, 'Unauthorized action.');
        }

        // Inertia v3 manda X-Requested-With: sem `! inertia()` o ramo DataTable engoliria a visita.
        if (! request()->boolean('classico') && ! (request()->ajax() && ! request()->inertia())
            && isset(self::USO_POR_TIPO[$category_type])) {
            $tela = $this->telaReact((string) $category_type);
            if ($tela !== null) {
                return $tela;
            }
        }

        if (request()->ajax()) {
            $can_edit = true;
            if ($category_type == 'product' && ! auth()->user()->can('category.update')) {
                $can_edit = false;
            }

            $can_delete = true;
            if ($category_type == 'product' && ! auth()->user()->can('category.delete')) {
                $can_delete = false;
            }

            $business_id = request()->session()->get('user.business_id');

            $category = Category::where('business_id', $business_id)
                            ->where('category_type', $category_type)
                            ->select(['name', 'short_code', 'description', 'id', 'parent_id']);

            return Datatables::of($category)
                ->addColumn(
                    'action', function ($row) use ($can_edit, $can_delete, $category_type) {
                        $html = '';
                        if ($can_edit) {
                            $html .= '<button data-href="'.action([\App\Http\Controllers\TaxonomyController::class, 'edit'], [$row->id]).'?type='.$category_type.'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_category_button"><i class="glyphicon glyphicon-edit"></i>'.__('messages.edit').'</button>';
                        }

                        if ($can_delete) {
                            $html .= '&nbsp;<button data-href="'.action([\App\Http\Controllers\TaxonomyController::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_category_button"><i class="glyphicon glyphicon-trash"></i> '.__('messages.delete').'</button>';
                        }

                        return $html;
                    }
                )
                ->editColumn('name', function ($row) {
                    if ($row->parent_id != 0) {
                        return '--'.$row->name;
                    } else {
                        return $row->name;
                    }
                })
                ->removeColumn('id')
                ->removeColumn('parent_id')
                ->rawColumns(['action'])
                ->make(true);
        }

        $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

        return view('taxonomy.index')->with(compact('module_category_data', 'module_category_data'));
    }

    /**
     * Tela React do tipo, ou null quando a flag está desligada ou o módulo dono não declara o tipo para
     * este usuário (aí a rota segue na Blade, como antes).
     */
    private function telaReact(string $tipo): ?\Inertia\Response
    {
        $business_id = (int) request()->session()->get('user.business_id');
        if (! app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return null;
        }

        $declarados = [];
        foreach ((array) $this->moduleUtil->getModuleData('addTaxonomies') as $dados) {
            foreach ((array) $dados as $chave => $valor) {
                if (! empty($valor) && isset(self::USO_POR_TIPO[$chave])) {
                    $declarados[$chave] = $valor;
                }
            }
        }
        if (empty($declarados[$tipo])) {
            return null;
        }

        $meu = $declarados[$tipo];
        $abas = collect($declarados)
            ->filter(fn ($v) => ($v['navbar'] ?? null) === ($meu['navbar'] ?? null))
            ->map(fn ($v, $k) => ['tipo' => (string) $k, 'titulo' => (string) ($v['heading'] ?? $k)])
            ->values()->all();

        $itens = fn () => $this->itensDoTipo($tipo, $business_id);

        return Inertia::render('Configuracoes/Taxonomias/Index', [
            'tipo' => $tipo,
            'titulo' => (string) ($meu['heading'] ?? $tipo),
            'subtitulo' => (string) ($meu['sub_heading'] ?? ''),
            'rotulo' => (string) ($meu['taxonomy_label'] ?? $tipo),
            'com_codigo' => ! empty($meu['enable_taxonomy_code']),
            'rotulo_codigo' => (string) ($meu['taxonomy_code_label'] ?? 'Código'),
            'abas' => $abas,
            'itens' => Inertia::defer($itens),
        ]);
    }

    /** Itens do tipo no negócio da sessão, com a contagem de uso de cada um. */
    private function itensDoTipo(string $tipo, int $business_id): array
    {
        $uso = $this->usoPorItem($tipo, $business_id);

        return Category::where('business_id', $business_id)
            ->where('category_type', $tipo)
            ->orderBy('name')
            ->get(['id', 'name', 'short_code', 'description'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'nome' => (string) $c->name,
                'codigo' => $c->short_code,
                'descricao' => $c->description,
                'em_uso' => (int) ($uso[$c->id] ?? 0),
            ])->values()->all();
    }

    /**
     * Quantos registros do negócio usam cada item do tipo, por id. Vazio se a tabela/coluna não existe.
     *
     * @return array<int|string, int>
     */
    private function usoPorItem(string $tipo, int $business_id): array
    {
        [$tabela, $coluna] = self::USO_POR_TIPO[$tipo];
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
            return [];
        }

        return DB::table($tabela)
            ->where('business_id', $business_id)
            ->whereNotNull($coluna)
            ->groupBy($coluna)
            ->selectRaw($coluna.' as item, count(*) as n')
            ->pluck('n', 'item')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $category_type = request()->get('type');
        if ($category_type == 'product' && ! auth()->user()->can('category.create')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');

        $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

        $categories = Category::where('business_id', $business_id)
                        ->where('parent_id', 0)
                        ->where('category_type', $category_type)
                        ->select(['name', 'short_code', 'id'])
                        ->get();

        $parent_categories = [];
        if (! empty($categories)) {
            foreach ($categories as $category) {
                $parent_categories[$category->id] = $category->name;
            }
        }

        return view('taxonomy.create')
                    ->with(compact('parent_categories', 'module_category_data', 'category_type'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /** Pai aceito: categoria raiz (`parent_id = 0`) do negócio da sessão, do mesmo tipo, e não a própria. */
    private function paiValido($parentId, int $business_id, string $tipo, int $propria = 0): bool
    {
        return (int) $parentId !== $propria
            && Category::where('business_id', $business_id)->where('category_type', $tipo)
                ->where('parent_id', 0)->where('id', (int) $parentId)->exists();
    }

    public function store(Request $request)
    {
        $category_type = request()->input('category_type');
        if ($category_type == 'product' && ! auth()->user()->can('category.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'short_code', 'category_type', 'description']);
            $input['business_id'] = $request->session()->get('user.business_id');
            if (! empty($request->input('add_as_sub_cat')) && $request->input('add_as_sub_cat') == 1 && ! empty($request->input('parent_id'))) {
                if (! $this->paiValido($request->input('parent_id'), (int) $input['business_id'], (string) $category_type)) {
                    throw new \DomainException('A categoria pai escolhida não serve: precisa ser uma categoria principal deste negócio. Nada foi gravado.');
                }
                $input['parent_id'] = $request->input('parent_id');
            } else {
                $input['parent_id'] = 0;
            }
            $input['created_by'] = $request->session()->get('user.id');

            $category = Category::create($input);
            $output = ['success' => true,
                'data' => $category,
                'msg' => __('category.added_success'),
            ];
        } catch (\DomainException $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $category_type = request()->get('type');
        if ($category_type == 'product' && ! auth()->user()->can('category.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $category = Category::where('business_id', $business_id)->find($id);

            $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

            $parent_categories = Category::where('business_id', $business_id)
                                        ->where('parent_id', 0)
                                        ->where('category_type', $category_type)
                                        ->where('id', '!=', $id)
                                        ->pluck('name', 'id');
            $is_parent = false;

            if ($category->parent_id == 0) {
                $is_parent = true;
                $selected_parent = null;
            } else {
                $selected_parent = $category->parent_id;
            }

            return view('taxonomy.edit')
                ->with(compact('category', 'parent_categories', 'is_parent', 'selected_parent', 'module_category_data'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description']);
                $business_id = $request->session()->get('user.business_id');

                $category = Category::where('business_id', $business_id)->findOrFail($id);

                if ($category->category_type == 'product' && ! auth()->user()->can('category.update')) {
                    abort(403, 'Unauthorized action.');
                }

                $category->name = $input['name'];
                $category->description = $input['description'];
                $category->short_code = $request->input('short_code');

                if (! empty($request->input('add_as_sub_cat')) && $request->input('add_as_sub_cat') == 1 && ! empty($request->input('parent_id'))) {
                    // Tier 0 + hierarquia de 2 níveis: o mesmo filtro da lista de pais do modal clássico
                    // (raiz, mesmo negócio e tipo, não ela mesma) e categoria com filhas não vira filha.
                    if (! $this->paiValido($request->input('parent_id'), (int) $business_id, (string) $category->category_type, (int) $category->id)
                        || Category::where('business_id', $business_id)->where('parent_id', $category->id)->exists()) {
                        throw new \DomainException('A categoria pai escolhida não serve: precisa ser uma categoria principal deste negócio. Nada foi gravado.');
                    }
                    $category->parent_id = $request->input('parent_id');
                } else {
                    $category->parent_id = 0;
                }
                $category->save();

                $output = ['success' => true,
                    'msg' => __('category.updated_success'),
                ];
            } catch (\DomainException $e) {
                $output = ['success' => false, 'msg' => $e->getMessage()];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return mixed JSON {success, msg[, em_uso, filhas]} do ajax; nada fora do ajax
     */
    public function destroy($id)
    {
        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $category = Category::where('business_id', $business_id)->findOrFail($id);

                if ($category->category_type == 'product' && ! auth()->user()->can('category.delete')) {
                    abort(403, 'Unauthorized action.');
                }

                // Playbook Produto · thread 02 (charter Cadastros R4): categoria de produto em uso ou com
                // subcategorias não sai. Antes o legado apagava só o pai (soft delete) e deixava as
                // filhas e os produtos apontando pra uma categoria apagada. As contagens são as mesmas
                // que a aba Categorias de /units mostra. Outras taxonomias (módulos) seguem como eram.
                $emUso = 0;
                $filhas = 0;
                // RUNBOOK-taxonomias §10 linha 5: item das listas dos módulos em uso também não sai —
                // senão o lead ou o colaborador fica apontando para um item apagado.
                $doModulo = isset(self::USO_POR_TIPO[$category->category_type]);
                if ($doModulo) {
                    $emUso = (int) ($this->usoPorItem($category->category_type, (int) $business_id)[$category->id] ?? 0);
                } elseif ($category->category_type == 'product') {
                    $emUso = \App\Product::where('business_id', $business_id)
                        ->where(fn ($q) => $q->where('category_id', $category->id)->orWhere('sub_category_id', $category->id))
                        ->count();
                    $filhas = Category::where('business_id', $business_id)->where('parent_id', $category->id)->count();
                }

                if ($emUso > 0 || $filhas > 0) {
                    $motivos = array_filter([
                        $emUso > 0 ? ($doModulo ? "{$emUso} registro(s) usam este item" : "{$emUso} produto(s) usam esta categoria") : null,
                        $filhas > 0 ? "{$filhas} subcategoria(s) estão dentro dela" : null,
                    ]);
                    $output = ['success' => false, 'em_uso' => $emUso, 'filhas' => $filhas,
                        'msg' => implode(' e ', $motivos).($doModulo ? '. Troque nos registros antes de excluir.' : '. Mova ou exclua antes de excluir a categoria.'),
                    ];
                } else {
                    $category->delete();

                    $output = ['success' => true,
                        'msg' => __('category.deleted_success'),
                    ];
                }
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function getCategoriesApi()
    {
        try {
            $api_token = request()->header('API-TOKEN');

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $categories = Category::catAndSubCategories($api_settings->business_id);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($categories);
    }

    /**
     * get taxonomy index page
     * through ajax
     *
     * @return \Illuminate\Http\Response
     */
    public function getTaxonomyIndexPage(Request $request)
    {
        if (request()->ajax()) {
            $category_type = $request->get('category_type');
            $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

            return view('taxonomy.ajax_index')
                ->with(compact('module_category_data', 'category_type'));
        }
    }
}
