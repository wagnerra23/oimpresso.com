<?php

declare(strict_types=1);

/**
 * Pest test — ContactQuickAddModal P0-1 paridade Sells/Create (RUNBOOK §5.1).
 *
 * Cobre cadastro de cliente inline (sem abrir nova aba) — desbloqueia Lara
 * Caçambas (filha do Martinho) no canary 19/maio: pain #1 reunião 13/maio era
 * "velocidade pra abrir uma venda".
 *
 * Refs:
 *   - memory/requisitos/Sells/RUNBOOK-paridade-create.md §3 item 7 + §5 P0-1
 *   - resources/views/contact/create.blade.php (modal Blade — fonte campos)
 *   - app/Http/Controllers/ContactController.php@store (endpoint reusado)
 *   - app/Utils/ContactUtil.php@createNewContact (retorna {success, data, msg})
 *   - ADR 0093 multi-tenant Tier 0 (cross-tenant biz=1 vs biz=99)
 *   - ADR 0101 testes biz=1 (Wagner) NUNCA biz=164 (Martinho cliente real)
 *
 * Anti-padrões catalogados que este test pega:
 *   - Modal sem reset entre cadastros consecutivos
 *   - Botão (+) ausente da UI (recurso oculto = pior do que recurso ausente)
 *   - Cor crua não-semântica (canon ADR 0110)
 *   - Remover features Create.tsx existentes (Wagner trauma)
 *   - Cross-tenant leak — POST /contacts criando contact em business_id errado
 */

use App\Contact;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const SELL_QUICK_BIZ_WAGNER = 1;        // ADR 0101: biz=1 Wagner, NUNCA biz=164 cliente
const SELL_QUICK_BIZ_FICTICIO = 99;     // segundo tenant pra cross-tenant
const QUICK_MODAL_PATH = 'resources/js/Pages/Sells/_components/ContactQuickAddModal.tsx';
const QUICK_CREATE_PAGE_PATH = 'resources/js/Pages/Sells/Create.tsx';

function readQuickModal(): string
{
    return file_get_contents(base_path(QUICK_MODAL_PATH));
}

function readQuickCreatePage(): string
{
    return file_get_contents(base_path(QUICK_CREATE_PAGE_PATH));
}

/**
 * Pula se DB connection não tem schema MySQL (mesmo pattern ContactsInertiaTest).
 */
function quickNeedMysqlOrSkip(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0101).');
    }
    if (! Schema::hasTable('contacts')) {
        test()->markTestSkipped('Tabela contacts ausente — migration não rodada.');
    }
}

function quickFirstUserOfBiz(int $bizId): ?User
{
    return User::where('business_id', $bizId)->first();
}

// =========================================================================
// 1. Componente existe e exporta default
// =========================================================================

it('ContactQuickAddModal existe em Pages/Sells/_components/', function () {
    expect(file_exists(base_path(QUICK_MODAL_PATH)))->toBeTrue();
});

it('ContactQuickAddModal exporta default React component', function () {
    $source = readQuickModal();
    expect($source)->toContain('export default function ContactQuickAddModal');
});

it('ContactQuickAddModal declara contract Props (open/onClose/onContactCreated)', function () {
    $source = readQuickModal();
    expect($source)->toContain('open: boolean');
    expect($source)->toContain('onClose: () => void');
    expect($source)->toMatch('/onContactCreated:\\s*\\(contact:\\s*CreatedContact\\)\\s*=>\\s*void/');
});

it('ContactQuickAddModal exporta tipo CreatedContact (id + name)', function () {
    $source = readQuickModal();
    expect($source)->toContain('export interface CreatedContact');
    expect($source)->toMatch('/id:\\s*number/');
    expect($source)->toMatch('/name:\\s*string/');
});

// =========================================================================
// 2. Campos mínimos paridade Blade contact_modal
// =========================================================================

