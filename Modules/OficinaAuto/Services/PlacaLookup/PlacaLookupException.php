<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services\PlacaLookup;

use RuntimeException;

/**
 * Falha na consulta de placa por config ausente ou erro de comunicação com o
 * fornecedor. Distingue-se de "placa não encontrada" (que é `null`, não exceção).
 *
 * A mensagem NUNCA deve carregar a placa em claro (PII) — quem loga redaciona
 * via PiiRedactor (Anti-hook charter v2).
 */
final class PlacaLookupException extends RuntimeException
{
    public static function notConfigured(string $driver): self
    {
        return new self("Driver de consulta de placa '{$driver}' não está configurado (verifique oficina-auto.placa_lookup).");
    }

    public static function providerFailed(int $status): self
    {
        return new self("Fornecedor de consulta de placa respondeu com status {$status}.");
    }

    public static function unreachable(string $reason): self
    {
        return new self("Fornecedor de consulta de placa indisponível: {$reason}.");
    }
}
