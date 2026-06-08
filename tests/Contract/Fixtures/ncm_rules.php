<?php

declare(strict_types=1);

/**
 * Fixture: contract test do CRUD NCM rules NFe (NfeBrasil/Tributacao).
 *
 * Cobre os 2 endpoints da tela "Regras NCM" (subdominio de Tributacao —
 * regras tributarias por NCM/UF). Diferente do fixture `nfe_config.php`
 * (que cobre config DEFAULT do business), este cobre o CRUD per-row.
 *
 * Endpoints cobertos:
 *   1. POST /nfe-brasil/tributacao/regras       → cria nova regra (1 round-trip end-to-end)
 *   2. PUT  /nfe-brasil/tributacao/regras/{id}  → atualiza regra existente (10 campos)
 *
 * Ambos retornam RedirectResponse (302). Test file faz POST/PUT + read-back
 * via Eloquent NfeFiscalRule (casts float aplicados), seguindo pattern
 * service_order_edit.php + nfe_config.php (loop custom inline, sem
 * AutosaveContractRunner::run default).
 *
 * MUTEX CSOSN/CST (validator UpsertRegraTributariaRequest::withValidator):
 *   - withValidator falha se AMBOS preenchidos. Decisao do fixture:
 *     - baseFields default mantem `csosn='102'` + `cst=null` (regime Simples).
 *     - Pra cobrir caminho CST (Regime Normal), tab `editar_regra_cst` faz
 *       PUT setando `csosn=null` + `cst='000'`. Iteracao isolada — NAO mistura
 *       com base CSOSN.
 *
 * Unique constraint nfe_fiscal_rules: nao ha unique constraint dura no schema
 * (so indexes nfe_fiscal_rules_biz_ncm_idx + nfe_fiscal_rules_cascade_idx —
 * MySQL trata NULL como distinct). Idempotencia fica no service via
 * firstOrCreate, mas no test usamos NCMs distintos por iteracao quando
 * necessario (POST cria nova row cada vez).
 *
 * EXCLUIDOS POR DESIGN (outros PRs):
 *   - DELETE regra (destrutivo, fixture proprio futuro)
 *   - Import CSV em massa (multipart + side-effects multi-row)
 *   - GET edit/index (Inertia render, nao autosave)
 *
 * EXCLUIDOS POR ESCOPO (campos avancados NT 2025.002 Reforma Tributaria):
 *   - c_class_trib, cst_ibs, cst_cbs, aliquota_ibs, aliquota_cbs —
 *     adicionados em migration 2026_05_26 mas nao validados pelo
 *     UpsertRegraTributariaRequest atual. Quando validator estender,
 *     atualizar este fixture.
 *
 * Schema fixture estendido (vs cliente_drawer.php) — espelha nfe_config:
 *   - 'read_back' => 'model'         — usa Eloquent NfeFiscalRule (casts)
 *   - 'where'     => ['id' => ...]   — read-back especifico per-row
 *   - 'method'    => 'post'|'put'
 *   - 'expectStatus' => 302
 *
 * @see Modules\NfeBrasil\Http\Controllers\TributacaoController::store
 * @see Modules\NfeBrasil\Http\Controllers\TributacaoController::update
 * @see Modules\NfeBrasil\Http\Requests\UpsertRegraTributariaRequest
 * @see ADR 0205 — contract tests autosave canon
 * @see tests/Contract/Fixtures/nfe_config.php — fixture relacionado (config default)
 */

