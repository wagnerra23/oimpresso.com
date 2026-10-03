<?php

declare(strict_types=1);

namespace App\Contracts\Ponto;

use App\User;

/**
 * Contrato do núcleo para a fila do gestor do REP-P no app das lojas (tela 39).
 *
 * O núcleo (app/) depende deste contrato; o módulo Ponto o implementa e registra no
 * próprio ServiceProvider. Sem o módulo, vale App\Contracts\Ponto\Nulo\SemFilaGestorPonto
 * (ninguém vê a fila). A seta de dependência fica módulo → núcleo (DependencyDirectionTest).
 *
 * Tier 0 (ADR 0093): toda consulta recebe o business_id explícito.
 *
 * @phpstan-type ItemFila array{id: string, colaborador_nome: string, tipo: string, local_texto: ?string,
 *     marcada_em: ?string, nsr: int, dispositivo: ?string, hash_curto: string, estado: string}
 */
interface FilaGestorPonto
{
    public const VALIDADA = 'validada';
    public const RECUSADA = 'recusada';
    public const NAO_ENCONTRADA = 'nao_encontrado';
    public const JA_REVISADA = 'ja_revisada';
    public const TRILHA_DESLIGADA = 'trilha_desligada';

    /** Quem vê a fila: a regra de acesso do módulo Ponto. */
    public function podeVer(User $user): bool;

    /**
     * Marcações do celular fora do geofence, últimos 7 dias, mais nova primeiro, com o estado
     * da decisão (`pendente` | `validada` | `recusada`).
     *
     * @return list<ItemFila>
     */
    public function marcacoes(int $businessId): array;

    /** Valida (registro na trilha; a marcação não muda). Devolve uma das constantes acima. */
    public function validar(User $user, int $businessId, string $id): string;

    /**
     * Recusa (grava anulação; nunca UPDATE/DELETE na marcação).
     *
     * @return array{resultado: string, nsr_anulacao: ?int}
     */
    public function recusar(User $user, int $businessId, string $id): array;
}
