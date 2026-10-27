<?php

declare(strict_types=1);

namespace Modules\Fiscal\Services\Sped;

use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Support\CnpjDoEmitente;

/**
 * Leitura das fontes do emitente, comum aos geradores de SPED (EFD-ICMS/IPI e EFD-Contribuições).
 *
 * Os três métodos são `protected` para o teste trocar só a leitura e rodar o resto do gerador sem
 * banco. As fontes são as do grupo `emit` da NF-e — ver `EmitenteSped`.
 */
trait CarregaEmitente
{
    protected function emitente(object $business, int $businessId): EmitenteSped
    {
        return EmitenteSped::de(
            $business,
            $this->resolverCnpj($businessId),
            $this->carregarUfDoEstabelecimento($businessId),
            $this->carregarCodigoMunicipio($business),
        );
    }

    /** Mesmo dono do CNPJ que a manifestação usa (certificado → business.cnpj → tax_number_1). */
    protected function resolverCnpj(int $businessId): ?string
    {
        return CnpjDoEmitente::resolver($businessId);
    }

    /** UF crua da primeira location — a mesma que `NfeService::resolverUF` lê para a NF-e. */
    protected function carregarUfDoEstabelecimento(int $businessId): ?string
    {
        return DB::table('business_locations')->where('business_id', $businessId)->orderBy('id')->value('state');
    }

    /** IBGE do município pela mesma fonte do `cMun` da NF-e (`cidades.officeimpresso_codigo`). */
    protected function carregarCodigoMunicipio(object $business): ?string
    {
        if (empty($business->cidade_id)) {
            return null;
        }

        return DB::table('cidades')->where('id', $business->cidade_id)->value('officeimpresso_codigo');
    }
}
