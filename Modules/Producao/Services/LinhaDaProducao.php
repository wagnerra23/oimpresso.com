<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;

/**
 * Linha do tempo por máquina (thread 07 · UC-PCV-08, 09, 30 · US-PCP-015): as etapas de um dia em
 * cada máquina, com a carga contra a capacidade do posto e o gargalo com uma sugestão aplicável.
 *
 * - Dias: hoje e o próximo dia útil do calendário de produção (UC-FLX-06: pula folga e feriado).
 * - Turno: o do calendário (`pcp_turnos` → expediente do RH → 08h–18h). Bloco que termina depois
 *   do fim do turno sai com `estouro`.
 * - Carga: a mesma conta do Painel (`PainelDaProducao::carga`), para o número ser um só.
 * - Travas: arte não aprovada (`ArteDasOrdens`) e OS em "Aguardando material" (D3). Travado move,
 *   mas segue travado — a trava é da OS, não do horário.
 * - Entrega prometida (US-PCP-028): bloco que termina depois do fim do turno no dia prometido, ou
 *   em dia posterior, sai com `passa_da_promessa`.
 * - Gargalo (D5, regra simples): máquina acima da capacidade recebe UMA sugestão — mover a etapa
 *   pendente de menor duração que resolve para outra máquina do mesmo setor que tenha folga, com
 *   responsável que opere a de destino (UC-FLX-08). Nunca move sozinho; quem aplica é o PCP.
 *
 * Tier 0 (ADR 0093): toda consulta por `$businessId` explícito.
 */
final class LinhaDaProducao
{
    public function __construct(
        private readonly CalendarioDeProducao $calendario,
        private readonly PainelDaProducao $painel,
        private readonly QuadroDaProducao $quadro,
        private readonly OperadoresDoPosto $operadores,
        private readonly ArteDasOrdens $arte,
    ) {}

    /** @return array{hoje: string, proximo: string, turno: array{inicio: string, fim: string}} */
    public function dias(int $businessId, CarbonInterface $hoje): array
    {
        $regras = $this->calendario->regras($businessId, null, $hoje);
        $prox = Carbon::parse($hoje->toDateString());
        for ($i = 0; $i < 30; $i++) {
            $prox->addDay();
            if (! in_array($prox->dayOfWeek, $regras['folgas'], true) && ! isset($regras['feriados'][$prox->toDateString()])) {
                break;
            }
        }
        $hm = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        return ['hoje' => $hoje->toDateString(), 'proximo' => $prox->toDateString(),
            'turno' => ['inicio' => $hm($regras['abre']), 'fim' => $hm($regras['fecha'])]];
    }

