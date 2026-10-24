<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;

/**
 * Quadro da produção (thread 06 · UC-PCV-05): uma OS = um cartão, na coluna da etapa atual.
 *
 * Etapa atual = a etapa em andamento; sem nenhuma, a próxima pendente pelo início previsto. OS sem
 * etapa aberta (tudo concluído ou cancelado) sai do quadro. As OS vêm de `pcp_programacoes`, então
 * OS da CV (`comvis_os`) e da venda (`pcp_ordem`) entram sem mudar de dono (D-RELACAO).
 *
 * O cartão leva o que o alocador (UC-FLX-10) e o simulador de bobina (UC-FLX-13) precisam: o id da
 * etapa atual e, nas OS da CV, as mídias com medida no orçamento.
 *
 * Coluna do cartão (UC-PCV-07): o setor da etapa atual, ou o setor para onde o PCP moveu a OS à mão
 * (`pcp_quadro_colunas`, gravado por `ColunaDoQuadro`) enquanto o operador não andar com ela.
 *
 * Tier 0 (ADR 0093): toda consulta por `$businessId` explícito.
 */
final class QuadroDaProducao
{
    public const SEM_SETOR = 'Sem setor';

    /** Eventos que mostram que a OS andou de verdade: depois deles a coluna manual não vale mais. */
    public const ANDOU = [ApontamentoOperadorService::EV_ETAPA_INICIADA, ApontamentoOperadorService::EV_ETAPA_CONCLUIDA];

    public function __construct(private readonly OperadoresDoPosto $operadores, private readonly ArteDasOrdens $arte) {}

    /** @return list<array<string, mixed>> */
    public function cartoes(int $businessId, CarbonInterface $hoje): array
    {
        $etapas = DB::table('pcp_programacoes')->where('business_id', $businessId)->where('status', '!=', 'cancelada')
            ->orderBy('inicio_previsto')->orderBy('id')
            ->get(['id', 'os_tipo', 'os_id', 'posto_id', 'nome', 'status', 'user_id', 'inicio_previsto', 'fim_previsto']);

        $porOs = $etapas->groupBy(fn ($e) => $e->os_tipo.':'.$e->os_id)
            ->filter(fn (Collection $es) => $es->contains(fn ($e) => in_array($e->status, ['pendente', 'iniciada'], true)));
        if ($porOs->isEmpty()) {
            return [];
        }

        $postos = DB::table('pcp_postos')->where('business_id', $businessId)->get(['id', 'nome', 'setor'])->keyBy('id');
        $abertas = $porOs->map(fn (Collection $es) => $es->firstWhere('status', 'iniciada') ?? $es->firstWhere('status', 'pendente'));
        $nomes = $this->nomes($businessId, $abertas->pluck('user_id'));
        $cv = $this->osDaCv($businessId, $this->ids($porOs, RomaneioDeImpressao::OS_TIPO));
        $op = $this->ordens($businessId, $this->ids($porOs, Ordem::OS_TIPO));
        $arte = $this->arte->travadas($businessId, $this->ids($porOs, Ordem::OS_TIPO));
        $manual = $this->colunasManuais($businessId);

        $cartoes = [];
        foreach ($porOs as $chave => $es) {
            $atual = $abertas[$chave];
            $osId = (int) $atual->os_id;
            $dados = $atual->os_tipo === RomaneioDeImpressao::OS_TIPO ? ($cv[$osId] ?? null) : ($op[$osId] ?? null);
            if ($dados === null && in_array($atual->os_tipo, [RomaneioDeImpressao::OS_TIPO, Ordem::OS_TIPO], true)) {
                continue; // OS apagada no módulo dono: a etapa órfã não vira cartão.
            }
            $posto = $atual->posto_id !== null ? ($postos[(int) $atual->posto_id] ?? null) : null;
            $feitas = $es->where('status', 'concluida')->count();
            $prazo = $dados['prazo'] ?? null;

            $cartoes[] = [
                'chave' => $chave, 'os_tipo' => $atual->os_tipo, 'os_id' => $osId,
                'numero' => $dados['numero'] ?? ('OS '.$osId), 'titulo' => $dados['titulo'] ?? null, 'cliente' => $dados['cliente'] ?? null,
                'etapa' => ['id' => (int) $atual->id, 'nome' => $atual->nome, 'status' => $atual->status,
                    'posto_id' => $posto ? (int) $posto->id : null, 'posto' => $posto?->nome, 'setor' => $posto?->setor,
                    'user_id' => $atual->user_id !== null ? (int) $atual->user_id : null,
                    'responsavel' => $atual->user_id !== null ? ($nomes[(int) $atual->user_id] ?? null) : null,
                    'inicio_previsto' => Carbon::parse($atual->inicio_previsto)->format('Y-m-d\TH:i'),
                    'fim_previsto' => Carbon::parse($atual->fim_previsto)->format('Y-m-d\TH:i')],
                // UC-PCV-07: a coluna onde o cartão aparece; `movida` = o PCP pôs ali à mão.
                'coluna' => $manual[$chave] ?? (trim((string) $posto?->setor) ?: self::SEM_SETOR),
                'movida' => isset($manual[$chave]),
                'progresso' => (int) round($feitas / max(1, $es->count()) * 100),
                'prazo' => $prazo,
                'atraso' => $prazo === null ? null : ($prazo < $hoje->toDateString() ? 'atrasada' : ($prazo === $hoje->toDateString() ? 'vence hoje' : null)),
                'midias' => $dados['midias'] ?? [],
                // UC-PCV-04: só a OS por item tem prova de arte; "aguardando" ou "ajuste" trava, aprovada não.
                'arte' => $atual->os_tipo === Ordem::OS_TIPO ? ($arte[$osId]['estado'] ?? null) : null,
            ];
        }

        return $cartoes;
    }

