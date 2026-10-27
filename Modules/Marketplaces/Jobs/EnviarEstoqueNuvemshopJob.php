<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Jobs;

use App\VariationLocationDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Modules\Marketplaces\Entities\Anuncio;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use RuntimeException;

/**
 * Manda para a loja Nuvemshop o estoque atual de UMA variante anunciada (ADR 0443, fatia d).
 *
 * Só LÊ o estoque do ERP (`qty_available` da variação no local de venda da conta) e o envia com
 * `POST {api}/{store}/products/{product_id}/variants/stock` `{action: replace, value, id}`.
 * Não grava estoque no ERP. A Nuvemshop só aceita inteiro: vai a parte inteira, nunca negativo.
 * O id do produto na loja fica em `mkt_listings.attributes.product_id`; o da variante em
 * `item_id_external` (o mesmo vínculo que o pedido usa).
 *
 * Único por empresa+anúncio e com atraso: uma rajada de baixas vira um envio, com o saldo final.
 */
class EnviarEstoqueNuvemshopJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public int $businessId, public int $anuncioId)
    {
    }

    public function uniqueId(): string
    {
        return $this->businessId.':'.$this->anuncioId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Quantidade que vai para a loja, ou null quando este anúncio não deve receber estoque.
     * Público para o teste conferir o valor por um segundo caminho.
     */
    public static function quantidadeParaLoja(int $businessId, int $anuncioId): ?int
    {
        if (! config('marketplaces.nuvemshop.ativo', false)) {
            return null;
        }
        // SUPERADMIN: job em fila sem sessão; a empresa vem do construtor e entra explícita na consulta.
        $anuncio = Anuncio::withoutGlobalScopes()->where('business_id', $businessId)->where('id', $anuncioId)->where('status', 'active')->first();
        $conta = $anuncio === null ? null : ContaVendedor::withoutGlobalScopes()->where('business_id', $businessId)
            ->where('id', $anuncio->account_id)->where('status', ContaVendedor::STATUS_ATIVA)->where('enviar_estoque', 1)->first();
        if ($anuncio === null || $conta === null || ! $conta->location_id || ! $anuncio->variation_id) {
            return null;
        }

        $qtd = VariationLocationDetails::query()
            ->where('variation_id', $anuncio->variation_id)
            ->where('location_id', (int) $conta->location_id)
            ->value('qty_available');

        return max(0, (int) floor((float) ($qtd ?? 0)));
    }

    public function handle(): void
    {
        $qtd = self::quantidadeParaLoja($this->businessId, $this->anuncioId);
        if ($qtd === null) {
            return;
        }

        // SUPERADMIN: job em fila sem sessão; a empresa vem do construtor e entra explícita na consulta.
        $anuncio = Anuncio::withoutGlobalScopes()->where('business_id', $this->businessId)->findOrFail($this->anuncioId);
        $conta = ContaVendedor::withoutGlobalScopes()->where('business_id', $this->businessId)->findOrFail($anuncio->account_id);
        // getAttribute: `$anuncio->attributes` colide com a propriedade interna do Eloquent.
        $produtoLoja = (string) (((array) ($anuncio->getAttribute('attributes') ?? []))['product_id'] ?? '');
        if ($produtoLoja === '') {
            $anuncio->forceFill(['sync_error' => 'anúncio sem o id do produto na loja'])->save();

            return;
        }

        $base = rtrim((string) Marketplace::query()->where('id', $conta->marketplace_id)->value('api_base_url'), '/');
        $resposta = Http::withToken((string) $conta->access_token)->acceptJson()->asJson()->timeout(15)
            ->withHeaders(['User-Agent' => (string) config('marketplaces.nuvemshop.user_agent')])
            ->post("{$base}/{$conta->seller_id_external}/products/{$produtoLoja}/variants/stock", [
                'action' => 'replace',
                'value' => $qtd,
                'id' => (int) $anuncio->item_id_external,
            ]);

        if (! $resposta->successful()) {
            $anuncio->forceFill(['sync_error' => 'Nuvemshop devolveu HTTP '.$resposta->status()])->save();
            throw new RuntimeException('Nuvemshop recusou o estoque (HTTP '.$resposta->status().').');
        }

        $anuncio->forceFill(['stock_marketplace' => $qtd, 'last_sync_at' => now(), 'sync_error' => null])->save();
    }
}
