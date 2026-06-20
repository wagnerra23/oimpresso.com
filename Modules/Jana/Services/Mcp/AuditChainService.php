<?php

namespace Modules\Jana\Services\Mcp;

use Illuminate\Support\Carbon;
use Modules\Jana\Entities\Mcp\McpAuditLog;

/**
 * AuditChainService — hash-chain SHA-256 tamper-evident pro mcp_audit_log (ADR 0294).
 *
 * Cadeia GLOBAL unica (todo o audit log, cross-tenant) — detecta adulteracao
 * inclusive exclusao de linha entre tenants. Padrao transplantado de
 * Modules/Ponto/Services/MarcacaoService (Portaria 671/2021), provado.
 *
 * FAILSAFE: payloadCanonico()/hash() NUNCA lancam (campo faltando -> ''). Os
 * call sites do audit sao try/catch best-effort; se o hash estourasse, o audit
 * sumiria silencioso — anti-padrao que a ADR 0294 proibe explicitamente.
 */
class AuditChainService
{
    public const ALGO = 'sha256';

    /**
     * String canonica deterministica dos campos FORENSES (identidade do evento).
     * Exclui id/created_at (gerados pelo banco) e custo/tokens/duration (volateis,
     * nao definem quem-acessou-o-que-quando). Ordem fixa, failsafe (faltando -> '').
     */
    public static function payloadCanonico(array $d): string
    {
        $ts = $d['ts'] ?? null;
        if ($ts instanceof Carbon) {
            $ts = $ts->format('Y-m-d H:i:s');
        } elseif (is_string($ts) && $ts !== '') {
            try {
                $ts = Carbon::parse($ts)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                // mantem a string original se nao parseavel
            }
        }

        $partes = [
            $d['request_id']       ?? '',
            $d['user_id']          ?? '',
            $d['business_id']      ?? '',
            $ts                    ?? '',
            $d['endpoint']         ?? '',
            $d['tool_or_resource'] ?? '',
            $d['status']           ?? '',
            $d['error_code']       ?? '',
            $d['mcp_token_id']     ?? '',
            $d['hash_anterior']    ?? '',
        ];

        return implode('|', array_map(static fn ($v) => (string) ($v ?? ''), $partes));
    }

    /**
     * Hash da linha = H(payloadCanonico com hash_anterior injetado). FAILSAFE.
     */
    public static function hash(array $d, ?string $hashAnterior): string
    {
        $d['hash_anterior'] = $hashAnterior ?? '';
        try {
            return hash(self::ALGO, self::payloadCanonico($d));
        } catch (\Throwable $e) {
            // FAILSAFE absoluto: jamais deixar o audit sumir por erro de hash.
            return hash(self::ALGO, ($hashAnterior ?? '') . '|erro-hash');
        }
    }

    /**
     * Verifica uma sequencia JA ORDENADA por id asc — LOGICA PURA (sem DB).
     * Tolera prefixo legado (hash null, linhas pre-migration 0294): pula ate a
     * primeira linha com hash e ancora a cadeia dali.
     *
     * @param  iterable<array|McpAuditLog>  $rows
     * @return array{ok: bool, quebrados: array<int, array{id: mixed, motivo: string}>}
     */
    public static function verificarCadeia(iterable $rows): array
    {
        $quebrados = [];
        $ultimoHash = null;
        $iniciado = false;

        foreach ($rows as $row) {
            $d = $row instanceof McpAuditLog ? $row->getAttributes() : (array) $row;
            $hash = $d['hash'] ?? null;

            // Prefixo legado (pre-0294): hash null. So aceitavel ANTES da cadeia comecar.
            if ($hash === null || $hash === '') {
                if ($iniciado) {
                    $quebrados[] = [
                        'id'     => $d['id'] ?? null,
                        'motivo' => 'linha sem hash no meio da cadeia (esperado so no prefixo legado)',
                    ];
                }
                continue;
            }

            if ($iniciado && (($d['hash_anterior'] ?? null) !== $ultimoHash)) {
                $quebrados[] = [
                    'id'     => $d['id'] ?? null,
                    'motivo' => 'hash_anterior nao bate com o hash da linha N-1 (elo quebrado / linha removida)',
                ];
            }

            $esperado = self::hash($d, $d['hash_anterior'] ?? null);
            if ($esperado !== $hash) {
                $quebrados[] = [
                    'id'     => $d['id'] ?? null,
                    'motivo' => 'hash recalculado diverge do armazenado (payload adulterado)',
                ];
            }

            $iniciado = true;
            $ultimoHash = $hash;
        }

        return ['ok' => empty($quebrados), 'quebrados' => $quebrados];
    }

    /**
     * Verifica a cadeia GLOBAL inteira no banco (cross-tenant, SUPERADMIN).
     * withoutGlobalScopes: sem isto o global scope (ADR 0093) filtra por tenant
     * e a cadeia quebra entre business. lazy() = memory-safe (chunk interno).
     */
    public static function verificarIntegridade(): array
    {
        return self::verificarCadeia(
            McpAuditLog::withoutGlobalScopes()->orderBy('id')->lazy(500)
        );
    }
}
