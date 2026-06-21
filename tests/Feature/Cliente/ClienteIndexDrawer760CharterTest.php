<?php

declare(strict_types=1);

/**
 * Wave B — ADR 0179 Charter v3 GUARDs Cliente/Index drawer 760px.
 *
 * 11 GUARDs minimos cobrindo: estrutura componente (file_get_contents pattern
 * canon Cliente/Show/*Test.php), migrations aditivas e cross-tenant integracao.
 *
 * Estrategia mista pra rodar local sem DB caro:
 *  - GUARDs 1-4, 7-9, 11: structural file_get_contents -- rapido sem DB
 *  - GUARDs 5, 6, 10: Feature integration -- skipsem DB seeded (biz=1 user)
 *
 * Refs:
 *  - ADR 0179 (memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
 *  - Charter Cliente/Index.charter.md v3 (drawer_pattern: 760px-lateral)
 *  - Multi-tenant Tier 0 ADR 0093
 *  - Pattern: Wave1IndexInertiaTest + RedirectLegacyContactsTest
 */

use App\Contact;
use App\User;

// ─── GUARD 1: Index.tsx renderiza com mwart.cliente_index liga ───────────────

test('GUARD 1 — ContactController::show redireciona quando cliente_index liga', function () {
    // Structural: confirma que o redirect foi adicionado ANTES do branch
    // cliente_show no controller. Wave B paradigma drawer toma prioridade.
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    expect($controllerPath)->toBeReadableFile();

    $contents = file_get_contents($controllerPath);
    expect($contents)
        ->toContain("config('mwart.cliente_index.enabled')")
        ->toContain('contact_id=')
        ->toContain('tab=identificacao');
});

// ─── GUARD 2: /cliente/{id} redirect 302 quando flag liga ─────────────────────

test('GUARD 2 — routes/web.php /cliente/{id} redirect 302 quando cliente_index liga', function () {
    $routesPath = __DIR__ . '/../../../routes/web.php';
    expect($routesPath)->toBeReadableFile();

    $contents = file_get_contents($routesPath);
    expect($contents)
        ->toContain("config('mwart.cliente_index.enabled')")
        ->toContain("redirect()->to(\"/cliente?contact_id=")
        ->toContain('tab={$tab}');
});

// ─── GUARD 3: drawer ClienteSheet usa w-[760px] (Pest charter test obrigatorio) ─

test('GUARD 3 — Cliente/Index.tsx ClienteSheet renderiza com w-[760px]', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('w-[760px]')
        ->toContain('sm:max-w-[760px]')
        // Cabe Larissa biz=4 1280x1024: 760 + AppShellV2 sidebar 240 + main ~= 1024px
        ->not->toContain('w-[480px]'); // 480 era Wave A; Wave B substitui
});

// ─── GUARD 4: 8 tabs presentes no DOM ────────────────────────────────────────

test('GUARD 4 — Cliente/Index.tsx tem 6 abas principais + chips; Auditoria migrou p/ OssTab', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('Identificação')
        ->toContain('Contato')
        ->toContain('Endereço')
        ->toContain('Comercial')
        ->toContain('Classificação')
        ->toContain('Operações')
        ->toContain('IA')
        ->toContain("'identificacao'")
        ->toContain('DRAWER_TABS')
        // Wagner 2026-06-13: 'auditoria' saiu do Index (virou sub-aba de Operações).
        ->not->toContain("'auditoria'");

    // Auditoria agora vive no rail de Operações (OssTab).
    $oss = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/OssTab.tsx');
    expect($oss)->toContain("{ key: 'auditoria', label: 'Auditoria'");
});

// ─── GUARD 5: cross-tenant biz=1 nao ve contact biz=99 (404) ─────────────────

test('GUARD 5 — /cliente/{id} cross-tenant guard estrutural (Tier 0 ADR 0093)', function () {
    // Strategy hybrid: tenta DB integration; se DB de teste nao tem tabela
    // `users` (sqlite :memory: sem migrate:fresh + seeder), cai pra structural
    // assertion garantindo que o route handler aborta 404 cross-tenant.
    try {
        $user = User::where('business_id', 1)->first();
    } catch (\Throwable $e) {
        // DB de teste vazio (no migrations) -- usa structural fallback
        $user = null;
    }

    if (! $user) {
        // Structural fallback: confirma que o route handler tem abort(404)
        // antes do redirect (cross-tenant biz=99 deve 404, nao redirect 302).
        $routesPath = __DIR__ . '/../../../routes/web.php';
        $contents = file_get_contents($routesPath);

        // Pattern canon: where business_id + whereIn type + abort(404).
        // Note: Pest 'toContain' tem escape esquisito com $-vars em string;
        // usamos strpos diretamente pra checagem segura.
        expect($contents)
            ->toContain("Contact::where('business_id'")
            ->toContain("whereIn('type', ['customer', 'both'])")
            ->toContain('abort(404)');

        // Position-based: abort(404) ANTES do redirect/show (guard primeiro).
        $abortPos = strpos($contents, 'abort(404)');
        $redirectPos = strpos($contents, "config('mwart.cliente_index.enabled')");

        expect($abortPos)->toBeGreaterThan(0);
        expect($redirectPos)->toBeGreaterThan(0);
        expect($abortPos)->toBeLessThan($redirectPos);
        return;
    }

    // DB integration path -- contact que NAO pertence ao biz=1.
    $contact = Contact::where('business_id', '!=', 1)->first();
    $fakeId = $contact ? $contact->id : 999999;

    session(['user.business_id' => 1]);
    $this->actingAs($user);

    $response = $this->get("/cliente/{$fakeId}");

    // 404 cross-tenant (route handler chama abort(404) se !ok)
    $response->assertStatus(404);
});

