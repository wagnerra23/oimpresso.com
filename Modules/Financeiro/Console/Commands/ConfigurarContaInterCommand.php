<?php

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Modules\Financeiro\Models\ContaBancaria;

/**
 * Wizard CLI pra preencher os campos da Inter API numa conta bancária.
 *
 * Cenário: dev faz upload do cert/key via SFTP/SSH, depois roda esse comando
 * pra apontar client_id/client_secret/paths sem precisar mexer no DB direto
 * ou abrir a Sheet web.
 *
 * Uso:
 *   php artisan financeiro:inter:configurar-conta --conta=1
 *   php artisan financeiro:inter:configurar-conta --conta=1 \
 *       --client-id=abc --client-secret=xyz \
 *       --cert=inter/cert.crt --chave=inter/cert.key
 *
 * Paths --cert e --chave são RELATIVOS a storage/app/private/. Coloque
 * os arquivos lá via SFTP antes de rodar:
 *   storage/app/private/inter/cert.crt
 *   storage/app/private/inter/cert.key
 *
 * IMPORTANTE: NÃO use storage/app/ direto nem public/uploads/ — esses são
 * servidos pela web. /private/ está fora do document root.
 */
class ConfigurarContaInterCommand extends Command
{
    protected $signature = 'financeiro:inter:configurar-conta
                            {--conta= : ID de fin_contas_bancarias}
                            {--client-id= : Client ID OAuth do Inter}
                            {--client-secret= : Client Secret OAuth do Inter}
                            {--cert= : Path do certificado (.crt) relativo a storage/app}
                            {--chave= : Path da chave privada (.key) relativo a storage/app}
                            {--cert-senha= : Senha do certificado (se houver)}';

    protected $description = 'Configura credenciais e certificado Inter API numa conta bancária existente.';

    public function handle(): int
    {
        $contaId = (int) $this->option('conta');
        if (! $contaId) {
            $this->error('--conta=<id> é obrigatório.');

            return self::FAILURE;
        }

        $conta = ContaBancaria::find($contaId);
        if (! $conta) {
            $this->error("ContaBancaria {$contaId} não encontrada.");

            return self::FAILURE;
        }

        if ($conta->banco_codigo !== '077') {
            $this->error("Conta {$contaId} é banco {$conta->banco_codigo}, esperado 077 (Inter).");

            return self::FAILURE;
        }

        $clientId = $this->option('client-id') ?: $this->secret('Client ID OAuth do Inter');
        $clientSecret = $this->option('client-secret') ?: $this->secret('Client Secret OAuth do Inter');
        $cert = $this->option('cert') ?: $this->ask('Path do .crt (relativo a storage/app/private/)', 'inter/cert.crt');
        $chave = $this->option('chave') ?: $this->ask('Path do .key (relativo a storage/app/private/)', 'inter/cert.key');
        $certSenha = $this->option('cert-senha');

        $base = storage_path('app/private/');
        if (! file_exists($base . $cert)) {
            $this->error("Arquivo não encontrado: storage/app/private/{$cert}. Faça upload via SFTP primeiro.");

            return self::FAILURE;
        }
        if (! file_exists($base . $chave)) {
            $this->error("Arquivo não encontrado: storage/app/private/{$chave}. Faça upload via SFTP primeiro.");

            return self::FAILURE;
        }

        $conta->inter_client_id_encrypted = $clientId;
        $conta->inter_client_secret_encrypted = $clientSecret;
        $conta->certificado_path = $cert;
        $conta->certificado_chave_path = $chave;
        if ($certSenha !== null) {
            $conta->certificado_password_encrypted = $certSenha;
        }
        $conta->save();

        $this->info("Conta {$contaId} configurada pra Inter API:");
        $this->line("  client_id        : OK (encriptado)");
        $this->line("  client_secret    : OK (encriptado)");
        $this->line("  certificado      : storage/app/private/{$cert}");
        $this->line("  certificado_chave: storage/app/private/{$chave}");
        $this->line('');
        $this->warn('Próximo passo: php artisan financeiro:inter:registrar-webhook --conta=' . $contaId);

        return self::SUCCESS;
    }
}
