<?php

namespace Modules\RecurringBilling\Services\Boleto\Drivers;

use Carbon\Carbon;
use Eduardokum\LaravelBoleto\Api\Banco\Inter as InterApi;
use Eduardokum\LaravelBoleto\Boleto\Banco\Inter as BoletoInter;
use Eduardokum\LaravelBoleto\Pessoa;
use Modules\RecurringBilling\Contracts\BoletoDriverContract;
use Modules\RecurringBilling\Dto\BoletoResult;

/**
 * Driver Banco Inter — API OAuth2 mTLS (certificado .crt + .key).
 * Boleto registrado, sem taxa em contas PJ básica.
 *
 * Credenciais necessárias por tenant (rb_boleto_credentials):
 *   client_id, client_secret, certificado_crt (path), certificado_key (path),
 *   conta_corrente, cnpj_beneficiario, nome_beneficiario,
 *   cep, logradouro, numero, bairro, cidade, uf
 */
class InterDriver implements BoletoDriverContract
{
    public function __construct(private readonly array $config) {}

    public function emitir(array $params): BoletoResult
    {
        $beneficiario = new Pessoa([
            'nome'      => $this->config['nome_beneficiario'],
            'cpf_cnpj'  => $this->config['cnpj_beneficiario'],
            'cep'       => $this->config['cep'],
            'endereco'  => $this->config['logradouro'],
            'numero'    => $this->config['numero'],
            'bairro'    => $this->config['bairro'],
            'cidade'    => $this->config['cidade'],
            'uf'        => $this->config['uf'],
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

        $boleto = new BoletoInter([
            'beneficiario'        => $beneficiario,
            'pagador'             => $pagador,
            'valor'               => $params['valor'],
            'vencimento'          => Carbon::parse($params['data_vencimento']),
            'numero'              => $params['numero_documento'],
            'numeroDocumento'     => $params['numero_documento'],
            'operacao'            => $this->config['operacao'] ?? '003',
            'descricaoDemonstrativo' => $params['descricao'] ?? [],
            'instrucoes'          => $params['instrucoes'] ?? ['Não receber após o vencimento'],
            'diasBaixaAutomatica' => $params['dias_baixa'] ?? 30,
        ]);

        $api = $this->api();
        $response = $api->createBoleto($boleto);

        $nossoNumero = $response->nossoNumero ?? $response->codigoSolicitacao ?? '';
        $pdfBase64 = base64_encode($api->getPdfNossoNumero($nossoNumero));

        return new BoletoResult(
            nossoNumero:    $nossoNumero,
            linhaDigitavel: $response->linhaDigitavel ?? '',
            codigoBarras:   $response->codigoBarras ?? '',
            dataVencimento: $params['data_vencimento'],
            valor:          (float) $params['valor'],
            pixQrCode:      $response->pixCopiaECola ?? null,
            pdfBase64:      $pdfBase64,
        );
    }

    public function cancelar(string $nossoNumero, string $motivo = 'ACERTOS'): bool
    {
        $this->api()->cancelNossoNumero($nossoNumero, $motivo);

        return true;
    }

    public function pdf(string $nossoNumero): string
    {
        return base64_encode($this->api()->getPdfNossoNumero($nossoNumero));
    }

    private function writeTempCert(string $prefix, string $content): string
    {
        $path = sys_get_temp_dir() . '/' . $prefix . '_' . md5($content) . '.pem';
        if (! file_exists($path)) {
            file_put_contents($path, $content, LOCK_EX);
            chmod($path, 0600);
        }
        return $path;
    }

    private function api(): InterApi
    {
        // Inter API precisa de arquivos físicos para curl mTLS
        // Conteúdo vem do banco (base64) e é gravado em temp file
        $crtPath = $this->writeTempCert('inter_crt', base64_decode($this->config['certificado_crt_b64']));
        $keyPath = $this->writeTempCert('inter_key', base64_decode($this->config['certificado_key_b64']));

        return new InterApi([
            'conta'             => $this->config['conta_corrente'],
            'cnpj'              => $this->config['cnpj_beneficiario'],
            'certificado'       => $crtPath,
            'certificadoChave'  => $keyPath,
            'certificadoSenha'  => $this->config['certificado_senha'] ?? '',
            'client_id'         => $this->config['client_id'],
            'client_secret'     => $this->config['client_secret'],
            'identificador'     => $this->config['identificador'] ?? null,
        ]);
    }
}
