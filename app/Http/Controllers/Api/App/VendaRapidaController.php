<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\BusinessLocation;
use App\Events\SellCreatedOrModified;
use App\Http\Controllers\Controller;
use App\Services\AppLojas\RegistrarVendaRapida;
use App\Services\AppLojas\VendaRapidaInvalida;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Venda rápida do app das lojas (tela 11). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §2.2.
 *
 * Leitura (busca de produtos) e escrita (POST /api/app/vendas). A escrita é REGRA MESTRE de
 * valor/estoque: dupla prova + tabela antes→depois + ok do [W] antes do merge.
 *
 * Regras medidas na web (2026-10-02), espelhadas aqui:
 *  - Local de venda: o ERP não tem "local padrão do usuário". A web (SellController::create,
 *    BusinessLocation::forDropdown) usa o 1º local ATIVO que o usuário pode acessar. Mesmo aqui.
 *  - Preço: o da variação (`sell_price_inc_tax`) ou, se o local tem grupo de preço padrão, o do
 *    grupo pelo MESMO cálculo do PDV Blade (ProductUtil::getVariationGroupPrice, usado quando
 *    não-vazio). O POST vai recalcular por esta mesma função — o número que o app mostra é o
 *    que o ERP confere.
 *  - Estoque: o do local de venda; `null` quando o produto não controla estoque OU o business
 *    permite vender sem estoque (aí o ERP não barra, e o app não deve pôr teto).
 *
 * Tier 0 (ADR 0093): business_id do usuário do token, nunca da requisição.
 */
class VendaRapidaController extends Controller
{
    private const LIMITE = 20;

    public function produtos(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $this->podeVender($user)) {
            return $this->semPermissao();
        }
        $local = self::localDeVenda($user);
        if ($local === null) {
            return $this->semLocal();
        }

        $bizId = (int) $user->business_id;
        $busca = trim((string) $request->query('q', ''));
        // Business que vende sem estoque (pos_settings.allow_overselling): o ERP aceita a venda
        // mesmo com saldo zero ou negativo, então o app não deve pôr teto — estoque vai `null`.
        $semTeto = self::permiteVenderSemEstoque($bizId);

