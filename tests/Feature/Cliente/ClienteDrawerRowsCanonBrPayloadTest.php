<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de canon-source (ContactController::buildClienteIndexCustomers select cols) contra fonte-da-verdade móvel — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

/**
 * Fix 2026-05-27 (Wagner reportou drawer Identificacao sem IE/CNPJ no Acme
 * Comercio Ltda biz=1).
 *
 * IdentificacaoTab espera `contact.ie` + `contact.cpf_cnpj_masked` + `contact.rg`
 * + `contact.nascimento` + `contact.cargo` no row. ContactController::
 * buildClienteIndexCustomers nao enviava — drawer abria com placeholders
 * mesmo com dado no banco.
 *
 * Estrategia structural file_get_contents (alinhado ClienteListagemTurbinadaTest).
 *
 * Refs: ADR 0178 (canon BR restaurado pos UPOS 6.7), ADR 0179 (drawer 760).
 */

// ─── GUARD 1: select cols inclui cpf_cnpj + inscricao_estadual + rg ────────

test('GUARD 1 — buildClienteIndexCustomers select inclui canon BR fields graceful', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        // hasColumn graceful pra ambiente pre-Wave 2026-05-21.
        ->toContain("Schema::hasColumn('contacts', 'cpf_cnpj')")
        ->toContain("'contacts.cpf_cnpj'")
        ->toContain("'contacts.inscricao_estadual'")
        ->toContain("'contacts.rg'")
        // Wave drawer 2026-05-22 — nascimento + cargo + ie (alias Cowork).
        ->toContain("Schema::hasColumn('contacts', 'cargo')")
        ->toContain("'contacts.nascimento'")
        ->toContain("'contacts.cargo'")
        // `ie` (Wave drawer) e a fonte de verdade — autosave grava la, NAO em
        // `inscricao_estadual` (Wave canon BR — kept como fallback legacy).
        ->toContain("'contacts.ie'");
});

// ─── GUARD 2: payload row tem chaves drawer-friendly ──────────────────────

test('GUARD 2 — buildClienteIndexCustomers payload tem cpf_cnpj_masked + ie + rg + nascimento + cargo', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        // PII: cpf_cnpj_masked sempre passa por maskTaxNumber (LGPD).
        ->toMatch("/\\\$payload\\['cpf_cnpj_masked'\\]\\s*=\\s*\\\$this->maskTaxNumber/")
        // Fallback canon -> legacy UPOS pra cadastros pre-Wave 2026-05-21.
        ->toContain('cpf_cnpj ?? null')
        ->toContain('?? $contact->tax_number')
        // ie prioriza coluna Wave drawer (`contacts.ie`) com fallback canon BR
        // (`inscricao_estadual`) pra cadastros pre-drawer. Autosave grava em `ie`.
        ->toContain('$contact->ie ?? null')
        ->toContain('$contact->inscricao_estadual ?? null')
        // ie + rg + nascimento + cargo escapam o mask (sao livres, nao PII numerica).
        ->toMatch("/\\\$payload\\['ie'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['rg'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['nascimento'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['cargo'\\]\\s*=/");
});

// ─── GUARD 3: contrato frontend nao quebrou — IdentificacaoTab ainda le essas chaves

test('GUARD 3 — IdentificacaoTab.tsx ContactInfo declara ie + cpf_cnpj_masked', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('cpf_cnpj_masked?: string | null')
        ->toContain('ie?: string | null')
        ->toContain('rg?: string | null')
        ->toContain('nascimento?: string | null')
        ->toContain('cargo?: string | null')
        // useState inicializa do contact prop (autosave debounce).
        ->toContain('useState<string>(contact.ie ??')
        ->toContain('useState<string>(contact.cpf_cnpj_masked ??');
});

// ─── GUARD 4: Index.tsx ContactRow declara as chaves (contrato shared) ─────

test('GUARD 4 — Cliente/Index.tsx ClienteRow declara ie + cpf_cnpj_masked + rg + nascimento + cargo', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('cpf_cnpj_masked?: string | null')
        ->toContain('ie?: string | null')
        ->toContain('rg?: string | null')
        ->toContain('nascimento?: string | null')
        ->toContain('cargo?: string | null');
});

// ─── GUARD 5: ContatoTab — telefones + emails + site + canal ──────────────

