<?php

declare(strict_types=1);
// Cobre UC-CIDX-14 (Index.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anotação da aba Documentos da ficha React (`_show/DocumentsTab`, também na gaveta da lista de
 * clientes). A tela salva sozinha enquanto se digita: o primeiro salvamento cria a nota
 * (POST /note-documents) e os seguintes a atualizam (PUT /note-documents/{id}) — desde que o
 * servidor devolva o id. Antes de 2026-11-12 ele não devolvia, e cada pausa na digitação criava
 * uma nota nova.
 *
 * Tenant de teste 98 (ADR 0358). Textos fictícios.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts') || ! Schema::hasTable('document_and_notes')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);

    $this->contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id, 'created_by' => $this->user->id, 'type' => 'customer',
        'name' => 'Cliente Anotacao '.uniqid(), 'contact_status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

/** POST como a aba Documentos manda (FormData, XHR, JSON). */
function canSalvar($teste, array $campos, ?int $id = null)
{
    if ($id !== null) {
        $campos['_method'] = 'PUT';
    }

    return $teste->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post($id === null ? '/note-documents' : "/note-documents/{$id}", $campos);
}

test('UC-CIDX-14 a anotação criada volta com o id, e salvar de novo atualiza a mesma nota', function () {
    $base = ['notable_id' => $this->contato, 'notable_type' => 'App\\Contact'];

    $criada = canSalvar($this, $base + ['description' => 'ZQ primeira parte']);
    $criada->assertOk();
    expect($criada->json('success'))->toBeTrue();
    $id = (int) $criada->json('note.id');
    expect($id)->toBeGreaterThan(0);

    canSalvar($this, $base + ['description' => 'ZQ primeira parte e a segunda'], $id)
        ->assertOk()->assertJsonPath('success', true);

    $notas = DB::table('document_and_notes')
        ->where('notable_id', $this->contato)->where('notable_type', 'App\\Contact')->get();
    expect($notas)->toHaveCount(1);
    expect($notas->first()->description)->toBe('ZQ primeira parte e a segunda');
    expect((int) $notas->first()->business_id)->toBe((int) $this->business->id);
});
