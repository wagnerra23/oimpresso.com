<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => public_path('uploads'),
            'throw' => false,
        ],

        // Certificados A1 NFe/NFSe — fora do webroot (US-NFE-041 security)
        'nfe_certs' => [
            'driver' => 'local',
            'root' => storage_path('app/nfe-certs'),
            'throw' => true,
        ],

        /*
         * Modules/Arquivos backbone — ADR 0123.
         *
         * Disk default pra anexos comuns (NFe XML, ticket attachment, foto OS, etc).
         * Em CT 100 prod: bind volume `/var/lib/oimpresso-arquivos`.
         * Em local dev: storage/app/arquivos (criado on-demand).
         * Swap futuro Fase 3 ([ADR 0123](memory/decisions/0123-modules-arquivos-backbone.md)):
         * S3-compatible (Backblaze/Wasabi) quando CT 100 disk passar 80%.
         */
        'arquivos' => [
            'driver' => 'local',
            'root'   => env('ARQUIVOS_DISK_ROOT', storage_path('app/arquivos')),
            'throw'  => false,
        ],

        /*
         * Disk vault — encryption-at-rest pra bucket=sensitive (.env/.pfx/.rdp/PII XML/etc).
         *
         * Agent C 2026-05-10 security review flagou que Laravel Filesystem NÃO suporta
         * encryption nativo. Sprint 1: armazena em pasta separada com permissions 0700 e
         * controle de acesso via signed URL + audit log. Sprint 2+ implementa middleware
         * `league/flysystem-encrypted` OU `Crypt::encrypt` antes de Storage::put.
         *
         * NUNCA expor publicamente. Acesso APENAS via Modules/Arquivos/Service signedUrl().
         */
        'vault' => [
            'driver' => 'local',
            'root'   => env('ARQUIVOS_VAULT_ROOT', storage_path('app/vault')),
            'throw'  => true,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        /*
         * ADR 0214 (aceita 2026-05-28) — Arquivos backbone S3 MinIO CT 100.
         *
         * Disk `arquivos-minio` é o backbone canon de qualquer anexo do
         * oimpresso após adoção `HasArquivos` trait pelos módulos
         * (Sprint 1 WhatsApp, Sprint 2+ Sells/Financeiro/Crm/etc).
         *
         * Endpoint: https://minio.oimpresso.com (Traefik proxy → CT 100 :9000)
         * Bucket: oimpresso-arquivos (criado 2026-05-28)
         * Auth: user MinIO `oimpresso-app` policy `arquivos-rw` (limit aos 2 buckets)
         *
         * Tier 0 (ADR 0093): path canon `biz=<businessId>/YYYY/MM/<md5>.<ext>`.
         * Multi-tenant via prefix path, NÃO bucket-per-business (simplifica
         * management; ACL aplicada via signed URL TTL no Controller que serve).
         *
         * Path-style endpoint OBRIGATÓRIO MinIO (não virtual-hosted como AWS).
         *
         * Signed URL TTL default 15min via Storage::temporaryUrl() —
         * suficiente pra render thumb em tela atendimento + previne
         * URL leak. Mídia grande: refresh on demand.
         */
        'arquivos-minio' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY'),
            'secret' => env('MINIO_SECRET_KEY'),
            'region' => env('MINIO_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET_ARQUIVOS', 'oimpresso-arquivos'),
            'endpoint' => env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'url' => env('MINIO_PUBLIC_URL'),
            'throw' => false,
        ],

        /*
         * Bucket dedicado pra sensitive (encrypted-at-rest via VaultEncryptionService).
         * ADR 0123 princípio 6 — upload .env/.pfx/.rdp/.pem/XML PII vai pra cá
         * automaticamente. Audit log obrigatório.
         */
        'arquivos-vault-minio' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY'),
            'secret' => env('MINIO_SECRET_KEY'),
            'region' => env('MINIO_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET_VAULT', 'oimpresso-arquivos-vault'),
            'endpoint' => env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'url' => env('MINIO_PUBLIC_URL'),
            'throw' => false,
        ],

        'dropbox' => [
            'driver' => 'dropbox',
            'authorization_token' => env('DROPBOX_ACCESS_TOKEN'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
