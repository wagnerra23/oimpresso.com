<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Canais;

/**
 * Mensagem recebida de um canal NÃO-WhatsApp (Instagram Direct, Messenger, e-mail),
 * já traduzida do payload do provedor para o vocabulário da Caixa Unificada
 * (US-WA-329 · ADR 0135 Fase 1/2).
 *
 * É o contrato entre o normalizador de cada provedor e o `RecebimentoNoCanal`:
 * o normalizador sabe ler o payload; o recebimento sabe gravar na mesma
 * `conversations`/`messages` que o WhatsApp usa. Nenhum dos dois conhece o outro.
 *
 * `remetenteExterno` é o id do cliente NAQUELE canal (IGSID, PSID, endereço de
 * e-mail) — vira `conversations.customer_external_id`, a chave da conversa.
 */
final class MensagemRecebida
{
    public function __construct(
        public readonly string $remetenteExterno,
        public readonly string $idNoProvedor,
        public readonly ?string $texto,
        public readonly string $tipo = 'text',
        public readonly ?string $nomeDoContato = null,
        public readonly bool $enviadaPorNos = false,
        public readonly ?string $assunto = null,
        public readonly ?string $urlDaMidia = null,
        public readonly ?string $mimeDaMidia = null,
        public readonly ?int $enviadaEm = null,
        public readonly array $payloadBruto = [],
    ) {
    }
}
