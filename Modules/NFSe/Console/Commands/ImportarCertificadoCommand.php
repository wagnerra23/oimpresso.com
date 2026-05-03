<?php

namespace Modules\NFSe\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\NFSe\Models\NfseCertificado;
use Modules\NFSe\Models\NfseProviderConfig;

/**
 * Importa certificado A1 (.pfx) para o cofre criptografado em nfe_certificados.
 *
 * Uso:
 *   php artisan nfse:importar-cert --pfx=/caminho/cert.pfx --senha=SUA_SENHA --business=1
 *
 * O arquivo PFX nunca sai do servidor — é lido localmente, criptografado (AES-256)
 * e salvo no banco. A senha de transporte e o cert em disco devem ser apagados após
 * a importação bem-sucedida.
 */
class ImportarCertificadoCommand extends Command
{
    protected $signature = 'nfse:importar-cert
                            {--pfx= : Caminho absoluto para o arquivo .pfx}
                            {--senha= : Senha do certificado PFX}
                            {--business=1 : ID do business (padrão: 1)}';

    protected $description = 'Importa certificado A1 (.pfx) para o banco de dados criptografado';

    public function handle(): int
    {
        $pfxPath   = $this->option('pfx');
        $senha     = $this->option('senha');
        $businessId = (int) $this->option('business');

        if (! $pfxPath || ! $senha) {
            $this->error('Informe --pfx e --senha.');
            return self::FAILURE;
        }

        if (! file_exists($pfxPath)) {
            $this->error("Arquivo não encontrado: {$pfxPath}");
            return self::FAILURE;
        }

        $pfxContent = file_get_contents($pfxPath);

        // Valida senha + extrai dados do cert
        $certs = [];
        if (! openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            $this->error('Senha incorreta ou arquivo PFX inválido.');
            return self::FAILURE;
        }

        $parsed     = openssl_x509_parse($certs['cert']);
        $validoAte  = Carbon::createFromTimestamp($parsed['validTo_time_t']);
        $cn         = $parsed['subject']['CN'] ?? '';

        // Extrai CNPJ do CN (formato "NOME:CNPJ")
        $titularCnpj = null;
        $titularNome = $cn;
        if (str_contains($cn, ':')) {
            [$titularNome, $titularCnpj] = explode(':', $cn, 2);
        }

        $this->info("Certificado: {$cn}");
        $this->info("Válido até: {$validoAte->format('d/m/Y')}");

        if ($validoAte->isPast()) {
            $this->error('Certificado EXPIRADO. Importe um cert válido.');
            return self::FAILURE;
        }

        $certReg = NfseCertificado::uploadCert($businessId, $pfxContent, $senha);

        // Vincula à config do provider
        NfseProviderConfig::where('business_id', $businessId)
            ->update(['cert_id' => $certReg->id, 'updated_at' => now()]);

        $this->info("Certificado importado com sucesso. ID: {$certReg->id}");
        $this->info("CNPJ: {$titularCnpj} | Nome: {$titularNome}");
        $this->warn('Recomendado: apague o .pfx do disco após confirmar emissão em homologação.');

        return self::SUCCESS;
    }
}
