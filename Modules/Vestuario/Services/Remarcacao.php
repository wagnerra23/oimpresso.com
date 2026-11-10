<?php

declare(strict_types=1);

namespace Modules\Vestuario\Services;

use App\Utils\Util;
use App\Variation;
use Illuminate\Support\Facades\DB;
use Modules\Vestuario\Exceptions\RemarcacaoRecusada;

/**
 * US-VEST-032 — remarcação por coleção (decisão [W] 2026-10-27).
 *
 * Escolhe as peças por filtro (categoria, subcategoria, marca, trecho do nome — a "coleção" ainda
 * não é atributo, US-VEST-029), calcula o preço novo e mostra a prévia antes → depois. Gravar exige
 * a assinatura da prévia: se algum preço mudou depois dela, nada é gravado. Cada lote guarda o preço
 * de antes de cada variação, e reverter só devolve o preço que ninguém mexeu depois.
 *
 * O preço remarcado é o de etiqueta (`sell_price_inc_tax`); o sem imposto e a margem são derivados
 * pelos mesmos helpers do cadastro (`Util::calc_percentage_base` / `get_percent`).
 * Tier 0: todo acesso filtra pelo `business_id` recebido.
 */
class Remarcacao
{
    public const LIMITE = 2000;

    public function __construct(private Util $util)
    {
    }

    /** @return array{linhas: array<int, array>, ignoradas: int, assinatura: string} */
    public function previa(int $biz, array $filtro, string $modo, float $valor, bool $travar = false): array
    {
        $linhas = [];
        $ignoradas = 0;
        foreach ($this->variacoes($biz, $filtro, $travar) as $v) {
            $antes = round((float) $v->sell_price_inc_tax, 2);
            $depois = $modo === 'percentual' ? round($antes * (1 + $valor / 100), 2) : round($valor, 2);
            if ($depois <= 0 || $depois === $antes) {
                $ignoradas++;
                continue;
            }
            $linhas[] = [
                'variation_id' => (int) $v->id, 'product_id' => (int) $v->product_id,
                'produto' => $v->produto, 'variacao' => $v->variacao, 'sku' => $v->sub_sku,
                'preco_antes' => $antes, 'preco_depois' => $depois,
            ];
        }

        return ['linhas' => $linhas, 'ignoradas' => $ignoradas, 'assinatura' => $this->assinar($linhas)];
    }

