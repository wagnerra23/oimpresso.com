<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Ficha da OS por item (UC-PCV-10 · auditoria lote 3.2): cabeçalho, situação e as etapas do roteiro com
 * posto, responsável, previsto × apontado. Só leitura. Previsto = fim − início programados; apontado =
 * soma dos pares início→fim fechados do apontamento da etapa (o mesmo par que o CustoRealService usa).
 * Tier 0: tudo filtrado por `$businessId`; OS de outra empresa → NAO_ENCONTRADO (404 no controller).
 */
final class FichaDaOrdem
{
    /** @return array{os: array<string, mixed>, etapas: list<array<string, mixed>>} */
    public function montar(int $businessId, int $ordemId): array
    {
        $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->with('currentStage')->find($ordemId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS não encontrada.');

        $cab = DB::table('pcp_ordens as po')
            ->leftJoin('products as p', fn ($j) => $j->on('p.id', '=', 'po.product_id')->on('p.business_id', '=', 'po.business_id'))
            ->leftJoin('transactions as t', fn ($j) => $j->on('t.id', '=', 'po.transaction_id')->on('t.business_id', '=', 'po.business_id'))
            ->leftJoin('contacts as c', fn ($j) => $j->on('c.id', '=', 't.contact_id')->on('c.business_id', '=', 'po.business_id'))
            ->where('po.business_id', $businessId)->where('po.id', $ordemId)
            ->first(['po.quantidade', 'po.prazo_prometido', 'p.name as produto', 't.invoice_no', 'c.name as cliente']);

        $progs = DB::table('pcp_programacoes as pg')
            ->leftJoin('pcp_postos as ps', fn ($j) => $j->on('ps.id', '=', 'pg.posto_id')->on('ps.business_id', '=', 'pg.business_id'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.id', '=', 'pg.user_id')->on('u.business_id', '=', 'pg.business_id'))
            ->where('pg.business_id', $businessId)->where('pg.os_tipo', Ordem::OS_TIPO)->where('pg.os_id', $ordemId)
            ->orderBy('pg.bloco')->orderBy('pg.trilha')->orderBy('pg.ordem')->orderBy('pg.id')
            ->get(['pg.id', 'pg.nome', 'pg.status', 'pg.trilha', 'pg.junta', 'pg.posto_id', 'pg.user_id', 'pg.inicio_previsto', 'pg.fim_previsto',
                'ps.nome as posto', 'ps.setor', 'u.first_name', 'u.last_name']);

        $segundos = DB::table('pcp_apontamentos as i')
            ->join('pcp_apontamentos as f', fn ($j) => $j->on('f.inicio_id', '=', 'i.id')->on('f.business_id', '=', 'i.business_id'))
            ->where('i.business_id', $businessId)->where('i.tipo', 'inicio')->where('i.os_tipo', Ordem::OS_TIPO)->where('i.os_id', $ordemId)
            ->get(['i.programacao_id', 'i.momento as de', 'f.momento as ate'])
            ->groupBy('programacao_id')->map(fn ($ps) => $ps->sum(fn ($p) => max(0, strtotime($p->ate) - strtotime($p->de))));

        $etapas = $progs->map(fn ($p) => [
            'id' => (int) $p->id, 'nome' => $p->nome, 'status' => $p->status, 'trilha' => (int) $p->trilha, 'junta' => $p->junta,
            'posto_id' => $p->posto_id !== null ? (int) $p->posto_id : null, 'posto' => $p->posto, 'setor' => $p->setor,
            'user_id' => $p->user_id !== null ? (int) $p->user_id : null,
            'responsavel' => $p->user_id !== null ? trim($p->first_name.' '.$p->last_name) : null,
            'previsto_h' => round(max(0, strtotime((string) $p->fim_previsto) - strtotime((string) $p->inicio_previsto)) / 3600, 1),
            'apontado_h' => round(($segundos[$p->id] ?? 0) / 3600, 1),
        ])->values()->all();

        return [
            'os' => [
                'id' => (int) $ordem->id, 'numero' => 'OP '.$ordem->id, 'produto' => $cab?->produto, 'cliente' => $cab?->cliente,
                'venda' => $cab?->invoice_no, 'quantidade' => $cab?->quantidade,
                'prazo' => $cab?->prazo_prometido !== null ? substr((string) $cab->prazo_prometido, 0, 10) : null,
                'situacao' => $ordem->currentStage?->key, 'situacao_nome' => $ordem->currentStage?->name,
            ],
            'etapas' => $etapas,
        ];
    }
}
