<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Services\ChatTurnoService;

/**
 * Chat com a Jana no app das lojas (tela 25). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.4.
 * Canal = Jana (decisão [W]). Reaproveita o fluxo do chat web: as mesmas conversas
 * (jana_conversas / jana_mensagens) e o mesmo turno (ChatTurnoService). Resposta síncrona.
 *
 * Acesso = o do chat web: módulo Jana no plano (`jana_module`, como o atalho "IA" da barra
 * lateral) + `jana.access` + `jana.chat`. A conversa é do usuário: de outro usuário, ou de
 * outro business, responde 404 (ninguém lê a conversa de outro, como na web).
 *
 * Tier 0 (ADR 0093): business_id e user_id do token em toda consulta — sem sessão, o global
 * scope da Conversa não filtra, então o filtro é explícito.
 */
class ChatController extends Controller
{
    public const MAX_MENSAGEM = 1000;

    public function __construct(private ModuleUtil $moduleUtil)
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
            $erro = ['mensagem' => ['Digite uma mensagem antes de enviar.']];
        } elseif (mb_strlen($texto) > self::MAX_MENSAGEM) {
            $erro = ['mensagem' => ['Mensagem muito longa (máx ' . self::MAX_MENSAGEM . ' caracteres).']];
        }
        $conversaId = $request->input('conversa_id');
        if ($erro === null && $conversaId !== null && ! (is_string($conversaId) || is_int($conversaId))) {
            $erro = ['conversa_id' => ['Conversa inválida.']];
        }
        if ($erro !== null) {
            return response()->json(['erro' => 'validacao', 'mensagem' => reset($erro)[0], 'campos' => $erro], 422);
        }
        $texto = trim($texto);

        if ($conversaId !== null) {
            $conversa = $this->conversaDo($user, (string) $conversaId);
            if (! $conversa) {
                return $this->naoEncontrada();
            }
        } else {
            $conversa = Conversa::create([
                'business_id' => (int) $user->business_id,
                'user_id'     => (int) $user->id,
                'titulo'      => Str::limit($texto, 60),
                'status'      => 'ativa',
                'iniciada_em' => now(),
            ]);
        }

        $resposta = app(ChatTurnoService::class)->responder($conversa, $texto);

        return response()->json([
            'conversa_id' => (string) $conversa->id,
            'resposta' => [
                'de' => 'jana',
                'texto' => (string) $resposta->content,
                'criada_em' => $resposta->created_at?->toIso8601String(),
            ],
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
        $conversa = $this->conversaDo($user, $conversaId);
        if (! $conversa) {
            return $this->naoEncontrada();
        }

        $mensagens = Mensagem::query()
            ->where('conversa_id', $conversa->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['role', 'content', 'created_at']);

        return response()->json([
            'conversa_id' => (string) $conversa->id,
            'mensagens' => $mensagens->map(fn (Mensagem $m) => [
                'de' => $m->role === 'user' ? 'eu' : 'jana',
                'texto' => (string) $m->content,
                'criada_em' => $m->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    private function conversaDo(User $user, string $id): ?Conversa
    {
        if (! ctype_digit($id)) {
            return null;
        }

        return Conversa::query()
            ->where('business_id', (int) $user->business_id)
            ->where('user_id', (int) $user->id)
            ->whereKey((int) $id)
            ->first();
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