    /**
     * Número, cliente e prazo de cada OS, pela chave `os_tipo:os_id` (feed de eventos do Painel e blocos da Linha).
     *
     * @param  list<array{0: string, 1: int}>  $refs
     * @return array<string, array{numero: string, cliente: ?string, prazo: ?string}>
     */
    public function rotulos(int $businessId, array $refs): array
    {
        $porTipo = [];
        foreach ($refs as [$tipo, $id]) {
            $porTipo[$tipo][(int) $id] = (int) $id;
        }
        $porTipo = array_map('array_values', $porTipo);
        $cv = $this->osDaCv($businessId, $porTipo[RomaneioDeImpressao::OS_TIPO] ?? []);
        $op = $this->ordens($businessId, $porTipo[Ordem::OS_TIPO] ?? []);
        $saida = [];
        foreach ([RomaneioDeImpressao::OS_TIPO => $cv, Ordem::OS_TIPO => $op] as $tipo => $dados) {
            foreach ($dados as $id => $d) {
                $saida[$tipo.':'.$id] = ['numero' => (string) $d['numero'], 'cliente' => $d['cliente'] ?? null, 'prazo' => $d['prazo'] ?? null];
            }
        }

        return $saida;
    }

    /** @return list<array{id: int, nome: string, setor: ?string}> */
    public function postos(int $businessId): array
    {
        return DB::table('pcp_postos')->where('business_id', $businessId)->where('ativo', true)->where('veiculo', false)
            ->orderBy('setor')->orderBy('nome')->get(['id', 'nome', 'setor'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'nome' => (string) $p->nome, 'setor' => $p->setor])->all();
    }

    /**
     * Quem pode ser responsável: usuário da empresa com `producao.access`, e quem o posto restringe
     * (US-PCP-030). Mesma regra que o `AlocacaoDaEtapa` confere ao gravar.
     *
     * @return array{pessoas: list<array{id: int, nome: string}>, por_posto: array<int, list<int>>}
     */
    public function pessoas(int $businessId, array $postoIds): array
    {
        $pessoas = User::query()->where('business_id', $businessId)->orderBy('first_name')->limit(300)->get()
            ->filter(fn (User $u) => $u->can('producao.access'))
            ->map(fn (User $u) => ['id' => (int) $u->id, 'nome' => trim($u->first_name.' '.$u->last_name)])->values()->all();
        $porPosto = [];
        foreach ($postoIds as $id) {
            $porPosto[(int) $id] = $this->operadores->doPosto($businessId, (int) $id);
        }

        return ['pessoas' => $pessoas, 'por_posto' => $porPosto];
    }

    /**
     * Mídias das OS da CV no quadro, com a bobina cadastrada (ou null: falta a largura útil).
     *
     * @param  list<array<string, mixed>>  $cartoes
     * @return list<array{id: int, nome: string, bobina: ?array{largura_util_m: string, margem_m: string, permite_girar: bool}}>
     */
    public function midias(int $businessId, array $cartoes): array
    {
        $ids = collect($cartoes)->pluck('midias')->flatten()->unique()->values()->all();
        if ($ids === []) {
            return [];
        }
        $bobinas = DB::table('pcp_bobinas')->where('business_id', $businessId)->whereIn('material_id', $ids)
            ->get(['material_id', 'largura_util_m', 'margem_m', 'permite_girar'])->keyBy('material_id');

        return DB::table('comvis_materiais')->where('business_id', $businessId)->whereIn('id', $ids)->whereNull('deleted_at')
            ->orderBy('nome')->get(['id', 'nome'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'nome' => (string) $m->nome, 'bobina' => ($b = $bobinas[$m->id] ?? null) === null ? null
                : ['largura_util_m' => (string) $b->largura_util_m, 'margem_m' => (string) $b->margem_m, 'permite_girar' => (bool) $b->permite_girar]])
            ->all();
    }