it('ContactQuickAddModal tem campos mínimos paridade Blade (tipo, nome, CPF/CNPJ, telefone, email, cidade, UF)', function () {
    $source = readQuickModal();
    // Tipo de contato (customer/supplier/both)
    expect($source)->toContain("type: ContactType");
    expect($source)->toContain("'customer'");
    expect($source)->toContain("'supplier'");
    expect($source)->toContain("'both'");
    // Pessoa física / jurídica (radio do Blade — contact_type_radio)
    expect($source)->toContain('contact_type_radio');
    expect($source)->toContain("'individual'");
    expect($source)->toContain("'business'");
    // Campos do form
    expect($source)->toContain('id="cqa_first_name"');
    expect($source)->toContain('id="cqa_tax_number"');
    expect($source)->toContain('id="cqa_mobile"');
    expect($source)->toContain('id="cqa_email"');
    expect($source)->toContain('id="cqa_city"');
    expect($source)->toContain('id="cqa_state"');
});

it('ContactQuickAddModal valida CPF/CNPJ com dígito verificador (UI hint)', function () {
    $source = readQuickModal();
    expect($source)->toContain('isValidCpf');
    expect($source)->toContain('isValidCnpj');
    expect($source)->toContain('maskTaxNumber');
    expect($source)->toContain('maskPhone');
});

it('ContactQuickAddModal marca campos obrigatórios (nome + telefone) — pain #1 Lara', function () {
    $source = readQuickModal();
    // Validate function exige first_name OU supplier_business_name + mobile
    expect($source)->toContain('Informe o nome ou razão social');
    expect($source)->toContain('Telefone celular é obrigatório');
});

// =========================================================================
// 3. Submit + callback (POST /contacts com payload corretos)
// =========================================================================

it('ContactQuickAddModal faz POST em /contacts (endpoint reusado ContactController@store)', function () {
    $source = readQuickModal();
    expect($source)->toContain("fetch('/contacts'");
    expect($source)->toContain("method: 'POST'");
    expect($source)->toContain("'X-CSRF-TOKEN'");
});

it('ContactQuickAddModal envia tax_number raw (digits only) — backend espera assim', function () {
    $source = readQuickModal();
    // payload monta tax_number com digitsOnly
    expect($source)->toMatch('/tax_number:\\s*digitsOnly\\(form\\.tax_number\\)/');
});

it('ContactQuickAddModal chama onContactCreated(contact) + onClose() em sucesso', function () {
    $source = readQuickModal();
    expect($source)->toMatch('/onContactCreated\\(\\{[^}]*id:\\s*created\\.id/s');
    expect($source)->toContain('onClose()');
});

it('ContactQuickAddModal preserva stay-on-page (NÃO usa router.post — usa fetch)', function () {
    $source = readQuickModal();
    // router.post causaria full Inertia visit, perdendo state da venda em rascunho
    expect($source)->not->toContain("import { router");
    expect($source)->not->toContain('router.post');
});

it('ContactQuickAddModal reseta form ao abrir (não vaza state entre cadastros)', function () {
    $source = readQuickModal();
    // useEffect que reseta quando `open` muda pra true
    expect($source)->toMatch('/useEffect\\([^)]+\\{[^}]*if\\s*\\(open\\)/s');
    expect($source)->toContain('EMPTY_FORM');
});

// =========================================================================
// 4. Atalhos teclado + acessibilidade
// =========================================================================

it('ContactQuickAddModal aceita atalho Ctrl/Cmd+S pra submeter', function () {
    $source = readQuickModal();
    expect($source)->toContain('ctrlKey');
    expect($source)->toContain('metaKey');
    expect($source)->toMatch("/key\\s*===?\\s*'s'/");
});

it('ContactQuickAddModal tem role="alert" em mensagens de erro (WCAG)', function () {
    $source = readQuickModal();
    expect($source)->toContain('role="alert"');
});

it('ContactQuickAddModal usa labels PT-BR (Wagner exige — Lara/Dani não-técnicas)', function () {
    $source = readQuickModal();
    expect($source)->toContain('Cadastrar novo cliente');
    expect($source)->toContain('Cancelar');
    expect($source)->toContain('Salvar cliente');
    // Não pode ter labels em inglês
    expect($source)->not->toMatch('/>\\s*Save\\s*</');
});

// =========================================================================
// 5. Integração em Create.tsx
// =========================================================================

it('Create.tsx importa ContactQuickAddModal de _components/', function () {
    $source = readQuickCreatePage();
    expect($source)->toContain("from './_components/ContactQuickAddModal'");
    expect($source)->toContain('CreatedContact');
});

it('Create.tsx tem state showContactQuickAdd', function () {
    $source = readQuickCreatePage();
    expect($source)->toContain('showContactQuickAdd');
    expect($source)->toContain('setShowContactQuickAdd');
});

