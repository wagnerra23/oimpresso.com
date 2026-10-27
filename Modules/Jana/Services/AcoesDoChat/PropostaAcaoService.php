<?php

declare(strict_types=1);

namespace Modules\Jana\Services\AcoesDoChat;

use App\Utils\Util;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\AcaoAprovacao;

/**
 * Proposta → confirmação → execução das ações do chat (ADR 0440).
 *
 * - `propor()`: guarda o que a Jana sugeriu, já validado, com o resumo que o cartão
 *   mostra. Nada é executado.
 * - `confirmar()`: só o autor da proposta, na mesma empresa, antes de expirar, e
 *   com a permissão conferida DE NOVO na hora (ela pode ter mudado). Executa pelo
 *   serviço da tela, como esse usuário.
 * - `cancelar()`: fecha a proposta sem executar.
 *
 * `business_id` sempre vem do servidor (constructor da tool ou sessão), nunca do
 * modelo. Cada passo vai para o `activity_log` com o usuário como autor.
 */
class PropostaAcaoService
{
    public const ACOES = [
        'criar-tarefa' => CriarTarefaAcao::class,
    ];

    public const VALIDADE_MINUTOS = 30;

    public function __construct(private Util $util) {}

    public function acao(string $chave): AcaoDoChat
    {
        if (! array_key_exists($chave, self::ACOES)) {
            throw new AcaoPropostaException("Ação desconhecida: {$chave}.", 422);
        }

        return app(self::ACOES[$chave]);
    }

    /** @param array<string, mixed> $campos */
    public function propor(int $businessId, Model $user, ?int $conversaId, string $chave, array $campos): AcaoAprovacao
    {
        $acao = $this->acao($chave);
        if (! $acao->permitida($user, $businessId)) {
            throw new AcaoPropostaException('Você não tem permissão para esta ação.', 403);
        }
        $campos = $acao->validar($campos);

        $proposta = AcaoAprovacao::create([
            'business_id' => $businessId,
            'user_id'     => (int) $user->id,
            'acao_key'    => $chave,
            'origem'      => 'chat',
            'conversa_id' => $conversaId,
            'status'      => 'proposta',
            'previa'      => $acao->resumo($campos),
            'contexto'    => $campos,
            'expira_em'   => Carbon::now()->addMinutes(self::VALIDADE_MINUTOS),
        ]);

        $this->auditar($proposta, 'jana_acao_proposta', $businessId);

        return $proposta;
    }

    public function confirmar(int $id, int $businessId, Model $user): AcaoAprovacao
    {
        $proposta = DB::transaction(function () use ($id, $businessId, $user) {
            $p = $this->pendenteDoUsuario($id, $businessId, $user);
            $acao = $this->acao($p->acao_key);

            if (! $acao->permitida($user, $businessId)) {
                throw new AcaoPropostaException('Você não tem permissão para esta ação.', 403);
            }

            try {
                // Savepoint: se a execução quebrar no meio, o que ela gravou volta e só
                // o status `falhou` fica.
                $resultado = DB::transaction(fn () => $acao->executar($businessId, $user, (array) $p->contexto));
            } catch (\Throwable $e) {
                report($e);
                $p->update(['status' => 'falhou', 'decidida_em' => Carbon::now(), 'resultado' => ['erro' => 'Não foi possível executar.']]);

                return $p;
            }

            $p->update(['status' => 'executada', 'decidida_em' => Carbon::now(), 'aprovada_em' => Carbon::now(), 'resultado' => $resultado]);

            return $p;
        });

        $this->auditar($proposta, 'jana_acao_'.$proposta->status, $businessId);

        return $proposta;
    }

    public function cancelar(int $id, int $businessId, Model $user): AcaoAprovacao
    {
        $p = DB::transaction(function () use ($id, $businessId, $user) {
            $p = $this->pendenteDoUsuario($id, $businessId, $user);
            $p->update(['status' => 'cancelada', 'decidida_em' => Carbon::now()]);

            return $p;
        });

        $this->auditar($p, 'jana_acao_cancelada', $businessId);

        return $p;
    }

    private function pendenteDoUsuario(int $id, int $businessId, Model $user): AcaoAprovacao
    {
        /** @var AcaoAprovacao|null $p */
        $p = AcaoAprovacao::where('id', $id)
            ->where('business_id', $businessId)
            ->where('user_id', (int) $user->id)
            ->where('origem', 'chat')
            ->lockForUpdate()
            ->first();

        if ($p === null) {
            throw new AcaoPropostaException('Proposta não encontrada.', 404);
        }
        if ($p->status !== 'proposta') {
            throw new AcaoPropostaException('Esta proposta já foi decidida.', 409);
        }
        if ($p->expira_em !== null && $p->expira_em->isPast()) {
            throw new AcaoPropostaException('A proposta expirou. Peça de novo à Jana.', 410);
        }

        return $p;
    }

    private function auditar(AcaoAprovacao $p, string $evento, int $businessId): void
    {
        $this->util->activityLog($p, $evento, null, [
            'acao'   => $p->acao_key,
            'resumo' => $p->previa,
            'status' => $p->status,
        ], false, $businessId);
    }
}
