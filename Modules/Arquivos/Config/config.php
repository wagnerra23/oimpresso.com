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
     * Atenção: Laravel Filesystem NÃO suporta encryption nativo — precisa
     * (a) league/flysystem-encrypted middleware OU (b) Crypt::encrypt antes
     * de Storage::put. Decisão Wagner antes de Sprint 1 dia 4.
     */
    'disk_vault' => env('ARQUIVOS_DISK_VAULT', 'local'),

    /**
     * Cap upload por contexto. Sprint 1 default genérico; refinamento por
     * context (nfe-xml/ticket-anexo/repair-foto) entra Sprint 2.
     */
    'upload_max_mb' => env('ARQUIVOS_UPLOAD_MAX_MB', 50),

    /**
     * Retention default — dias após soft-delete pra hard-delete (job mensal).
     * NULL = sem retenção explícita (LGPD compliance audit pendente).
     */
    'retention_days_default' => env('ARQUIVOS_RETENTION_DAYS', 90),

    /**
     * Signed URL expiração (minutos). Default 60min (ADR 0123 §6).
     */
    'signed_url_expiration_minutes' => env('ARQUIVOS_SIGNED_URL_EXPIRATION', 60),
];
