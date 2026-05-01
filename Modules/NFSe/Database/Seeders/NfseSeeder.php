<?php

namespace Modules\NFSe\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NfseSeeder extends Seeder
{
    public function run(): void
    {
        // business_id=1 = oimpresso (empresa Wagner, Tubarão-SC)
        // Aguarda cert A1 para preencher nfe_certificados.
        // Dados fiscais: IBGE 4218707 (Tubarão), LC 116 1.05 (licenciamento).
        DB::table('nfse_provider_configs')->updateOrInsert(
            ['business_id' => 1, 'municipio_codigo_ibge' => '4218707'],
            [
                'provider'          => 'sn_nfse_federal',
                'serie_default'     => 'RPS',
                'cnae'              => '6201-5/00',
                'lc116_codigo_default' => '1.05',
                'aliquota_iss'      => null, // pendente confirmação com contador (tubarao.sc.gov.br)
                'ambiente'          => 'homologacao',
                'cert_id'           => null, // preencher após upload do cert A1
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );
    }
}
