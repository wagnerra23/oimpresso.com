<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\CustomerMemory\Sources;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * US-WA-VOZ-002 — Source Firebird via arquivo JSON pré-exportado.
 *
 * Workflow:
 *   1. Wagner (ou cron CT 100) roda `scripts/firebird/export-customers.py`
 *      conectando direto no Firebird WR Sistemas (local OU servidor cliente).
 *   2. Script gera JSON: `storage/app/firebird/customers-{biz}-{date}.json`
 *      formato `[{cliente_id, nome, fone1, fone2, email, bloqueado, ...}]`.
 *   3. Service carrega JSON em memória (índice por phone digits) — Hostinger
 *      sem Firebird driver, evita dependência PECL.
 *   4. `lookupByPhone()` normaliza dígitos + busca no índice (sufixo 8 dígitos
 *      mesmo pattern do ConversationContactLinker — colisão ~10^-8).
 *
 * Performance: arquivo até 50k clientes (~5MB) carrega em <100ms,
 * lookup O(1) por hash.
 *
 * Idempotente: re-carregar mesmo JSON não duplica nada. Caller usa.
 *
 * @see Modules/Whatsapp/Services/CustomerMemory/Sources/FirebirdLookupSourceContract.php
 * @see scripts/firebird/export-customers.py
 */
class JsonFileFirebirdSource implements FirebirdLookupSourceContract
{
    /** Cache em memória do índice phone_suffix → array de clientes. */
    protected ?array $indexByPhoneSuffix = null;
    protected ?array $rawCustomers = null;
    protected ?string $loadedFile = null;
    protected ?\DateTimeInterface $exportDate = null;

    /** Sufixo mínimo de dígitos pra index (alinhado ConversationContactLinker). */
    public const SUFFIX_LENGTH = 8;

    public function __construct(
        protected readonly string $jsonFilePath,
    ) {
    }

    public function lookupByPhone(string $phoneE164): array
    {
        $this->ensureLoaded();

        $digits = preg_replace('/\D+/', '', $phoneE164);
        if (mb_strlen((string) $digits) < self::SUFFIX_LENGTH) {
            return [];
        }

        $suffix = mb_substr($digits, -self::SUFFIX_LENGTH);
        return $this->indexByPhoneSuffix[$suffix] ?? [];
    }

    public function isHealthy(): bool
    {
        if (! file_exists($this->jsonFilePath)) {
            return false;
        }
        // Considera healthy se arquivo existe e tem <30 dias (avisa Wagner se velho)
        $mtime = filemtime($this->jsonFilePath);
        if ($mtime === false) {
            return false;
        }
        $ageSeconds = time() - $mtime;
        return $ageSeconds < (30 * 86400);
    }

    public function sourceLabel(): string
    {
        $this->ensureLoaded();
        $dateStr = $this->exportDate?->format('Y-m-d') ?? 'unknown';
        return "firebird_office_json:{$dateStr}";
    }

    /**
     * Lazy load do JSON. Indexação por sufixo 8 dígitos de fone1+fone2.
     */
    protected function ensureLoaded(): void
    {
        if ($this->indexByPhoneSuffix !== null) {
            return; // já carregado
        }

        if (! file_exists($this->jsonFilePath)) {
            Log::channel('single')->warning('[firebird_source.file_missing]', [
                'path' => $this->jsonFilePath,
            ]);
            $this->indexByPhoneSuffix = [];
            $this->rawCustomers = [];
            return;
        }

        try {
            $raw = file_get_contents($this->jsonFilePath);
            if ($raw === false) {
                throw new \RuntimeException("não consegui ler {$this->jsonFilePath}");
            }

            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw new \RuntimeException('JSON Firebird não é array');
            }

            // Suporta 2 formatos:
            //   A) array plain de customers (legacy)
            //   B) {meta:{exported_at,...}, customers:[...]}
            if (isset($decoded['customers'])) {
                $customers = $decoded['customers'];
                if (isset($decoded['meta']['exported_at'])) {
                    try {
                        $this->exportDate = new \DateTimeImmutable($decoded['meta']['exported_at']);
                    } catch (\Throwable) {
                    }
                }
            } else {
                $customers = $decoded;
            }

            $this->rawCustomers = $customers;
            $this->indexByPhoneSuffix = $this->buildIndex($customers);
            $this->loadedFile = $this->jsonFilePath;
        } catch (Throwable $e) {
            Log::channel('single')->error('[firebird_source.load_failed]', [
                'path' => $this->jsonFilePath,
                'error' => $e->getMessage(),
            ]);
            $this->indexByPhoneSuffix = [];
            $this->rawCustomers = [];
        }
    }

    /**
     * Constrói índice phone_suffix → list of customers.
     * Cada cliente pode aparecer em até 2 buckets (fone1 + fone2).
     */
    protected function buildIndex(array $customers): array
    {
        $index = [];
        foreach ($customers as $c) {
            $shape = $this->normalizeShape($c);
            foreach (['fone1', 'fone2'] as $field) {
                $phone = $shape[$field] ?? null;
                if ($phone === null) {
                    continue;
                }
                $digits = preg_replace('/\D+/', '', (string) $phone);
                if (mb_strlen((string) $digits) < self::SUFFIX_LENGTH) {
                    continue;
                }
                $suffix = mb_substr($digits, -self::SUFFIX_LENGTH);
                $index[$suffix][] = $shape;
            }
        }
        return $index;
    }

    /**
     * Normaliza shape esperado pelo contrato + handles legacy keys
     * (script Python pode exportar com nomes Firebird raw).
     */
    protected function normalizeShape(array $c): array
    {
        return [
            'cliente_id' => (int) ($c['cliente_id'] ?? $c['CODIGO'] ?? $c['codigo'] ?? 0),
            'nome' => (string) ($c['nome'] ?? $c['RAZAO_SOCIAL'] ?? $c['razao_social'] ?? $c['NOME'] ?? ''),
            'fone1' => $this->normalizeOptional($c['fone1'] ?? $c['FONE1'] ?? null),
            'fone2' => $this->normalizeOptional($c['fone2'] ?? $c['FONE2'] ?? null),
            'email' => $this->normalizeOptional($c['email'] ?? $c['EMAIL'] ?? null),
            'bloqueado' => $this->normalizeBoolean($c['bloqueado'] ?? $c['BLOQUEADO'] ?? null),
            'cpf_cnpj' => $this->normalizeOptional($c['cpf_cnpj'] ?? $c['CPF'] ?? $c['CNPJ'] ?? null),
            'cidade' => $this->normalizeOptional($c['cidade'] ?? $c['CIDADE'] ?? null),
            'data_cadastro' => $this->normalizeOptional($c['data_cadastro'] ?? $c['DATACADASTRO'] ?? null),
        ];
    }

    protected function normalizeOptional(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        return trim((string) $v);
    }

    protected function normalizeBoolean(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            return strtoupper(trim($v)) === 'S' || $v === '1' || strtolower($v) === 'true';
        }
        return $v === 1;
    }
}
