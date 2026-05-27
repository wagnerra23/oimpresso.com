<?php

declare(strict_types=1);

/**
 * Fixture: contract test do dominio Fiscal/Config + NfeBrasil/Configuracao
 * (tela unificada Fiscal/Config.tsx — Wagner 2026-05-27 consolidacao).
 *
 * 3 endpoints autosave-style do NfeBrasil que persistem `business` e
 * `nfe_business_configs`. Endpoints retornam RedirectResponse (302) — test
 * file faz POST + read-back via DB column lookup (pattern ServiceOrderEdit,
 * nao usa AutosaveContractRunner::run default).
 *
 * Endpoints cobertos (9 campos auto-verificados):
 *   1. POST /nfe-brasil/configuracao/certificado/ambiente → business.ambiente (ADR 0142)
 *   2. POST /nfe-brasil/tributacao/auto-emission/toggle   → nfe_business_configs.auto_emission_enabled (ADR 0093)
 *   3. POST /nfe-brasil/tributacao/config-default         → nfe_business_configs.{regime, tributacao_default JSON}
 *
 * EXCLUIDOS POR DESIGN (outros PRs):
 *   - POST /nfe-brasil/configuracao/certificado (upload .pfx multipart — fixture proprio)
 *   - POST /nfe-brasil/configuracao/certificado/testar (action ping SEFAZ, nao persiste)
 *   - POST /nfe-brasil/tributacao/templates/{slug}/aplicar (bulk mutate, tests dedicados)
 *   - CRUD /nfe-brasil/tributacao/regras (subdominio NCM — fixture proprio)
 *   - Import CSV (multipart + side-effects multi-row)
 *
 * EXCLUIDOS POR PII/SECRETS (Tier 0 — NUNCA em fixture/teste):
 *   - Senha .pfx (Laravel encrypt + MemCofre — nao DB plain)
 *   - CSC NFC-e + chave privada cert (Vaultwarden, nao DB)
 *
 * Schema fixture estendido (vs cliente_drawer.php):
 *   - 'read_back' => 'db'|'model'   — db raw DB::table; model usa Eloquent (casts)
 *   - 'table'     => 'business'     — tabela alvo do read-back
 *   - 'column'    => 'ambiente'     — coluna alvo (scalar)
 *   - 'json_path' => 'col.nested'   — quando dado vive dentro de JSON column
 *   - 'where'     => ['id' => '{business_id}']  — chave WHERE com placeholder
 *
 * Pattern referencia: AutosaveContractRunner (cliente_drawer) + ServiceOrderEdit
 * (inline custom loop pra endpoints redirect-based). ADR 0205.
 */

return [
    // 1. POST /nfe-brasil/configuracao/certificado/ambiente
    // Toggle SEFAZ 1=PRODUCAO / 2=HOMOLOGACAO. ADR 0142 tinyInteger 1|2.
    // Controller faz early-return se ambiente_novo === ambiente_antes — por isso
    // testamos OS DOIS valores sequencialmente (ambos round-trips com mudanca real).
    'ambiente_sefaz' => [
        'endpoint' => '/nfe-brasil/configuracao/certificado/ambiente',
        'method' => 'post',
        'expectStatus' => 302,
        'read_back' => 'db',
        'table' => 'business',
        'where' => ['id' => '{business_id}'],
        'fields' => [
            ['send' => 'ambiente', 'value' => 2, 'recv' => 'ambiente', 'column' => 'ambiente', 'match' => 'int'],
            ['send' => 'ambiente', 'value' => 1, 'recv' => 'ambiente', 'column' => 'ambiente', 'match' => 'int'],
        ],
    ],

    // 2. POST /nfe-brasil/tributacao/auto-emission/toggle
    // Per-business gate emissao automatica (ADR 0093 multi-tenant Tier 0).
    // Validator inline Request::boolean. Controller early-redirect se row nao
    // existe — test file seed baseline em beforeEach.
    'auto_emission_toggle' => [
        'endpoint' => '/nfe-brasil/tributacao/auto-emission/toggle',
        'method' => 'post',
        'expectStatus' => 302,
        'read_back' => 'db',
        'table' => 'nfe_business_configs',
        'where' => ['business_id' => '{business_id}'],
        'fields' => [
            ['send' => 'enabled', 'value' => true,  'recv' => 'auto_emission_enabled', 'column' => 'auto_emission_enabled', 'match' => 'bool'],
            ['send' => 'enabled', 'value' => false, 'recv' => 'auto_emission_enabled', 'column' => 'auto_emission_enabled', 'match' => 'bool'],
        ],
    ],

    // 3. POST /nfe-brasil/tributacao/config-default
    // Upsert Nivel 4 cascade tributario. Validator UpsertConfigDefaultRequest
    // exige TODOS campos juntos — baseFields garante payload valido pra cada
    // iteracao varia 1 campo. regime e coluna scalar; demais ficam dentro do
    // JSON tributacao_default (read-back via Eloquent cast => array).
    // NOTA: regime canon validator e 'simples' (nao 'simples_nacional' label UI).
    'config_default' => [
        'endpoint' => '/nfe-brasil/tributacao/config-default',
        'method' => 'post',
        'expectStatus' => 302,
        'read_back' => 'model',
        'table' => 'nfe_business_configs',
        'where' => ['business_id' => '{business_id}'],
        'baseFields' => [
            'regime' => 'simples',
            'ncm_default' => '49019900',
            'cfop_default' => '5102',
            'csosn' => '102',
            'aliquota_icms' => 0.18,
            'aliquota_pis' => 0.0165,
            'aliquota_cofins' => 0.076,
        ],
        'fields' => [
            ['send' => 'regime',         'value' => 'lucro_presumido', 'recv' => 'regime', 'column' => 'regime'],
            ['send' => 'ncm_default',    'value' => '85423100', 'recv' => 'tributacao_default.ncm_default',  'json_path' => 'tributacao_default.ncm_default'],
            ['send' => 'cfop_default',   'value' => '5405',     'recv' => 'tributacao_default.cfop_default', 'json_path' => 'tributacao_default.cfop_default'],
            ['send' => 'csosn',          'value' => '500',      'recv' => 'tributacao_default.csosn',        'json_path' => 'tributacao_default.csosn'],
            ['send' => 'aliquota_icms',  'value' => 0.12,       'recv' => 'tributacao_default.aliquota_icms', 'json_path' => 'tributacao_default.aliquota_icms', 'match' => 'float'],
        ],
    ],
];
