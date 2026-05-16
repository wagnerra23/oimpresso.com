<?php

namespace Modules\NFSe\Models;

use Modules\NfeBrasil\Models\NfeCertificado;

/**
 * Alias de NfeCertificado (schema unificado após migration 2026_05_07_210000).
 *
 * Mantido para compatibilidade com NfseProviderConfig::certificado() e
 * tests do módulo NFSe. Internamente usa a mesma tabela nfe_certificados
 * com o schema novo (uuid, cnpj_titular, encrypted_password).
 *
 * Wave 17 — Multi-tenant Tier 0 (ADR 0093):
 *
 * Esta classe HERDA `HasBusinessScope` (App\Concerns) do pai
 * `Modules\NfeBrasil\Models\NfeCertificado`. NÃO aplicar trait local
 * pra evitar duplicação de boot e double-scope. O scope `business_id`
 * global é ativado automaticamente em `NfseCertificado::query()`,
 * `NfseCertificado::all()` etc — comportamento idêntico ao pai.
 *
 * Isolamento cross-tenant coberto indiretamente por
 * `Modules\NfeBrasil\Tests` (certificado é o mesmo registro físico em
 * `nfe_certificados`). Cobertura específica NFSe via
 * `NfseProviderConfig::certificado()` belongsTo — herda o scope.
 *
 * @see Modules/NfeBrasil/Models/NfeCertificado.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class NfseCertificado extends NfeCertificado
{
    /** Alias de isVencido() — compatibilidade com código NFSe existente. */
    public function isExpirado(): bool
    {
        return $this->isVencido();
    }
}
