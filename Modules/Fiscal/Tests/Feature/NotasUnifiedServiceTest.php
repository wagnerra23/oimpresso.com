<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Services\NotasUnifiedService;

uses(Tests\TestCase::class);

/**
 * `NotasUnifiedService` — a lista do cockpit Fiscal vem de DADO REAL.
 *
 * ÂNCORA DE CONTRATO (não deriva do código — deriva da regra escrita):
 *   CU-FISC-16 · memory/requisitos/Fiscal/_STATUS-GENERATED.md
 *     "Distinguir dado real de dado de demonstração"
 *
 * POR QUE EXISTE: até 2026-09-02 o `CockpitController` servia `mockNotasUnificadas()`
 * — 10 notas fictícias. Medido na produção viva no mesmo dia, a tela exibia três
 * números para a mesma coisa: header "0 notas" (KPI real), lista com 10 linhas (mock)
 * e chip "Todas 18" (outro mock). O operador não tinha como saber qual era o real,
 * e o certificado A1 estava vencido há 26 dias no mesmo cockpit.
 *
 * CONTROLE ANTI-VÁCUO: o caso das CHAVES existe porque o frontend lê
 * `savedViewCounts[v.id] ?? 0` — chave divergente NÃO dá erro, o chip só mostra 0.
 * Um teste que apenas contasse "6 contadores" passaria com as 6 chaves erradas.
 * Aqui a asserção é sobre os ids LITERAIS que o Cockpit.tsx declara.
 */
beforeEach(function () {
    $this->service = new NotasUnifiedService();
});

it('CU-FISC-16 · sem emissão real, a lista vem VAZIA — nunca preenchida com demonstração', function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }

    $notas = $this->service->listar();

    expect($notas)->toBeArray();

    // O ponto do caso: seja qual for a contagem, ela NÃO pode conter as fixtures do
    // mock antigo. Estes clientes só existiam em mockNotasUnificadas().
    $clientes = array_column($notas, 'cliente');
    expect($clientes)->not->toContain('TechPro Equipamentos');
    expect($clientes)->not->toContain('Imobiliária Horizonte');
    expect($clientes)->not->toContain('Gráfica Ribeirão Ltda');
});

it('CU-FISC-16 · os contadores usam os ids LITERAIS das visões salvas do Cockpit.tsx', function () {
    // Cockpit.tsx:158-163 — SAVED_VIEWS. Se alguém renomear lá e esquecer aqui, o chip
    // cai pra 0 em silêncio (o `?? 0` engole). Este caso é o que impede isso.
    $esperados = ['todas', 'resolver', 'janela24', 'processando', 'nfse', 'nfce'];

    $contadores = $this->service->contadores([]);

    expect(array_keys($contadores))->toEqualCanonicalizing($esperados);
    foreach ($esperados as $id) {
        expect($contadores[$id])->toBeInt();
    }
});

it('CU-FISC-16 · os contadores derivam da MESMA lista — chip e tabela não podem divergir', function () {
    // Era daqui que saía o "Todas 18" contra 10 linhas renderizadas: os dois vinham de
    // mocks diferentes. Derivando da lista, `todas` é a contagem da lista, por construção.
    $notas = [
        ['kind' => 'nfe',  'statusKind' => 'sefaz', 'status' => 100, 'modelo' => 55, 'prazoCancel' => ['label' => '2d', 'urgency' => 'ok']],
        ['kind' => 'nfe',  'statusKind' => 'sefaz', 'status' => 110, 'modelo' => 65, 'prazoCancel' => null],
        ['kind' => 'nfse', 'statusKind' => 'nfse',  'status' => 'processando', 'modelo' => null, 'prazoCancel' => null],
    ];

    $c = $this->service->contadores($notas);

    expect($c['todas'])->toBe(count($notas));   // a régua: chip == tabela
    expect($c['resolver'])->toBe(1);            // só o cstat 110
    expect($c['janela24'])->toBe(1);            // só quem tem prazoCancel
    expect($c['processando'])->toBe(1);         // a NFS-e
    expect($c['nfse'])->toBe(1);
    expect($c['nfce'])->toBe(1);                // modelo 65
});

it('CU-FISC-16 · o serviço é READ-ONLY — não escreve em nfe_emissoes nem nfse_emissoes', function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }

    $antes = DB::table('nfe_emissoes')->count();
    $this->service->listar();
    $depois = DB::table('nfe_emissoes')->count();

    expect($depois)->toBe($antes);
});