    /** Grava o lote. Lança se a prévia envelheceu (algum preço mudou desde que foi mostrada). */
    public function aplicar(int $biz, int $userId, string $nome, array $filtro, string $modo, float $valor, string $assinatura): int
    {
        return DB::transaction(function () use ($biz, $userId, $nome, $filtro, $modo, $valor, $assinatura) {
            // Lê travando: quem gravar junto espera aqui, vê o preço novo e a assinatura não bate.
            $previa = $this->previa($biz, $filtro, $modo, $valor, true);
            if ($previa['linhas'] === [] || ! hash_equals($previa['assinatura'], $assinatura)) {
                throw new RemarcacaoRecusada('Os preços mudaram desde a prévia. Gere a prévia de novo.');
            }
            $loteId = (int) DB::table('vestuario_remarcacoes')->insertGetId([
                'business_id' => $biz, 'nome' => $nome, 'filtro' => json_encode($filtro), 'modo' => $modo,
                'valor' => $valor, 'qtd_itens' => count($previa['linhas']), 'status' => 'aplicada',
                'aplicada_por' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($previa['linhas'] as $l) {
                $v = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
                    ->leftJoin('tax_rates as t', fn ($j) => $j->on('t.id', '=', 'p.tax')->where('t.business_id', $biz))
                    ->where('p.business_id', $biz)->where('v.id', $l['variation_id'])->lockForUpdate()
                    ->first(['v.sell_price_inc_tax', 'v.default_sell_price', 'v.profit_percent', 'v.default_purchase_price', 't.amount as taxa']);
                $venda = round($this->util->calc_percentage_base($l['preco_depois'], (float) ($v->taxa ?? 0)), 4); // @phpstan-ignore argument.type (docblock do Util diz int; preço e taxa são float — mesma conta do cadastro)
                $lucro = round($this->util->get_percent((float) $v->default_purchase_price, $venda), 4); // @phpstan-ignore argument.type, argument.type (idem: custo e venda são float)
                DB::table('vestuario_remarcacao_itens')->insert([
                    'business_id' => $biz, 'remarcacao_id' => $loteId, 'product_id' => $l['product_id'],
                    'variation_id' => $l['variation_id'], 'preco_antes' => $v->sell_price_inc_tax, 'preco_depois' => $l['preco_depois'],
                    'venda_antes' => $v->default_sell_price, 'venda_depois' => $venda,
                    'lucro_antes' => $v->profit_percent, 'lucro_depois' => $lucro,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('variations')->where('id', $l['variation_id'])->update([
                    'sell_price_inc_tax' => $l['preco_depois'], 'default_sell_price' => $venda,
                    'profit_percent' => $lucro, 'updated_at' => now(),
                ]);
                $this->auditar($biz, $l['variation_id'], 'remarcacao.aplicada', $loteId,
                    [$v->sell_price_inc_tax, $v->default_sell_price, $v->profit_percent],
                    [$l['preco_depois'], $venda, $lucro]);
            }

            return $loteId;
        });
    }

    /** Devolve o preço de antes. Variação remarcada de novo depois fica como está (conflito). */
    public function reverter(int $biz, int $userId, int $loteId): array
    {
        return DB::transaction(function () use ($biz, $userId, $loteId) {
            $lote = DB::table('vestuario_remarcacoes')->where('business_id', $biz)->where('id', $loteId)->lockForUpdate()->first();
            if (! $lote) {
                throw new RemarcacaoRecusada('Remarcação não encontrada.');
            }
            if ($lote->status !== 'aplicada') {
                throw new RemarcacaoRecusada('Esta remarcação já foi revertida.');
            }
            $revertidos = 0;
            $conflitos = 0;
            $itens = DB::table('vestuario_remarcacao_itens')->where('business_id', $biz)->where('remarcacao_id', $loteId)->get();
            foreach ($itens as $i) {
                $atual = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
                    ->where('p.business_id', $biz)->where('v.id', $i->variation_id)->lockForUpdate()
                    ->first(['v.sell_price_inc_tax', 'v.default_sell_price', 'v.profit_percent']);
                $intacto = $atual !== null && $this->intacto($atual, $i);
                if ($intacto) {
                    DB::table('variations')->where('id', $i->variation_id)->update([
                        'sell_price_inc_tax' => $i->preco_antes, 'default_sell_price' => $i->venda_antes,
                        'profit_percent' => $i->lucro_antes, 'updated_at' => now(),
                    ]);
                    $this->auditar($biz, (int) $i->variation_id, 'remarcacao.revertida', $loteId,
                        [$atual->sell_price_inc_tax, $atual->default_sell_price, $atual->profit_percent],
                        [$i->preco_antes, $i->venda_antes, $i->lucro_antes]);
                    $revertidos++;
                } else {
                    $conflitos++;
                }
                DB::table('vestuario_remarcacao_itens')->where('id', $i->id)
                    ->update(['reversao' => $intacto ? 'revertido' : 'conflito', 'updated_at' => now()]);
            }
            DB::table('vestuario_remarcacoes')->where('id', $loteId)->update([
                'status' => 'revertida', 'revertida_por' => $userId, 'revertida_em' => now(), 'updated_at' => now(),
            ]);

            return ['revertidos' => $revertidos, 'conflitos' => $conflitos];
        });
    }

    /**
     * A variação segue como a remarcação deixou? Olha etiqueta, sem imposto e margem: uma compra muda
     * custo e margem sem tocar na etiqueta (`ProductUtil::updateProductFromPurchase`), e devolver a
     * margem antiga sobre o custo novo deixaria a margem errada.
     */
    private function intacto(object $atual, object $item): bool
    {
        return round((float) $atual->sell_price_inc_tax, 2) === round((float) $item->preco_depois, 2)
            && round((float) $atual->default_sell_price, 4) === round((float) $item->venda_depois, 4)
            && round((float) $atual->profit_percent, 4) === round((float) $item->lucro_depois, 4);
    }

    /**
     * Grava na trilha geral. O `UPDATE` por `DB::table` não passa no `LogsActivity` da `Variation`, e
     * mesmo passando a linha sairia sem `business_id` (a variação não tem a coluna) e a Auditoria não veria.
     *
     * @param  array{0: mixed, 1: mixed, 2: mixed}  $antes  etiqueta, sem imposto, margem
     * @param  array{0: mixed, 1: mixed, 2: mixed}  $depois  etiqueta, sem imposto, margem
     */
    private function auditar(int $biz, int $variationId, string $acao, int $loteId, array $antes, array $depois): void
    {
        $campos = ['sell_price_inc_tax', 'default_sell_price', 'profit_percent'];
        // Só o id é lido pelo log: a linha já foi lida e travada, com filtro de empresa, por quem chama.
        $this->util->activityLog((new Variation())->setAttribute('id', $variationId), $acao, null, [
            'old' => array_combine($campos, $antes), 'attributes' => array_combine($campos, $depois), 'remarcacao_id' => $loteId,
        ], false, $biz);
    }

    private function variacoes(int $biz, array $f, bool $travar = false): \Illuminate\Support\Collection
    {
        $q = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $biz)->where('p.is_inactive', 0)->whereNull('v.deleted_at')
            ->where('p.type', '!=', 'modifier');
        foreach (['category_id', 'sub_category_id', 'brand_id'] as $c) {
            if (! empty($f[$c])) {
                $q->where("p.$c", (int) $f[$c]);
            }
        }
        if (trim((string) ($f['nome'] ?? '')) !== '') {
            $q->where('p.name', 'like', '%' . addcslashes(trim($f['nome']), '%_\\') . '%');
        }
        if (! array_filter([$f['category_id'] ?? null, $f['sub_category_id'] ?? null, $f['brand_id'] ?? null, trim((string) ($f['nome'] ?? ''))])) {
            throw new RemarcacaoRecusada('Escolha ao menos um filtro: categoria, subcategoria, marca ou nome.');
        }

        if ($travar) {
            $q->lockForUpdate();
        }
        $todas = $q->orderBy('p.name')->orderBy('v.id')->limit(self::LIMITE + 1)
            ->get(['v.id', 'v.product_id', 'v.sub_sku', 'v.sell_price_inc_tax', 'p.name as produto', 'v.name as variacao']);
        if ($todas->count() > self::LIMITE) {
            throw new RemarcacaoRecusada('Mais de ' . self::LIMITE . ' variações. Estreite o filtro.');
        }

        return $todas;
    }

    private function assinar(array $linhas): string
    {
        return hash('sha256', json_encode(array_map(fn ($l) => [$l['variation_id'], $l['preco_antes'], $l['preco_depois']], $linhas)));
    }
}