        $q = DB::table('products as p')
            ->join('variations as v', 'v.product_id', '=', 'p.id')
            ->join('product_locations as pl', function ($j) use ($local) {
                $j->on('pl.product_id', '=', 'p.id')->where('pl.location_id', '=', $local->id);
            })
            ->leftJoin('variation_location_details as vld', function ($j) use ($local) {
                $j->on('vld.variation_id', '=', 'v.id')->where('vld.location_id', '=', $local->id);
            })
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.business_id', $bizId)
            ->where('p.is_inactive', 0)
            ->where('p.not_for_selling', 0)
            // Combo e modificador ficam fora da v1: a baixa de combo e o preço de modificador
            // seguem outros caminhos no ERP e não estão no contrato da tela 11.
            ->whereIn('p.type', ['single', 'variable'])
            ->whereNull('v.deleted_at');

        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $q->where(fn ($w) => $w->where('p.name', 'like', $like)
                ->orWhere('v.name', 'like', $like)
                ->orWhere('p.sku', 'like', $like)
                ->orWhere('v.sub_sku', 'like', $like)
                ->orWhere('c.name', 'like', $like));
        }

        $linhas = $q->orderBy('p.name')->orderBy('v.id')
            ->limit(self::LIMITE)
            ->get([
                'v.id as variacao_id', 'p.name as produto', 'p.type', 'v.name as variacao',
                'c.name as categoria', 'p.enable_stock', 'p.tax as tax_id',
                'v.sell_price_inc_tax', 'vld.qty_available',
            ]);

        return response()->json([
            'itens' => $linhas->map(fn ($l) => [
                'id' => (int) $l->variacao_id,
                'nome' => $l->type === 'variable' && $l->variacao !== null && $l->variacao !== 'DUMMY'
                    ? $l->produto . ' — ' . $l->variacao
                    : $l->produto,
                'categoria' => $l->categoria,
                'preco' => self::precoDeVenda($local, (int) $l->variacao_id, (float) $l->sell_price_inc_tax, $l->tax_id),
                'estoque' => (int) $l->enable_stock === 1 && ! $semTeto ? round((float) ($l->qty_available ?? 0), 4) : null,
            ])->values(),
        ]);
    }

    /**
     * POST /api/app/vendas — REGRA MESTRE (valor + estoque). A gravação é do RegistrarVendaRapida; aqui
     * ficam formato, permissão e a Idempotency-Key, reservada na MESMA transação de banco da venda: se a
     * venda falha, a reserva some junto; repetida depois do sucesso, devolve a mesma resposta (200).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $this->podeVender($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        $moduleUtil = app(ModuleUtil::class);
        if (! $moduleUtil->isSubscribed($bizId) || ! $moduleUtil->isQuotaAvailable('invoices', $bizId)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'A assinatura da empresa não permite novas vendas.'], 403);
        }

        $chave = trim((string) $request->header('Idempotency-Key', ''));
        // Números como TEXTO com ponto e até 2 casas: nunca float locale-ambíguo (incidente num_uf 2026-06-05).
        $numero = ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,2})?$/'];
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'cliente_id' => ['nullable', 'integer'],
            'metodo' => ['required', 'in:' . implode(',', array_keys(RegistrarVendaRapida::METODOS))],
            'itens' => ['required', 'array', 'min:1', 'max:100'],
            'itens.*.variacao_id' => ['required', 'integer'],
            'itens.*.quantidade' => $numero,
            'itens.*.preco_unitario' => $numero,
            'total_previsto' => $numero,
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'metodo.required' => 'Escolha a forma de pagamento.',
            'metodo.in' => 'Forma de pagamento não aceita: use PIX, crédito, débito ou dinheiro.',
            'itens.required' => 'Adicione ao menos um produto.',
            'itens.min' => 'Adicione ao menos um produto.',
            '*.regex' => 'Use número com ponto e até 2 casas (ex.: 12.50).',
            'itens.*.quantidade.regex' => 'Use número com ponto e até 2 casas (ex.: 2.00).',
            'itens.*.preco_unitario.regex' => 'Use número com ponto e até 2 casas (ex.: 12.50).',
            'total_previsto.regex' => 'Use número com ponto e até 2 casas (ex.: 12.50).',
        ]);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }
        $d = $v->validated();
        unset($d['idempotency_key']);
        $d['cliente_id'] = isset($d['cliente_id']) ? (int) $d['cliente_id'] : null;
        $hash = hash('sha256', (string) json_encode([$d['cliente_id'], $d['metodo'], array_map(
            fn ($i) => [(int) $i['variacao_id'], (string) $i['quantidade'], (string) $i['preco_unitario']],
            array_values($d['itens'])
        ), $d['total_previsto']]));

        $local = self::localDeVenda($user);
        if ($local === null) {
            return $this->semLocal();
        }

        $ja = $this->idempotencia($bizId, (int) $user->id, $chave);
        if ($ja !== null) {
            return $this->repetida($ja, $hash);
        }

        DB::beginTransaction();
        try {
            try {
                $reservaId = DB::table('app_idempotencia')->insertGetId([
                    'business_id' => $bizId, 'user_id' => (int) $user->id, 'rota' => 'vendas', 'chave' => $chave,
                    'hash_corpo' => $hash, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Outra requisição com a mesma chave ganhou a corrida e já confirmou (o INSERT esperou o lock).
                DB::rollBack();
                $ja = $this->idempotencia($bizId, (int) $user->id, $chave);

                return $ja !== null ? $this->repetida($ja, $hash) : $this->emAndamento();
            }

            $r = app(RegistrarVendaRapida::class)->registrar($user, $local, $d);
            DB::table('app_idempotencia')->where('id', $reservaId)->update([
                'transaction_id' => $r['transaction']->id,
                'resposta' => json_encode($r['resposta']),
                'updated_at' => now(),
            ]);
            DB::commit();
        } catch (VendaRapidaInvalida $e) {
            DB::rollBack();

            return $this->invalido($e->campos);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('API app vendas.store: ' . $e->getMessage(), ['business_id' => $bizId]);

            return response()->json(['erro' => 'falha', 'mensagem' => 'Não foi possível registrar a venda.'], 500);
        }

        SellCreatedOrModified::dispatch($r['transaction']);

        return response()->json($r['resposta'], 201);
    }

    private function idempotencia(int $bizId, int $userId, string $chave): ?object
    {
        return DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', $userId)
            ->where('rota', 'vendas')->where('chave', $chave)->first();
    }

    private function repetida(object $ja, string $hash): JsonResponse
    {
        if (! hash_equals((string) $ja->hash_corpo, $hash)) {
            return response()->json([
                'erro' => 'idempotencia_conflito',
                'mensagem' => 'Esta chave já foi usada para outra venda. Gere uma chave nova.',
            ], 422);
        }
        if ($ja->resposta === null) {
            return $this->emAndamento();
        }

        return response()->json(json_decode((string) $ja->resposta, true), 200);
    }

    private function emAndamento(): JsonResponse
    {
        return response()->json(['erro' => 'em_andamento', 'mensagem' => 'Esta venda ainda está sendo registrada. Tente de novo.'], 409);
    }

    /** @param array<string,string> $campos */
    private function invalido(array $campos): JsonResponse
    {
        return response()->json(['erro' => 'validacao', 'campos' => $campos], 422);
    }

    /** `pos_settings.allow_overselling` do business — a mesma chave que o mapPurchaseSell lê. */
    public static function permiteVenderSemEstoque(int $bizId): bool
    {
        $pos = json_decode((string) DB::table('business')->where('id', $bizId)->value('pos_settings'), true);

        return ! empty($pos['allow_overselling']);
    }

    /** 1º local ativo que o usuário pode acessar — a regra do SellController::create. */
    public static function localDeVenda(User $user): ?BusinessLocation
    {
        $q = BusinessLocation::where('business_id', (int) $user->business_id)->active()->orderBy('id');
        $permitidos = $user->permitted_locations((int) $user->business_id);
        if ($permitidos !== 'all') {
            $q->whereIn('id', (array) $permitidos);
        }

        return $q->first();
    }

    /**
     * Preço que a venda usa, em reais com 2 casas. Grupo de preço padrão do local quando há preço
     * não-vazio para a variação (mesmo teste `!empty` do PDV Blade); senão o preço da variação.
     */
    public static function precoDeVenda(BusinessLocation $local, int $variacaoId, float $base, mixed $taxId): float
    {
        $preco = $base;
        if (! empty($local->selling_price_group_id)) {
            $grupo = app(ProductUtil::class)->getVariationGroupPrice($variacaoId, (int) $local->selling_price_group_id, $taxId);
            if (! empty($grupo['price_inc_tax'])) {
                $preco = (float) $grupo['price_inc_tax'];
            }
        }

        return round($preco, 2);
    }

    private function podeVender(?User $user): bool
    {
        // As mesmas da criação de venda direta na web (SellPosController::store), sem `so.create`
        // (pedido de venda, que não baixa estoque nem é venda).
        return $user !== null && ($user->can('sell.create') || $user->can('direct_sell.access'));
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_permissao',
            'mensagem' => 'Seu usuário não pode registrar vendas.',
        ], 403);
    }

    private function semLocal(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_local',
            'mensagem' => 'Seu usuário não tem acesso a nenhum local de venda ativo.',
        ], 403);
    }
}
