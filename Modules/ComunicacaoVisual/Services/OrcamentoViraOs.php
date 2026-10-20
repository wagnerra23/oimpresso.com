<?php

namespace Modules\ComunicacaoVisual\Services;

use App\Media;
use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;
use Modules\ComunicacaoVisual\Entities\Os;
use Modules\ComunicacaoVisual\Entities\OsItem;

/**
 * Orçamento aprovado vira OS sem redigitar (UC-FLX-01 · US-COMVIS-020).
 *
 * Aprovar o orçamento: marca `aprovado` e cria a OS (`comvis_os`, etapa `arte`) com
 *  - um item por item do orçamento, com descrição, material, largura, altura, quantidade, área;
 *  - os arquivos anexados ao orçamento (`media` do core), apontando para o mesmo arquivo;
 *  - `valor_total` = `total` do orçamento. Nenhum preço é recalculado e o orçamento não muda de
 *    valor (regra mestre de valor, proibicoes.md): só o `status` dele é gravado.
 *
 * Idempotente: trava a linha do orçamento (`lockForUpdate`); se já existe OS dele, devolve a
 * mesma sem criar outra nem copiar de novo. Orçamento recusado ou expirado não vira OS.
 *
 * Tier 0 (ADR 0093): lê e grava só no `$businessId` recebido. Toda leitura tem
 * `where('business_id', $businessId)` explícito, além do escopo da sessão (que não filtra sem
 * sessão, em fila); toda gravação passa o `business_id` explícito. Nenhum escopo é desligado.
 */
final class OrcamentoViraOs
{
    /** Status de orçamento que podem ser aprovados (aprovado = reenvio idempotente). */
    public const APROVAVEIS = ['rascunho', 'enviado', 'aprovado'];

    /**
     * @return array{0: Os, 1: bool} a OS e se ela foi criada agora
     *
     * @throws \DomainException orçamento recusado/expirado
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException orçamento de outra empresa ou inexistente
     */
    public function aprovar(int $businessId, int $orcamentoId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($businessId, $orcamentoId, $userId) {
            $orc = Orcamento::query()->where('business_id', $businessId)
                ->whereKey($orcamentoId)->lockForUpdate()->firstOrFail();
            $existente = Os::query()->where('business_id', $businessId)
                ->where('orcamento_id', $orc->id)->first();
            if ($existente) {
                return [$existente, false];
            }

            if (! in_array($orc->status, self::APROVAVEIS, true)) {
                throw new \DomainException("Orçamento {$orc->status} não pode virar OS.");
            }

            if ($orc->status !== 'aprovado') {
                $orc->status = 'aprovado';
                $orc->save();
            }

            $os = Os::create([
                'business_id'  => $businessId,
                'orcamento_id' => $orc->id,
                'numero'       => $this->proximoNumero($businessId),
                'status_etapa' => 'arte',
                'data_inicio'  => now()->toDateString(),
                'vendedor_id'  => $orc->vendedor_id,
                'valor_total'  => $orc->total,
                'observacoes'  => $orc->observacoes,
            ]);

            $itens = OrcamentoItem::query()->where('business_id', $businessId)
                ->where('orcamento_id', $orc->id)->orderBy('ordem')->orderBy('id')->get();
            foreach ($itens as $i => $item) {
                OsItem::create([
                    'business_id'       => $businessId,
                    'os_id'             => $os->id,
                    'orcamento_item_id' => $item->id,
                    'material_id'       => $item->material_id,
                    'descricao'         => $item->descricao,
                    'largura_m'         => $item->largura_m,
                    'altura_m'          => $item->altura_m,
                    'quantidade'        => $item->quantidade,
                    'area_m2'           => $item->area_m2,
                    'observacoes'       => $item->observacoes,
                    'ordem'             => $i + 1,
                ]);
            }

            $this->copiarArquivos($businessId, $orc, $os, $userId);

            return [$os->load('itens'), true];
        });
    }

    /** Os anexos do orçamento passam a aparecer na OS — mesma `file_name`, mesmo arquivo em disco. */
    private function copiarArquivos(int $businessId, Orcamento $orc, Os $os, ?int $userId): void
    {
        $anexos = Media::where('business_id', $businessId)
            ->where('model_type', Orcamento::class)->where('model_id', $orc->id)->orderBy('id')->get();
        foreach ($anexos as $m) {
            Media::create([
                'business_id'      => $businessId,
                'file_name'        => $m->file_name,
                'description'      => $m->description,
                'uploaded_by'      => $m->uploaded_by ?? $userId,
                'model_type'       => Os::class,
                'model_id'         => $os->id,
                'model_media_type' => $m->model_media_type,
            ]);
        }
    }

    /** OS-YYYY-NNNNN por empresa, recomeça a cada ano (mesma regra do número do orçamento). */
    public function proximoNumero(int $businessId): string
    {
        $prefixo = 'OS-' . now()->year . '-';
        $ultimo = Os::query()->withTrashed()->where('business_id', $businessId)
            ->where('numero', 'LIKE', "{$prefixo}%")->max('numero');

        $proximo = $ultimo ? ((int) substr((string) $ultimo, strlen($prefixo))) + 1 : 1;

        return $prefixo . str_pad((string) $proximo, 5, '0', STR_PAD_LEFT);
    }
}
