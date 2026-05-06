<?php

namespace Modules\RecurringBilling\Services\Boleto\Drivers;

use Carbon\Carbon;
use Eduardokum\LaravelBoleto\Boleto\Banco\C6 as BoletoC6;
use Eduardokum\LaravelBoleto\Boleto\Render\Pdf;
use Eduardokum\LaravelBoleto\Pessoa;
use Modules\RecurringBilling\Contracts\BoletoDriverContract;
use Modules\RecurringBilling\Dto\BoletoResult;

/**
 * Driver C6 Bank — geração local (sem registro via API).
 * Boleto gerado com código de barras válido; registro feito via CNAB remessa.
 * Sem taxa em contas PJ C6.
 *
 * Credenciais necessárias (rb_boleto_credentials):
 *   agencia, conta_corrente, codigo_cliente, convenio,
 *   cnpj_beneficiario, nome_beneficiario, cep, logradouro, numero, bairro, cidade, uf
 */
class C6Driver implements BoletoDriverContract
{
    public function __construct(private readonly array $config) {}

    public function emitir(array $params): BoletoResult
    {
        $beneficiario = new Pessoa([
            'nome'     => $this->config['nome_beneficiario'],
            'cpf_cnpj' => $this->config['cnpj_beneficiario'],
            'cep'      => $this->config['cep'],
            'endereco' => $this->config['logradouro'],
            'numero'   => $this->config['numero'],
            'bairro'   => $this->config['bairro'],
            'cidade'   => $this->config['cidade'],
            'uf'       => $this->config['uf'],
        ]);

        $pagador = new Pessoa([
            'nome'     => $params['pagador_nome'],
            'cpf_cnpj' => $params['pagador_cpf_cnpj'],
            'cep'      => $params['pagador_cep'] ?? '00000-000',
            'endereco' => $params['pagador_endereco'] ?? 'Não informado',
            'numero'   => $params['pagador_numero'] ?? 'S/N',
            'bairro'   => $params['pagador_bairro'] ?? '',
            'cidade'   => $params['pagador_cidade'] ?? '',
            'uf'       => $params['pagador_uf'] ?? 'SP',
        ]);

        $boleto = new BoletoC6([
            'beneficiario'    => $beneficiario,
            'pagador'         => $pagador,
            'agencia'         => $this->config['agencia'],
            'conta'           => $this->config['conta_corrente'],
            'codigoCliente'   => $this->config['codigo_cliente'],
            // Lib eduardokum aceita: ['10', '20', '30', '40', '60']. Default '10'
            // (antes era '25', que disparava ValidationException — bug latente
            // descoberto em US-RB-040 com a 1ª cobertura Pest do driver)
            'carteira'        => $this->config['carteira'] ?? '10',
            'valor'           => $params['valor'],
            'vencimento'      => Carbon::parse($params['data_vencimento']),
            'numero'          => $params['numero_documento'],
            'numeroDocumento' => $params['numero_documento'],
            'instrucoes'      => $params['instrucoes'] ?? ['Não receber após o vencimento'],
        ]);

        $pdf = new Pdf();
        $pdf->addBoleto($boleto);
        $pdfBase64 = base64_encode($pdf->gerarBoleto(Pdf::OUTPUT_STRING));

        return new BoletoResult(
            nossoNumero:    $boleto->getNossoNumero(),
            linhaDigitavel: $boleto->getLinhaDigitavel(),
            codigoBarras:   $boleto->getCodigoBarras(),
            dataVencimento: $params['data_vencimento'],
            valor:          (float) $params['valor'],
            pdfBase64:      $pdfBase64,
        );
    }

    public function cancelar(string $nossoNumero, string $motivo = 'ACERTOS'): bool
    {
        // C6 não tem API de cancelamento — requer CNAB remessa de cancelamento
        // (ocorrência 02 - "Pedido de baixa") ou ação manual no portal C6.
        // Lançar exceção é mais seguro que fingir sucesso (US-RB-042).
        throw new \BadMethodCallException(
            "C6Driver::cancelar() não suportado via API. Cancele '{$nossoNumero}' " .
            "manualmente no portal C6 (Empresarial → Cobrança → Boletos) OU " .
            "implementar geração CNAB de remessa de cancelamento (ocorrência 02). " .
            "Motivo registrado: {$motivo}"
        );
    }

    public function pdf(string $nossoNumero): string
    {
        // PDF já gerado em emitir() — re-gerar requer os dados originais
        throw new \RuntimeException('C6Driver: re-geração de PDF requer re-emissão com dados originais.');
    }
}
