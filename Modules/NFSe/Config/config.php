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
        'homologacao' => 'https://sefin.producaorestrita.nfse.gov.br',
        'producao'    => 'https://sefin.nfse.gov.br',
    ],

    /**
     * Código IBGE do município padrão (Tubarão-SC = 4218707)
     */
    'municipio_ibge_default' => env('NFSE_MUNICIPIO_IBGE', '4218707'),
];
