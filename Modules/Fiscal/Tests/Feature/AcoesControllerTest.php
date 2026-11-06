<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * PR #4 Wave Ações Mutação Fiscal — a superfície das ações chega ao AcoesController (UC-FNFE-08 a).
 *
 * Histórico: em 2026-07-28 duas podas tiraram daqui 13 casos tautológicos (validator local, array
 * literal). Os 5 que ficaram eram estruturais — `method_exists`, assinatura por reflexão,
 * `Route::has`, `class_exists` — e continuavam verdes com a rota apontando para outro método.
 * Em 2026-11-06 (auditoria Fiscal lote 2.3) viraram requisições de verdade: cada rota POST
 * responde com a recusa que só o método certo do AcoesController produz.
 *
 * A validação de cada campo (limites de texto, faixa) tem prova própria em AcoesContratoTest;
 * a escrita entre empresas em AcoesEscritaEntreEmpresasTest; o gate de permissão em
 * GatesPermissaoFiscalTest. Aqui só a fiação rota → método.
 */

const ACOES_ID_INEXISTENTE = 2_147_480_000;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: as ações leem nfe_emissoes/nfe_dfe_recebidos (MySQL)');
    }
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_dfe_recebidos')) {
        $this->markTestSkipped('Tabelas NfeBrasil ausentes — rodar migrate primeiro');
    }

    $tenant = $this->seededTenant();
    $biz = (int) $tenant->id;
    $user = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.nfe.acoes', 'fiscal.dfe.manage'], $tenant);
    $this->actingAs($user);
    session(['business.id' => $biz, 'user.business_id' => $biz]);
});

it('UC-FNFE-08 · cancelar, CC-e e inutilizar chegam ao controller e validam o corpo (422)', function () {
    $id = ACOES_ID_INEXISTENTE;

    $this->postJson("/fiscal/acoes/nfe/{$id}/cancelar", [])
        ->assertStatus(422)->assertJsonValidationErrors('motivo');
    $this->postJson("/fiscal/acoes/nfe/{$id}/cce", [])
        ->assertStatus(422)->assertJsonValidationErrors(['texto_correcao', 'n_seq_evento']);
    $this->postJson('/fiscal/acoes/nfe/inutilizar', [])
        ->assertStatus(422)->assertJsonValidationErrors(['modelo', 'serie', 'numero_de', 'numero_ate', 'justificativa']);
});

it('UC-FNFE-08 · com o corpo válido, cancelar e CC-e procuram a nota (404 para id inexistente)', function () {
    $id = ACOES_ID_INEXISTENTE;

    $this->postJson("/fiscal/acoes/nfe/{$id}/cancelar", ['motivo' => 'Erro de digitação no destinatário'])
        ->assertStatus(404);
    $this->postJson("/fiscal/acoes/nfe/{$id}/cce", ['texto_correcao' => 'Corrige o endereço do destinatário', 'n_seq_evento' => 1])
        ->assertStatus(404);
});

it('UC-FNFE-08 · retransmitir procura a nota (404 para id inexistente)', function () {
    $this->postJson('/fiscal/acoes/nfe/' . ACOES_ID_INEXISTENTE . '/retransmitir')
        ->assertStatus(404);
});

it('UC-FNFE-08 · manifestar DF-e procura o documento (404) e exige justificativa em desconhecer (422)', function () {
    $id = ACOES_ID_INEXISTENTE;

    $this->postJson("/fiscal/acoes/dfe/{$id}/cienciar")->assertStatus(404);
    $this->postJson("/fiscal/acoes/dfe/{$id}/desconhecer", [])
        ->assertStatus(422)->assertJsonValidationErrors('justificativa');
});

it('UC-FNFE-08 · controle: sem fiscal.nfe.acoes a mesma rota recusa antes da validação (403)', function () {
    $tenant = $this->seededTenant();
    $semAcoes = $this->usuarioComPermissoes(['fiscal.access'], $tenant);
    $this->actingAs($semAcoes);

    $this->postJson('/fiscal/acoes/nfe/' . ACOES_ID_INEXISTENTE . '/cancelar', [])->assertStatus(403);
});
