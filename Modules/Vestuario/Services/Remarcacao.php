<?php

declare(strict_types=1);

namespace Modules\Vestuario\Services;

use App\Utils\Util;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
    public function previa(int $biz, array $filtro, string $modo, float $valor): array
    {
        $linhas = [];
        $ignoradas = 0;
        foreach ($this->variacoes($biz, $filtro) as $v) {
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
            $previa = $this->previa($biz, $filtro, $modo, $valor);
            if ($previa['linhas'] === [] || ! hash_equals($previa['assinatura'], $assinatura)) {
                throw new RuntimeException('Os preços mudaram desde a prévia. Gere a prévia de novo.');
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
                    ->first(['v.default_sell_price', 'v.profit_percent', 'v.default_purchase_price', 't.amount as taxa']);
                $venda = round($this->util->calc_percentage_base($l['preco_depois'], (float) ($v->taxa ?? 0)), 4); // @phpstan-ignore argument.type (docblock do Util diz int; preço e taxa são float — mesma conta do cadastro)
                $lucro = round($this->util->get_percent((float) $v->default_purchase_price, $venda), 4); // @phpstan-ignore argument.type, argument.type (idem: custo e venda são float)
                DB::table('vestuario_remarcacao_itens')->insert([
                    'business_id' => $biz, 'remarcacao_id' => $loteId, 'product_id' => $l['product_id'],
                    'variation_id' => $l['variation_id'], 'preco_antes' => $l['preco_antes'], 'preco_depois' => $l['preco_depois'],
                    'venda_antes' => $v->default_sell_price, 'venda_depois' => $venda,
                    'lucro_antes' => $v->profit_percent, 'lucro_depois' => $lucro,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('variations')->where('id', $l['variation_id'])->update([
                    'sell_price_inc_tax' => $l['preco_depois'], 'default_sell_price' => $venda,
                    'profit_percent' => $lucro, 'updated_at' => now(),
                ]);
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
                throw new RuntimeException('Remarcação não encontrada.');
            }
            if ($lote->status !== 'aplicada') {
                throw new RuntimeException('Esta remarcação já foi revertida.');
            }
            $revertidos = 0;
            $conflitos = 0;
            $itens = DB::table('vestuario_remarcacao_itens')->where('business_id', $biz)->where('remarcacao_id', $loteId)->get();
            foreach ($itens as $i) {
                $atual = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
                    ->where('p.business_id', $biz)->where('v.id', $i->variation_id)->lockForUpdate()->value('v.sell_price_inc_tax');
                $intacto = $atual !== null && round((float) $atual, 2) === round((float) $i->preco_depois, 2);
                if ($intacto) {
                    DB::table('variations')->where('id', $i->variation_id)->update([
                        'sell_price_inc_tax' => $i->preco_antes, 'default_sell_price' => $i->venda_antes,
                        'profit_percent' => $i->lucro_antes, 'updated_at' => now(),
                    ]);
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

    private function variacoes(int $biz, array $f): \Illuminate\Support\Collection
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
            throw new RuntimeException('Escolha ao menos um filtro: categoria, subcategoria, marca ou nome.');
        }

        $todas = $q->orderBy('p.name')->orderBy('v.id')->limit(self::LIMITE + 1)
            ->get(['v.id', 'v.product_id', 'v.sub_sku', 'v.sell_price_inc_tax', 'p.name as produto', 'v.name as variacao']);
        if ($todas->count() > self::LIMITE) {
            throw new RuntimeException('Mais de ' . self::LIMITE . ' variações. Estreite o filtro.');
        }

        return $todas;
    }

    private function assinar(array $linhas): string
    {
        return hash('sha256', json_encode(array_map(fn ($l) => [$l['variation_id'], $l['preco_antes'], $l['preco_depois']], $linhas)));
    }
}
