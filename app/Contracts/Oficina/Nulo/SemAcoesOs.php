<?php

declare(strict_types=1);

namespace App\Contracts\Oficina\Nulo;

use App\Contracts\Oficina\AcoesOs;
use App\User;

/** Padrão quando o módulo OficinaAuto não está carregado: nenhuma OS existe. */
final class SemAcoesOs implements AcoesOs
{
    public function acoes(User $user, int $businessId, int $osId): ?array
    {
        return null;
    }

    public function executar(User $user, int $businessId, int $osId, string $chave, ?string $motivo = null): array
    {
        return ['resultado' => 'nao_encontrado', 'mensagem' => 'OS não encontrada.'];
    }

    public function criar(User $user, int $businessId, array $dados): ?int
    {
        return null;
    }

    public function criarVeiculo(User $user, int $businessId, array $dados): ?int
    {
        return null;
    }

    public function consultaPlacaDisponivel(): bool
    {
        return false;
    }

    public function consultarPlaca(int $businessId, string $placa): array
    {
        return ['resultado' => 'sem_configuracao'];
    }
}
