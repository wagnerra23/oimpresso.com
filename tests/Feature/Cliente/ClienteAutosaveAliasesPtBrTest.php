<?php

declare(strict_types=1);

/**
 * Fix Daniela 2026-05-27 — drawer Cliente nao salvava Razao Social/CNPJ/Tel
 * principal porque frontend envia chaves PT-BR (`nome`, `doc`, `tel`, `site`,
 * `canal`) mas backend validator so aceita canon EN (`name`, `tax_number`,
 * `mobile`, `site_url`, `canal_preferido`).
 *
 * Validator filtra com `validated()` -> chaves desconhecidas viram silent no-op.
 * `Eloquent::update([])` retorna sem persistir. Badge "Salvo" verde aparece
 * (status 200) mas dado nunca chega no banco.
 *
 * Fix: normalizar aliases ANTES do validator (input map).
 * Plus: campo `contato` (Nome do responsavel principal) ganhou coluna nova
 * via migration 2026_05_27_180000_add_contato_to_contacts.
 *
 * GUARDs estruturais (file_get_contents, sem DB).
 */

// ─── GUARD 1: identificacao normaliza nome/doc + aceita contato ───────────

test('GUARD 1 — identificacao() normaliza nome->name + doc->tax_number + aceita contato', function () {
    $path = __DIR__ . '/../../../Modules/Crm/Http/Controllers/ClienteAutosaveController.php';
    $contents = file_get_contents($path);

    expect($contents)
        // Normalizacao input antes do validator.
        ->toContain("array_key_exists('nome', \$input)")
        ->toContain("\$input['name'] = \$input['nome']")
        ->toContain("array_key_exists('doc', \$input)")
        ->toContain("\$input['tax_number'] = \$input['doc']")
        // Validator aceita `contato` novo.
        ->toContain("'contato' => ['nullable', 'string', 'max:100']");
});

// ─── GUARD 2: contato() normaliza tel/site/canal ──────────────────────────

test('GUARD 2 — contato() normaliza tel->mobile + site->site_url + canal->canal_preferido', function () {
    $path = __DIR__ . '/../../../Modules/Crm/Http/Controllers/ClienteAutosaveController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("array_key_exists('tel', \$input)")
        ->toContain("\$input['mobile'] = \$input['tel']")
        ->toContain("array_key_exists('site', \$input)")
        ->toContain("\$input['site_url'] = \$input['site']")
        ->toContain("array_key_exists('canal', \$input)")
        ->toContain("\$input['canal_preferido'] = \$input['canal']");
});

// ─── GUARD 3: shapeContactResponse retorna contato ────────────────────────

test('GUARD 3 — shapeContactResponse retorna contato (anti-regressao)', function () {
    $path = __DIR__ . '/../../../Modules/Crm/Http/Controllers/ClienteAutosaveController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'contato' => \$contact->contato ?? null");
});

// ─── GUARD 4: Migration cria coluna contato ───────────────────────────────

test('GUARD 4 — Migration 2026_05_27_180000 cria coluna contato VARCHAR(100) nullable', function () {
    $path = __DIR__ . '/../../../database/migrations/2026_05_27_180000_add_contato_to_contacts.php';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain("Schema::hasColumn('contacts', 'contato')")
        ->toContain("string('contato', 100)")
        ->toContain('->nullable()')
        ->toContain("->after('cargo')");
});

// ─── GUARD 5: buildClienteIndexCustomers select + payload com contato ─────

test('GUARD 5 — ContactController select + payload contato graceful', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Schema::hasColumn('contacts', 'contato')")
        ->toContain("'contacts.contato'")
        ->toMatch("/\\\$payload\\['contato'\\]\\s*=/");
});
