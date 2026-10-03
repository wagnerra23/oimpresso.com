<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Contracts\Chat\ChatAssistente;
use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chat com a Jana no app das lojas (tela 25). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.4.
 * Canal = Jana (decisão [W]). Reaproveita o fluxo do chat web: as mesmas conversas
 * (jana_conversas / jana_mensagens) e o mesmo turno, pelo contrato do núcleo ChatAssistente que o
 * módulo Jana implementa (a seta de dependência fica módulo → núcleo). Resposta síncrona.
 *
 * Acesso = o do chat web: módulo Jana no plano (`jana_module`, como o atalho "IA" da barra
 * lateral) + `jana.access` + `jana.chat`. A conversa é do usuário: de outro usuário, ou de
 * outro business, responde 404 (ninguém lê a conversa de outro, como na web).
 *
 * Tier 0 (ADR 0093): business_id e user_id do token vão para o contrato, que filtra por eles.
 */
class ChatController extends Controller
{
    public const MAX_MENSAGEM = 1000;

    public function __construct(private ModuleUtil $moduleUtil, private ChatAssistente $chat)
    {
    }

    /** Área `assistente` do Início: a mesma regra que abre estas rotas. */
    public function podeConversar(User $user): bool
    {
        $bizId = (int) $user->business_id;
        $temModulo = $user->can('superadmin')
            || $this->moduleUtil->hasThePermissionInSubscription($bizId, 'jana_module', 'superadmin_package');

        return $temModulo && $user->can('jana.access') && $user->can('jana.chat');
    }

    /** POST /api/app/chat { mensagem, conversa_id? } → { conversa_id, resposta:{de, texto, criada_em} } */
    public function enviar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeConversar($user)) {
            return $this->semPermissao();
        }

        $texto = $request->input('mensagem');
        $erro = null;
        if (! is_string($texto) || trim($texto) === '') {
            $erro = ['mensagem' => 'Digite uma mensagem antes de enviar.'];
        } elseif (mb_strlen($texto) > self::MAX_MENSAGEM) {
            $erro = ['mensagem' => 'Mensagem muito longa (máx ' . self::MAX_MENSAGEM . ' caracteres).'];
        }
        $conversaId = $request->input('conversa_id');
        if ($erro === null && $conversaId !== null && ! (is_string($conversaId) || is_int($conversaId))) {
            $erro = ['conversa_id' => 'Conversa inválida.'];
        }
        if ($erro !== null) {
            return response()->json(['erro' => 'validacao', 'mensagem' => (string) reset($erro), 'campos' => $erro], 422);
        }

        $r = $this->chat->enviar(
            $user,
            (int) $user->business_id,
            trim($texto),
            $conversaId === null ? null : (string) $conversaId,
        );
        if ($r === null) {
            return $this->naoEncontrada();
        }

        return response()->json([
            'conversa_id' => $r['conversa_id'],
            'resposta' => ['de' => 'jana', 'texto' => $r['texto'], 'criada_em' => $r['criada_em']],
        ]);
    }

    /** GET /api/app/chat/{conversa_id} → { conversa_id, mensagens:[{de, texto, criada_em}] } */
    public function mostrar(Request $request, string $conversaId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeConversar($user)) {
            return $this->semPermissao();
        }
        $mensagens = $this->chat->historico($user, (int) $user->business_id, $conversaId);
        if ($mensagens === null) {
            return $this->naoEncontrada();
        }

        return response()->json(['conversa_id' => (string) (int) $conversaId, 'mensagens' => $mensagens]);
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Você não tem acesso ao assistente.'], 403);
    }

    private function naoEncontrada(): JsonResponse
    {
        return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Conversa não encontrada.'], 404);
    }
}