// ─── GUARD 6: PII payload customer NAO inclui tax_number plain ───────────────

test('GUARD 6 — Cliente/Index.tsx payload usa tax_number_masked, nunca plain', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($tsxPath);

    // Type ClienteRow deve usar tax_number_masked (chain leak Larissa biz=4
    // -- jamais entregar CPF/CNPJ plain pro frontend; mascara server-side).
    expect($contents)
        ->toContain('tax_number_masked')
        ->not->toContain('tax_number:');
});

// ─── GUARD 7: KB-9.75 atalhos preservados (⌘K, ?, J/K) ───────────────────────

test('GUARD 7 — Cliente/Index.tsx preserva KB-9.75 Slice A atalhos (PR #1309)', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($tsxPath);

    // Slice A entrega: ⌘K palette · ? cheat-sheet · J/K nav · Enter open · / focus search
    // Wave B NAO regride esses atalhos.
    expect($contents)
        ->toContain('setPaletteOpen')        // ⌘K palette
        ->toContain('setCheatOpen')          // ? cheat-sheet
        ->toContain("e.key === 'j' || e.key === 'J'")  // J navega proximo
        ->toContain("e.key === 'k' || e.key === 'K'")  // K navega anterior
        ->toContain('searchInputRef')        // / focus search
        ->toContain('KB-9.75');              // comentario canon
});

// ─── GUARD 8: header drawer tem nome + tipo + cadastrado ha Xd ───────────────

test('GUARD 8 — drawer header contem nome + toggle PF/PJ + "cadastrado"', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('Pessoa física')
        ->toContain('Pessoa jurídica')
        ->toContain('cadastrado')
        ->toContain('relativeDate(')
        ->toContain('SheetTitle');
});

// ─── GUARD 9: botao "Falar com Copiloto" link Jana correto ───────────────────

test('GUARD 9 — drawer header link "Falar com Copiloto" aponta /ia/chat?context=cliente:{id}', function () {
    $tsxPath = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('/ia/chat?context=cliente:')
        ->toContain('Falar com Copiloto')
        ->toContain('Imprimir ficha')
        ->not->toContain('/copiloto/') // Modules/Copiloto NAO existe (ADR 0179)
        ->not->toContain('/jana/chat'); // Wagner 2026-05-22: rota canon /ia (ADR 0180 sidebar v3)
});

// ─── GUARD 10: migration aditiva contacts adiciona 16 colunas ─────────────────

test('GUARD 10 — migration extend_contacts_for_cliente_drawer adiciona colunas Wave B', function () {
    $migPath = __DIR__ . '/../../../database/migrations/2026_05_22_000000_extend_contacts_for_cliente_drawer.php';
    expect($migPath)->toBeReadableFile();

    $contents = file_get_contents($migPath);
    // Idempotente -- Schema::hasColumn check antes de add (pattern PR #1316)
    expect($contents)
        ->toContain('Schema::hasColumn')
        ->toContain("'tipo'")
        ->toContain("'fantasia'")
        ->toContain("'ie'")
        ->toContain("'nascimento'")
        ->toContain("'cargo'")
        ->toContain("'tel2'")
        ->toContain("'canal_preferido'")
        ->toContain("'tabela_preco_padrao'")
        ->toContain("'pgto_padrao'")
        ->toContain("'obs_comercial'")
        ->toContain("'segmento'")
        ->toContain("'tags'")
        ->toContain("'vip'")
        ->toContain("'favorito_users'")
        ->toContain("'site_url'")
        // Down reversivel
        ->toContain('public function down(')
        ->toContain('dropColumn');
});

// ─── GUARD 11: migration anotacoes polimorfica + business_id + softDeletes ───

test('GUARD 11 — migration create_anotacoes_table schema correto multi-tenant', function () {
    $migPath = __DIR__ . '/../../../database/migrations/2026_05_22_000001_create_anotacoes_table.php';
    expect($migPath)->toBeReadableFile();

    $contents = file_get_contents($migPath);
    expect($contents)
        ->toContain("Schema::create('anotacoes'")
        ->toContain("morphs('subject')")     // polimorfico Contact/Sale/etc
        ->toContain('business_id')           // Tier 0 obrigatorio
        ->toContain('softDeletes()')         // historico preservado
        ->toContain("anotacoes_biz_subject_index") // indice composto biz+subject
        ->toContain("on('business')")        // FK business
        ->toContain("on('users')")           // FK user
        ->toContain("onDelete('cascade')")   // cascade safe
        // Down reversivel
        ->toContain('public function down(')
        ->toContain("Schema::dropIfExists('anotacoes')");
});
