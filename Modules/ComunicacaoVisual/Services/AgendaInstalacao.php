<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Entities\Instalacao;

/**
 * Agenda da equipe de instalação (US-COMVIS-007 · UC-FLX-21).
 *
 * Agenda a instalação de uma OS da CV (`comvis_os`) numa janela [início, fim), com equipe (users
 * da mesma empresa) e veículo (texto: placa/apelido — não há cadastro de frota). Recusa quando a
 * janela se sobrepõe a outra instalação agendada ou em execução da MESMA empresa que use o mesmo
 * veículo ou alguém da mesma equipe. Sobreposição é `início < fim_existente E fim > início_existente`:
 * horários encostados (um termina às 11:00, o outro começa às 11:00) passam.
 *
 * Fora do escopo: aviso ao cliente, checklist de EPI/ferramentas, reagendamento (nenhum código grava
 * `aguardando_reagendamento`) e instalações antigas sem `data_fim` (não ocupam a agenda).
 *
 * Tier 0 (ADR 0093): todo acesso filtra `business_id` explícito — o escopo de sessão do model não
 * filtra fora de request.
 */
final class AgendaInstalacao
{
    /** Estados que ocupam equipe e veículo. */
    public const OCUPAM = ['agendada', 'em_execucao'];

    /**
     * @param  list<int>  $equipe  ids de users da empresa
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException OS de outra empresa ou inexistente
     * @throws \DomainException horário inválido, equipe de fora ou conflito (mensagem em PT-BR)
     */
    public function agendar(
        int $businessId,
        int $osId,
        CarbonInterface $inicio,
        CarbonInterface $fim,
        array $equipe,
        ?string $veiculo = null,
        ?string $observacoes = null,
    ): Instalacao {
        $os = DB::table('comvis_os')->where('business_id', $businessId)->where('id', $osId)->whereNull('deleted_at')->first(['id', 'numero']);
        if ($os === null) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(\Modules\ComunicacaoVisual\Entities\Os::class, [$osId]);
        }
        if ($fim <= $inicio) {
            throw new \DomainException('O fim da instalação tem que ser depois do início.');
        }
        $equipe = array_values(array_unique(array_map('intval', $equipe)));
        if ($equipe === []) {
            throw new \DomainException('Informe ao menos uma pessoa da equipe.');
        }
        $daEmpresa = DB::table('users')->where('business_id', $businessId)->whereIn('id', $equipe)->count();
        if ($daEmpresa !== count($equipe)) {
            throw new \DomainException('A equipe só pode ter pessoas desta empresa.');
        }
        $veiculo = $veiculo !== null && trim($veiculo) !== '' ? trim($veiculo) : null;

        return DB::transaction(function () use ($businessId, $os, $inicio, $fim, $equipe, $veiculo, $observacoes) {
            $sobrepostas = DB::table('cv_instalacoes')
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->whereIn('status', self::OCUPAM)
                ->where('data_agendada', '<', $fim)
                ->where('data_fim', '>', $inicio)
                ->lockForUpdate()
                ->get(['id', 'os_id', 'equipe_user_ids_json', 'veiculo', 'data_agendada', 'data_fim']);

            foreach ($sobrepostas as $outra) {
                $motivo = $this->motivoDoConflito($outra, $equipe, $veiculo);
                if ($motivo !== null) {
                    $numero = $outra->os_id ? DB::table('comvis_os')->where('id', $outra->os_id)->value('numero') : null;
                    throw new \DomainException(sprintf(
                        'Sem janela: %s na instalação #%d%s, de %s a %s.',
                        $motivo,
                        $outra->id,
                        $numero ? " (OS {$numero})" : '',
                        Carbon::parse($outra->data_agendada)->format('d/m/Y H:i'),
                        Carbon::parse($outra->data_fim)->format('H:i'),
                    ));
                }
            }

            return Instalacao::create([ // business_id explícito: o creating hook só preenche quando null
                'business_id' => $businessId,
                'os_id' => (int) $os->id,
                'equipe_user_ids_json' => $equipe,
                'veiculo' => $veiculo,
                'data_agendada' => $inicio,
                'data_fim' => $fim,
                'status' => 'agendada',
                'observacoes' => $observacoes,
            ]);
        });
    }

    /** Instalações da empresa que tocam a janela, sem as canceladas, em ordem de início. */
    public function listar(int $businessId, CarbonInterface $de, CarbonInterface $ate): Collection
    {
        $linhas = DB::table('cv_instalacoes as i')
            ->leftJoin('comvis_os as o', fn ($j) => $j->on('o.id', '=', 'i.os_id')->where('o.business_id', $businessId))
            ->where('i.business_id', $businessId)
            ->whereNull('i.deleted_at')
            ->where('i.status', '!=', 'cancelada')
            ->whereNotNull('i.data_fim')
            ->where('i.data_agendada', '<', $ate)
            ->where('i.data_fim', '>', $de)
            ->orderBy('i.data_agendada')
            ->get(['i.id', 'i.os_id', 'o.numero as os_numero', 'i.data_agendada', 'i.data_fim', 'i.equipe_user_ids_json', 'i.veiculo', 'i.status']);

        $ids = $linhas->flatMap(fn ($l) => json_decode((string) $l->equipe_user_ids_json, true) ?: [])->unique()->all();
        $nomes = DB::table('users')->where('business_id', $businessId)->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name.' '.$u->last_name)]);

        return $linhas->map(function ($l) use ($nomes) {
            $equipe = array_map('intval', json_decode((string) $l->equipe_user_ids_json, true) ?: []);

            return [
                'id' => (int) $l->id,
                'os_id' => $l->os_id !== null ? (int) $l->os_id : null,
                'os_numero' => $l->os_numero,
                'inicio' => Carbon::parse($l->data_agendada)->toIso8601String(),
                'fim' => Carbon::parse($l->data_fim)->toIso8601String(),
                'equipe' => array_map(fn ($id) => ['id' => $id, 'nome' => $nomes[$id] ?? null], $equipe),
                'veiculo' => $l->veiculo,
                'status' => $l->status,
            ];
        })->values();
    }

    private function motivoDoConflito(object $outra, array $equipe, ?string $veiculo): ?string
    {
        if ($veiculo !== null && $outra->veiculo !== null && self::chaveVeiculo($veiculo) === self::chaveVeiculo($outra->veiculo)) {
            return "o veículo {$outra->veiculo} já está";
        }
        $comuns = array_values(array_intersect($equipe, array_map('intval', json_decode((string) $outra->equipe_user_ids_json, true) ?: [])));
        if ($comuns === []) {
            return null;
        }
        $nomes = DB::table('users')->whereIn('id', $comuns)->get(['first_name', 'last_name'])
            ->map(fn ($u) => trim($u->first_name.' '.$u->last_name))->implode(', ');

        return count($comuns) > 1 ? "{$nomes} já estão" : "{$nomes} já está";
    }

    /** "Fiorino abc-1d23" e "FIORINO ABC1D23" são o mesmo veículo. */
    private static function chaveVeiculo(string $veiculo): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($veiculo));
    }
}
