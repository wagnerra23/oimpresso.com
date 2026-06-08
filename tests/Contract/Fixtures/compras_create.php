<?php

declare(strict_types=1);

/**
 * Fixture: contract test do fluxo Compras/Create (MWART Wave2 B5 — PurchaseCreate.tsx).
 *
 * Tela Compras/Create faz submit full-form `form.post('/purchases', ...)` que retorna
 * `redirect('purchases')->with('status', $output)` — NAO JSON. Por ADR 0205 P2
 * "tela CRUD tradicional ... opcional", cobrimos os endpoints COMPANHEIROS do
 * fluxo onde bug silencioso e possivel:
 *
 *   1. POST /contacts (type=supplier)  — Quick-add Fornecedor.
 *      Mesma rota usada por Sells/Create QuickAddCustomerSheet, mas type=supplier
 *      ativa shape diferente: `supplier_business_name` (razao social PJ) e´ a
 *      identidade primaria, NAO `first_name` (que e´ usado em type=customer PF).
 *      Aliases UPOS historicos PJ supplier diferem de PF customer — bug silencioso
 *      tipo "drawer Cliente PT-BR aliases" pode bater so´ aqui sem teste.
 *   2. POST /purchases/check_ref_number  — Validador AJAX de duplicidade ref_no.
 *      Endpoint critico do fluxo: tela pré-checa se ref_no + fornecedor ja existe
 *      ANTES do submit principal. Retorna echo "true"/"false" (string raw, NAO
 *      JSON). Hoje DESATIVADO no fixture (ver bloco final) — runner ainda nao
 *      suporta raw_body response shape.
 *
 * NAO cobre:
 *   - POST /purchases (submit principal) — redirect-flow sem JSON. Erros 422 sao
 *     visiveis. Sem bug silencioso possivel: validator falha => redirect back
 *     with errors. Mesmo trade-off que Sells/Create POST /pos.
 *   - POST /purchases/update-status — pos-criacao (FSM), test proprio em
 *     PurchaseStatusTransitionTest (caso exista) ou Modules/Inventory dedicated.
 *     Response shape so´ tem `success`+`msg`, baixo valor pra contract.
 *   - POST /purchases/get_purchase_entry_row — server-render Blade partial
 *     (legacy non-Inertia), nao consumido pelo Create.tsx Inertia novo.
 *   - POST /import-purchase-products — fluxo separado (upload XML), nao Create form.
 *
 * Total: 5 campos auto-verificados. Bugs evitados:
 *   - `supplier_business_name` removido do $request->only() => fornecedor PJ
 *     vira contact sem razao social, listagem mostra "name" vazio (regressao
 *     classica UPOS upstream — ja aconteceu em 6.7).
 *   - check_ref_number retornando "1"/"0" em vez de "true"/"false" => frontend
 *     vai sempre achar que e´ duplicado, bloqueia submit sem mensagem clara.
 *     [coberto quando runner suportar raw_body — ver bloco DESATIVADO abaixo]
 *   - cpf_cnpj fornecedor nao persiste => emissao NFe falha em prod (PII +
 *     legal risk LGPD/Receita).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL): setup cria contact + transaction
 * stub no business autenticado. Rollback automatico via DatabaseTransactions.
 *
 * @see app/Http/Controllers/PurchaseController.php (createInertia §425+, store §475+)
 * @see app/Http/Controllers/PurchaseController.php (checkRefNumber §1675+)
 * @see app/Http/Controllers/ContactController.php (store §1431+ compartilhado com Sells)
 * @see resources/js/Pages/Purchase/Create.tsx (form.post('/purchases'))
 * @see tests/Contract/README.md — receita
 * @see ADR 0205 — contract tests autosave canon
 * @see ADR 0114 — gate visual MWART (Cowork loop)
 */

