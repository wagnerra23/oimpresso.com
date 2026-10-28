<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Painel da produção (thread 06 · UC-PCV-01..04, UC-PCV-29 · US-PCP-017): o que trava a produção hoje.
 *
 * Tudo parte dos cartões do Quadro (`QuadroDaProducao::cartoes`), então "atrasadas ou vencendo" e "arte
 * travando" são o mesmo número no Painel e no filtro do Quadro (UC-PCV-03). Os alertas só existem quando
 * há problema, cada um com uma ação (UC-PCV-01) — dia limpo devolve `alertas = []`.
 *
 * Fora deste corte: refugo em dinheiro (fórmula de custo é do Custo, thread 08), produtividade por setor
 * (UC-PCV-27, LGPD) e aproveitamento de mídia agregado (UC-PCV-26).
 *
 * Tier 0 (ADR 0093): toda consulta por `$businessId` explícito.
 */
final class PainelDaProducao
{
    /** Eventos que falam de uma pessoa, não da produção (apontado × presença): ficam fora do feed. */
    private const FORA_DO_FEED = [ApontamentoOperadorService::EV_PENDENCIA_GESTOR, AlocacaoDaEtapa::EV_AVISO_REPROGRAMADA];

    private const ROTULOS = [
        ApontamentoOperadorService::EV_ETAPA_INICIADA => 'Iniciou',
        ApontamentoOperadorService::EV_ETAPA_CONCLUIDA => 'Concluiu',
        ApontamentoOperadorService::EV_AVISO_PROXIMO => 'Avisou o próximo da fila',
        ApontamentoOperadorService::EV_FECHADO_AUTO => 'Cronômetro fechado no fim do turno',
        ApontamentoOperadorService::EV_CORRECAO => 'Pediu correção do apontamento',
        AlocacaoDaEtapa::EV_ALOCADA => 'Realocou',
        AprovacaoArteService::EV_SOLICITADA => 'Mandou a arte para o cliente aprovar',
        AprovacaoArteService::EV_APROVADA => 'Cliente aprovou a arte',
        AprovacaoArteService::EV_RECUSADA => 'Cliente pediu ajuste na arte',
        SituacaoDaOrdem::EV_RETRABALHO => 'Abriu retrabalho',
        ColunaDoQuadro::EV_MOVIDA => 'Moveu à mão',
    ];

    public function __construct(private readonly QuadroDaProducao $quadro, private readonly ArteDasOrdens $arte) {}

