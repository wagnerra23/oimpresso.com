<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

use App\Contracts\Chat\ChatAssistente;
use App\User;
use Illuminate\Support\Str;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;

/**
 * Implementação do contrato do núcleo ChatAssistente (tela 25 do app das lojas): as mesmas
 * conversas do chat web (jana_conversas / jana_mensagens) e o mesmo turno (ChatTurnoService).
 * Registrada no JanaServiceProvider.
 *
 * Tier 0 (ADR 0093): sem sessão numa chamada com token, o global scope da Conversa não filtra —
 * business_id e user_id vão explícitos em toda consulta.
 */
final class ChatDoApp implements ChatAssistente
{
    public function __construct(private ChatTurnoService $turno)
    {
    }

    public function enviar(User $user, int $businessId, string $texto, ?string $conversaId): ?array
    {
        if ($conversaId !== null) {
            $conversa = $this->conversaDo($user, $businessId, $conversaId);
            if (! $conversa) {
                return null;
            }
        } else {
            $conversa = Conversa::create([
                'business_id' => $businessId,
                'user_id'     => (int) $user->id,
                'titulo'      => Str::limit($texto, 60),
                'status'      => 'ativa',
                'iniciada_em' => now(),
            ]);
        }

        $resposta = $this->turno->responder($conversa, $texto);

        return [
            'conversa_id' => (string) $conversa->id,
            'texto' => (string) $resposta->content,
            'criada_em' => $resposta->created_at?->toIso8601String(),
        ];
    }

    public function historico(User $user, int $businessId, string $conversaId): ?array
    {
        $conversa = $this->conversaDo($user, $businessId, $conversaId);
        if (! $conversa) {
            return null;
        }

        return Mensagem::query()
            ->where('conversa_id', $conversa->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['role', 'content', 'created_at'])
            ->map(fn (Mensagem $m) => [
                'de' => $m->role === 'user' ? 'eu' : 'jana',
                'texto' => (string) $m->content,
                'criada_em' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function conversaDo(User $user, int $businessId, string $id): ?Conversa
    {
        if (! ctype_digit($id)) {
            return null;
        }

        return Conversa::query()
            ->where('business_id', $businessId)
            ->where('user_id', (int) $user->id)
            ->whereKey((int) $id)
            ->first();
    }
}
