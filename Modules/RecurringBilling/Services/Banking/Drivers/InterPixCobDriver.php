<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services\Banking\Drivers;

use Modules\RecurringBilling\Dto\PixCobResult;
use Modules\RecurringBilling\Services\Banking\InterBankingClient;

/**
 * Driver PIX cob imediata Inter — adapta `InterBankingClient` pra criar
 * QR Code dinâmico de cobrança PIX.
 *
 * Separado do `InterDriver` (boleto) — SoC ADR 0094 §5: cob PIX imediata
 * usa Inter API v3 (cobranca v3), endpoints diferentes do boleto cobrança.
 *
 * @see US-RB-047
 */
class InterPixCobDriver
{
    public function __construct(
        private readonly InterBankingClient $client,
        private readonly string $chavePix,
    ) {}

    /**
     * Cria cob imediata. `$txid` deve ter 26-35 alfanuméricos.
     *
     * @param  array{cpf?: string, cnpj?: string, nome?: string}  $devedor
     */
    public function criarCobImediata(
        string $txid,
        float $valor,
        array $devedor = [],
        ?string $solicitacaoPagador = null,
        int $expiracaoSegundos = 3600,
    ): PixCobResult {
        $body = [
            'calendario' => ['expiracao' => $expiracaoSegundos],
            'valor'      => ['original' => number_format($valor, 2, '.', '')],
            'chave'      => $this->chavePix,
        ];

        if (! empty($devedor)) {
            $body['devedor'] = array_filter([
                'cpf'   => $devedor['cpf']   ?? null,
                'cnpj'  => $devedor['cnpj']  ?? null,
                'nome'  => $devedor['nome']  ?? null,
            ], fn ($v) => $v !== null);
        }

        if ($solicitacaoPagador !== null) {
            $body['solicitacaoPagador'] = mb_substr($solicitacaoPagador, 0, 140);
        }

        $response = $this->client->criarCobImediata($txid, $body);
        $qrcode = $this->client->getQrCodeBase64($txid);

        return new PixCobResult(
            txid:               (string) ($response['txid'] ?? $txid),
            status:             (string) ($response['status'] ?? 'ATIVA'),
            valor:              (float)  ($response['valor']['original'] ?? $valor),
            pixCopiaECola:      (string) ($response['pixCopiaECola'] ?? ''),
            qrcodeBase64:       $qrcode,
            expiracaoSegundos:  (int)    ($response['calendario']['expiracao'] ?? $expiracaoSegundos),
            raw:                $response,
        );
    }
}