return [
    // 1. POST /nfe-brasil/tributacao/regras
    // Cria 1 regra nova com payload completo, le de volta TODOS os campos.
    // Diferente do PUT (loop por campo), aqui validamos um POST "all-in" — se
    // validator dropa qualquer chave silenciosamente, o read-back pega.
    //
    // Cada iteracao envia o MESMO payload base. Em vez de variar 1 campo, o
    // proposito e validar persistencia de cada campo individual no payload
    // mesmo apos cascata de validator (validated() filter).
    //
    // No test file: cria 1 regra via POST com payload completo, depois para
    // cada `field` valida que o valor enviado === valor lido de volta.
    'criar_regra_simples' => [
        'endpoint' => '/nfe-brasil/tributacao/regras',
        'method' => 'post',
        'expectStatus' => 302,
        'read_back' => 'model',
        'table' => 'nfe_fiscal_rules',
        // Payload completo enviado UMA VEZ no test — todos required + opcionais.
        // Test file fara 1 POST, depois iterara `fields` validando read-back.
        'baseFields' => [
            'ncm' => '87082999',
            'uf_origem' => 'SP',
            'uf_destino' => 'RJ',
            'cfop' => '5102',
            'csosn' => '102',
            'aliquota_icms' => 0.18,
            'aliquota_pis' => 0.0065,
            'aliquota_cofins' => 0.03,
            'aliquota_ipi' => 0.0,
            'mva' => 0.4,
            'fcp' => 0.02,
        ],
        // Verificacoes de read-back — cada campo validado individualmente
        // contra a row recem-criada.
        'fields' => [
            ['send' => 'ncm',             'value' => '87082999', 'recv' => 'ncm',             'column' => 'ncm'],
            ['send' => 'uf_origem',       'value' => 'SP',       'recv' => 'uf_origem',       'column' => 'uf_origem'],
            ['send' => 'uf_destino',      'value' => 'RJ',       'recv' => 'uf_destino',      'column' => 'uf_destino'],
            ['send' => 'cfop',            'value' => '5102',     'recv' => 'cfop',             'column' => 'cfop'],
            ['send' => 'csosn',           'value' => '102',      'recv' => 'csosn',            'column' => 'csosn'],
            ['send' => 'aliquota_icms',   'value' => 0.18,       'recv' => 'aliquota_icms',    'column' => 'aliquota_icms',   'match' => 'float'],
            ['send' => 'aliquota_pis',    'value' => 0.0065,     'recv' => 'aliquota_pis',     'column' => 'aliquota_pis',    'match' => 'float'],
            ['send' => 'aliquota_cofins', 'value' => 0.03,       'recv' => 'aliquota_cofins',  'column' => 'aliquota_cofins', 'match' => 'float'],
            ['send' => 'aliquota_ipi',    'value' => 0.0,        'recv' => 'aliquota_ipi',     'column' => 'aliquota_ipi',    'match' => 'float'],
            ['send' => 'mva',             'value' => 0.4,        'recv' => 'mva',              'column' => 'mva',             'match' => 'float'],
            ['send' => 'fcp',             'value' => 0.02,       'recv' => 'fcp',              'column' => 'fcp',             'match' => 'float'],
        ],
    ],

    // 2. PUT /nfe-brasil/tributacao/regras/{id}
    // Atualiza regra base existente (criada no beforeEach do test).
    // Pattern service_order_edit.php — payload completo + 1 campo variado
    // por iteracao. baseFields garantem que validator passa cada PUT.
    //
    // CSOSN: mantemos no base (regra Simples Nacional CRT 1). Cada PUT
    // varia 1 campo cadastral. cst fica null sempre (mutex protege).
    'editar_regra_existente' => [
        'endpoint' => '/nfe-brasil/tributacao/regras/{id}',
        'method' => 'put',
        'expectStatus' => 302,
        'read_back' => 'model',
        'table' => 'nfe_fiscal_rules',
        'baseFields' => [
            'ncm' => '87082999',
            'uf_origem' => 'SP',
            'uf_destino' => 'RJ',
            'cfop' => '5102',
            'csosn' => '102',
            'aliquota_icms' => 0.18,
            'aliquota_pis' => 0.0065,
            'aliquota_cofins' => 0.03,
            'aliquota_ipi' => 0.0,
        ],
        'fields' => [
            // NCM peças automotivas → eletrônicos (8 dígitos validos regex).
            ['send' => 'ncm',             'value' => '85423100',    'recv' => 'ncm',             'column' => 'ncm'],
            // UF origem São Paulo → Paraná.
            ['send' => 'uf_origem',       'value' => 'PR',          'recv' => 'uf_origem',       'column' => 'uf_origem'],
            // UF destino RJ → MG (nullable mas aqui setado).
            ['send' => 'uf_destino',      'value' => 'MG',          'recv' => 'uf_destino',      'column' => 'uf_destino'],
            // CFOP 5102 (venda dentro estado) → 6102 (fora estado).
            ['send' => 'cfop',            'value' => '6102',        'recv' => 'cfop',            'column' => 'cfop'],
            // CSOSN 102 → 500 (3 digitos regex).
            ['send' => 'csosn',           'value' => '500',         'recv' => 'csosn',           'column' => 'csosn'],
            // Aliquotas — float roundtrip via Eloquent cast.
            ['send' => 'aliquota_icms',   'value' => 0.12,          'recv' => 'aliquota_icms',   'column' => 'aliquota_icms',   'match' => 'float'],
            ['send' => 'aliquota_pis',    'value' => 0.0165,        'recv' => 'aliquota_pis',    'column' => 'aliquota_pis',    'match' => 'float'],
            ['send' => 'aliquota_cofins', 'value' => 0.076,         'recv' => 'aliquota_cofins', 'column' => 'aliquota_cofins', 'match' => 'float'],
            ['send' => 'aliquota_ipi',    'value' => 0.05,          'recv' => 'aliquota_ipi',    'column' => 'aliquota_ipi',    'match' => 'float'],
            // MVA opcional (ICMS-ST) — null no baseFields, setado aqui.
            ['send' => 'mva',             'value' => 0.5,           'recv' => 'mva',             'column' => 'mva',             'match' => 'float'],
            // FCP opcional (Fundo Combate Pobreza) — idem.
            ['send' => 'fcp',             'value' => 0.02,          'recv' => 'fcp',             'column' => 'fcp',             'match' => 'float'],
        ],
    ],

    // 3. PUT /nfe-brasil/tributacao/regras/{id} — caminho CST (Regime Normal).
    // Mutex CSOSN/CST: pra preencher cst, REMOVER csosn no payload.
    // baseFields seta csosn=null + cst='000' (Tributada integralmente).
    // 1 iteracao so — valida que o caminho CST funciona end-to-end.
    'editar_regra_cst' => [
        'endpoint' => '/nfe-brasil/tributacao/regras/{id}',
        'method' => 'put',
        'expectStatus' => 302,
        'read_back' => 'model',
        'table' => 'nfe_fiscal_rules',
        'baseFields' => [
            'ncm' => '87082999',
            'uf_origem' => 'SP',
            'uf_destino' => 'RJ',
            'cfop' => '5102',
            // CSOSN explicitamente null pra desbloquear CST (mutex).
            'csosn' => null,
            'cst' => '000',
            'aliquota_icms' => 0.18,
            'aliquota_pis' => 0.0065,
            'aliquota_cofins' => 0.03,
            'aliquota_ipi' => 0.0,
        ],
        'fields' => [
            // Valida que CST persiste e CSOSN fica null apos PUT.
            ['send' => 'cst', 'value' => '000', 'recv' => 'cst', 'column' => 'cst'],
        ],
    ],
];