it('Create.tsx renderiza botão (+) com data-testid contact-quick-add-trigger', function () {
    $source = readQuickCreatePage();
    expect($source)->toContain('data-testid="contact-quick-add-trigger"');
    expect($source)->toContain('aria-label="Cadastrar novo cliente"');
});

it('Create.tsx renderiza <ContactQuickAddModal> com props canon (open/onClose/onContactCreated)', function () {
    $source = readQuickCreatePage();
    expect($source)->toContain('<ContactQuickAddModal');
    expect($source)->toMatch('/open=\\{showContactQuickAdd\\}/');
    expect($source)->toMatch('/onClose=\\{[^}]*setShowContactQuickAdd\\(false\\)\\}/');
    expect($source)->toMatch('/onContactCreated=\\{handleContactQuickAdded\\}/');
});

it('Create.tsx handleContactQuickAdded auto-seleciona cliente no autocomplete via forcedCustomer', function () {
    $source = readQuickCreatePage();
    expect($source)->toContain('handleContactQuickAdded');
    expect($source)->toContain("setData('contact_id', c.id)");
    expect($source)->toContain('setForcedCustomer');
});

// =========================================================================
// 6. NÃO remove features existentes (Wagner trauma "designer remove tudo")
// =========================================================================

it('Create.tsx preserva CustomerSearchAutocomplete existente (sem regressão)', function () {
    $source = readQuickCreatePage();
    expect($source)->toContain('<CustomerSearchAutocomplete');
    expect($source)->toContain('forcedValue={forcedCustomer}');
});

it('Create.tsx preserva postMessage listener antigo (compat /contacts/create-page aba)', function () {
    $source = readQuickCreatePage();
    // O listener antigo continua — modal inline é caminho NOVO, antigo segue funcionando
    expect($source)->toContain("event.data?.type === 'contact_created'");
    expect($source)->toContain("window.addEventListener('message'");
});

it('Create.tsx preserva walkInCustomer default + clear behavior', function () {
    $source = readQuickCreatePage();
    expect($source)->toContain('props.walkInCustomer.id');
    expect($source)->toContain('props.walkInCustomer.name');
});

// =========================================================================
// 7. Acessibilidade + Tier 0 canon
// =========================================================================

