<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Events\ProductsCreatedOrModified;
use App\Http\Controllers\Controller;
use App\Product;
use App\User;
use App\Utils\ProductUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Novo produto do app das lojas (tela 20). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §9.4.
 *
 * Decisão [W] 2026-10-02: o app cria produto SEM preço — igual à tela React de criar produto hoje
 * (Produto/Create.casos.md, pendência de contrato). A variação nasce com preço zerado, como lá, e o
 * preço se acerta na web. Nada aqui calcula valor.
 *
 * Grava pelo mesmo caminho do ProductController::store da web (Product::create + evento +
 * ProductUtil::createSingleProductVariation + addRackDetails), no business do token, só tipo simples.
 * Números chegam com ponto decimal e NÃO passam por num_uf (o parser do incidente de 2026-06-05).
 *
 * Tier 0 (ADR 0093): categoria e unidade precisam ser do business do token (a mesma trava do
 * UC-PCAD-05 da web); o produto e a prateleira nascem no business do token.
 */
class NovoProdutoController extends Controller
{
    /** GET /api/app/produtos/opcoes — categorias e unidades do business, para o formulário. */
    public function opcoes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('product.create')) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        return response()->json([
            'categorias' => DB::table('categories')->where('business_id', $bizId)->where('parent_id', 0)
                ->where(fn ($q) => $q->whereNull('category_type')->orWhere('category_type', 'product'))
                ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => (int) $c->id, 'nome' => (string) $c->name])->values(),
            'unidades' => DB::table('units')->where('business_id', $bizId)->whereNull('deleted_at')
                ->orderBy('actual_name')->get(['id', 'actual_name', 'short_name'])
                ->map(fn ($u) => ['id' => (int) $u->id, 'nome' => (string) $u->actual_name, 'curta' => (string) $u->short_name])->values(),
        ]);
    }

    /** POST /api/app/produtos — Novo produto (tela 20), sem preço. */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('product.create')) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        $v = Validator::make($request->all(), [
            'nome' => ['required', 'string', 'max:191'],
            'codigo' => ['nullable', 'string', 'max:191'],
            'categoria_id' => ['nullable', 'integer'],
            'unidade_id' => ['required', 'integer'],
            'estoque' => ['nullable', 'array'],
            'estoque.controla' => ['nullable', 'boolean'],
            'estoque.minimo' => ['nullable', 'numeric', 'min:0'],
            'prateleira' => ['nullable', 'array'],
            'prateleira.rack' => ['nullable', 'string', 'max:191'],
            'prateleira.fileira' => ['nullable', 'string', 'max:191'],
            'prateleira.posicao' => ['nullable', 'string', 'max:191'],
            'fiscal' => ['nullable', 'array'],
            'fiscal.ncm' => ['nullable', 'regex:/^\d{8}$/'],
            'fiscal.cest' => ['nullable', 'regex:/^\d{7}$/'],
            'fiscal.cfop_interno' => ['nullable', 'regex:/^\d{4}$/'],
            'fiscal.cfop_externo' => ['nullable', 'regex:/^\d{4}$/'],
        ], [
            'nome.required' => 'Informe o nome.',
            'unidade_id.required' => 'Escolha como o produto é vendido.',
            'fiscal.ncm.regex' => 'O NCM tem 8 dígitos.',
            'fiscal.cest.regex' => 'O CEST tem 7 dígitos.',
            'fiscal.cfop_interno.regex' => 'O CFOP tem 4 dígitos.',
            'fiscal.cfop_externo.regex' => 'O CFOP tem 4 dígitos.',
        ]);
        // validated() lança se falhou: junta os erros do validador com as travas de business abaixo.
        $campos = $v->fails() ? collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all() : [];

        // Mesma trava da web (UC-PCAD-05): categoria e unidade só do business do token.
        if (! isset($campos['unidade_id']) && $request->filled('unidade_id')
            && ! DB::table('units')->where('id', (int) $request->input('unidade_id'))->where('business_id', $bizId)->exists()) {
            $campos['unidade_id'] = 'Unidade inválida.';
        }
        if ($request->filled('categoria_id')
            && ! DB::table('categories')->where('id', (int) $request->input('categoria_id'))->where('business_id', $bizId)->exists()) {
            $campos['categoria_id'] = 'Categoria inválida.';
        }
        if ($campos !== []) {
            return response()->json(['erro' => 'validacao', 'campos' => $campos], 422);
        }
        $d = $v->validated();

        $controla = (bool) ($d['estoque']['controla'] ?? false);
        $fiscal = $d['fiscal'] ?? [];
        $codigo = trim((string) ($d['codigo'] ?? ''));
        $locais = $user->permitted_locations($bizId);
        $lojas = DB::table('business_locations')->where('business_id', $bizId)
            ->when($locais !== 'all', fn ($q) => $q->whereIn('id', (array) $locais ?: [0]))
            ->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::beginTransaction();
        try {
            $dados = array_filter([
                'name' => trim($d['nome']),
                'business_id' => $bizId,
                'created_by' => (int) $user->id,
                'type' => 'single',
                'unit_id' => (int) $d['unidade_id'],
                'category_id' => isset($d['categoria_id']) ? (int) $d['categoria_id'] : null,
                'sku' => $codigo !== '' ? $codigo : ' ',
                'enable_stock' => $controla ? 1 : 0,
                'alert_quantity' => $controla && isset($d['estoque']['minimo']) ? (float) $d['estoque']['minimo'] : null,
                'ncm' => $fiscal['ncm'] ?? null,
                'cest' => $fiscal['cest'] ?? null,
                'cfop_interno' => $fiscal['cfop_interno'] ?? null,
                'cfop_externo' => $fiscal['cfop_externo'] ?? null,
            ], fn ($x) => $x !== null);
            $produto = Product::create($dados);
            event(new ProductsCreatedOrModified($dados, 'added'));

            if ($codigo === '') {
                // A regra do ProductUtil::generateProductSku, que lê o business da SESSÃO (o app não tem):
                // prefixo do business + id com 4 dígitos.
                $prefixo = (string) DB::table('business')->where('id', $bizId)->value('sku_prefix');
                $produto->sku = $prefixo . str_pad((string) $produto->id, 4, '0', STR_PAD_LEFT);
                $produto->save();
            }

            if ($lojas !== []) {
                $produto->product_locations()->sync($lojas);
            }

            // Sem preço (decisão [W]): a variação nasce com os 5 valores zerados, como na tela React.
            app(ProductUtil::class)->createSingleProductVariation($produto->id, $produto->sku, 0, 0, 0, 0, 0);

            $p = $d['prateleira'] ?? null;
            if ($p && $lojas !== [] && array_filter([$p['rack'] ?? null, $p['fileira'] ?? null, $p['posicao'] ?? null])) {
                app(ProductUtil::class)->addRackDetails($bizId, $produto->id, [
                    $lojas[0] => ['rack' => $p['rack'] ?? null, 'row' => $p['fileira'] ?? null, 'position' => $p['posicao'] ?? null],
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('API app produtos.store: ' . $e->getMessage());

            return response()->json(['erro' => 'falha', 'mensagem' => 'Não foi possível cadastrar o produto.'], 500);
        }

        return response()->json(['id' => (int) $produto->id, 'codigo' => (string) $produto->sku], 201);
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não pode cadastrar produto.'], 403);
    }
}
