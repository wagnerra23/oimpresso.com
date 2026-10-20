<?php

declare(strict_types=1);

namespace App\Contracts\Atendimento;

/**
 * Pesquisa de satisfação por WhatsApp (US-SELL-071 · UC-FLX-25), vista pelo núcleo.
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\Whatsapp\Services\Csat\PesquisaSatisfacaoWhatsapp` (wrapper do
 * CsatDispatcher), ligado pelo WhatsappServiceProvider. Sem o módulo, a interface não está
 * ligada: quem consome confere `app()->bound(EnviaPesquisaSatisfacao::class)` e segue por e-mail.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é argumento explícito em todo método.
 */
interface EnviaPesquisaSatisfacao
{
    /**
     * Envia a pesquisa (nota 1-5) ao cliente na conversa dele nesta empresa.
     *
     * @return array{enviado: bool, motivo: ?string, referencia: ?int}
     *   `referencia` identifica a pesquisa no módulo, para ler a nota depois; `motivo` quando não saiu
     *   (ex.: `sem_conversa`, `canal_nao_suportado`, `falha_envio`).
     */
    public function enviar(int $businessId, int $contactId, int $transactionId, int $userId): array;

    /** Nota 1-5 respondida para a `referencia` devolvida por `enviar`, ou null se ainda não respondeu. */
    public function notaRespondida(int $businessId, int $referencia): ?int;
}
