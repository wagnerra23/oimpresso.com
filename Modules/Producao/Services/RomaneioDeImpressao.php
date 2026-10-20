<?php

namespace Modules\Producao\Services;

use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Entities\Romaneio;
use Modules\Producao\Entities\RomaneioLinha;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Romaneio de impressão peça a peça (UC-FLX-12 · US-PCP-023, referência Mubisys): na passagem
 * Pré-impressão → Impressão, a lista do que foi para o setor de impressão e corte.
 *
 * Gerado por ação explícita do PCP sobre uma OS de comunicação visual (`comvis_os`) e a etapa de
 * destino dela (`pcp_programacoes`). As linhas copiam os itens do orçamento da OS — descrição, mídia,
 * largura × altura, quantidade, área. Romaneio gravado não muda: gerar de novo cria a versão seguinte,
 * e a leitura sinaliza a peça que já saiu numa versão anterior.
 *
 * Tier 0 (ADR 0093): tudo por `$businessId`; OS, etapa ou romaneio de outra empresa → NAO_ENCONTRADO.
 */
final class RomaneioDeImpressao
{
    public const OS_TIPO = 'comvis_os';

    public function gerar(int $businessId, int $osId, int $programacaoId, int $userId): Romaneio
    {
        return DB::transaction(function () use ($businessId, $osId, $programacaoId, $userId): Romaneio {
            $os = DB::table('comvis_os')->where('business_id', $businessId)->where('id', $osId)->whereNull('deleted_at')
                ->lockForUpdate()->first(['id', 'numero', 'orcamento_id']);
            $etapa = Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
                ->where('os_tipo', self::OS_TIPO)->where('os_id', $osId)->find($programacaoId);
            if ($os === null || $etapa === null) {
                throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS ou etapa não encontrada.');
            }

            $itens = $os->orcamento_id === null ? collect() : DB::table('comvis_orcamento_itens as i')
                ->leftJoin('comvis_materiais as m', fn ($j) => $j->on('m.id', '=', 'i.material_id')->on('m.business_id', '=', 'i.business_id'))
                ->where('i.business_id', $businessId)->where('i.orcamento_id', (int) $os->orcamento_id)
                ->orderBy('i.ordem')->orderBy('i.id')
                ->get(['i.id', 'i.descricao', 'i.material_id', 'm.nome as material_nome', 'i.largura_m', 'i.altura_m', 'i.quantidade', 'i.area_m2']);
            if ($itens->isEmpty()) {
                throw new ApontamentoRecusado(ApontamentoRecusado::QUANTIDADE_INVALIDA, 'A OS não tem peças para enviar à impressão.');
            }

            $versao = 1 + (int) Romaneio::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
                ->where('os_tipo', self::OS_TIPO)->where('os_id', $osId)->max('versao');
            $posto = DB::table('pcp_postos')->where('business_id', $businessId)->where('id', (int) $etapa->posto_id)->first(['id', 'nome']);

            $romaneio = Romaneio::create([
                'business_id' => $businessId, 'os_tipo' => self::OS_TIPO, 'os_id' => $osId, 'versao' => $versao,
                'numero' => sprintf('ROM-%s-v%d', $os->numero, $versao), 'programacao_id' => $etapa->id,
                'posto_id' => $posto?->id, 'posto_nome' => $posto?->nome ?? $etapa->nome, 'gerado_por' => $userId, 'gerado_em' => now(),
            ]);
            foreach ($itens->values() as $i => $item) {
                RomaneioLinha::create([
                    'business_id' => $businessId, 'romaneio_id' => $romaneio->id, 'orcamento_item_id' => $item->id, 'ordem' => $i + 1,
                    'descricao' => $item->descricao, 'material_id' => $item->material_id, 'material_nome' => $item->material_nome,
                    'largura_m' => $item->largura_m, 'altura_m' => $item->altura_m, 'quantidade' => (int) $item->quantidade, 'area_m2' => $item->area_m2,
                ]);
            }

            return $romaneio;
        });
    }

    /** @return array<string, mixed> */
    public function ler(int $businessId, int $romaneioId): array
    {
        $r = Romaneio::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->with('linhas')->find($romaneioId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Romaneio não encontrado.');

        // Peça que já saiu numa versão anterior da mesma OS: sinalizada com o número daquele romaneio.
        $anteriores = DB::table('pcp_romaneio_linhas as l')->join('pcp_romaneios as r', 'r.id', '=', 'l.romaneio_id')
            ->where('r.business_id', $businessId)->where('r.os_tipo', $r->os_tipo)->where('r.os_id', $r->os_id)->where('r.versao', '<', $r->versao)
            ->whereNotNull('l.orcamento_item_id')->orderBy('r.versao')->pluck('r.numero', 'l.orcamento_item_id');

        return [
            'id' => (int) $r->id, 'numero' => $r->numero, 'versao' => (int) $r->versao, 'os' => ['tipo' => $r->os_tipo, 'id' => (int) $r->os_id],
            'etapa' => ['programacao_id' => (int) $r->programacao_id, 'posto_id' => $r->posto_id === null ? null : (int) $r->posto_id, 'nome' => $r->posto_nome],
            'gerado_por' => (int) $r->gerado_por, 'gerado_em' => $r->gerado_em->toIso8601String(),
            'linhas' => $r->linhas->map(fn (RomaneioLinha $l) => [
                'ordem' => (int) $l->ordem, 'orcamento_item_id' => $l->orcamento_item_id === null ? null : (int) $l->orcamento_item_id, 'descricao' => $l->descricao,
                'material' => $l->material_id === null ? null : ['id' => (int) $l->material_id, 'nome' => $l->material_nome],
                'largura_m' => $l->largura_m, 'altura_m' => $l->altura_m, 'quantidade' => (int) $l->quantidade, 'area_m2' => $l->area_m2,
                'ja_enviada_em' => $l->orcamento_item_id !== null ? ($anteriores[$l->orcamento_item_id] ?? null) : null,
            ])->values()->all(),
        ];
    }
}
