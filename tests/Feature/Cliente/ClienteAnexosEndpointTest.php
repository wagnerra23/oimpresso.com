<?php

declare(strict_types=1);

/**
 * Wagner 2026-06-01 — endpoint de anexos do cliente (drawer Operacoes -> Documentos).
 * GET /cliente/{id}/anexos devolve os arquivos (media) anexados aos document-notes
 * do contato como JSON, fechando o gap Wave D (o painel nao carregava os anexos
 * existentes — mostrava "Anexos (0)" mesmo com arquivos salvos, divergindo do
 * contador "N anexos" do header introduzido no PR #2082).
 *
 * GUARDs estruturais (file_get_contents, sem DB) + 1 integracao (auth gate),
 * mesmo bar do GUARD 12 de ClienteListagemTurbinadaTest (/cliente/export).
 *
 * Refs: ADR 0093 (multi-tenant Tier 0) · ADR 0179 (drawer 760) · PR #2082.
 */

// ─── GUARD 1: rota registrada ─────────────────────────────────────────────

test('GUARD 1 — routes/web.php registra GET /cliente/{id}/anexos', function () {
    $path = __DIR__ . '/../../../routes/web.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Route::get('/cliente/{id}/anexos', [ContactController::class, 'anexos'])")
        ->toContain("->name('cliente.anexos.index')");
});

// ─── GUARD 2: ContactController::anexos — tenant scope + larastan-safe ─────

test('GUARD 2 — ContactController::anexos com business_id scope em todas as queries', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function anexos($id)')
        // Tier 0 (ADR 0093 IRREVOGAVEL): contato + notas + media TODOS scoped.
        ->toContain("Contact::where('business_id', \$business_id)->findOrFail(\$id)")
        ->toContain("DocumentAndNote::where('business_id', \$business_id)")
        ->toContain("Media::where('business_id', \$business_id)")
        ->toContain("->where('model_type', \\App\\DocumentAndNote::class)")
        // shape DocumentItem (download via accessor display_url do Media).
        ->toContain("'download_url' => \$m->display_url")
        ->toContain("response()->json(['documents' => \$documents])");
});

// ─── GUARD 3: DocumentsTab carrega anexos no mount ────────────────────────

test('GUARD 3 — DocumentsTab busca /cliente/{id}/anexos no mount (guarda docsProp)', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('/cliente/${contactId}/anexos')
        // Nao sobrescreve uso prop-driven/SSR.
        ->toContain('if (docsProp !== undefined) return');
});

// ─── GUARD 4: endpoint exige sessao (integracao, nao vaza sem auth) ───────

test('GUARD 4 — GET /cliente/{id}/anexos nao vaza anexos sem auth', function () {
    // Cross-tenant real precisa de DB+seed que sqlite memory CI nao tem;
    // skip-graceful pattern canon (igual GUARD 12 /cliente/export).
    if (! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $response = $this->get('/cliente/1/anexos');
    // Sem auth: redirect login (302) ou 401/403/404. O importante e NAO 200
    // (vazaria anexos sem sessao) nem 500 (erro de codigo).
    expect(in_array($response->status(), [302, 401, 403, 404], true))->toBeTrue();
});
