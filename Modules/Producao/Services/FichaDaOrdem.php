<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Ficha da OS por item (UC-PCV-10 · auditoria lote 3.2): cabeçalho, situação e as etapas do roteiro com
 * posto, responsável, previsto × apontado. Só leitura. Previsto = fim − início programados; apontado =
 * soma dos pares início→fim fechados do apontamento da etapa (o mesmo par que o CustoRealService usa).
 * Tier 0: tudo filtrado por `$businessId`; OS de outra empresa → NAO_ENCONTRADO (404 no controller).
 *
 * Abas Materiais e Arte (lote 3.4), também só leitura: materiais = MateriaisDaEtapa (previsto pela receita ×
 * apontado na OS) com o saldo em décimos de milésimo inteiros, sem float; arte = os pedidos de aprovação
 * da OS, do mais novo ao mais antigo, com a versão que vale para produzir (a última aprovada, UC-PCV-11).
 */
final class FichaDaOrdem
{
    public function __construct(private readonly MateriaisDaEtapa $materiais, private readonly FaltaDeMaterialDaOs $falta) {}

    /** @return array{os: array<string, mixed>, etapas: list<array<string, mixed>>} */
    public function montar(int $businessId, int $ordemId): array
    {
        $ordem = Ordem::query()->where('business_id', $businessId)->with('currentStage')->find($ordemId)
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

    /** @return array{itens: list<array<string, mixed>>, falta: list<array<string, mixed>>} */
    public function materiais(int $businessId, int $ordemId, ?int $programacaoId): array
    {
        $ordem = Ordem::query()->where('business_id', $businessId)->with('currentStage')->findOrFail($ordemId);
        $lista = $programacaoId === null ? [] : ($this->materiais->paraEtapas($businessId, [$programacaoId])[$programacaoId] ?? []);
        $itens = array_map(function (array $m) {
            $saldo = $m['previsto'] === null ? null : $this->dm($m['previsto']) - $this->dm($m['apontado']);

            return ['variation_id' => $m['variation_id'], 'nome' => $m['nome'], 'unidade' => $m['unidade'], 'previsto' => $m['previsto'],
                'apontado' => $m['apontado'], 'saldo' => $saldo === null ? null : $this->texto($saldo), 'estourou' => $saldo !== null && $saldo < 0];
        }, $lista);

        return ['itens' => $itens, 'falta' => $ordem->currentStage?->key === 'aguardando_material' ? $this->falta->daOrdem($businessId, $ordem) : []];
    }

    /**
     * Um item por envio, do mais novo ao mais antigo. A versão é a GRAVADA no envio (UC-PCART-09, VersaoDaArte): sobe
     * só quando o arquivo muda, então o "Reenviar link" do mesmo arquivo aparece com o mesmo número, igual ao que o
     * operador vê no celular. Contar os envios faria o reenvio virar versão nova.
     *
     * @return list<array<string, mixed>>
     */
    public function artes(int $businessId, int $ordemId): array
    {
        $pedidos = DB::table('pcp_aprovacoes_arte')->where('business_id', $businessId)->where('ordem_id', $ordemId)
            ->orderByDesc('id')->get(['id', 'arquivo', 'versao', 'decisao', 'comentario', 'decidido_em', 'created_at']);
        $vigente = $pedidos->where('decisao', 'aprovada')->sortByDesc('decidido_em')->first()?->id;

        return $pedidos->values()->map(fn ($p) => ['id' => (int) $p->id, 'versao' => (int) $p->versao, 'arquivo' => (string) $p->arquivo,
            'decisao' => $p->decisao, 'comentario' => $p->comentario, 'enviada_em' => substr((string) $p->created_at, 0, 16),
            'decidida_em' => $p->decidido_em !== null ? substr((string) $p->decidido_em, 0, 16) : null, 'vigente' => $p->id === $vigente])->all();
    }

    private function dm(string $v): int
    {
        $neg = str_starts_with(trim($v), '-');
        [$i, $f] = array_pad(explode('.', ltrim(trim($v), '-'), 2), 2, '');
        $n = (int) $i * 10000 + (int) substr(str_pad($f, 4, '0'), 0, 4);

        return $neg ? -$n : $n;
    }

    private function texto(int $n): string
    {
        return ($n < 0 ? '-' : '').sprintf('%d.%04d', intdiv(abs($n), 10000), abs($n) % 10000);
    }
}
