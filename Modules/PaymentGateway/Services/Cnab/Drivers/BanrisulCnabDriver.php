<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Banrisul as BanrisulBoleto;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver CNAB do Banrisul — Banco do Estado do Rio Grande do Sul.
 *
 * Relevante regional pra clientes oimpresso vestuário SC/RS: Banrisul tem
 * presença forte no Sul (Porto Alegre/RS, agências em SC) e várias gráficas /
 * confecções do segmento usam Banrisul como banco operacional principal.
 *
 * Camadas suportadas (delegadas a CnabBoletoAdapter):
 *   - emissão boleto registrado (CNAB 240) com nossoNumero próprio Banrisul
 *     (8 dígitos sequenciais + 2 DV via CalculoDV::banrisulNossoNumero)
 *   - cancelamento via arquivo de instrução (ocorrência '02' layout 240)
 *   - healthCheck por smoke instanciação da classe Boleto
 *   - emitirPix / cartão / consultar / webhook → DriverNotSupported
 *     (CNAB é file-based — retorno chega via upload de arquivo,
 *      processado pelo Job CnabRetornoProcessor)
 *
 * Carteiras Banrisul aceitas pela lib (subset relevante):
 *   - '1' Cobrança Simples
 *   - '2' Cobrança Vinculada
 *   - '3' Cobrança Caucionada
 *   - (lista completa em \Eduardokum\LaravelBoleto\Boleto\Banco\Banrisul::$carteiras)
 *
 * Campos obrigatórios em `config_json`:
 *   - agencia (4 dígitos)
 *   - conta (até 7 dígitos numéricos + DV calculado)
 *   - carteira (subset acima — recomendado '1' pra cobrança simples padrão)
 *   - codigo_cliente (7 dígitos + 2 DV — vem da carta-circular Banrisul)
 *   - cedente_nome (string)
 *   - cedente_documento (CNPJ 14 dígitos sem máscara)
 *
 * Multi-tenant Tier 0 honrado integralmente via CnabBoletoAdapter:
 * `$cred->business_id` é usado no path Storage de remessa/instrução.
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.cnab/Banrisul
 */
final class BanrisulCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'banrisul_cnab';
    }

    protected function getBoletoClass(): string
    {
        return BanrisulBoleto::class;
    }

    /**
     * Banrisul opera CNAB 240 (FEBRABAN) — layout 400 está em descontinuação
     * conforme circular Banrisul 12/2020. Padrão Onda 4f.cnab/Banrisul: 240.
     */
    protected function getLayoutVersion(): int
    {
        return 240;
    }

    /**
     * Campos obrigatórios pra emitir boleto Banrisul registrado.
     *
     * `codigo_cliente` é o identificador-chave do convênio Banrisul (sem ele
     * a lib não consegue montar o campo livre — getCampoLivre() chama
     * Util::numberFormatGeral($this->getCodigoCliente(), 11) e geraria string
     * de zeros, invalidando o boleto na compensação).
     */
    protected function camposObrigatoriosCnab(): array
    {
        return [
            'agencia',
            'conta',
            'carteira',
            'codigo_cliente',
            'cedente_nome',
            'cedente_documento',
        ];
    }
}
