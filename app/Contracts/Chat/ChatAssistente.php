<?php

declare(strict_types=1);

namespace App\Contracts\Chat;

use App\User;

/**
 * Contrato do núcleo para o chat com a Jana no app das lojas (tela 25).
 *
 * O núcleo (app/) depende deste contrato; o módulo Jana o implementa e registra no próprio
 * ServiceProvider. Sem o módulo, vale App\Contracts\Chat\Nulo\SemChatAssistente (nenhuma
 * conversa). A seta de dependência fica módulo → núcleo (DependencyDirectionTest).
 *
 * Tier 0 (ADR 0093): toda consulta recebe o business_id explícito; a conversa é do usuário.
 *
 * @phpstan-type MensagemChat array{de: string, texto: string, criada_em: ?string}
 */
interface ChatAssistente
{
    /**
     * Um turno: grava a mensagem do usuário e a resposta. `$conversaId` null abre conversa nova.
     * null se a conversa não existe ou não é do usuário neste business.
     *
     * @return array{conversa_id: string, texto: string, criada_em: ?string}|null
     */
    public function enviar(User $user, int $businessId, string $texto, ?string $conversaId): ?array;

    /**
     * Mensagens da conversa em ordem cronológica (`de` = `eu` | `jana`). null se a conversa não
     * existe ou não é do usuário neste business.
     *
     * @return list<MensagemChat>|null
     */
    public function historico(User $user, int $businessId, string $conversaId): ?array;
}
