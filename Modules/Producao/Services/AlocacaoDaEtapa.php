<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Alocador decide quem e quando começa (UC-FLX-10 · US-PCP-029). O gestor escolhe o responsável e o
 * início previsto de uma etapa que ainda não começou. O Liberar (SituacaoDaOrdem) já exige o
 * responsável; isto é o que vem depois: trocar a pessoa ou o horário sem mexer na situação da OS.
 *
 * Grava só `pcp_programacoes.user_id`, `inicio_previsto` e `fim_previsto` (a duração da etapa se
 * mantém), nunca a coluna do FSM. Quem mudou e quando fica em `pcp_eventos` (append-only), tipo
 * `etapa_alocada`, com o antes e o depois. Tier 0: tudo por `$businessId` (ADR 0093).
 *
 * Responsável válido = usuário da MESMA empresa com `producao.access`. Ainda não há cadastro de
 * quem trabalha em qual posto; quando houver, a regra entra aqui.
 */
final class AlocacaoDaEtapa
{
    public const EV_ALOCADA = 'etapa_alocada';

    public function alocar(int $businessId, int $programacaoId, User $gestor, ?int $userId, ?string $inicio): Programacao
    {
        if ($userId === null && $inicio === null) {
            throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO, 'Escolha o responsável ou o início previsto.');
        }
        if ($userId !== null) {
            $responsavel = User::query()->where('business_id', $businessId)->find($userId);
            if (! $responsavel || ! $responsavel->can('producao.access')) {
                throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO, 'Responsável não encontrado nesta empresa ou sem acesso à Produção.');
            }
        }

        return DB::transaction(function () use ($businessId, $programacaoId, $gestor, $userId, $inicio) {
            $etapa = Programacao::query()->where('business_id', $businessId)->lockForUpdate()->find($programacaoId)
                ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Etapa não encontrada.');
            if ($etapa->status !== 'pendente') {
                throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ENCERRADA, 'Esta etapa já começou ou terminou: não se realoca.',
                    ['programacao_id' => $etapa->id, 'status' => $etapa->status]);
            }

            $uid = fn () => $etapa->user_id !== null ? (int) $etapa->user_id : null;
            $antes = ['user_id' => $uid(), 'inicio_previsto' => $etapa->inicio_previsto->toDateTimeString()];
            $mudar = [];
            if ($userId !== null) {
                $mudar['user_id'] = $userId;
            }
            if ($inicio !== null) {
                $novo = Carbon::parse($inicio);
                $duracao = (int) $etapa->inicio_previsto->diffInSeconds($etapa->fim_previsto, true);
                $mudar['inicio_previsto'] = $novo;
                $mudar['fim_previsto'] = $novo->copy()->addSeconds($duracao);
            }
            $etapa->forceFill($mudar)->save();

            Evento::create(['business_id' => $businessId, 'tipo' => self::EV_ALOCADA, 'os_tipo' => $etapa->os_tipo, 'os_id' => $etapa->os_id,
                'posto_id' => $etapa->posto_id, 'user_id' => $gestor->id, 'dados' => ['programacao_id' => $etapa->id, 'antes' => $antes,
                    'depois' => ['user_id' => $uid(), 'inicio_previsto' => $etapa->inicio_previsto->toDateTimeString()]]]);

            return $etapa->fresh();
        });
    }

    /**
     * Fila do posto para o alocador: etapas abertas (pendentes e em andamento) pela ordem do início
     * previsto. Posto de outra empresa → não encontrado.
     *
     * @return Collection<int, array{id: int, os_tipo: string, os_id: int, nome: string|null, status: string, user_id: int|null, responsavel: string|null, inicio_previsto: string, fim_previsto: string}>
     */
    public function fila(int $businessId, int $postoId): Collection
    {
        Posto::query()->where('business_id', $businessId)->find($postoId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Posto não encontrado.');

        $nomes = fn (Collection $ids) => DB::table('users')->where('business_id', $businessId)->whereIn('id', $ids->filter()->unique()->all())
            ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name.' '.$u->last_name)]);

        $etapas = Programacao::query()->where('business_id', $businessId)->where('posto_id', $postoId)
            ->whereIn('status', ['pendente', 'iniciada'])->orderBy('inicio_previsto')->orderBy('id')->get();
        $quem = $nomes($etapas->pluck('user_id'));

        return $etapas->map(fn (Programacao $p) => ['id' => (int) $p->id, 'os_tipo' => $p->os_tipo, 'os_id' => (int) $p->os_id,
            'nome' => $p->nome, 'status' => $p->status, 'user_id' => $p->user_id !== null ? (int) $p->user_id : null,
            'responsavel' => $p->user_id !== null ? ($quem[(int) $p->user_id] ?? null) : null,
            'inicio_previsto' => $p->inicio_previsto->toDateTimeString(), 'fim_previsto' => $p->fim_previsto->toDateTimeString()])->values();
    }
}
