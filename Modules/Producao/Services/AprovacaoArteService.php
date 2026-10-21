<?php

namespace Modules\Producao\Services;

use App\Scopes\ScopeByBusiness;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Producao\Entities\AprovacaoArte;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Cliente aprova a arte por link (UC-FLX-02 · US-COMVIS-NEW-004), na OS por item (ADR 0436).
 *
 * Solicitar: o designer escolhe a etapa "Aprovação do cliente" (pendente) e o arquivo da prova; a
 * etapa de arte é a última já feita num bloco anterior. Sai um token aleatório (só o sha256 fica no
 * banco), válido por DIAS_VALIDADE dias.
 *
 * Decidir (cliente, sem login): uso único — decidido, vencido ou com a etapa já fechada → recusa.
 * Aprovar conclui a etapa de aprovação e passa a OS adiante pelo mesmo caminho do apontamento
 * (SituacaoDaOrdem::aoConcluirEtapa → motor FSM, nunca o `current_stage_id` direto). Recusar não
 * muda a situação: a etapa de arte volta como etapa nova "(alteração)", com o comentário no evento;
 * a aprovação fica pendente e a próxima etapa espera (regra do bloco anterior).
 *
 * Tier 0: o token carrega a empresa; tudo depois dele filtra pelo `business_id` do pedido.
 */
final class AprovacaoArteService
{
    public const DIAS_VALIDADE = 7;

    public const EV_SOLICITADA = 'arte_aprovacao_solicitada';

    public const EV_APROVADA = 'arte_aprovada';

    public const EV_RECUSADA = 'arte_recusada';

    public function __construct(private readonly SituacaoDaOrdem $situacao) {}

    /** @return array{id: int, token: string, expira_em: Carbon} */
    public function solicitar(int $businessId, int $ordemId, int $programacaoId, string $arquivo, User $user): array
    {
        $ordem = $this->situacao->ordem($businessId, $ordemId);
        if (in_array($ordem->currentStage?->key, ['concluida', 'cancelada'], true)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ENCERRADA, 'Esta OS já foi encerrada.');
        }
        $etapas = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $ordem->id);
        $aprovacao = (clone $etapas)->where('status', 'pendente')->find($programacaoId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'A etapa de aprovação tem de ser uma etapa pendente desta OS.');
        $arte = (clone $etapas)->where('status', 'concluida')->where('bloco', '<', (int) $aprovacao->bloco)
            ->orderByDesc('bloco')->orderByDesc('id')->first()
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::OS_NAO_LIBERADA, 'Conclua a etapa de arte antes de pedir a aprovação do cliente.');

        $token = Str::random(48);
        $pedido = AprovacaoArte::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => $businessId, 'ordem_id' => $ordem->id, 'programacao_id' => $aprovacao->id,
            'arte_programacao_id' => $arte->id, 'token_hash' => self::hash($token), 'arquivo' => $arquivo,
            'expira_em' => now()->addDays(self::DIAS_VALIDADE), 'created_by' => $user->id,
        ]);
        $this->evento($pedido, self::EV_SOLICITADA, $user->id, []);

        return ['id' => (int) $pedido->id, 'token' => $token, 'expira_em' => $pedido->expira_em];
    }

    /** Pedido ainda aberto para o token — vencido, decidido ou com a etapa fechada → recusa. */
    public function aberto(string $token): AprovacaoArte
    {
        $pedido = $this->porToken($token);
        $this->exigirAberto($pedido);

        return $pedido;
    }

    public function aprovar(string $token): AprovacaoArte
    {
        return DB::transaction(function () use ($token) {
            $pedido = $this->porToken($token, true);
            $etapa = $this->exigirAberto($pedido, true);
            $etapa->forceFill(['status' => 'concluida'])->save();
            $pedido->forceFill(['decisao' => AprovacaoArte::APROVADA, 'decidido_em' => now()])->save();
            $this->evento($pedido, self::EV_APROVADA, null, []);
            $this->situacao->aoConcluirEtapa((int) $pedido->business_id, $etapa, (int) $pedido->created_by);

            return $pedido;
        });
    }

    public function recusar(string $token, string $comentario): AprovacaoArte
    {
        $comentario = trim($comentario);
        if ($comentario === '') {
            throw new ApontamentoRecusado(ApontamentoRecusado::JUSTIFICATIVA_OBRIGATORIA, 'Diga o que precisa mudar na arte.');
        }

        return DB::transaction(function () use ($token, $comentario) {
            $pedido = $this->porToken($token, true);
            $this->exigirAberto($pedido, true);
            $arte = Programacao::query()->where('business_id', $pedido->business_id)->findOrFail($pedido->arte_programacao_id);
            $nova = $arte->replicate(['status', 'created_at', 'updated_at']);
            $nova->fill(['status' => 'pendente', 'nome' => $arte->nome.' (alteração)', 'inicio_previsto' => now(),
                'fim_previsto' => now()->addSeconds($arte->inicio_previsto->diffInSeconds($arte->fim_previsto))])->save();
            $pedido->forceFill(['decisao' => AprovacaoArte::RECUSADA, 'comentario' => $comentario, 'decidido_em' => now()])->save();
            $this->evento($pedido, self::EV_RECUSADA, null, ['comentario' => $comentario, 'nova_programacao_id' => $nova->id]);

            return $pedido;
        });
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function porToken(string $token, bool $travar = false): AprovacaoArte
    {
        // SUPERADMIN: rota pública sem sessão — a empresa vem do próprio pedido achado pelo token.
        $q = AprovacaoArte::withoutGlobalScope(ScopeByBusiness::class)->where('token_hash', self::hash($token));

        return ($travar ? $q->lockForUpdate() : $q)->first()
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Link não encontrado.');
    }

    private function exigirAberto(AprovacaoArte $pedido, bool $travar = false): Programacao
    {
        if ($pedido->decisao !== null) {
            throw new ApontamentoRecusado(ApontamentoRecusado::APROVACAO_ENCERRADA, 'Esta prova já foi respondida.');
        }
        if ($pedido->expira_em->isPast()) {
            throw new ApontamentoRecusado(ApontamentoRecusado::APROVACAO_ENCERRADA, 'Este link venceu. Peça um novo à gráfica.');
        }
        $q = Programacao::query()->where('business_id', $pedido->business_id)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $pedido->ordem_id);
        $etapa = ($travar ? $q->lockForUpdate() : $q)->find($pedido->programacao_id);
        if ($etapa === null || $etapa->status !== 'pendente') {
            throw new ApontamentoRecusado(ApontamentoRecusado::APROVACAO_ENCERRADA, 'Esta prova já foi respondida.');
        }

        return $etapa;
    }

    /** @param array<string, mixed> $dados */
    private function evento(AprovacaoArte $pedido, string $tipo, ?int $userId, array $dados): void
    {
        Evento::create(['business_id' => $pedido->business_id, 'tipo' => $tipo, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $pedido->ordem_id,
            'user_id' => $userId, 'dados' => ['aprovacao_id' => $pedido->id, 'programacao_id' => $pedido->programacao_id] + $dados]);
    }
}
