<?php

declare(strict_types=1);

/**
 * Fixture: contract test do fluxo Sells/Create (PDV principal).
 *
 * Sells/Create.tsx em si e submit-full-page (POST /pos) — por ADR 0205 P2
 * "tela CRUD tradicional ... opcional". Cobrimos os 2 endpoints COMPANHEIROS
 * do fluxo onde bug silencioso e possivel:
 *
 *   1. POST /contacts — QuickAddCustomerSheet embed (Onda R6 Dor 4 Larissa).
 *      Aliases historicos UPOS (first_name => name no controller via implode).
 *   2. PATCH /sells/{id}/commission-split — ADR 0192 Onda 2.
 *      Shape NESTED + Rule::exists multi-tenant + total=100.
 *
 * NAO cobre: POST /pos (erros 422 visiveis), product line edit (client-side),
 * PUT /sells/update-shipping/{id} (pertence a Sells/Edit).
 *
 * Total: 7 campos. Usa `setupSellsContext` (cria contact + sell stub).
 * Multi-tenant Tier 0 ADR 0093 preservado.
 */

return [
    // ============================================================
    // 1. POST /contacts (quick-add embedded no Sells/Create)
    // ============================================================
    // Endpoint nao usa {id} — POST cria recurso novo. Cada PATCH (ops, cada POST)
    // cria contact novo. Idempotente em termos de schema (nao colide), mas gera
    // N contacts orfaos em DB transacional — OK pq DatabaseTransactions rollback.
    'quick_add_customer' => [
        'endpoint' => '/contacts',
        'method' => 'post',
        'responseRoot' => 'data',  // ContactController@store retorna { success, msg, data: {id, name, mobile, ...} }
        'expectStatus' => 200,
        // Campos sempre enviados (validador exige `contact_type_radio` e `type`
        // pra montar nome + classificar customer/supplier).
        'baseFields' => [
            'type' => 'customer',
            'contact_type_radio' => 'customer',
            'first_name' => 'CT Quick {stamp}',  // sempre presente — nome NOT NULL
            'pay_term_number' => null,
            'pay_term_type' => null,
            'credit_limit' => '',
            'opening_balance' => '0',
        ],
        'fields' => [
            // Alias historico UPOS: frontend envia `first_name`, controller monta `name`
            // via implode([prefix, first, middle, last]). Sem teste, mudar order do
            // implode quebra silenciosamente (Daniela tinha bug parecido em drawer Cliente).
            ['send' => 'first_name', 'value' => 'CTNM {stamp}', 'recv' => 'name', 'match' => 'partial'],

            // Mobile passa direto — validator UPOS aceita string ate 25 chars.
            // partial match porque alguns validators reformatam o telefone.
            ['send' => 'mobile', 'value' => '11999990{stamp}', 'recv' => 'mobile', 'match' => 'partial'],

            // Email — direto, mesmo recv key, equals strict.
            ['send' => 'email', 'value' => 'ct{stamp}@example.test', 'recv' => 'email'],

            // City — direto, recv = city. Comum esquecer cidade no $request->only(),
            // bug silencioso classico.
            ['send' => 'city', 'value' => 'CTCidade{stamp}', 'recv' => 'city'],

            // cpf_cnpj — campo BR restaurado em migration 2026_05_21_140000 (regressao UPOS 6.7).
            // Valor sintetico (NAO usar CPF/CNPJ real — PII LGPD).
            // Match partial porque algumas instalacoes ou observers podem reformatar
            // (ex. strip pontuacao). O CRITICO e o valor chegar na coluna, nao a forma.
            ['send' => 'cpf_cnpj', 'value' => '111.222.333-44', 'recv' => 'cpf_cnpj', 'match' => 'partial'],
        ],
    ],

    // ============================================================
    // 2. PATCH /sells/{id}/commission-split (ADR 0192)
    // ============================================================
    // Endpoint dedicado, validador custom, shape NESTED (commission_split: {...}).
    // Resposta: { success, commission_split: {mecanico_id, mecanico_pct, balcao_id, balcao_pct}, msg }
    // payloadShape 'nested:commission_split' garante runner envia objeto wrapped.
    //
    // Restricao: ambos mecanico_id E balcao_id precisam ser users.id reais do mesmo
    // business (Rule::exists). Runner setupSellsContext garante 1 user existe (o
    // autenticado), mas commission split exige 2 users distintos pra split partial.
    // Por isso testamos APENAS modo "100% mecanico" (balcao_id null, balcao_pct 0) —
    // que so precisa de 1 user e ainda assim valida o contrato de chave/shape.
    //
    // baseFields injeta os outros 3 campos do shape canon junto com cada field test
    // pra payload sempre passar validacao de "total === 100".
    //
    // Limitacao conhecida: nao testa o cenario com balcao_id != null pq seeder
    // de 2 users por business nao e garantido. Cobrir esse caso quando houver
    // factory User compartilhada.
    'commission_split' => [
        'endpoint' => '/sells/{id}/commission-split',
        'method' => 'patch',
        'responseRoot' => 'commission_split',
        'payloadShape' => 'nested:commission_split',
        // mecanico_id sera SOBRESCRITO em runtime pelo test file via $authUserId.
        // Aqui colocamos placeholder 1 que sera resolvido — ver test file beforeEach.
        // (runner nao tem acesso a $this->user direto via fixture estatico.)
        // Em pratica, test file fornece baseFields completo via wrapper run() call.
        'baseFields' => [
            // Placeholders — test file substitui mecanico_id por $this->user->id
            // antes de invocar runner (override via merge no fixture loaded).
            'mecanico_id' => 0,
            'mecanico_pct' => 100,
            'balcao_id' => null,
            'balcao_pct' => 0,
        ],
        'fields' => [
            // Testa: backend persiste e devolve mecanico_pct exato.
            // Como total tem que ser 100, so podemos variar entre 100 e 100 aqui
            // (modo solo mecanico). Match 'int' pq backend faz round(2) e
            // PHP pode retornar 100.0 vs frontend enviar 100 int.
            ['send' => 'mecanico_pct', 'value' => 100, 'recv' => 'mecanico_pct', 'match' => 'int'],

            // Testa: backend aceita balcao_pct=0 quando solo mecanico.
            ['send' => 'balcao_pct', 'value' => 0, 'recv' => 'balcao_pct', 'match' => 'int'],
        ],
    ],
];