return [
    // ============================================================
    // 1. POST /contacts (quick-add Fornecedor type=supplier)
    // ============================================================
    // Cada POST cria contact novo. Idempotente em termos de schema (insertGetId
    // sem unique-constraint colidindo), gera N orfaos — OK pq DatabaseTransactions
    // rollback ao fim do test.
    //
    // Diferenca chave vs Sells/Create fixture:
    //   - type=supplier (nao customer)
    //   - supplier_business_name PREENCHIDO (razao social PJ — identidade primaria)
    //   - first_name fica vazio ou auxiliar (contato pessoal dentro do supplier)
    //
    // Validator StoreContactRequest (App\Http\Requests\Cliente\StoreContactRequest)
    // aceita nullable em todos esses; nada e´ required. Mas controller monta `name`
    // via implode([prefix, first, middle, last]) — se NADA preenchido, fica string
    // vazia, pode quebrar listagem. Por isso baseFields inclui first_name fallback.
    'quick_add_fornecedor' => [
        'endpoint' => '/contacts',
        'method' => 'post',
        'responseRoot' => 'data',  // ContactController@store retorna { success, msg, data: {id, name, ...} }
        'expectStatus' => 200,
        // Campos sempre enviados (validator + controller flow exigem pra montar
        // contact valido). type=supplier ativa shape PJ — supplier_business_name
        // se torna campo primario.
        'baseFields' => [
            'type' => 'supplier',
            'contact_type_radio' => 'supplier',
            'first_name' => 'CT Contato Fornec {stamp}',  // contato pessoal opcional do supplier
            'pay_term_number' => null,
            'pay_term_type' => null,
            'credit_limit' => '',
            'opening_balance' => '0',
        ],
        'fields' => [
            // CRITICO: razao social PJ. Bug silencioso aqui = supplier sem nome
            // empresarial em listagem/relatorios. Frontend pode mostrar "name"
            // (contato pessoal) mas relatorio fiscal/NFe usa supplier_business_name.
            // match partial pq alguns observers podem trim/normalize espacos.
            ['send' => 'supplier_business_name', 'value' => 'CT Fornec LTDA {stamp}', 'recv' => 'supplier_business_name', 'match' => 'partial'],

            // Mobile do supplier — direto, recv = mobile.
            ['send' => 'mobile', 'value' => '11999990{stamp}', 'recv' => 'mobile', 'match' => 'partial'],

            // Email do supplier — comercial padrao.
            ['send' => 'email', 'value' => 'fornec{stamp}@example.test', 'recv' => 'email'],

            // City — comum esquecer no $request->only(), bug silencioso classico.
            ['send' => 'city', 'value' => 'CTCidadeFornec{stamp}', 'recv' => 'city'],

            // cpf_cnpj fornecedor — CNPJ PJ. Validator usa CpfCnpj mod-11 SEFAZ
            // (App\Rules\BR\CpfCnpj), entao precisa ser sintetico VALIDO.
            // 11.444.777/0001-61 e´ valido mod-11 (ver tests/Unit/Rules/BR/CpfCnpjTest.php).
            // NAO usar CNPJ real (PII LGPD ADR 0127). Match partial pq observer pode strip pontuacao.
            ['send' => 'cpf_cnpj', 'value' => '11.444.777/0001-61', 'recv' => 'cpf_cnpj', 'match' => 'partial'],
        ],
    ],

    // ============================================================
    // 2. [DESATIVADO] POST /purchases/check_ref_number
    // ============================================================
    // Endpoint critico do fluxo pre-submit: tela checa se ref_no + contact_id ja
    // existe em outras compras desse fornecedor. Retorna echo "true" (livre)
    // ou "false" (duplicado). NAO retorna JSON — apenas echo + exit (ver
    // PurchaseController@checkRefNumber §1693-1699).
    //
    // Como o runner valida via response->json() + data_get(), echo raw "true" nao
    // e´ JSON parseavel — data_get retorna null. Por isso NAO incluimos este tab
    // no fixture ativo hoje.
    //
    // SOLUCAO PROPOSTA: extender AutosaveContractRunner com nova chave
    // `responseShape: 'raw_body'` que muda da semantica json+path pra strpos no
    // response->getContent(). Pequena PR ortogonal — quando estiver mergeada,
    // descomentar bloco abaixo e o test ja cobre check_ref_number.
    //
    // // 'check_ref_number' => [
    // //     'endpoint' => '/purchases/check_ref_number',
    // //     'method' => 'post',
    // //     'responseShape' => 'raw_body',  // FEATURE FUTURA — runner ext
    // //     'expectStatus' => 200,
    // //     'baseFields' => [
    // //         'contact_id' => 0,  // overridden pelo test file pra $this->contactId
    // //         'purchase_id' => null,
    // //     ],
    // //     'fields' => [
    // //         ['send' => 'ref_no', 'value' => 'CT-REF-{stamp}', 'recv' => '__body_raw', 'match' => 'partial'],
    // //     ],
    // // ],
];