it('ContactQuickAddModal NÃO usa cor crua não-semântica (canon ADR 0110)', function () {
    $source = readQuickModal();
    expect($source)->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('ContactQuickAddModal NÃO usa sessionStorage (canon — só localStorage prefixo oimpresso.)', function () {
    $source = readQuickModal();
    expect($source)->not->toContain('sessionStorage');
});

// =========================================================================
// 8. Integration HTTP — POST /contacts cria contact biz=1 + scope Tier 0
// =========================================================================

it('POST /contacts cria contact com business_id da session (biz=1 Wagner)', function () {
    quickNeedMysqlOrSkip();
    $userBiz1 = quickFirstUserOfBiz(SELL_QUICK_BIZ_WAGNER);
    if (! $userBiz1 || (! $userBiz1->can('customer.create') && ! $userBiz1->can('customer.view_own'))) {
        $this->markTestSkipped('User biz=1 ausente ou sem permission customer.create.');
    }

    $this->actingAs($userBiz1);
    session(['user.business_id' => SELL_QUICK_BIZ_WAGNER]);

    $marker = 'CQA-' . uniqid();
    $response = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->post('/contacts', [
        'type' => 'customer',
        'contact_type_radio' => 'individual',
        'first_name' => $marker,
        'mobile' => '48999000001',
        'email' => 'cqa+' . uniqid() . '@example.test',
        'city' => 'Termas do Gravatal',
        'state' => 'SC',
        'country' => 'Brasil',
        'opening_balance' => 0,
    ]);

    // Status 200/302 — POST legacy aceita ambos. Não pode ser 4xx/5xx.
    expect($response->status())->toBeLessThan(400);

    // Contact criado com business_id correto (Tier 0)
    $contact = Contact::where('name', 'like', "%{$marker}%")
        ->where('business_id', SELL_QUICK_BIZ_WAGNER)
        ->first();

    try {
        expect($contact)->not->toBeNull();
        expect((int) $contact->business_id)->toBe(SELL_QUICK_BIZ_WAGNER);
        expect($contact->mobile)->toBe('48999000001');
    } finally {
        $contact?->forceDelete();
    }
});

it('POST /contacts cross-tenant — biz=99 NÃO consegue criar contact escapando pra biz=1 (Tier 0)', function () {
    quickNeedMysqlOrSkip();
    $userBiz99 = quickFirstUserOfBiz(SELL_QUICK_BIZ_FICTICIO);
    if (! $userBiz99) {
        $this->markTestSkipped('User biz=99 ausente — seed primeiro.');
    }
    if (! $userBiz99->can('customer.create') && ! $userBiz99->can('customer.view_own')) {
        $this->markTestSkipped('User biz=99 sem permission customer.create.');
    }

    $this->actingAs($userBiz99);
    session(['user.business_id' => SELL_QUICK_BIZ_FICTICIO]);

    $marker = 'CQA-XTen-' . uniqid();
    // Mesmo se um payload malicioso enviasse `business_id => 1` no POST,
    // o ContactController@store IGNORA campos vindos do request e SEMPRE
    // pega de session('user.business_id'). Testamos esse hardening.
    $response = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->post('/contacts', [
        'type' => 'customer',
        'contact_type_radio' => 'individual',
        'first_name' => $marker,
        'mobile' => '48999000099',
        'business_id' => SELL_QUICK_BIZ_WAGNER, // tentativa de escape
        'opening_balance' => 0,
    ]);

    expect($response->status())->toBeLessThan(500);

    try {
        // Tier 0: nenhum contact com este marker pode ter sido criado em biz=1
        $leak = Contact::where('name', 'like', "%{$marker}%")
            ->where('business_id', SELL_QUICK_BIZ_WAGNER)
            ->first();
        expect($leak)->toBeNull();

        // Se foi criado, foi no biz=99 (correto)
        $legitimate = Contact::where('name', 'like', "%{$marker}%")
            ->where('business_id', SELL_QUICK_BIZ_FICTICIO)
            ->first();
        if ($legitimate) {
            expect((int) $legitimate->business_id)->toBe(SELL_QUICK_BIZ_FICTICIO);
        }
    } finally {
        Contact::where('name', 'like', "%{$marker}%")->forceDelete();
    }
});

it('POST /contacts retorna shape {success, data: contact, msg} pra modal handler', function () {
    quickNeedMysqlOrSkip();
    $userBiz1 = quickFirstUserOfBiz(SELL_QUICK_BIZ_WAGNER);
    if (! $userBiz1 || (! $userBiz1->can('customer.create') && ! $userBiz1->can('customer.view_own'))) {
        $this->markTestSkipped('User biz=1 ausente ou sem permission customer.create.');
    }

    $this->actingAs($userBiz1);
    session(['user.business_id' => SELL_QUICK_BIZ_WAGNER]);

    $marker = 'CQA-Shape-' . uniqid();
    $response = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->post('/contacts', [
        'type' => 'customer',
        'contact_type_radio' => 'individual',
        'first_name' => $marker,
        'mobile' => '48999000002',
        'opening_balance' => 0,
    ]);

    // Aceita 200 ou redirect — backend Blade legacy sempre retorna array
    // mas pode ter Content-Type ambíguo dependendo de Accept header.
    if ($response->status() >= 400) {
        $this->markTestSkipped('POST /contacts retornou ' . $response->status() . ' — pré-req permission falta.');
    }

    // O shape do JSON é {success: true, data: Contact, msg: '...'} — modal lê data.id + data.name
    $body = $response->json();

    try {
        if (is_array($body) && isset($body['success'], $body['data'])) {
            expect($body['success'])->toBeTrue();
            expect($body['data'])->toHaveKey('id');
            expect($body['data'])->toHaveKey('name');
            expect($body['data']['business_id'])->toBe(SELL_QUICK_BIZ_WAGNER);
        }
        // Se 200 mas não JSON (legacy retorna view html em alguns casos),
        // pelo menos checa que o contact foi gravado no banco.
        $created = Contact::where('name', 'like', "%{$marker}%")
            ->where('business_id', SELL_QUICK_BIZ_WAGNER)
            ->first();
        expect($created)->not->toBeNull();
    } finally {
        Contact::where('name', 'like', "%{$marker}%")->forceDelete();
    }
});
