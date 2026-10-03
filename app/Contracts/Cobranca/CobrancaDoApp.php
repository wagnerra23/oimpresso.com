<?php

declare(strict_types=1);

namespace App\Contracts\Cobranca;

/**
 * Contrato do núcleo para gerar, consultar e cancelar cobrança pelo app das lojas (tela 15).
 *
 * O núcleo (app/) decide QUEM pode cobrar O QUÊ e QUANTO — o valor (saldo em aberto) é calculado
 * no núcleo e chega pronto. O módulo PaymentGateway implementa o "como": conta/credencial padrão,
 * chamada ao banco e reconciliação. Ele registra a implementação no próprio ServiceProvider; sem o
 * módulo vale App\Contracts\Cobranca\Nulo\SemCobrancaDoApp. A seta de dependência fica
 * módulo → núcleo (DependencyDirectionTest).
 *
 * Falhas saem como FalhaCobranca com um código estável (o controller traduz para HTTP).
 * Tier 0 (ADR 0093): toda operação recebe o business_id explícito.
 */
interface CobrancaDoApp
{
    /**
     * Emite a cobrança e devolve o id dela em `cobrancas`.
     *
     * @param array{
     *   contact_id: int, valor_centavos: int, vencimento: string, descricao: string,
     *   origem_id: int, metodo: 'pix'|'boleto',
     *   pagador: array{nome: ?string, documento: ?string, email: ?string}
     * } $dados  vencimento em Y-m-d; origem = venda (sale) `origem_id`
     *
     * @throws FalhaCobranca sem_configuracao | provedor_indisponivel | pagador_invalido | ja_existe
     */
    public function emitir(int $businessId, array $dados): int;

    /**
     * Pergunta ao banco; se a cobrança foi paga, aplica a mesma reconciliação do webhook.
     *
     * @throws FalhaCobranca nao_encontrado | sem_configuracao | provedor_indisponivel
     */
    public function consultar(int $businessId, int $cobrancaId): void;

    /**
     * Cancela no banco. Cobrança já cancelada: nada a fazer.
     *
     * @throws FalhaCobranca nao_encontrado | nao_cancelavel | sem_configuracao | provedor_indisponivel
     */
    public function cancelar(int $businessId, int $cobrancaId): void;
}
