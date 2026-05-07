<?php

namespace Modules\NFSe\Models;

use Modules\NfeBrasil\Models\NfeCertificado;

/**
 * Alias de NfeCertificado (schema unificado após migration 2026_05_07_210000).
 *
 * Mantido para compatibilidade com NfseProviderConfig::certificado() e
 * tests do módulo NFSe. Internamente usa a mesma tabela nfe_certificados
 * com o schema novo (uuid, cnpj_titular, encrypted_password).
 */
class NfseCertificado extends NfeCertificado
{
    /** Alias de isVencido() — compatibilidade com código NFSe existente. */
    public function isExpirado(): bool
    {
        return $this->isVencido();
    }
}
