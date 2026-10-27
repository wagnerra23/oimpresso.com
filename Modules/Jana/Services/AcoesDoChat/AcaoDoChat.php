<?php

declare(strict_types=1);

namespace Modules\Jana\Services\AcoesDoChat;

use Illuminate\Database\Eloquent\Model;

/**
 * Uma ação que a Jana pode PROPOR no chat (ADR 0440). Ela nunca executa sozinha:
 * `executar()` só roda depois que o usuário confirma o cartão.
 *
 * `permitida()` responde com a permissão do próprio usuário — a Jana não faz o que
 * ele não poderia fazer na tela. `executar()` chama o serviço que a tela usa.
 */
interface AcaoDoChat
{
    public function chave(): string;

    public function permitida(Model $user, int $businessId): bool;

    /**
     * Valida e normaliza os campos que o modelo mandou. Lança ValidationException.
     *
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    public function validar(array $campos): array;

    /** @param array<string, mixed> $campos */
    public function resumo(array $campos): string;

    /**
     * @param  array<string, mixed>  $campos  já validados
     * @return array{id: int, rotulo: string, url: string|null}
     */
    public function executar(int $businessId, Model $user, array $campos): array;
}
