<?php

declare(strict_types=1);

namespace Modules\Repair\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores da OS de assistência, lidos da FSM (ADR 0143). Nenhum contador paralelo:
 * tudo sai de `sale_stage_history` (as transições) e de `repair_job_sheets.current_stage_id`
 * (onde a OS está agora).
 *
 * Quatro indicadores, todos na janela fixa de JANELA_DIAS (não é filtro de usuário: o
 * charter põe "filtro por período" como Non-Goal):
 *   · tempo médio por etapa: entre a entrada numa etapa e a entrada na seguinte, da mesma OS;
 *   · OS paradas além do prazo: abertas no fluxo, com `delivery_date` vencida, por etapa atual;
 *   · retorno em garantia: das OS entregues na janela, quantas tiveram `registrar_garantia`;
 *   · produtividade por técnico: OS que chegaram a "concluído" na janela, e o tempo da
 *     abertura até a conclusão.
 *
 * ⚠️ `sale_stage_history.transaction_id` é sobrecarregado: guarda o id da VENDA para os
 * processos de venda e o id da OS (`repair_job_sheets.id`) para o processo do Repair. Ids de
 * tabelas diferentes colidem. Por isso toda leitura do histórico faz as duas coisas: filtra
 * `to_stage_id` pelas etapas do processo do Repair E junta com `repair_job_sheets` do mesmo
 * `business_id`. Só uma das duas não bastaria.
 *
 * Multi-tenant (ADR 0093 Tier 0): `business_id` explícito em toda consulta. `sale_stage_history`
 * tem global scope por business, mas aqui o acesso é por `DB::table`, que não o aplica.
 */
final class IndicadoresOsService
{
    public const JANELA_DIAS = 90;

    /** Processo canônico do Repair (FsmProcessoOsReparoPadraoSeeder · RepairFsmActionController). */
    public const PROCESSO_PADRAO = 'os_reparo_padrao';

    public const ETAPA_CONCLUIDA = 'concluido_aguardando_retirada';

    public const ETAPA_ENTREGUE = 'entregue_completo';

    public const ETAPA_GARANTIA = 'garantia_acionada';

    /**
     * @return array{
     *   janela_dias:int,
     *   tempo_por_etapa:list<array{key:string,label:string,horas_media:float,amostras:int}>,
     *   paradas_por_etapa:list<array{key:string,label:string,count:int}>,
     *   garantia:array{entregues:int,acionadas:int,taxa:?float},
     *   produtividade:list<array{label:string,concluidas:int,horas_media:?float}>
     * }
     */
    public function montar(int $businessId, ?CarbonInterface $agora = null): array
    {
        $agora = $agora ? Carbon::instance($agora) : now();
        $desde = $agora->copy()->subDays(self::JANELA_DIAS);
        $etapas = $this->etapasDoRepair($businessId);

        $vazio = [
            'janela_dias' => self::JANELA_DIAS,
            'tempo_por_etapa' => [],
            'paradas_por_etapa' => [],
            'garantia' => ['entregues' => 0, 'acionadas' => 0, 'taxa' => null],
            'produtividade' => [],
        ];
        if ($etapas->isEmpty()) {
            return $vazio;
        }

        $historico = DB::table('sale_stage_history as h')
            ->join('repair_job_sheets as js', function ($j) use ($businessId) {
                $j->on('js.id', '=', 'h.transaction_id')->where('js.business_id', '=', $businessId);
            })
            ->where('h.business_id', $businessId)
            ->whereIn('h.to_stage_id', $etapas->keys()->all())
            ->where('h.executed_at', '>=', $desde)
            ->orderBy('h.transaction_id')->orderBy('h.executed_at')->orderBy('h.id')
            ->get(['h.transaction_id', 'h.to_stage_id', 'h.executed_at', 'js.service_staff', 'js.created_at as aberta_em']);

        return [
            'janela_dias' => self::JANELA_DIAS,
            'tempo_por_etapa' => $this->tempoPorEtapa($historico, $etapas),
            'paradas_por_etapa' => $this->paradasPorEtapa($businessId, $etapas, $agora),
            'garantia' => $this->garantia($historico, $etapas),
            'produtividade' => $this->produtividade($businessId, $historico, $etapas),
        ];
    }

    /**
     * Etapas do processo do Repair deste business: o processo padrão pela chave, mais
     * qualquer processo em que alguma OS do business esteja hoje (empresa com modelo próprio).
     *
     * @return Collection<int|string, \stdClass> id => {id,key,name,sort_order,is_terminal}
     */
    private function etapasDoRepair(int $businessId): Collection
    {
        $processos = DB::table('sale_processes')
            ->where('business_id', $businessId)
            ->where('key', self::PROCESSO_PADRAO)
            ->pluck('id')
            ->merge(
                DB::table('repair_job_sheets as js')
                    ->join('sale_process_stages as s', 's.id', '=', 'js.current_stage_id')
                    ->join('sale_processes as p', 'p.id', '=', 's.process_id')
                    ->where('js.business_id', $businessId)
                    ->where('p.business_id', $businessId)
                    ->distinct()
                    ->pluck('p.id')
            )
            ->unique()->values();

        if ($processos->isEmpty()) {
            return collect();
        }

        return DB::table('sale_process_stages')
            ->whereIn('process_id', $processos->all())
            ->orderBy('sort_order')
            ->get(['id', 'key', 'name', 'sort_order', 'is_terminal'])
            ->keyBy('id');
    }

