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
 * Trava (UC-PCV-11): arte aprovada não se troca em silêncio. `trocarArquivo` é o único caminho para
 * mudar o arquivo depois da aprovação — reabre a Aprovação do cliente como etapa nova "(nova versão)"
 * no mesmo bloco (as etapas seguintes voltam a esperar) e gera link novo. Enquanto o cliente não
 * aprova a nova versão, `arteVigente` continua devolvendo a última aprovada.
 *
 * Tier 0: o token carrega a empresa; tudo depois dele filtra pelo `business_id` do pedido.
 */
final class AprovacaoArteService
{
    public const DIAS_VALIDADE = 7;

    public const EV_SOLICITADA = 'arte_aprovacao_solicitada';

    public const EV_APROVADA = 'arte_aprovada';

    public const EV_RECUSADA = 'arte_recusada';

    public const EV_ARQUIVO_TROCADO = 'arte_arquivo_trocado';

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

    /**
     * UC-PCV-11 · troca o arquivo de uma arte já aprovada: a OS volta a esperar a aprovação do cliente.
     *
     * @return array{id: int, token: string, expira_em: Carbon}
     */
    public function trocarArquivo(int $businessId, int $ordemId, string $arquivo, User $user): array
    {
        return DB::transaction(function () use ($businessId, $ordemId, $arquivo, $user) {
            $ordem = $this->situacao->ordem($businessId, $ordemId);
            if (in_array($ordem->currentStage?->key, ['concluida', 'cancelada'], true)) {
                throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ENCERRADA, 'Esta OS já foi encerrada.');
            }
            $ultimo = AprovacaoArte::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
                ->where('ordem_id', $ordem->id)->orderByDesc('id')->lockForUpdate()->first();
            if ($ultimo === null || $ultimo->decisao !== AprovacaoArte::APROVADA) {
                throw new ApontamentoRecusado(ApontamentoRecusado::APROVACAO_ENCERRADA, $ultimo === null || $ultimo->decisao === null
                    ? 'Ainda não há arte aprovada: peça a aprovação do cliente.'
                    : 'O cliente pediu ajuste: mande a nova prova pela aprovação pendente.');
            }
            if ($ultimo->arquivo === $arquivo) {
                throw new ApontamentoRecusado(ApontamentoRecusado::APROVACAO_ENCERRADA, 'Este é o arquivo já aprovado.');
            }
            $aprovacao = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)
                ->where('os_id', $ordem->id)->findOrFail($ultimo->programacao_id);
            $nova = $aprovacao->replicate(['status', 'created_at', 'updated_at']);
            $nova->fill(['status' => 'pendente', 'nome' => $aprovacao->nome.' (nova versão)', 'inicio_previsto' => now(),
                'fim_previsto' => now()->addSeconds($aprovacao->inicio_previsto->diffInSeconds($aprovacao->fim_previsto)), 'created_by' => $user->id])->save();

            $token = Str::random(48);
            $pedido = AprovacaoArte::withoutGlobalScope(ScopeByBusiness::class)->create([
                'business_id' => $businessId, 'ordem_id' => $ordem->id, 'programacao_id' => $nova->id,
                'arte_programacao_id' => $ultimo->arte_programacao_id, 'token_hash' => self::hash($token), 'arquivo' => $arquivo,
                'expira_em' => now()->addDays(self::DIAS_VALIDADE), 'created_by' => $user->id,
            ]);
            $this->evento($pedido, self::EV_ARQUIVO_TROCADO, $user->id, ['aprovacao_anterior_id' => $ultimo->id]);

            return ['id' => (int) $pedido->id, 'token' => $token, 'expira_em' => $pedido->expira_em];
        });
    }

    /**
     * UC-PCART-07 · Painel: reenviar o link ou enviar a nova prova enquanto a arte não foi aprovada.
     *
     * Vale o pedido mais recente da OS. Aguardando o cliente: sai link novo (o arquivo é o mesmo, salvo se
     * vier outro) e o anterior deixa de valer — o banco só guarda o hash, não dá para reenviar o mesmo.
     * Ajuste pedido: exige o arquivo da nova prova. Arte aprovada: o caminho é `trocarArquivo`.
     *
     * @return array{id: int, token: string, expira_em: Carbon}
     */
    public function reenviar(int $businessId, int $ordemId, ?string $arquivo, User $user): array
    {
        return DB::transaction(function () use ($businessId, $ordemId, $arquivo, $user) {
            $ordem = $this->situacao->ordem($businessId, $ordemId);
            $ultimo = AprovacaoArte::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
                ->where('ordem_id', $ordem->id)->orderByDesc('id')->lockForUpdate()->first()
                ?? throw new ApontamentoRecusado(ApontamentoRecusado::APROVACAO_ENCERRADA, 'Ainda não há prova enviada: peça a aprovação do cliente.');
            if ($ultimo->decisao === AprovacaoArte::APROVADA) {
                throw new ApontamentoRecusado(ApontamentoRecusado::APROVACAO_ENCERRADA, 'A arte já foi aprovada: para mudar, troque o arquivo.');
            }
            if ($ultimo->decisao === AprovacaoArte::RECUSADA && ($arquivo === null || $arquivo === $ultimo->arquivo)) {
                throw new ApontamentoRecusado(ApontamentoRecusado::APROVACAO_ENCERRADA, 'O cliente pediu ajuste: informe o arquivo da nova prova.');
            }
            if ($ultimo->decisao === null) {
                $ultimo->forceFill(['expira_em' => now()->subSecond()])->save();
            }

            return $this->solicitar($businessId, $ordem->id, (int) $ultimo->programacao_id, $arquivo ?? (string) $ultimo->arquivo, $user);
        });
    }

    /** A OS existe nesta empresa? (404 para OS de outra empresa — Tier 0). */
    public function exigirOrdem(int $businessId, int $ordemId): void
    {
        $this->situacao->ordem($businessId, $ordemId);
    }

    /**
     * UC-PCV-11 · a arte que vale para produzir: o arquivo do último pedido APROVADO, mesmo que haja
     * uma versão nova esperando o cliente. Null = OS ainda sem arte aprovada.
     *
     * @return array{arquivo: string, aprovada_em: string}|null
     */
    public function arteVigente(int $businessId, int $ordemId): ?array
    {
        $p = AprovacaoArte::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->where('ordem_id', $ordemId)
            ->where('decisao', AprovacaoArte::APROVADA)->orderByDesc('decidido_em')->orderByDesc('id')->first();

        return $p === null ? null : ['arquivo' => (string) $p->arquivo, 'aprovada_em' => $p->decidido_em->toIso8601String()];
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
        // SUPERADMIN: rota pública — a empresa vem do pedido, não da sessão de quem abre o link
        // (logado noutra empresa, o escopo da sessão escondia a etapa e o link dava 410).
        $q = Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $pedido->business_id)->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $pedido->ordem_id);
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
