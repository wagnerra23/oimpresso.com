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
 * Responsável válido = usuário da MESMA empresa com `producao.access` e, se o posto da etapa tem
 * operadores cadastrados (US-PCP-030), um deles — a mesma regra do Liberar e do início da etapa
 * (`OperadoresDoPosto::podeAtuar`). Posto sem cadastro aceita qualquer pessoa, como antes.
 */
final class AlocacaoDaEtapa
{
    public const EV_ALOCADA = 'etapa_alocada';

    /** Aviso no app ao responsável de uma etapa reprogramada na Linha (D-AVISO). `user_id` = quem recebe. */
    public const EV_AVISO_REPROGRAMADA = 'aviso_reprogramada';

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
            $posto = $etapa->posto_id !== null ? (int) $etapa->posto_id : null;
            if ($userId !== null && ! app(OperadoresDoPosto::class)->podeAtuar($businessId, $posto, $userId)) {
                throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO, 'Este posto tem operadores cadastrados e o responsável não é um deles.',
                    ['programacao_id' => $etapa->id, 'posto_id' => $posto, 'user_id' => $userId]);
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
     * Reprogramar na Linha do tempo (UC-PCV-30 · thread 07): mover a etapa pendente de horário e,
     * se quiser, para outra máquina do MESMO setor. Mais estrito que o `alocar`, porque a Linha é a
     * agenda da máquina: recusa outro setor, recusa horário que choca com outra etapa da máquina de
     * destino (diz com qual OS) e, trocando de máquina, recusa responsável que não opera a de destino
     * (UC-FLX-08) — o `contexto` traz os operadores para a tela perguntar quem faz.
     *
     * Passar do fim do turno ou da entrega prometida NÃO recusa: é aviso, que a Linha calcula.
     * Grava o mesmo evento `etapa_alocada` (origem `linha`, com a máquina antes e depois) e avisa o
     * responsável no app (`aviso_reprogramada`, D-AVISO). Desfazer é reprogramar de volta.
     */
    public function reprogramar(int $businessId, int $programacaoId, User $gestor, int $postoId, string $inicio, ?int $userId = null): Programacao
    {
        if ($userId !== null) {
            $responsavel = User::query()->where('business_id', $businessId)->find($userId);
            if (! $responsavel || ! $responsavel->can('producao.access')) {
                throw new ApontamentoRecusado(ApontamentoRecusado::RESPONSAVEL_INVALIDO, 'Responsável não encontrado nesta empresa ou sem acesso à Produção.');
            }
        }

        return DB::transaction(function () use ($businessId, $programacaoId, $gestor, $postoId, $inicio, $userId) {
            $etapa = Programacao::query()->where('business_id', $businessId)->lockForUpdate()->find($programacaoId)
                ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Etapa não encontrada.');
            $destino = Posto::query()->where('business_id', $businessId)->where('ativo', true)->find($postoId)
                ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Máquina não encontrada.');
            if ($etapa->status !== 'pendente') {
                throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ENCERRADA, 'Esta etapa já começou ou terminou: só a etapa que ainda não começou se reprograma.',
                    ['programacao_id' => $etapa->id, 'status' => $etapa->status]);
            }
            $origem = $etapa->posto_id !== null ? Posto::query()->where('business_id', $businessId)->find($etapa->posto_id) : null;
            if ($origem && trim((string) $origem->setor) !== trim((string) $destino->setor)) {
                throw new ApontamentoRecusado(ApontamentoRecusado::OUTRO_SETOR, 'Não dá para levar a etapa para '.$destino->nome.': é de outro setor.',
                    ['setor_origem' => $origem->setor, 'setor_destino' => $destino->setor]);
            }

            $novoInicio = Carbon::parse($inicio);
            $novoFim = $novoInicio->copy()->addSeconds((int) $etapa->inicio_previsto->diffInSeconds($etapa->fim_previsto, true));
            $choca = Programacao::query()->where('business_id', $businessId)->where('posto_id', $destino->id)->where('id', '!=', $etapa->id)
                ->where('status', '!=', 'cancelada')->where('inicio_previsto', '<', $novoFim)->where('fim_previsto', '>', $novoInicio)
                ->orderBy('inicio_previsto')->first();
            if ($choca) {
                throw new ApontamentoRecusado(ApontamentoRecusado::CHOQUE_DE_HORARIO, 'Choca com outra etapa nesta máquina. Escolha outro horário.',
                    ['programacao_id' => (int) $choca->id, 'os_tipo' => $choca->os_tipo, 'os_id' => (int) $choca->os_id,
                        'inicio' => $choca->inicio_previsto->format('H:i'), 'fim' => $choca->fim_previsto->format('H:i')]);
            }
            $resp = $userId ?? ($etapa->user_id !== null ? (int) $etapa->user_id : null);
            if ($resp !== null && ! app(OperadoresDoPosto::class)->podeAtuar($businessId, (int) $destino->id, $resp)) {
                throw new ApontamentoRecusado(ApontamentoRecusado::OPERADOR_DESTINO, 'Quem responde pela etapa não opera '.$destino->nome.': escolha um operador dela.',
                    ['posto_id' => (int) $destino->id, 'operadores' => app(OperadoresDoPosto::class)->doPosto($businessId, (int) $destino->id)]);
            }

            $foto = fn () => ['posto_id' => $etapa->posto_id !== null ? (int) $etapa->posto_id : null,
                'user_id' => $etapa->user_id !== null ? (int) $etapa->user_id : null, 'inicio_previsto' => $etapa->inicio_previsto->toDateTimeString()];
            $antes = $foto();
            $etapa->forceFill(['posto_id' => $destino->id, 'user_id' => $resp, 'inicio_previsto' => $novoInicio, 'fim_previsto' => $novoFim])->save();

            Evento::create(['business_id' => $businessId, 'tipo' => self::EV_ALOCADA, 'os_tipo' => $etapa->os_tipo, 'os_id' => $etapa->os_id,
                'posto_id' => $etapa->posto_id, 'user_id' => $gestor->id, 'dados' => ['programacao_id' => $etapa->id, 'origem' => 'linha', 'antes' => $antes, 'depois' => $foto()]]);
            if ($resp !== null) {
                Evento::create(['business_id' => $businessId, 'tipo' => self::EV_AVISO_REPROGRAMADA, 'os_tipo' => $etapa->os_tipo, 'os_id' => $etapa->os_id,
                    'posto_id' => $etapa->posto_id, 'user_id' => $resp, 'dados' => ['programacao_id' => $etapa->id]]);
            }

            return $etapa->fresh();
        });
    }

    /**
     * Fila do posto para o alocador: etapas abertas (pendentes e em andamento) pela ordem do início
     * previsto. Posto de outra empresa → não encontrado.
     *
     * @return list<array<string, mixed>>
     */
    public function fila(int $businessId, int $postoId): array
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
            'inicio_previsto' => $p->inicio_previsto->toDateTimeString(), 'fim_previsto' => $p->fim_previsto->toDateTimeString()])->values()->all();
    }
}
