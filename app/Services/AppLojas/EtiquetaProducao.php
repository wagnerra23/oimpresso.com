<?php

declare(strict_types=1);

namespace App\Services\AppLojas;

/**
 * Código da etiqueta de produção lida pelo app (P8): `oimpresso:p:<business_hash>:<pedido_id>`.
 *
 * O `pedido_id` é o id da OS de comunicação visual (`comvis_os.id`), a mesma que o apontamento
 * aponta (`comvis_apontamentos.os_id`). O `business_hash` NÃO é o business_id: é um HMAC com a
 * APP_KEY, para que o QR não exponha nem permita adivinhar o id da empresa. Quem gera o QR no PDF
 * (PR 07 do playbook propostas-app) chama `codigo()`; quem lê chama `ler()`.
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-02-apontamento.md.
 */
final class EtiquetaProducao
{
    public const PREFIXO = 'oimpresso:p:';

    /** 12 hex = 48 bits: basta para não colidir entre empresas e cabe num QR pequeno. */
    private const TAMANHO_HASH = 12;

    public static function hashDaEmpresa(int $bizId): string
    {
        return substr(hash_hmac('sha256', 'etiqueta-producao:' . $bizId, (string) config('app.key')), 0, self::TAMANHO_HASH);
    }

    public static function codigo(int $bizId, int $pedidoId): string
    {
        return self::PREFIXO . self::hashDaEmpresa($bizId) . ':' . $pedidoId;
    }

    /**
     * Lê um código no formato do QR. Devolve null se não for o formato (o chamador decide se aceita
     * número digitado); `empresa` diz se o hash é o da empresa de quem leu.
     *
     * @return array{empresa: bool, pedido_id: int}|null
     */
    public static function ler(string $codigo, int $bizId): ?array
    {
        if (! preg_match('/^oimpresso:p:([0-9a-f]{' . self::TAMANHO_HASH . '}):([1-9][0-9]{0,18})$/', trim($codigo), $m)) {
            return null;
        }

        return [
            'empresa' => hash_equals(self::hashDaEmpresa($bizId), $m[1]),
            'pedido_id' => (int) $m[2],
        ];
    }
}