    /**
     * Colunas manuais ainda válidas, pela chave `os_tipo:os_id`: some a que tem evento de etapa
     * iniciada ou concluída mais novo que o movimento.
     *
     * @return array<string, string>
     */
    private function colunasManuais(int $businessId): array
    {
        $manuais = DB::table('pcp_quadro_colunas')->where('business_id', $businessId)->get(['os_tipo', 'os_id', 'setor', 'ate_evento_id']);
        if ($manuais->isEmpty()) {
            return [];
        }
        $andou = DB::table('pcp_eventos')->where('business_id', $businessId)->whereIn('tipo', self::ANDOU)
            ->whereIn('os_id', $manuais->pluck('os_id')->unique()->all())
            ->groupBy('os_tipo', 'os_id')->selectRaw('os_tipo, os_id, MAX(id) as ultimo')->get()
            ->mapWithKeys(fn ($e) => [$e->os_tipo.':'.$e->os_id => (int) $e->ultimo]);

        return $manuais->filter(fn ($m) => ($andou[$m->os_tipo.':'.$m->os_id] ?? 0) <= (int) $m->ate_evento_id)
            ->mapWithKeys(fn ($m) => [$m->os_tipo.':'.$m->os_id => (string) $m->setor])->all();
    }

    /** @return list<int> */
    private function ids(Collection $porOs, string $tipo): array
    {
        return $porOs->keys()->filter(fn (string $k) => str_starts_with($k, $tipo.':'))
            ->map(fn (string $k) => (int) substr($k, strlen($tipo) + 1))->values()->all();
    }

    /** @return array<int, string> */
    private function nomes(int $businessId, Collection $ids): array
    {
        $ids = $ids->filter()->unique()->all();

        return $ids === [] ? [] : DB::table('users')->where('business_id', $businessId)->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name.' '.$u->last_name)])->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function osDaCv(int $businessId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $os = DB::table('comvis_os as os')
            ->leftJoin('comvis_orcamentos as o', fn ($j) => $j->on('o.id', '=', 'os.orcamento_id')->on('o.business_id', '=', 'os.business_id'))
            ->leftJoin('contacts as c', fn ($j) => $j->on('c.id', '=', 'o.contato_id')->on('c.business_id', '=', 'os.business_id'))
            ->where('os.business_id', $businessId)->whereIn('os.id', $ids)->whereNull('os.deleted_at')
            ->get(['os.id', 'os.numero', 'os.orcamento_id', 'os.data_prazo', 'c.name as cliente']);
        $itens = DB::table('comvis_orcamento_itens')->where('business_id', $businessId)
            ->whereIn('orcamento_id', $os->pluck('orcamento_id')->filter()->unique()->all())->orderBy('ordem')->orderBy('id')
            ->get(['orcamento_id', 'descricao', 'material_id', 'largura_m', 'altura_m'])->groupBy('orcamento_id');

        return $os->mapWithKeys(function ($o) use ($itens) {
            $doOrc = $o->orcamento_id !== null ? ($itens[$o->orcamento_id] ?? collect()) : collect();

            return [(int) $o->id => ['numero' => (string) $o->numero, 'cliente' => $o->cliente,
                'titulo' => $doOrc->pluck('descricao')->filter()->take(2)->implode(' + ') ?: null,
                'prazo' => $o->data_prazo !== null ? substr((string) $o->data_prazo, 0, 10) : null,
                'midias' => $doOrc->filter(fn ($i) => $i->material_id !== null && $i->largura_m !== null && $i->altura_m !== null)
                    ->pluck('material_id')->map(fn ($id) => (int) $id)->unique()->values()->all()]];
        })->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function ordens(int $businessId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('pcp_ordens as po')
            ->leftJoin('products as p', fn ($j) => $j->on('p.id', '=', 'po.product_id')->on('p.business_id', '=', 'po.business_id'))
            ->leftJoin('transactions as t', fn ($j) => $j->on('t.id', '=', 'po.transaction_id')->on('t.business_id', '=', 'po.business_id'))
            ->leftJoin('contacts as c', fn ($j) => $j->on('c.id', '=', 't.contact_id')->on('c.business_id', '=', 'po.business_id'))
            ->where('po.business_id', $businessId)->whereIn('po.id', $ids)
            ->get(['po.id', 'po.prazo_prometido', 'p.name as produto', 't.invoice_no', 'c.name as cliente'])
            ->mapWithKeys(fn ($o) => [(int) $o->id => ['numero' => 'OP '.$o->id, 'titulo' => $o->produto,
                'cliente' => $o->cliente, 'prazo' => $o->prazo_prometido !== null ? substr((string) $o->prazo_prometido, 0, 10) : null]])
            ->all();
    }
}