test('GUARD 5 — payload tem landline + tel2 + alternate_number + email + email_billing + email_nfe + site + canal', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'contacts.landline'")
        ->toContain("'contacts.alternate_number'")
        ->toContain("'contacts.email'")
        ->toContain("'contacts.tel2'")
        ->toContain("'contacts.site_url'")
        ->toContain("'contacts.canal_preferido'")
        ->toContain("Schema::hasColumn('contacts', 'email_billing')")
        ->toContain("'contacts.email_billing'")
        ->toContain("'contacts.email_nfe'")
        ->toMatch("/\\\$payload\\['landline'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['tel2'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['alternate_number'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['email'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['email_billing'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['email_nfe'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['site_url'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['site'\\]\\s*=\\s*\\\$payload\\['site_url'\\]/")
        ->toMatch("/\\\$payload\\['canal_preferido'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['canal'\\]\\s*=\\s*\\\$payload\\['canal_preferido'\\]/");
});

// ─── GUARD 6: ComercialTab — limite, prazo, tabela, pgto, obs ──────────────

test('GUARD 6 — payload tem credit_limit + pay_term_number + tabela_preco_padrao + pgto_padrao + obs_comercial + aliases PT-BR', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'contacts.credit_limit'")
        ->toContain("'contacts.pay_term_number'")
        ->toContain("'contacts.tabela_preco_padrao'")
        ->toContain("'contacts.pgto_padrao'")
        ->toContain("'contacts.obs_comercial'")
        ->toMatch("/\\\$payload\\['credit_limit'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['limite_credito'\\]\\s*=\\s*\\\$payload\\['credit_limit'\\]/")
        ->toMatch("/\\\$payload\\['pay_term_number'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['prazo_padrao_dias'\\]\\s*=\\s*\\\$payload\\['pay_term_number'\\]/")
        ->toMatch("/\\\$payload\\['tabela_preco_padrao'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['pgto_padrao'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['obs_comercial'\\]\\s*=/");
});

// ─── GUARD 7: ClassificacaoTab + SEFAZ derivados ───────────────────────────

test('GUARD 7 — payload tem contact_status + SEFAZ derivados (ind_ie_dest, sefaz_cad_*)', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'contacts.contact_status'")
        ->toMatch("/\\\$payload\\['contact_status'\\]\\s*=/")
        ->toContain("Schema::hasColumn('contacts', 'sefaz_cad_sit')")
        ->toContain("'contacts.ind_ie_dest'")
        ->toContain("'contacts.sefaz_cad_sit'")
        ->toContain("'contacts.sefaz_cad_ind_cred_nfe'")
        ->toContain("'contacts.sefaz_cad_consultado_em'")
        ->toMatch("/\\\$payload\\['ind_ie_dest'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['sefaz_cad_sit'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['sefaz_cad_ind_cred_nfe'\\]\\s*=/")
        ->toMatch("/\\\$payload\\['sefaz_cad_consultado_em'\\]\\s*=/");
});

// ─── GUARD 8: `status` (derivado OS) NÃO foi renomeado — sem regressão ─────

test('GUARD 8 — payload[status] ainda eh late|active|idle derivado OS (FrescorPill)', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("\$status = \$atrasadas > 0 ? 'late' : (\$abertas > 0 ? 'active' : 'idle')")
        ->toMatch("/'status'\\s*=>\\s*\\\$status/");
});

// ─── GUARD 9: shipping_address (endereço de entrega) — Wagner 2026-06-02 ────
// Sem o campo no select + payload, o drawer EnderecoTab reabre vazio mesmo com
// dado salvo no DB (mesma classe do bug zip_code/address_line). A venda (Fase 2)
// puxa o endereço de entrega daqui.

test('GUARD 9 — buildClienteIndexCustomers select + payload incluem shipping_address', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'contacts.shipping_address'")
        ->toContain("'shipping_address' => \$contact->shipping_address");
});

test('GUARD 9b — EnderecoTab.tsx declara shipping_address + autosave on blur', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('shipping_address?: string | null')
        ->toContain("useState<string>(contact.shipping_address ??")
        ->toContain("scheduleAutosave('shipping_address'")
        ->toContain("handleBlur('shipping_address'");
});
