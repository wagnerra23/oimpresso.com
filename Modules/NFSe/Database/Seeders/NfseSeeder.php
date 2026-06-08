<?php

namespace Modules\NFSe\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NfseSeeder extends Seeder
{
    public function run(): void
    {
        // business_id=1 = oimpresso (WR2 Desenvolvimento de Software LTDA)
        // CNPJ: 36.613.150/0001-18 · Tubarão-SC IBGE 4218707
        // Cert A1: D:\Administrativo\EMPRESA\CERTIFICADOS DIGITAIS\2025\WR2 DESENVOLVIMENTO.pfx
        // Validade cert: 2026-08-06 (importar via `php artisan nfse:importar-cert`)
        // Alíquota ISS: 2% provisório (mínimo federal LC 116) — confirmar com contador
        DB::table('nfse_provider_configs')->updateOrInsert(
            ['business_id' => 1],
            [
                'provider'             => 'sn_nfse_federal',
                'prestador_cnpj'       => '36613150000118',
                'prestador_im'         => null, // Inscrição Municipal — Wagner confirma com prefeitura
                'municipio_codigo_ibge' => '4218707',
                'serie_default'        => 'RPS',
                'cnae'                 => '6201-5/00',
                'lc116_codigo_default' => '1.05',
                'aliquota_iss'         => 0.0500, // 5% — padrão SC para TI (confirmar via fazenda@tubarao.sc.gov.br)
                'ambiente'             => 'homologacao',
                'cert_id'              => null, // preencher após: php artisan nfse:importar-cert
                'created_at'           => now(),
                'updated_at'           => now(),
            ]
        );
    }
}
