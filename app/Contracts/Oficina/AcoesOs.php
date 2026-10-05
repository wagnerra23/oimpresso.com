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
 * Só as ações que AVANÇAM a OS na linha principal (ACOES_DO_APP) e as que a ENCERRAM sem
 * efeito (ACOES_QUE_ENCERRAM: cancelar, recusar orçamento — pedido [W] 2026-10-05; acionar
 * garantia — pedido [W] 2026-10-05, com motivo obrigatório). O override do gate fica só na web.
 * Ação que no banco tenha efeito colateral (side_effect_class / event_class) é recusada: o app
 * não move valor nem estoque.
 *
 * Tier 0 (ADR 0093): toda consulta recebe o business_id explícito (o escopo do model lê a
 * sessão, que a API não tem).
 *
 * @phpstan-type AcaoOs array{chave: string, rotulo: string, tipo: string, critica: bool, pode: bool, bloqueio: ?string, motivo_obrigatorio: bool, destino: ?array{chave: string, rotulo: string}}
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

    /** Ações que encerram a OS (etapa terminal), oferecidas depois das de avanço. */
    public const ACOES_QUE_ENCERRAM = [
        'recusar_orcamento',
        'cancelar_os',
        'acionar_garantia',
    ];

    /**
     * Ações que só saem com motivo (≤ 500). A garantia vai ser questionada depois, e o pipeline do
     * Repair já exige motivo para ela (ReopenJobSheetRequest); no app ela não sai sem explicação.
     */
    public const ACOES_COM_MOTIVO = [
        'acionar_garantia',
    ];

    /**
     * Ações que saem da etapa atual da OS: primeiro as de avanço (`tipo` = avanco), depois as que
     * encerram (`tipo` = encerra). null se a OS não existe neste business. Lista vazia quando a
     * OS está fora do pipeline ou numa etapa terminal.
     *
     * @return list<AcaoOs>|null
     */
    public function acoes(User $user, int $businessId, int $osId): ?array;

    /**
     * Executa a ação. `resultado`: ok · nao_encontrado · sem_permissao · bloqueado (gate) ·
     * etapa_mudou (a ação não sai da etapa atual) · nao_suportada (fora das duas listas ou com
     * efeito colateral no banco). `mensagem` explica os não-ok. `$motivo` vai para a trilha
     * (sale_stage_history.payload_snapshot).
     *
     * @return array{resultado: string, mensagem: ?string}
     */
    public function executar(User $user, int $businessId, int $osId, string $chave, ?string $motivo = null): array;

    /**
     * Nova OS de mecânica, como o create da web: nasce `aberta`, entra no pipeline da oficina
     * (Recepção) e liga o veículo se ele estiver livre. Sem item, valor, venda nem WhatsApp.
     * Veículo e cliente já validados como do business pelo chamador. null se a oficina não
     * está disponível (módulo ausente).
     *
     * @param  array{vehicle_id: int, contact_id: ?int, mileage_at_service: ?int, box_label: ?string, notes: ?string}  $dados
     */
    public function criar(User $user, int $businessId, array $dados): ?int;

    /**
     * Novo veículo, como o store da web (VehicleController@store): só insere em vehicles, sem valor,
     * estoque, venda nem cobrança. Dados já validados pelo chamador (placa normalizada, tipo da lista,
     * dono do business). null se a oficina não está disponível (módulo ausente).
     *
     * @param  array{plate: string, vehicle_type: string, secondary_plate: ?string, manufacture_year: ?int, model_year: ?int, color: ?string, mileage_at_entry: ?int, chassis: ?string, renavam: ?string, contact_id: ?int}  $dados
     */
    public function criarVeiculo(User $user, int $businessId, array $dados): ?int;

    /** A consulta de placa pode responder neste ambiente (fornecedor real, ou stub fora de produção)? */
    public function consultaPlacaDisponivel(): bool;

    /**
     * Consulta de placa da web (VehicleLookupService, cache 24h por business+placa, só dados técnicos,
     * sem proprietário). `resultado`: ok · nao_encontrado · sem_configuracao · indisponivel. Placa já
     * normalizada e validada pelo chamador.
     *
     * @return array{resultado: string, dados?: array{placa: string, ano_fabricacao: ?int, ano_modelo: ?int, cor: ?string, chassi: ?string, renavam: ?string, marca_modelo: ?string}}
     */
    public function consultarPlaca(int $businessId, string $placa): array;
}
