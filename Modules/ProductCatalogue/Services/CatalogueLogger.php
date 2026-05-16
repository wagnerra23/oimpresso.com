<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Services;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * Wrapper de log pro catálogo público — Wave 11 D7 LGPD (2026-05-16).
 *
 * Catálogo público é READ-ONLY e não captura PII de visitantes por design.
 * Porém, em logs de erro podem aparecer:
 *  - URLs com query params contendo identificadores (CPF/CNPJ em parâmetros customizados)
 *  - Mensagens de exception com payload do business (telefone, email do dono)
 *  - User-Agent ou referer de redes com email em parâmetro
 *
 * Estratégia: TODO log de erro/warning do módulo passa por PiiRedactor antes
 * de gravar — placeholder [REDACTED:CPF|CNPJ|EMAIL|PHONE|CEP] no log final.
 *
 * Embasamento:
 *  - LGPD Art. 7º (tratamento legítimo) + Art. 16 (eliminação após uso)
 *  - ADR 0094 §4 (Constituição v2 — Compliance LGPD)
 *  - ADR 0154 (PiiRedactor aplicação módulos)
 *
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */
final class CatalogueLogger
{
    /**
     * Loga erro com PII redacionada.
     *
     * @param  string  $message  Mensagem livre (será redacionada)
     * @param  array<string, mixed>  $context  Contexto estruturado (strings serão redacionadas)
     */
    public static function error(string $message, array $context = []): void
    {
        Log::error(
            PiiRedactor::redactString($message),
            self::redactContext($context)
        );
    }

    /**
     * Loga warning com PII redacionada.
     *
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::warning(
            PiiRedactor::redactString($message),
            self::redactContext($context)
        );
    }

    /**
     * Loga info com PII redacionada (pra trace de catálogo público acessado).
     *
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info(
            PiiRedactor::redactString($message),
            self::redactContext($context)
        );
    }

    /**
     * Aplica PiiRedactor recursivo em values string do context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function redactContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $context[$key] = PiiRedactor::redactString($value);
            } elseif (is_array($value)) {
                $context[$key] = self::redactContext($value);
            }
        }

        return $context;
    }
}
