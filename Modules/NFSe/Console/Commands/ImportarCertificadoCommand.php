<?php

namespace Modules\NFSe\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\NfeBrasil\Services\CertificadoService;

/**
 * Importa certificado A1 (.pfx) para o cofre criptografado em nfe_certificados.
 *
 * Delegado ao CertificadoService (NfeBrasil) — schema unificado após
 * migration 2026_05_07_210000. Faz o mesmo que enviar o certificado na tela
 * /fiscal/config?tab=cert: o novo vira o ativo da empresa e vale para NF-e e NFS-e.
 *
 * Até 2026-11-18 o comando também gravava `nfse_provider_configs.cert_id`, e era o único
 * caminho que ligava um certificado à NFS-e. Desde então a NFS-e usa o certificado ativo
 * (CertificadoService::certificadoAtivo) e a coluna ficou sem leitor; não é mais gravada.
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

    protected $description = 'Importa certificado A1 (.pfx) como o certificado ativo da empresa (NF-e e NFS-e)';

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
