<?php

return [
    'name' => 'Arquivos',

    /**
     * Disk default pra arquivos comuns. Em CT 100, mountar volume
     * /var/lib/oimpresso-arquivos. Em local dev, usar 'local' até
     * Sprint 1 dia 4 quando US-PRE-ARQ-004 mount for feito.
     */
    'disk_default' => env('ARQUIVOS_DISK_DEFAULT', 'local'),

    /**
     * Disk pra bucket=sensitive. Encryption-at-rest mandatório (ADR 0123 §3).
     *
     * Decisão Wagner 2026-05-10: VaultEncryptionService usa Crypt::encryptString
     * (Laravel native, APP_KEY-backed AES-256-CBC) em vez de league/flysystem-encrypted.
     * Trade-off: arquivos vault NÃO podem ser servidos via Storage::url direto —
     * sempre passar pelo DownloadController. Ver ADR 0123 §6.
     *
     * @see Modules/Arquivos/Services/VaultEncryptionService.php
     */
    'disk_vault' => env('ARQUIVOS_DISK_VAULT', 'vault'),

    /**
     * Cap upload por contexto. Sprint 1 default genérico; refinamento por
     * context (nfe-xml/ticket-anexo/repair-foto) entra Sprint 2.
     */
    'upload_max_mb' => env('ARQUIVOS_UPLOAD_MAX_MB', 50),

    /**
     * Cap máximo para VaultEncryptionService::putEncrypted e putFileEncrypted.
     *
     * Crypt::encryptString carrega o arquivo inteiro em memória. Acima deste
     * limite o processo pode entrar em OOM. Cap conservador de 50MB alinhado
     * com upload_max_mb acima.
     *
     * Para ajustar temporariamente defina ARQUIVOS_VAULT_MAX_FILE_SIZE_MB no .env.
     * NÃO pode ser desabilitado (<=0 → RuntimeException).
     *
     * Chunked encryption real (stream AES-256-CBC, sem OOM) planejada Sprint 2.
     * @see memory/decisions/0126-vault-chunked-encryption-sprint-2.md
     * @see Modules/Arquivos/Services/VaultEncryptionService.php
     */
    'vault_max_file_size_mb' => env('ARQUIVOS_VAULT_MAX_FILE_SIZE_MB', 50),

    /**
     * Retention default — dias após soft-delete pra hard-delete (job mensal).
     *
     * Default 90d (LGPD Art. 15-16 — eliminação tempestiva pós-finalidade).
     *
     * D7 LGPD (Wave 10 — 2026-05-16): documentação dos prazos canônicos
     * brasileiros por bucket/sub_destination (consultados pelo policy
     * RetentionCleanupCommand). Override por business via tabela
     * `arquivos.retention_days` (per-row) ou .env ARQUIVOS_RETENTION_DAYS.
     */
    'retention_days_default' => env('ARQUIVOS_RETENTION_DAYS', 90),

    /**
     * Retention policy por sub_destination/contexto.
     *
     * Base legal BR (compliance audit Wave 10 — 2026-05-16):
     *   - `nfe-xml` / `nfse-xml`: 5 anos (1825d) — Lei 8.846/94 Art. 23 +
     *     SINIEF 07/2005 Art. 8 (guarda do XML autorizado).
     *   - `documentos-fiscais`: 5 anos (1825d) — Lei 5.172/66 (CTN) Art. 173.
     *   - `contratos`: 5 anos (1825d) — CDC Art. 27 (prescrição reparação) +
     *     CPC Art. 205 (prescrição decenal — ajustar conforme natureza contrato).
     *   - `repair-foto` / `os-anexo`: 2 anos (730d) — pós-encerramento OS.
     *   - `ticket-anexo`: 1 ano (365d) — pós-fechamento ticket.
     *   - `default` (sub_destination não mapeado): herda retention_days_default.
     *
     * Sprint 7+ pode evoluir pra policy engine que aplica overlay em runtime
     * antes de invocar RetentionCleanupCommand. Hoje serve como source-of-truth
     * documental + valor preenchido no campo `arquivos.retention_days` no upload.
     */
    'retention_days_policy' => [
        'nfe-xml'              => 1825,
        'nfse-xml'             => 1825,
        'documentos-fiscais'   => 1825,
        'contratos'            => 1825,
        'repair-foto'          => 730,
        'os-anexo'             => 730,
        'ticket-anexo'         => 365,
        'default'              => env('ARQUIVOS_RETENTION_DAYS', 90),
    ],

    /**
     * Signed URL expiração (minutos). Default 60min (ADR 0123 §6).
     */
    'signed_url_expiration_minutes' => env('ARQUIVOS_SIGNED_URL_EXPIRATION', 60),
];
