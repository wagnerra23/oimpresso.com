<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Canais;

/**
 * Traduz o webhook do Meta Graph (Instagram Direct e Messenger) em `MensagemRecebida`
 * (US-WA-329 · ADR 0135 Fase 1).
 *
 * Os dois chegam no mesmo formato — `object` = `instagram` | `page`, e cada
 * `entry[].messaging[]` traz `sender.id`, `recipient.id`, `timestamp` (ms) e
 * `message { mid, text, attachments[], is_echo }`. Eventos sem `message`
 * (leitura, entrega, reação, postback) são ignorados aqui: nesta fatia só
 * conversa entra na caixa.
 *
 * `is_echo` = a mensagem foi enviada PELA conta da empresa (app da Meta ou o
 * próprio oimpresso): o cliente é o `recipient`, não o `sender`.
 *
 * Função pura, sem banco nem rede — o tenant é decidido por quem chama.
 */
final class NormalizadorMetaGraph
{
    private const TIPO_DO_ANEXO = [
        'image' => 'image',
        'video' => 'video',
        'audio' => 'audio',
        'file' => 'document',
    ];

    /**
     * @return list<array{conta: string, objeto: string, mensagem: MensagemRecebida}>
     */
    public static function normalizar(array $payload): array
    {
        $objeto = (string) ($payload['object'] ?? '');
        if (! in_array($objeto, ['instagram', 'page'], true)) {
            return [];
        }

        $saida = [];
        foreach ((array) ($payload['entry'] ?? []) as $entrada) {
            $conta = (string) ($entrada['id'] ?? '');
            foreach ((array) ($entrada['messaging'] ?? []) as $evento) {
                $msg = $evento['message'] ?? null;
                if (! is_array($msg) || empty($msg['mid']) || $conta === '') {
                    continue;
                }

                $eco = (bool) ($msg['is_echo'] ?? false);
                $cliente = (string) ($eco ? ($evento['recipient']['id'] ?? '') : ($evento['sender']['id'] ?? ''));
                if ($cliente === '') {
                    continue;
                }

                $anexo = $msg['attachments'][0] ?? null;
                $tipo = is_array($anexo) ? (self::TIPO_DO_ANEXO[$anexo['type'] ?? ''] ?? 'text') : 'text';
                $url = is_array($anexo) ? ($anexo['payload']['url'] ?? null) : null;
                $ms = isset($evento['timestamp']) ? (int) $evento['timestamp'] : null;

                $saida[] = [
                    'conta' => $conta,
                    'objeto' => $objeto,
                    'mensagem' => new MensagemRecebida(
                        remetenteExterno: $cliente,
                        idNoProvedor: (string) $msg['mid'],
                        texto: isset($msg['text']) ? (string) $msg['text'] : null,
                        tipo: $tipo,
                        enviadaPorNos: $eco,
                        urlDaMidia: is_string($url) ? $url : null,
                        enviadaEm: $ms !== null ? intdiv($ms, 1000) : null,
                        payloadBruto: $evento,
                    ),
                ];
            }
        }

        return $saida;
    }
}
