<?php

declare(strict_types=1);

namespace App\Contracts\Oficina;

use App\User;

/**
 * Contrato do núcleo para avançar a etapa de uma OS pelo app das lojas (tela 03).
 *
 * O núcleo (app/) depende deste contrato; o módulo OficinaAuto o implementa e registra no
 * próprio ServiceProvider. Sem o módulo, vale App\Contracts\Oficina\Nulo\SemAcoesOs (nenhuma
 * OS encontrada). A seta de dependência fica módulo → núcleo (DependencyDirectionTest).
 *
 * Só as ações que AVANÇAM a OS na linha principal (ACOES_DO_APP). Cancelar, recusar orçamento,
 * acionar garantia e o override do gate ficam só na web. Ação que no banco tenha efeito
 * colateral (side_effect_class / event_class) é recusada: o app não move valor nem estoque.
 *
 * Tier 0 (ADR 0093): toda consulta recebe o business_id explícito (o escopo do model lê a
 * sessão, que a API não tem).
 *
 * @phpstan-type AcaoOs array{chave: string, rotulo: string, critica: bool, pode: bool, bloqueio: ?string}
 */
interface AcoesOs
{
    /** Ações de avanço que o app oferece, na ordem da linha principal do processo da oficina. */
    public const ACOES_DO_APP = [
        'iniciar_diagnostico',
        'enviar_orcamento',
        'aprovar_pedir_pecas',
        'aprovar_executar',
        'pecas_chegaram',
        'concluir_servico',
        'entregar',
    ];

    /**
     * Ações de avanço que saem da etapa atual da OS. null se a OS não existe neste business.
     * Lista vazia quando a OS está fora do pipeline ou numa etapa sem avanço (terminal).
     *
     * @return list<AcaoOs>|null
     */
    public function acoes(User $user, int $businessId, int $osId): ?array;

    /**
     * Executa a ação. `resultado`: ok · nao_encontrado · sem_permissao · bloqueado (gate) ·
     * etapa_mudou (a ação não sai da etapa atual) · nao_suportada (fora de ACOES_DO_APP ou com
     * efeito colateral no banco). `mensagem` explica os não-ok.
     *
     * @return array{resultado: string, mensagem: ?string}
     */
    public function executar(User $user, int $businessId, int $osId, string $chave): array;
}
