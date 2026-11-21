<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Webhook;

/**
 * Id de deduplicação de webhook quando o banco não manda id próprio do EVENTO.
 *
 * Auditoria PG 2.3: Sicoob, Inter boleto, C6 e BCB Pix caíam no identificador da
 * COBRANÇA (`nossoNumero`, `txid`, `transactionId`) como `gateway_event_id`. A
 * UNIQUE(business_id, gateway_key, gateway_event_id) então tratava como duplicata
 * qualquer segundo evento da mesma cobrança: a liquidação que chegava depois de
 * "registrada" ou "vencida" era descartada.
 *
 * Aqui o id da cobrança é composto com o nome do evento e as situações presentes
 * no payload. Reenvio idêntico gera o mesmo id (segue deduplicado); evento com
 * situação diferente gera id novo. Payload sem nome de evento nem situação
 * devolve o id da cobrança como estava.
 *
 * Só use para id que identifica a COBRANÇA. Id próprio do evento (`id`,
 * `eventId`) já é único por evento e não passa por aqui.
 */
final class WebhookEventId
{
    /** Caminhos (notação de ponto, `*` em lista) que carregam situação/ocorrência. */
    private const CAMPOS_SITUACAO = [
        'situacao',
        'situacaoBoleto',
        'codigoSituacao',
        'status',
        'tipoEvento',
        'ocorrencia',
        'codigoOcorrencia',
        'boleto.situacao',
        'boleto.situacaoBoleto',
        'cobranca.situacao',
        'cobranca.status',
        'data.status',
        'cobsr.*.status',
        'pix.*.endToEndId',
        'pix.*.devolucoes.*.status',
    ];

    /** Limite da coluna gateway_event_id (string 191). */
    private const MAX = 191;

    public static function daCobranca(string $idCobranca, string $eventName, array $payload): string
    {
        $partes = [];
        if ($eventName !== '' && $eventName !== 'unknown') {
            $partes[] = $eventName;
        }
        foreach (self::CAMPOS_SITUACAO as $caminho) {
            foreach (self::valores(data_get($payload, $caminho)) as $valor) {
                $partes[] = $valor;
            }
        }

        if ($partes === []) {
            return $idCobranca;
        }

        $id = $idCobranca . '|' . strtolower(implode('|', $partes));
        if (strlen($id) <= self::MAX) {
            return $id;
        }

        return substr($idCobranca, 0, self::MAX - 41) . '|' . sha1(strtolower(implode('|', $partes)));
    }

    /** @return list<string> */
    private static function valores(mixed $v): array
    {
        if (is_array($v)) {
            $out = [];
            array_walk_recursive($v, function ($x) use (&$out): void {
                if (is_scalar($x) && (string) $x !== '') {
                    $out[] = (string) $x;
                }
            });

            return $out;
        }

        return is_scalar($v) && (string) $v !== '' ? [(string) $v] : [];
    }
}
