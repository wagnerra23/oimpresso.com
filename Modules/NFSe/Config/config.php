<?php

return [
    'name' => 'NFSe',
    'module_version' => '0.1.0',

    /**
     * Ambiente SN-NFSe: homologacao | producao
     * Sobrescrito por NFSE_AMBIENTE no .env
     */
    'ambiente' => env('NFSE_AMBIENTE', 'homologacao'),

    /**
     * Caminho do certificado A1 (.pfx) — relativo a storage_path()
     * Ex.: storage/certs/oimpresso.pfx
     */
    'cert_path' => env('NFSE_CERT_PATH', ''),

    /**
     * Senha do certificado A1
     */
    'cert_senha' => env('NFSE_CERT_SENHA', ''),

    /**
     * Endpoints do Sistema Nacional NFSe
     * Ref: https://www.gov.br/nfse (documentação técnica)
     */
    'endpoints' => [
        // Base da API do Emissor Público Nacional; o adapter acrescenta /nfse (R-NFSE-005).
        'homologacao' => 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional',
        'producao'    => 'https://sefin.nfse.gov.br/SefinNacional',
    ],

    /**
     * Algoritmo da assinatura XMLDSig da DPS: 'sha256' (padrão) ou 'sha1'. O XSD
     * oficial não restringe; se a 1ª emissão em homologação mostrar que a Sefin só
     * aceita SHA-1, troque o valor aqui (R-NFSE-005). Sem env(): o Larastan proíbe
     * env() fora de config/ e a ADR 0409 não deixa crescer o baseline.
     */
    'assinatura_algoritmo' => 'sha256',

    /**
     * Código IBGE do município padrão (Tubarão-SC = 4218707)
     */
    'municipio_ibge_default' => env('NFSE_MUNICIPIO_IBGE', '4218707'),
];
