<?php

declare(strict_types=1);
// @covers-us US-CRM-066

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

// ════════════════════════════════════════════════════════════════════════
// Wagner 2026-06-01 — habilita ENVIAR/EXCLUIR anexos no drawer (era read-only:
// botoes ocultos + endpoint legado /post-document-upload nao persistia).
// ════════════════════════════════════════════════════════════════════════

// ─── GUARD 5: rotas POST + DELETE registradas ─────────────────────────────

test('GUARD 5 — routes/web.php registra POST + DELETE /cliente/{id}/anexos', function () {
    $path = __DIR__ . '/../../../routes/web.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Route::post('/cliente/{id}/anexos', [ContactController::class, 'storeAnexo'])")
        ->toContain("->name('cliente.anexos.store')")
        ->toContain("Route::delete('/cliente/{id}/anexos/{mediaId}', [ContactController::class, 'destroyAnexo'])")
        ->toContain("->name('cliente.anexos.destroy')");
});

// ─── GUARD 6: storeAnexo/destroyAnexo — tenant scope + persiste ───────────

test('GUARD 6 — storeAnexo/destroyAnexo com business_id scope + persistencia', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function storeAnexo(Request $request, $id)')
        ->toContain('public function destroyAnexo($id, $mediaId)')
        // Tier 0: contato resolvido DENTRO do business nos dois metodos.
        ->toContain("Contact::where('business_id', \$business_id)->findOrFail(\$id)")
        // Upload persiste: cria document-note + anexa media (helper canon).
        ->toContain('$contact->documentsAndnote()->create(')
        ->toContain("Media::uploadMedia(\$business_id, \$note, \$request, 'file', true)")
        // Delete valida que o media pertence a um note DESTE contato (scope).
        ->toContain("->where('model_type', \\App\\DocumentAndNote::class)")
        ->toContain('->firstOrFail()');
});

// ─── GUARD 7: frontend usa endpoints novos + permissoes habilitadas ───────

test('GUARD 7 — DocumentsTab usa endpoints novos + Index habilita permissoes', function () {
    $docTab = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx');
    $index = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx');

    expect($docTab)
        // Upload/delete pelos endpoints dedicados (recarrega do backend).
        ->toContain('await loadDocuments()')
        ->toContain('/cliente/${contactId}/anexos/${docId}')
        // Endpoint legado quebrado NAO e mais usado.
        ->not->toContain('/post-document-upload');

    // Index passa permissoes pro OssTab (senao botoes ficam ocultos no drawer).
    expect($index)
        ->toContain('permissions={{ upload: true, delete_document: true, edit_note: true }}');
});

// ─── GUARD 8: chip do header reflete contagem VIVA (Wagner: "nao somou") ───

test('GUARD 8 — chip anexos sincroniza contagem viva apos upload/delete', function () {
    $docTab = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx');
    $ossTab = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/OssTab.tsx');
    $index = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx');

    // DocumentsTab reporta a contagem viva + GET sem cache (pos-upload fresco).
    expect($docTab)
        ->toContain('onCountChange?: (count: number) => void')
        ->toContain('onCountChange?.(data.documents.length)')
        ->toContain("cache: 'no-store'");

    // OssTab repassa o callback pro DocumentsTab.
    expect($ossTab)
        ->toContain('onDocumentsCountChange')
        ->toContain('onCountChange={onDocumentsCountChange}');

    // Index: chip usa o count vivo (cai pro payload quando ausente) + wiring.
    expect($index)
        ->toContain('liveAnexosCount ?? contact?.documents_count ?? 0')
        ->toContain('onDocumentsCountChange={setLiveAnexosCount}');
});
