<?php

return [
    'name' => 'Arquivos',

    /**
     * Disk default pra arquivos comuns: 'arquivos' (storage/app/arquivos, fora do webroot).
     *
     * Era 'local' até 2026-11-18. Neste app o disco 'local' tem raiz em public/uploads, a pasta
     * servida pela web, e a produção não define ARQUIVOS_DISK_DEFAULT: medido no servidor em
     * 2026-11-17, config('arquivos.disk_default') = 'local'. O próximo anexo não-sensível iria
     * para a pasta pública, sem a assinatura do DownloadController. As linhas já gravadas
     * guardam o próprio disco na coluna `disk`, então a troca só vale para upload novo.
     * O destino da ADR 0214 (MinIO) segue pendente; este é o passo intermediário privado.
     */
    'disk_default' => env('ARQUIVOS_DISK_DEFAULT', 'arquivos'),

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
     * Tipos aceitos no upload compartilhado (ArquivosService::attach/attachNoNegocio), pelo
     * MIME detectado no conteúdo. É a UNIÃO do que os consumidores aceitam hoje (medido em
     * 2026-11-16): Fisio (foto, PDF, MP4), Associações (foto, PDF), OficinaAuto (foto com
     * HEIC/HEIF, PNG da assinatura), app das lojas (foto, PDF), NFe (XML) e anexos de
     * escritório. Cada consumidor continua com a lista própria, mais estreita; esta é a rede
     * de baixo, para quem chamar o serviço sem validar.
     */
    'upload_mimes' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif',
        'application/pdf',
        'video/mp4', 'application/mp4',
        'application/xml', 'text/xml',
        'application/zip', 'application/x-zip-compressed',
        'text/csv', 'text/plain',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
    ],

    /** Extensões recusadas mesmo que o conteúdo pareça de um tipo aceito (código executável). */
    'upload_extensoes_bloqueadas' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar',
        'exe', 'com', 'bat', 'cmd', 'sh', 'ps1', 'vbs', 'jar', 'js', 'mjs',
        'html', 'htm', 'svg', 'py', 'pl', 'cgi',
    ],

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
