<?php

namespace Modules\NFSe\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NFSe\Models\NfseProviderConfig;

/**
 * Importa certificado A1 (.pfx) para o cofre criptografado em nfe_certificados.
 *
 * Delegado ao CertificadoService (NfeBrasil) — schema unificado após
 * migration 2026_05_07_210000.
 *
 * Uso:
 *   php artisan nfse:importar-cert --pfx=/caminho/cert.pfx --senha=SUA_SENHA --business=1
 */
class ImportarCertificadoCommand extends Command
{
    protected $signature = 'nfse:importar-cert
                            {--pfx= : Caminho absoluto para o arquivo .pfx}
                            {--senha= : Senha do certificado PFX}
                            {--business=1 : ID do business (padrão: 1)}';

    protected $description = 'Importa certificado A1 (.pfx) para o banco de dados criptografado';

    public function handle(CertificadoService $service): int
    {
        $pfxPath    = $this->option('pfx');
        $senha      = $this->option('senha');
        $businessId = (int) $this->option('business');

        if (! $pfxPath || ! $senha) {
            $this->error('Informe --pfx e --senha.');
            return self::FAILURE;
        }

        if (! file_exists($pfxPath)) {
            $this->error("Arquivo não encontrado: {$pfxPath}");
            return self::FAILURE;
        }

        try {
            $pfxBase64 = base64_encode(file_get_contents($pfxPath));
            $cert      = $service->salvar($businessId, $pfxBase64, $senha);

            // Vincula à config do provider NFSe se existir
            NfseProviderConfig::where('business_id', $businessId)
                ->update(['cert_id' => $cert->id, 'updated_at' => now()]);

            $this->info("Certificado importado com sucesso. ID: {$cert->id}");
            $this->info("CNPJ: {$cert->cnpj_titular} | Válido até: {$cert->valido_ate->format('d/m/Y')}");
            $this->warn('Recomendado: apague o .pfx do disco após confirmar emissão em homologação.');

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error('Certificado inválido: ' . $e->getMessage());
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Erro ao salvar certificado: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