    /**
     * Máquinas com operadores e carga do dia, os blocos e a sugestão de gargalo.
     *
     * @return array{postos: list<array<string, mixed>>, blocos: list<array<string, mixed>>, sugestao: ?array<string, mixed>}
     */
    public function montar(int $businessId, CarbonInterface $dia, string $fimTurno): array
    {
        $carga = collect($this->painel->carga($businessId, $dia))->keyBy('posto_id');
        $postos = $this->postos($businessId, $carga->all());
        $blocos = $this->blocos($businessId, $dia, $fimTurno, array_column($postos, 'id'));

        return ['postos' => $postos, 'blocos' => $blocos, 'sugestao' => $this->sugestao($businessId, $postos, $blocos, $dia, $fimTurno)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $carga
     * @return list<array<string, mixed>>
     */
    private function postos(int $businessId, array $carga): array
    {
        $saida = [];
        foreach ($carga as $c) {
            $ids = $this->operadores->doPosto($businessId, (int) $c['posto_id']);
            $saida[] = ['id' => (int) $c['posto_id'], 'nome' => $c['nome'], 'setor' => $c['setor'], 'horas' => $c['horas'],
                'capacidade' => $c['capacidade'], 'operadores' => $this->pessoas($businessId, $ids)];
        }

        return $saida;
    }

    /**
     * @param  list<int>  $postoIds
     * @return list<array<string, mixed>>
     */
    private function blocos(int $businessId, CarbonInterface $dia, string $fimTurno, array $postoIds): array
    {
        if ($postoIds === []) {
            return [];
        }
        $d = $dia->toDateString();
        $etapas = DB::table('pcp_programacoes')->where('business_id', $businessId)->where('status', '!=', 'cancelada')
            ->whereIn('posto_id', $postoIds)->whereBetween('inicio_previsto', [$d.' 00:00:00', $d.' 23:59:59'])
            ->orderBy('inicio_previsto')->orderBy('id')
            ->get(['id', 'os_tipo', 'os_id', 'posto_id', 'nome', 'status', 'user_id', 'inicio_previsto', 'fim_previsto']);
        if ($etapas->isEmpty()) {
            return [];
        }
        $rotulos = $this->quadro->rotulos($businessId, $etapas->map(fn ($e) => [(string) $e->os_tipo, (int) $e->os_id])->all());
        $ordens = $etapas->where('os_tipo', Ordem::OS_TIPO)->pluck('os_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $arte = $this->arte->travadas($businessId, $ordens);
        $material = $ordens === [] ? [] : DB::table('pcp_ordens as po')
            ->join('sale_process_stages as s', 's.id', '=', 'po.current_stage_id')
            ->where('po.business_id', $businessId)->whereIn('po.id', $ordens)->where('s.key', 'aguardando_material')
            ->pluck('po.id')->map(fn ($id) => (int) $id)->flip()->all();
        $nomes = $this->nomes($businessId, $etapas->pluck('user_id')->filter()->unique()->values()->all());
        $limite = $d.' '.$fimTurno.':00';

        return $etapas->map(function ($e) use ($rotulos, $arte, $material, $nomes, $limite, $d) {
            $chave = $e->os_tipo.':'.$e->os_id;
            $ordem = $e->os_tipo === Ordem::OS_TIPO ? (int) $e->os_id : null;
            $travas = array_values(array_filter([
                $ordem !== null && isset($arte[$ordem]) ? ($arte[$ordem]['estado'] === ArteDasOrdens::AJUSTE ? 'ajuste de arte pedido' : 'arte aguardando aprovação') : null,
                $ordem !== null && isset($material[$ordem]) ? 'aguardando material' : null,
            ]));
            $fim = (string) $e->fim_previsto;
            $prazo = $rotulos[$chave]['prazo'] ?? null;

            return ['id' => (int) $e->id, 'os_tipo' => $e->os_tipo, 'os_id' => (int) $e->os_id, 'posto_id' => (int) $e->posto_id,
                'numero' => $rotulos[$chave]['numero'] ?? ('OS '.$e->os_id), 'cliente' => $rotulos[$chave]['cliente'] ?? null,
                'etapa' => $e->nome, 'status' => $e->status,
                'user_id' => $e->user_id !== null ? (int) $e->user_id : null,
                'responsavel' => $e->user_id !== null ? ($nomes[(int) $e->user_id] ?? null) : null,
                'inicio' => Carbon::parse($e->inicio_previsto)->format('Y-m-d\TH:i'), 'fim' => Carbon::parse($fim)->format('Y-m-d\TH:i'),
                'travas' => $travas, 'estouro' => $fim > $limite, 'prazo' => $prazo,
                'passa_da_promessa' => $prazo !== null && ($d > $prazo || ($d === $prazo && $fim > $limite))];
        })->values()->all();
    }

    /**
     * UC-PCV-09: uma sugestão por dia, para a máquina mais estourada. A etapa pendente de menor
     * duração que tira a máquina do vermelho vai para outra do mesmo setor com folga, no mesmo
     * horário ou no primeiro horário livre depois dele, sem passar do fim do turno.
     *
     * @param  list<array<string, mixed>>  $postos
     * @param  list<array<string, mixed>>  $blocos
     * @return ?array<string, mixed>
     */
    private function sugestao(int $businessId, array $postos, array $blocos, CarbonInterface $dia, string $fimTurno): ?array
    {
        $porId = collect($postos)->keyBy('id');
        $estourados = collect($postos)->filter(fn ($p) => $p['capacidade'] > 0 && $p['horas'] > $p['capacidade'])
            ->sortByDesc(fn ($p) => $p['horas'] - $p['capacidade']);
        $fecha = Carbon::parse($dia->toDateString().' '.$fimTurno);
        $dur = fn ($b) => Carbon::parse($b['inicio'])->diffInMinutes(Carbon::parse($b['fim']), true) / 60;

        foreach ($estourados as $origem) {
            $candidatas = collect($blocos)->where('posto_id', $origem['id'])->where('status', 'pendente')->sortBy($dur);
            foreach ($candidatas as $b) {
                $h = $dur($b);
                if (round($origem['horas'] - $h, 1) > $origem['capacidade']) {
                    continue; // não resolve sozinha
                }
                foreach ($porId as $destino) {
                    if ($destino['id'] === $origem['id'] || trim((string) $destino['setor']) !== trim((string) $origem['setor'])
                        || $destino['capacidade'] <= 0 || $destino['horas'] + $h > $destino['capacidade']
                        || ($b['user_id'] !== null && ! $this->operadores->podeAtuar($businessId, $destino['id'], $b['user_id']))) {
                        continue;
                    }
                    $inicio = $this->primeiroLivre(collect($blocos)->where('posto_id', $destino['id'])->all(), Carbon::parse($b['inicio']), $h);
                    if ($inicio->copy()->addMinutes((int) round($h * 60))->gt($fecha)) {
                        continue;
                    }

                    return ['programacao_id' => $b['id'], 'numero' => $b['numero'], 'etapa' => $b['etapa'], 'horas' => round($h, 1),
                        'de' => ['posto_id' => $origem['id'], 'nome' => $origem['nome'], 'horas' => $origem['horas'],
                            'depois' => round($origem['horas'] - $h, 1), 'capacidade' => $origem['capacidade'], 'inicio' => $b['inicio']],
                        'para' => ['posto_id' => $destino['id'], 'nome' => $destino['nome'], 'horas' => $destino['horas'],
                            'depois' => round($destino['horas'] + $h, 1), 'capacidade' => $destino['capacidade'], 'inicio' => $inicio->format('Y-m-d\TH:i')]];
                }
            }
        }

        return null;
    }

    /** @param  list<array<string, mixed>>  $ocupados */
    private function primeiroLivre(array $ocupados, Carbon $desde, float $horas): Carbon
    {
        $ini = $desde->copy();
        $ordenados = collect($ocupados)->sortBy('inicio')->values();
        foreach ($ordenados as $o) {
            $oi = Carbon::parse($o['inicio']);
            $of = Carbon::parse($o['fim']);
            if ($ini->lt($of) && $ini->copy()->addMinutes((int) round($horas * 60))->gt($oi)) {
                $ini = $of->copy();
            }
        }

        return $ini;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, nome: string}>
     */
    private function pessoas(int $businessId, array $ids): array
    {
        $nomes = $this->nomes($businessId, $ids);

        return array_map(fn ($id) => ['id' => $id, 'nome' => $nomes[$id] ?? ('#'.$id)], $ids);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nomes(int $businessId, array $ids): array
    {
        return $ids === [] ? [] : DB::table('users')->where('business_id', $businessId)->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name.' '.$u->last_name)])->all();
    }
}