    /**
     * @param  list<array<string, mixed>>  $cartoes  saída de `QuadroDaProducao::cartoes` para o mesmo dia
     * @return array<string, mixed>
     */
    public function montar(int $businessId, CarbonInterface $agora, array $cartoes): array
    {
        $carga = $this->carga($businessId, $agora);
        $fila = $this->filaDeArte($businessId, $cartoes);
        $indicadores = $this->indicadores($businessId, $agora, $cartoes);

        return [
            'indicadores' => $indicadores,
            'alertas' => $this->alertas($indicadores, $fila, $carga),
            'fila_arte' => $fila,
            'carga' => $carga,
            'eventos' => $this->eventos($businessId),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cartoes
     * @return array<string, mixed>
     */
    private function indicadores(int $businessId, CarbonInterface $agora, array $cartoes): array
    {
        $atrasos = array_values(array_filter($cartoes, fn ($c) => $c['atraso'] !== null));
        $semana = Carbon::parse($agora)->startOfWeek();
        $refugo = DB::table('pcp_refugos')->where('business_id', $businessId)->where('momento', '>=', $semana)->where('momento', '<=', $agora)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(quantidade), 0) as q')->first();

        return [
            'em_producao' => count($cartoes),
            'vencem_hoje' => count(array_filter($cartoes, fn ($c) => $c['atraso'] === 'vence hoje')),
            'atrasadas_ou_vencendo' => count($atrasos),
            'atrasadas_numeros' => array_column($atrasos, 'numero'),
            'atrasadas_so_atraso' => count(array_filter($atrasos, fn ($c) => $c['atraso'] === 'atrasada')),
            'arte_travando' => count(array_filter($cartoes, fn ($c) => ($c['arte'] ?? null) !== null)),
            'refugo_semana' => ['ocorrencias' => (int) $refugo->n, 'quantidade' => (string) round((float) $refugo->q, 2)],
        ];
    }

    /**
     * Fila de aprovação de arte (UC-PCV-04): o que o cliente disse e quando. Ajuste pedido primeiro.
     *
     * @param  list<array<string, mixed>>  $cartoes
     * @return list<array<string, mixed>>
     */
    private function filaDeArte(int $businessId, array $cartoes): array
    {
        $comArte = array_values(array_filter($cartoes, fn ($c) => ($c['arte'] ?? null) !== null));
        $situacao = $this->arte->travadas($businessId, array_map(fn ($c) => (int) $c['os_id'], $comArte));
        $fila = [];
        foreach ($comArte as $c) {
            $s = $situacao[(int) $c['os_id']] ?? null;
            if ($s === null) {
                continue;
            }
            $fila[] = ['chave' => $c['chave'], 'os_id' => (int) $c['os_id'], 'numero' => $c['numero'], 'cliente' => $c['cliente'], 'estado' => $s['estado'],
                'desde' => $s['desde'], 'comentario' => $s['comentario'], 'expira_em' => $s['expira_em']];
        }
        usort($fila, fn ($a, $b) => [$a['estado'] === ArteDasOrdens::AJUSTE ? 0 : 1, $a['desde']] <=> [$b['estado'] === ArteDasOrdens::AJUSTE ? 0 : 1, $b['desde']]);

        return $fila;
    }

    /**
     * Carga das máquinas no dia: horas das etapas previstas para começar nele (exceto canceladas) ÷ horas do dia do posto.
     * Pública porque a Linha do tempo (thread 07) mostra o mesmo número por máquina — uma conta só.
     *
     * @return list<array{posto_id: int, nome: string, setor: ?string, horas: float, capacidade: float, pct: int}>
     */
    public function carga(int $businessId, CarbonInterface $agora): array
    {
        $dia = $agora->toDateString();
        $postos = DB::table('pcp_postos')->where('business_id', $businessId)->where('ativo', true)->where('veiculo', false)
            ->where('tipo_custo', 'maquina')->orderBy('setor')->orderBy('nome')->get(['id', 'nome', 'setor', 'horas_por_dia']);
        $horas = DB::table('pcp_programacoes')->where('business_id', $businessId)->where('status', '!=', 'cancelada')
            ->whereNotNull('posto_id')->where('inicio_previsto', '>=', $dia.' 00:00:00')->where('inicio_previsto', '<=', $dia.' 23:59:59')
            ->get(['posto_id', 'inicio_previsto', 'fim_previsto'])->groupBy('posto_id')
            ->map(fn ($es) => $es->sum(fn ($e) => max(0, strtotime((string) $e->fim_previsto) - strtotime((string) $e->inicio_previsto))) / 3600);

        return $postos->map(function ($p) use ($horas) {
            $h = round((float) ($horas[$p->id] ?? 0), 1);
            $cap = (float) $p->horas_por_dia;

            return ['posto_id' => (int) $p->id, 'nome' => (string) $p->nome, 'setor' => $p->setor, 'horas' => $h, 'capacidade' => $cap,
                'pct' => $cap > 0 ? (int) round($h / $cap * 100) : 0];
        })->values()->all();
    }

    /**
     * Um alerta por problema, cada um com uma ação (UC-PCV-01). Nenhum problema → lista vazia.
     *
     * @param  array<string, mixed>  $ind
     * @param  list<array<string, mixed>>  $fila
     * @param  list<array<string, mixed>>  $carga
     * @return list<array{tipo: string, tom: string, titulo: string, texto: string, acao: array{rotulo: string, href: string}}>
     */
    private function alertas(array $ind, array $fila, array $carga): array
    {
        $alertas = [];
        foreach ($carga as $m) {
            if ($m['capacidade'] > 0 && $m['horas'] > $m['capacidade']) {
                $alertas[] = ['tipo' => 'maquina', 'tom' => 'danger', 'titulo' => $m['nome'].' passa do turno hoje',
                    'texto' => 'Carga de '.$this->h($m['horas']).' h para '.$this->h($m['capacidade']).' h no dia.',
                    'acao' => ['rotulo' => 'Ver no quadro por máquina', 'href' => '/producao/quadro?agrupar=maquina']];
            }
        }
        $n = $ind['atrasadas_ou_vencendo'];
        if ($n > 0) {
            $nums = $ind['atrasadas_numeros'];
            $alertas[] = ['tipo' => 'atraso', 'tom' => $ind['atrasadas_so_atraso'] > 0 ? 'danger' : 'warn',
                'titulo' => $n === 1 ? '1 OS atrasada ou vencendo hoje' : $n.' OS atrasadas ou vencendo hoje',
                'texto' => implode(', ', array_slice($nums, 0, 5)).(count($nums) > 5 ? ' e mais '.(count($nums) - 5) : '').'.',
                'acao' => ['rotulo' => 'Ver no quadro', 'href' => '/producao/quadro?so=atrasadas']];
        }
        if ($fila !== []) {
            $ajuste = count(array_filter($fila, fn ($f) => $f['estado'] === ArteDasOrdens::AJUSTE));
            $partes = array_filter([count($fila) - $ajuste ? (count($fila) - $ajuste).' aguardando o cliente' : null, $ajuste ? $ajuste.' com ajuste pedido' : null]);
            $alertas[] = ['tipo' => 'arte', 'tom' => 'warn',
                'titulo' => count($fila) === 1 ? '1 OS esperando arte' : count($fila).' OS esperando arte',
                'texto' => ucfirst(implode(' e ', $partes)).'.',
                'acao' => ['rotulo' => 'Ver no quadro', 'href' => '/producao/quadro?so=arte']];
        }

        return $alertas;
    }

    /**
     * Acontecendo agora (UC-PCV-29): os eventos mais recentes da produção, da mesma tabela que o quadro e o celular gravam.
     * Com `$dia`, só os eventos de máquina daquele dia — os "Eventos das máquinas" da Linha do tempo (thread 07).
     *
     * @return list<array{quando: string, texto: string, etapa: ?string, quem: ?string, os: ?string, posto_id: ?int}>
     */
    public function eventos(int $businessId, int $limite = 8, ?CarbonInterface $dia = null): array
    {
        $evs = DB::table('pcp_eventos')->where('business_id', $businessId)->whereNotIn('tipo', self::FORA_DO_FEED)
            ->when($dia !== null, fn ($q) => $q->whereNotNull('posto_id')
                ->whereBetween('created_at', [$dia->toDateString().' 00:00:00', $dia->toDateString().' 23:59:59']))
            ->orderByDesc('id')->limit($limite)->get(['tipo', 'os_tipo', 'os_id', 'posto_id', 'user_id', 'dados', 'created_at']);
        if ($evs->isEmpty()) {
            return [];
        }
        $dados = $evs->map(fn ($e) => json_decode((string) $e->dados, true) ?: []);
        $progIds = $dados->pluck('programacao_id')->filter()->unique()->values()->all();
        $etapas = $progIds === [] ? collect() : DB::table('pcp_programacoes')->where('business_id', $businessId)->whereIn('id', $progIds)->pluck('nome', 'id');
        $userIds = $evs->pluck('user_id')->filter()->unique()->values()->all();
        $nomes = $userIds === [] ? collect() : DB::table('users')->where('business_id', $businessId)->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name.' '.$u->last_name)]);
        $refs = $evs->filter(fn ($e) => $e->os_tipo !== null && $e->os_id !== null)->map(fn ($e) => [(string) $e->os_tipo, (int) $e->os_id])->values()->all();
        $rotulos = $this->quadro->rotulos($businessId, $refs);

        return $evs->values()->map(function ($e, $i) use ($dados, $etapas, $nomes, $rotulos) {
            $prog = $dados[$i]['programacao_id'] ?? null;
            // UC-PCV-07: "Moveu para Acabamento (à mão)", como no protótipo.
            $texto = $e->tipo === ColunaDoQuadro::EV_MOVIDA && isset($dados[$i]['para'])
                ? 'Moveu para '.$dados[$i]['para'].' (à mão)'
                : ($e->tipo === AlocacaoDaEtapa::EV_ALOCADA && ($dados[$i]['origem'] ?? null) === 'linha' ? 'Reprogramou na linha do tempo' : (self::ROTULOS[$e->tipo] ?? $e->tipo));

            return ['quando' => substr((string) $e->created_at, 0, 16), 'texto' => $texto,
                'etapa' => $prog !== null ? ($etapas[$prog] ?? null) : null,
                'quem' => $e->user_id !== null ? ($nomes[(int) $e->user_id] ?? null) : null,
                'os' => $e->os_tipo !== null ? ($rotulos[$e->os_tipo.':'.$e->os_id]['numero'] ?? null) : null,
                'posto_id' => $e->posto_id !== null ? (int) $e->posto_id : null];
        })->all();
    }

    private function h(float $horas): string
    {
        return str_replace('.', ',', (string) round($horas, 1));
    }
}
