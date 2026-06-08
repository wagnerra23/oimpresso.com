<?php

namespace Modules\RecurringBilling\Services\Boleto\Drivers;

use Illuminate\Support\Facades\Http;
use Modules\RecurringBilling\Contracts\BoletoDriverContract;
use Modules\RecurringBilling\Dto\BoletoResult;

/**
 * Driver Asaas — API REST.
 * Boleto registrado + PIX Copia e Cola + cobrança via cartão na mesma API.
 *
 * Credenciais necessárias (rb_boleto_credentials):
 *   api_key (token $aact_... do Asaas),
 *   ambiente: 'sandbox' | 'production'
 */
class AsaasDriver implements BoletoDriverContract
{
    private string $baseUrl;

    public function __construct(private readonly array $config)
    {
        $this->baseUrl = ($config['ambiente'] ?? 'production') === 'sandbox'
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/v3';
    }

    public function emitir(array $params): BoletoResult
    {
        // Garante que o cliente existe no Asaas (cria se não tiver)
        $customerId = $this->resolveCustomer($params);

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/payments", [
                'customer'       => $customerId,
                'billingType'    => 'BOLETO',
                'value'          => $params['valor'],
                'dueDate'        => $params['data_vencimento'],
                'description'    => $params['descricao'] ?? '',
                'externalReference' => $params['numero_documento'],
                'postalService'  => false,
            ])
            ->throw()
            ->json();

        $id = $response['id'];

        // Busca linha digitável e QR Pix separados
        $boletoInfo = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/payments/{$id}/identificationField")
            ->json();

        $pixInfo = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/payments/{$id}/pixQrCode")
            ->json();

        return new BoletoResult(
            nossoNumero:    $id,
            linhaDigitavel: $boletoInfo['identificationField'] ?? '',
            codigoBarras:   $boletoInfo['barCode'] ?? '',
            dataVencimento: $params['data_vencimento'],
            valor:          (float) $params['valor'],
            pixQrCode:      $pixInfo['payload'] ?? null,
            pdfUrl:         $response['bankSlipUrl'] ?? null,
        );
    }

    public function cancelar(string $nossoNumero, string $motivo = 'ACERTOS'): bool
    {
        Http::withHeaders($this->headers())
            ->delete("{$this->baseUrl}/payments/{$nossoNumero}")
            ->throw();

        return true;
    }

    /**
     * Refund (estorno) de cobrança JÁ PAGA — POST /payments/{id}/refund.
     *
     * Diferente de cancelar() que faz DELETE em charge pending, refund mexe
     * em charge com status RECEIVED/CONFIRMED e devolve dinheiro pro pagador.
     * Asaas executa via PIX/TED automático na conta de origem do pagador.
     *
     * @param  string  $chargeId  ID Asaas (pay_xxx) da cobrança paga
     * @param  string  $descricao  Motivo do estorno (vai no body Asaas)
     * @param  float|null  $valor  Valor parcial; null = refund total
     * @return array  Response Asaas decodificada — chaves esperadas:
     *                id, status (REFUNDED|PARTIALLY_REFUNDED), value, refundedDate
     */
    public function refund(string $chargeId, string $descricao, ?float $valor = null): array
    {
        $body = ['description' => $descricao];

        if ($valor !== null) {
            $body['value'] = $valor;
        }

        return Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/payments/{$chargeId}/refund", $body)
            ->throw()
            ->json();
    }

    /**
     * GET /payments/{id} — usado pra checar status atual (PENDING / RECEIVED /
     * CONFIRMED / REFUNDED) antes de decidir cancelar vs refund.
     *
     * Retorna array Asaas (id, status, value, dueDate, paymentDate, etc).
     */
    public function fetchPayment(string $chargeId): array
    {
        return Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/payments/{$chargeId}")
            ->throw()
            ->json();
    }

    public function pdf(string $nossoNumero): string
    {
        // Asaas disponibiliza URL pública do boleto PDF — retorna URL em base64 dummy
        // O PDF real está em $result->pdfUrl (bankSlipUrl)
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/payments/{$nossoNumero}")
            ->throw()
            ->json();

        return $response['bankSlipUrl'] ?? '';
    }

    private function resolveCustomer(array $params): string
    {
        // Busca por CPF/CNPJ — cria se não existir
        $search = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/customers", ['cpfCnpj' => preg_replace('/\D/', '', $params['pagador_cpf_cnpj'])])
            ->json();

        if (! empty($search['data'])) {
            return $search['data'][0]['id'];
        }

        $created = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/customers", [
                'name'    => $params['pagador_nome'],
                'cpfCnpj' => preg_replace('/\D/', '', $params['pagador_cpf_cnpj']),
                'email'   => $params['pagador_email'] ?? null,
                'phone'   => $params['pagador_telefone'] ?? null,
            ])
            ->throw()
            ->json();

        return $created['id'];
    }

    private function headers(): array
    {
        return [
            'access_token' => $this->config['api_key'],
            'Content-Type' => 'application/json',
        ];
    }
}
