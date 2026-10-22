<?php

namespace Modules\Producao\Exceptions;

use DomainException;

/**
 * Recusa do apontamento do operador (thread 04). Uma classe, um `codigo`: a PWA e a API do
 * app traduzem o código para HTTP sem conhecer a regra.
 *
 * Tabela de tradução combinada com a API do app (PR 06):
 *   nao_encontrado → 404 (inexistente, de outra empresa ou de outro operador: mesma resposta)
 *   etapa_alheia → 409 (contexto: etapa e responsável atuais)
 *   pessoa_ocupada → 409 (contexto: apontamento aberto)
 *   etapa_encerrada · apontamento_encerrado → 422 bloqueado
 *   responsavel_invalido → 422 (Liberar: etapa sem responsável, de outra OS ou responsável de outra empresa)
 *   sinal_pendente → 409 (Liberar: a empresa exige o sinal pago e a venda está abaixo do mínimo; contexto: pago × exigido)
 *   os_nao_liberada → 409 (OS por item fora de produção ou etapa anterior aberta; contexto: situação)
 *   material_invalido → 422 campos.variation_id · quantidade_invalida → 422 campos.quantidade · estoque_insuficiente → 422 (material, US-PCP-021)
 *   lote_invalido → 422 campos.lote_id (rolo escolhido de outro material, local ou empresa, ou sem saldo — US-COM-025)
 *   coluna_invalida → 422 campos.setor (mover no quadro para setor que a empresa não tem, ou o mesmo — UC-PCV-07)
 *   outro_setor · choque_de_horario · operador_destino → 422 (reprogramar na Linha do tempo — UC-PCV-30; contexto diz com quem choca ou quem opera o destino)
 *   idempotencia_conflito → 422 · momento_invalido → 422 campos.momento · motivo_obrigatorio → 422 campos.motivo_id · justificativa_obrigatoria → 422 campos.justificativa
 */
class ApontamentoRecusado extends DomainException
{
    public const NAO_ENCONTRADO = 'nao_encontrado';
    public const ETAPA_ALHEIA = 'etapa_alheia';
    public const ETAPA_ENCERRADA = 'etapa_encerrada';
    public const PESSOA_OCUPADA = 'pessoa_ocupada';
    public const APONTAMENTO_ENCERRADO = 'apontamento_encerrado';
    public const IDEMPOTENCIA_CONFLITO = 'idempotencia_conflito';
    public const MOMENTO_INVALIDO = 'momento_invalido';
    public const MOTIVO_OBRIGATORIO = 'motivo_obrigatorio';
    public const JUSTIFICATIVA_OBRIGATORIA = 'justificativa_obrigatoria';
    public const OS_NAO_LIBERADA = 'os_nao_liberada';
    public const RESPONSAVEL_INVALIDO = 'responsavel_invalido';
    /** Liberar com a regra "só libera com o sinal pago" ligada e o sinal abaixo do mínimo → 409 (US-PCP-025). */
    public const SINAL_PENDENTE = 'sinal_pendente';
    public const MATERIAL_INVALIDO = 'material_invalido';
    public const QUANTIDADE_INVALIDA = 'quantidade_invalida';
    public const ESTOQUE_INSUFICIENTE = 'estoque_insuficiente';
    public const LOTE_INVALIDO = 'lote_invalido';
    public const COLUNA_INVALIDA = 'coluna_invalida';
    /** Linha do tempo (UC-PCV-30): máquina de outro setor, horário ocupado, ou responsável que não opera o destino. */
    public const OUTRO_SETOR = 'outro_setor';
    public const CHOQUE_DE_HORARIO = 'choque_de_horario';
    public const OPERADOR_DESTINO = 'operador_destino';

    /** Link de aprovação da arte já respondido ou vencido → 410 (UC-FLX-02). */
    public const APROVACAO_ENCERRADA = 'aprovacao_encerrada';

    /** @param array<string, mixed> $contexto */
    public function __construct(public readonly string $codigo, string $mensagem, public readonly array $contexto = [])
    {
        parent::__construct($mensagem);
    }
}
