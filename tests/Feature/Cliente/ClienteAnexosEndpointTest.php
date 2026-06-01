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

// ─── GUARD 5: rotas POST + DELETE registradas ─────────────────────────────
// Wagner 2026-06-01 — upload/exclusao de anexo (fecha o gap do PR #2086 que so
// carregava). Endpoints novos no ContactController (espelham o GET anexos).

test('GUARD 5 — routes/web.php registra POST + DELETE /cliente/{id}/anexos', function () {
    $path = __DIR__ . '/../../../routes/web.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Route::post('/cliente/{id}/anexos', [ContactController::class, 'anexosStore'])")
        ->toContain("->name('cliente.anexos.store')")
        ->toContain("Route::delete('/cliente/{id}/anexos/{mediaId}', [ContactController::class, 'anexosDestroy'])")
        ->toContain("->name('cliente.anexos.destroy')");
});

// ─── GUARD 6: anexosStore — tenant scope + cria nota + uploadMedia + retorna ──

test('GUARD 6 — ContactController::anexosStore cria DocumentAndNote + media (Tier 0)', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function anexosStore(Request $request, $id)')
        // Tier 0 (ADR 0093): contato scoped antes de criar nada.
        ->toContain("Contact::where('business_id', \$business_id)->findOrFail(\$id)")
        // Cria 1 DocumentAndNote apontando pro contato (morph notable).
        ->toContain('new \App\DocumentAndNote()')
        ->toContain('$note->notable_type = \App\Contact::class')
        ->toContain('$note->business_id = $business_id')
        // Anexa o arquivo do campo `file` via helper canonico (is_single=true).
        ->toContain("Media::uploadMedia(\$business_id, \$note, \$request, 'file', true)")
        // Devolve o DocumentItem (mesmo shape do GET) pro React inserir sem refetch.
        ->toContain("'download_url' => \$media->display_url")
        ->toContain("'document' =>");
});

// ─── GUARD 7: anexosDestroy — valida media -> nota -> contato (Tier 0) + apaga ─

test('GUARD 7 — ContactController::anexosDestroy valida dono e exclui (Tier 0)', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function anexosDestroy(Request $request, $id, $mediaId)')
        // Tier 0: contato + media (model_type) + nota dona TODOS scoped business_id.
        ->toContain("Contact::where('business_id', \$business_id)->findOrFail(\$id)")
        ->toContain("Media::where('business_id', \$business_id)")
        ->toContain('->findOrFail($mediaId)')
        // A nota dona precisa ser um document-note DESTE contato (anti cross-tenant).
        ->toContain("->where('notable_type', \\App\\Contact::class)")
        ->toContain("->where('notable_id', \$id)")
        ->toContain("->where('id', \$media->model_id)")
        // Exclui arquivo + linha via helper canonico scoped business_id.
        ->toContain('Media::deleteMedia($business_id, $media->id)');
});

// ─── GUARD 8: DocumentsTab usa os endpoints novos (nao os legados quebrados) ──

test('GUARD 8 — DocumentsTab faz upload/exclusao pelos endpoints /cliente/{id}/anexos', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // Upload: campo `file` (que Media::uploadMedia le) pro POST anexos.
        ->toContain("fd.append('file', file)")
        // Exclusao: DELETE real por media id.
        ->toContain('/cliente/${contactId}/anexos/${mediaId}')
        ->toContain("method: 'DELETE'")
        // Nao usa mais o endpoint legado quebrado (arquivo orfao, sem `document`).
        ->not->toContain('/post-document-upload')
        ->not->toContain("fd.append('document', file)")
        // Nao manda mais media id pra DELETE /note-documents/{noteId} (semantica errada).
        ->not->toContain('/note-documents/${docId}');
});

// ─── GUARD 9: Index.tsx plumba permissoes reais pro OssTab/DocumentsTab ───────

test('GUARD 9 — Index.tsx passa permissions ao OssTab (botoes/textarea ativos)', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    // Sem essas flags o botao "Enviar", o excluir e a textarea de Notas ficam
    // inativos no drawer (default false do OssTab). Legado concede a quem ve o contato.
    expect($contents)
        ->toContain('upload: true,')
        ->toContain('delete_document: true,')
        ->toContain('edit_note: true,');
});

// ─── GUARD 10: endpoints de escrita exigem sessao (nao gravam sem auth) ───────

test('GUARD 10 — POST/DELETE /cliente/{id}/anexos nao escrevem sem auth', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $post = $this->post('/cliente/1/anexos');
    expect(in_array($post->status(), [302, 401, 403, 404, 419], true))->toBeTrue();

    $delete = $this->delete('/cliente/1/anexos/1');
    expect(in_array($delete->status(), [302, 401, 403, 404, 419], true))->toBeTrue();
});
