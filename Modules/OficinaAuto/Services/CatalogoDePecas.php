<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * US-AUTO-008 — catálogo de peças por veículo (decisão [W] 2026-10-26: marca e modelo em texto).
 *
 * A peça é um `product` da própria empresa; a compatibilidade é uma linha em
 * `oficina_pecas_compativeis`. Na OS de um veículo, o catálogo devolve as peças que servem nele:
 * mesma marca, mesmo modelo (ou modelo vazio = toda a marca), ano dentro da faixa (se o veículo
 * tiver ano). Comparação sem caixa e sem espaço nas pontas.
 *
 * Só leitura e cadastro de compatibilidade: não mexe em preço nem em estoque.
 * Tier 0 (ADR 0093): todo acesso filtra pelo `business_id` recebido, nunca pela sessão.
 */
class CatalogoDePecas
{
    public function ligado(): bool
    {
        return (bool) config('oficina-auto.catalogo_pecas.enabled', false);
    }

    /**
     * @return list<array{product_id:int, nome:string, sku:?string, marca:string, modelo:?string}>
     */
    public function compativeisCom(int $businessId, Vehicle $veiculo): array
    {
        if ((int) $veiculo->business_id !== $businessId) {
            throw new InvalidArgumentException('Veículo não pertence à empresa.');
        }
        // brand/model lidos por atributo: o Model não declara @property para eles (US-AUTO-008).
        $marca = self::norm($veiculo->getAttribute('brand'));
        if ($marca === '') {
            return [];
        }
        $modelo = self::norm($veiculo->getAttribute('model'));
        $ano = $veiculo->model_year ?: $veiculo->manufacture_year;

        $q = DB::table('oficina_pecas_compativeis as c')
            ->join('products as p', function ($j) {
                $j->on('p.id', '=', 'c.product_id')->on('p.business_id', '=', 'c.business_id');
            })
            ->where('c.business_id', $businessId)
            ->whereRaw('LOWER(TRIM(c.brand)) = ?', [$marca])
            ->where(function ($w) use ($modelo) {
                $w->whereNull('c.model')->orWhereRaw("TRIM(c.model) = ''");
                if ($modelo !== '') {
                    $w->orWhereRaw('LOWER(TRIM(c.model)) = ?', [$modelo]);
                }
            });

        if ($ano) {
            $q->where(fn ($w) => $w->whereNull('c.year_from')->orWhere('c.year_from', '<=', $ano))
                ->where(fn ($w) => $w->whereNull('c.year_to')->orWhere('c.year_to', '>=', $ano));
        }

        return $q->orderBy('p.name')
            ->get(['p.id as product_id', 'p.name as nome', 'p.sku', 'c.brand as marca', 'c.model as modelo'])
            ->unique('product_id')
            ->map(fn ($r) => [
                'product_id' => (int) $r->product_id,
                'nome'       => (string) $r->nome,
                'sku'        => $r->sku,
                'marca'      => (string) $r->marca,
                'modelo'     => $r->modelo,
            ])
            ->values()
            ->all();
    }

    /** Registra "esta peça serve em <marca> <modelo>". Idempotente. */
    public function marcarCompativel(
        int $businessId,
        int $productId,
        string $marca,
        ?string $modelo = null,
        ?int $anoDe = null,
        ?int $anoAte = null,
    ): int {
        $marca = trim($marca);
        $modelo = $modelo !== null && trim($modelo) !== '' ? trim($modelo) : null;
        if ($marca === '') {
            throw new InvalidArgumentException('Informe a marca.');
        }
        if ($anoDe && $anoAte && $anoDe > $anoAte) {
            throw new InvalidArgumentException('Ano inicial maior que o final.');
        }
        $daEmpresa = DB::table('products')->where('id', $productId)->where('business_id', $businessId)->exists();
        if (! $daEmpresa) {
            throw new InvalidArgumentException('Peça não encontrada nesta empresa.');
        }

        $chave = ['business_id' => $businessId, 'product_id' => $productId, 'brand' => $marca, 'model' => $modelo];
        $existente = DB::table('oficina_pecas_compativeis')->where($chave)->value('id');
        $dados = ['year_from' => $anoDe, 'year_to' => $anoAte, 'updated_at' => now()];
        if ($existente) {
            DB::table('oficina_pecas_compativeis')->where('id', $existente)->update($dados);

            return (int) $existente;
        }

        return (int) DB::table('oficina_pecas_compativeis')->insertGetId($chave + $dados + ['created_at' => now()]);
    }

    private static function norm(?string $s): string
    {
        return mb_strtolower(trim((string) $s));
    }
}
