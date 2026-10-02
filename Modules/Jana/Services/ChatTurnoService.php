<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

use Modules\Jana\Contracts\AiAdapter;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;

/**
 * Um turno do chat da Jana (modo não-streaming): grava a mensagem do usuário, obtém a resposta e
 * grava a mensagem da Jana com os tokens daquele turno.
 *
 * Um lugar só para o chat web (ChatController::send) e para o app das lojas
 * (POST /api/app/chat, tela 25). O streaming (sendStream) segue no controller.
 *
 * Quem chama garante o dono da conversa (user_id) e o business — aqui não há sessão.
 */
class ChatTurnoService
{
    public const RESPOSTA_FALHA = 'Estou com dificuldades técnicas no momento. Tente novamente em instantes.';

    public function __construct(
        private AiAdapter $ai,
        private BriefDiarioChatTrigger $briefTrigger,
    ) {
    }

    /** Devolve a mensagem da Jana gravada. */
    public function responder(Conversa $conversa, string $texto): Mensagem
    {
        Mensagem::create([
            'conversa_id' => $conversa->id,
            'role'        => 'user',
            'content'     => $texto,
        ]);

        // US-COPI-203: intent shortcut pro brief diário JANA Pro. Se user
        // pediu brief (regex match), invoca BriefDiarioAgent direto em vez
        // do ChatCopilotoAgent. Retorna markdown formatado Versão A.
        if ($this->briefTrigger->matches($texto)) {
            $resposta = $this->briefTrigger->gerar($conversa);
        } else {
            // Caminho padrão — IA conversacional ChatCopilotoAgent
            try {
                $resposta = $this->ai->responderChat($conversa, $texto);
            } catch (\Throwable $e) {
                $resposta = self::RESPOSTA_FALHA;
            }
        }

        // Tokens DESTE turno. O driver não grava mais sozinho — ele retornava
        // antes desta linha, então o UPDATE dele caía no turno ANTERIOR.
        // Ver AiAdapter::ultimoUsoTokens(). No atalho do brief o adapter nem é
        // chamado, e aí vem null/null (correto: não houve consumo por aqui).
        $uso = $this->ai->ultimoUsoTokens();

        return Mensagem::create([
            'conversa_id' => $conversa->id,
            'role'        => 'assistant',
            'content'     => $resposta,
            'tokens_in'   => $uso['tokens_in'] ?? null,
            'tokens_out'  => $uso['tokens_out'] ?? null,
        ]);
    }
}