    /** Duração de cada passagem = entrada na etapa até a entrada seguinte da MESMA OS. */
    private function tempoPorEtapa(Collection $historico, Collection $etapas): array
    {
        $soma = [];
        $n = [];
        foreach ($historico->groupBy('transaction_id') as $linhas) {
            $linhas = $linhas->values();
            for ($i = 0; $i < $linhas->count() - 1; $i++) {
                $etapa = (int) $linhas[$i]->to_stage_id;
                $seg = Carbon::parse($linhas[$i + 1]->executed_at)->getTimestamp()
                    - Carbon::parse($linhas[$i]->executed_at)->getTimestamp();
                $soma[$etapa] = ($soma[$etapa] ?? 0) + max(0, $seg);
                $n[$etapa] = ($n[$etapa] ?? 0) + 1;
            }
        }

        $saida = [];
        foreach ($etapas as $id => $e) {
            if (! isset($n[$id])) {
                continue;
            }
            $saida[] = [
                'key' => (string) $e->key,
                'label' => (string) $e->name,
                'horas_media' => round($soma[$id] / $n[$id] / 3600, 1),
                'amostras' => $n[$id],
            ];
        }

        return $saida;
    }

    private function paradasPorEtapa(int $businessId, Collection $etapas, CarbonInterface $agora): array
    {
        $abertas = $etapas->filter(fn ($e) => ! (bool) $e->is_terminal)->keys()->all();
        if ($abertas === []) {
            return [];
        }

        $contagem = DB::table('repair_job_sheets')
            ->where('business_id', $businessId)
            ->whereIn('current_stage_id', $abertas)
            ->whereNotNull('delivery_date')
            ->where('delivery_date', '<', $agora)
            ->groupBy('current_stage_id')
            ->selectRaw('current_stage_id, COUNT(*) as total')
            ->pluck('total', 'current_stage_id');

        $saida = [];
        foreach ($etapas as $id => $e) {
            if (! isset($contagem[$id])) {
                continue;
            }
            $saida[] = ['key' => (string) $e->key, 'label' => (string) $e->name, 'count' => (int) $contagem[$id]];
        }

        return $saida;
    }

    /** Denominador e numerador do mesmo universo: só conta garantia de OS entregue na janela. */
    private function garantia(Collection $historico, Collection $etapas): array
    {
        $entregues = $this->osQueEntraramEm($historico, $etapas, self::ETAPA_ENTREGUE);
        $acionadas = $this->osQueEntraramEm($historico, $etapas, self::ETAPA_GARANTIA)->intersect($entregues);

        return [
            'entregues' => $entregues->count(),
            'acionadas' => $acionadas->count(),
            'taxa' => $entregues->isEmpty() ? null : round($acionadas->count() / $entregues->count() * 100, 1),
        ];
    }

    private function produtividade(int $businessId, Collection $historico, Collection $etapas): array
    {
        $idsConcluida = $etapas->filter(fn ($e) => $e->key === self::ETAPA_CONCLUIDA)->keys()->all();
        $porOs = $historico
            ->filter(fn ($h) => in_array((int) $h->to_stage_id, $idsConcluida, true))
            ->groupBy('transaction_id')
            ->map(fn ($linhas) => $linhas->first());

        $porTecnico = $porOs->groupBy(fn ($h) => (int) ($h->service_staff ?? 0));
        $nomes = DB::table('users')
            ->where('business_id', $businessId)
            ->whereIn('id', $porTecnico->keys()->filter()->all())
            ->selectRaw("id, TRIM(CONCAT(COALESCE(surname, ''),' ',COALESCE(first_name, ''),' ',COALESCE(last_name,''))) as nome")
            ->pluck('nome', 'id');

        return $porTecnico
            ->map(function (Collection $linhas, int $tecnicoId) use ($nomes) {
                $horas = $linhas
                    ->filter(fn ($h) => $h->aberta_em !== null)
                    ->map(fn ($h) => max(0, Carbon::parse($h->executed_at)->getTimestamp() - Carbon::parse($h->aberta_em)->getTimestamp()) / 3600);

                return [
                    'label' => $tecnicoId === 0 ? 'Sem técnico' : ((string) ($nomes[$tecnicoId] ?? '') ?: 'Técnico #'.$tecnicoId),
                    'concluidas' => $linhas->count(),
                    'horas_media' => $horas->isEmpty() ? null : round($horas->avg(), 1),
                ];
            })
            ->sortByDesc('concluidas')
            ->values()
            ->all();
    }

    /** @return Collection<int, int> ids de OS distintos */
    private function osQueEntraramEm(Collection $historico, Collection $etapas, string $chave): Collection
    {
        $ids = $etapas->filter(fn ($e) => $e->key === $chave)->keys()->all();

        return $historico
            ->filter(fn ($h) => in_array((int) $h->to_stage_id, $ids, true))
            ->pluck('transaction_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
