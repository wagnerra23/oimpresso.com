<?php

declare(strict_types=1);

/**
 * Fixture: contract test do drawer 760 Cliente (ADR 0179).
 *
 * Cobre os 5 endpoints PATCH de autosave:
 *   - /cliente/{id}/identificacao  (8 campos)
 *   - /cliente/{id}/contato         (8 campos · aliases PT-BR -> EN)
 *   - /cliente/{id}/endereco        (7 campos)
 *   - /cliente/{id}/comercial       (5 campos)
 *   - /cliente/{id}/classificacao   (4 campos)
 *
 * Total: 32 campos auto-testados. Cobre TODOS os bugs descobertos em
 * 2026-05-27 sessão Wagner (aliases PT-BR silent no-op, coluna IE duplicada,
 * contact_status canon EN, campo `contato` órfão, etc).
 *
 * Pattern referencia: Tests\Contract\AutosaveContractRunner::run()
 * Plus ADR 0205 (contract tests autosave canon).
 */

return [
    'identificacao' => [
        'endpoint' => '/cliente/{id}/identificacao',
        'fields' => [
            ['send' => 'fantasia',  'value' => 'CT-{stamp}',           'recv' => 'fantasia'],
            ['send' => 'nome',      'value' => 'CT-{stamp}',           'recv' => 'name'],
            ['send' => 'doc',       'value' => '11.222.333/0001-44',   'recv' => 'tax_number_masked', 'match' => 'partial'],
            ['send' => 'ie',        'value' => 'CT-{stamp}',           'recv' => 'ie'],
            ['send' => 'rg',        'value' => 'CT-{stamp}',           'recv' => 'rg'],
            ['send' => 'contato',   'value' => 'CT-{stamp}',           'recv' => 'contato'],
            ['send' => 'cargo',     'value' => 'CT-{stamp}',           'recv' => 'cargo'],
        ],
    ],
    'contato' => [
        'endpoint' => '/cliente/{id}/contato',
        'fields' => [
            ['send' => 'tel',              'value' => '11999990001',      'recv' => 'mobile',           'match' => 'partial'],
            ['send' => 'tel2',             'value' => '11999990002',      'recv' => 'tel2',             'match' => 'partial'],
            ['send' => 'alternate_number', 'value' => '11999990003',      'recv' => 'alternate_number', 'match' => 'partial'],
            ['send' => 'email',            'value' => 'ct{stamp}@x.br',   'recv' => 'email'],
            ['send' => 'email_billing',    'value' => 'ct{stamp}c@x.br',  'recv' => 'email_billing'],
            ['send' => 'email_nfe',        'value' => 'ct{stamp}n@x.br',  'recv' => 'email_nfe'],
            ['send' => 'site',             'value' => 'ct{stamp}.com.br', 'recv' => 'site_url'],
            ['send' => 'canal',            'value' => 'whatsapp',         'recv' => 'canal_preferido'],
        ],
    ],
    'endereco' => [
        'endpoint' => '/cliente/{id}/endereco',
        'fields' => [
            ['send' => 'zip_code',        'value' => '01310-100',  'recv' => 'zip_code'],
            ['send' => 'address_line_1',  'value' => 'CT-{stamp}', 'recv' => 'address_line_1'],
            ['send' => 'numero',          'value' => '999',        'recv' => 'numero'],
            ['send' => 'address_line_2',  'value' => 'CT-{stamp}', 'recv' => 'address_line_2'],
            ['send' => 'neighborhood',    'value' => 'CT-{stamp}', 'recv' => 'neighborhood'],
            ['send' => 'city',            'value' => 'CT-{stamp}', 'recv' => 'city'],
            ['send' => 'state',           'value' => 'SP',         'recv' => 'state'],
        ],
    ],
    'comercial' => [
        'endpoint' => '/cliente/{id}/comercial',
        'fields' => [
            ['send' => 'credit_limit',          'value' => 12345.67, 'recv' => 'credit_limit',          'match' => 'int'],
            ['send' => 'pay_term_number',       'value' => 45,       'recv' => 'pay_term_number',       'match' => 'int'],
            ['send' => 'tabela_preco_padrao',   'value' => 'varejo', 'recv' => 'tabela_preco_padrao'],
            ['send' => 'pgto_padrao',           'value' => 'boleto', 'recv' => 'pgto_padrao'],
            ['send' => 'obs_comercial',         'value' => 'CT-{stamp}', 'recv' => 'obs_comercial'],
        ],
    ],
    'classificacao' => [
        'endpoint' => '/cliente/{id}/classificacao',
        'fields' => [
            ['send' => 'segmento',       'value' => 'corporativo', 'recv' => 'segmento'],
            ['send' => 'tags',           'value' => ['vip', 'fiel'], 'recv' => 'tags',           'match' => 'array_eq'],
            ['send' => 'contact_status', 'value' => 'active',      'recv' => 'contact_status'],
            ['send' => 'vip',            'value' => true,          'recv' => 'vip',            'match' => 'bool'],
            ['send' => 'bloqueado',      'value' => false,         'recv' => 'bloqueado',      'match' => 'bool'],
        ],
    ],
];
