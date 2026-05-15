<?php

/**
 * _remote_install.php — Executado APENAS no Hostinger via SSH.
 * Lê JSON do path em $_ENV['INTER_JSON'], encripta secrets via Crypt::encryptString,
 * insere em rb_boleto_credentials e deleta o JSON do /tmp.
 *
 * Boot Laravel sem artisan — caminho absoluto pra vendor/ e bootstrap/.
 *
 * NÃO ROTACIONA NEM EDITA ESTE ARQUIVO LOCALMENTE — é payload SCP-only.
 * Eventos auditáveis vão pra stdout no formato OK:id=<n> ou ERR_*.
 */

declare(strict_types=1);

$jsonPath = $_ENV['INTER_JSON'] ?? getenv('INTER_JSON');
if (! $jsonPath || ! file_exists($jsonPath)) {
    fwrite(STDERR, "ERR_NO_FILE: INTER_JSON={$jsonPath}\n");
    exit(1);
}

if (filesize($jsonPath) > 64 * 1024) {
    fwrite(STDERR, "ERR_TOO_BIG: " . filesize($jsonPath) . "\n");
    exit(1);
}

$raw = file_get_contents($jsonPath);
$creds = json_decode($raw, true);
if (! is_array($creds)) {
    fwrite(STDERR, "ERR_BAD_JSON\n");
    exit(1);
}

// Boot Laravel
$appDir = '/home/u906587222/domains/oimpresso.com/public_html';
if (! file_exists($appDir . '/vendor/autoload.php')) {
    fwrite(STDERR, "ERR_NO_VENDOR: {$appDir}/vendor/autoload.php\n");
    exit(1);
}

require $appDir . '/vendor/autoload.php';
$app = require $appDir . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Crypt;
use Modules\RecurringBilling\Models\BoletoCredential;

$businessId  = (int) ($creds['_business_id'] ?? 0);
$ambiente    = (string) ($creds['_ambiente'] ?? '');
$nomeDisplay = (string) ($creds['_nome_display'] ?? '');

if (! in_array($businessId, [1, 4], true)) {
    fwrite(STDERR, "ERR_INVALID_BIZ: {$businessId}\n");
    @unlink($jsonPath);
    exit(2);
}
if (! in_array($ambiente, ['sandbox', 'production'], true)) {
    fwrite(STDERR, "ERR_INVALID_AMBIENTE: {$ambiente}\n");
    @unlink($jsonPath);
    exit(2);
}

// Idempotência: nunca sobrescreve credencial existente
$exists = BoletoCredential::where('business_id', $businessId)
    ->where('banco', 'inter')
    ->first();
if ($exists) {
    fwrite(STDOUT, "ERR_ALREADY_EXISTS:id={$exists->id}\n");
    @unlink($jsonPath);
    exit(3);
}

// Sanity dos campos obrigatórios (sem expor valores em log)
$required = ['client_id', 'client_secret', 'conta_corrente', 'webhook_secret', 'certificado_crt_b64', 'certificado_key_b64'];
foreach ($required as $f) {
    if (empty($creds[$f])) {
        fwrite(STDERR, "ERR_MISSING_FIELD:{$f}\n");
        @unlink($jsonPath);
        exit(2);
    }
}

try {
    $cred = BoletoCredential::create([
        'business_id'  => $businessId,
        'banco'        => 'inter',
        'ambiente'     => $ambiente,
        'ativo'        => true,
        'nome_display' => $nomeDisplay,
        'config_json'  => [
            'client_id'           => $creds['client_id'],
            'client_secret'       => Crypt::encryptString($creds['client_secret']),
            'conta_corrente'      => $creds['conta_corrente'],
            'certificado_crt_b64' => $creds['certificado_crt_b64'],
            'certificado_key_b64' => Crypt::encryptString($creds['certificado_key_b64']),
            'webhook_secret'      => $creds['webhook_secret'],
        ],
    ]);
    fwrite(STDOUT, "OK:id={$cred->id} biz={$businessId} banco=inter ambiente={$ambiente}\n");
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "ERR_INSERT:" . $e->getMessage() . "\n");
    exit(4);
} finally {
    @unlink($jsonPath);
}
