// Camada META — testa o auditor de corruptores SQLite (controle-negativo, 2 lados).
//
// Contexto (2026-06-15): a triagem refutada da cauda longa do floor SDD provou que o
// `sqlite-test-corruptors.mjs` tinha ~48% de FALSO-POSITIVO — text-match de `Schema::`
// que ignora (a) guarda `markTestSkipped`+driver, (b) DDL dentro de `toContain` (source-
// reader), (c) `DB::purge` (não-DDL). Este suite refuta a NOVA classificação:
//
//   SENSIBILIDADE  → ainda PEGA corruptor real (drop não-guardado roda no MySQL).
//                    O caso crítico: beforeEach guardado MAS afterEach SEM guarda
//                    (PHPUnit 12 roda teardown em teste pulado) → NÃO pode escapar.
//   ESPECIFICIDADE → NÃO acusa inocente (guardado dos 2 lados / source-reader / DB::purge).
//
// Risco-mãe (a suíte mente): ensinar o linter a ver guardas NÃO pode fazê-lo SUB-CONTAR.
// Os casos SENSIBILIDADE travam exatamente isso.
//
// Refs: memory/sessions/2026-06-15-sdd-longtail-triage-refuted.md · ADR 0276 (refutador).

import { describe, it, expect } from 'vitest';
import { classifySource } from '../scripts/audit/sqlite-test-corruptors.mjs';

describe('sqlite-corruptors — SENSIBILIDADE (pega corruptor real)', () => {
  it('drop NÃO-guardado de tabela CORE no beforeEach → corruptsOnMysql + alto-raio', () => {
    const src = `<?php
beforeEach(function () {
    Schema::dropIfExists('business');
    Schema::create('business', function (Blueprint $t) { $t->id(); });
});
it('faz algo', function () { $this->assertTrue(true); });`;
    const r = classifySource(src, 'X/BusinessDropTest.php');
    expect(r).not.toBeNull();
    expect(r.corruptsOnMysql).toBe(true);
    expect(r.quarantined).toBe(false);
    expect(r.highBlast).toContain('business');
    expect(r.tier === 'S' || r.tier === 'A').toBe(true);
  });

  it('drop via VARIÁVEL/loop (Schema::dropIfExists($tbl)) no afterEach NÃO escapa', () => {
    // Bug real pego no sanity (BomResolver): regex de literal perde drop dinâmico.
    const src = `<?php
beforeEach(function () {
    Schema::create('products', function (Blueprint $t) { $t->id(); });
});
afterEach(function () {
    foreach (['variations', 'products'] as $tbl) {
        Schema::dropIfExists($tbl);
    }
});
it('algo', function () { $this->assertTrue(true); });`;
    const r = classifySource(src, 'X/BomResolverTest.php');
    expect(r).not.toBeNull();
    expect(r.corruptsOnMysql).toBe(true);
  });

  it('POLARIDADE: drop dentro de if(!== sqlite){...} RODA no MySQL → corrompe (não sub-contar)', () => {
    // O guard de dual-mode NÃO pode confundir polaridade: `!== 'sqlite'` é TRUE no MySQL.
    const src = `<?php
beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        Schema::dropIfExists('business');
    }
});
it('algo', function () { $this->assertTrue(true); });`;
    const r = classifySource(src, 'X/NegIfTest.php');
    expect(r).not.toBeNull();
    expect(r.corruptsOnMysql).toBe(true);
  });

  it('CASO CRÍTICO: beforeEach guardado MAS afterEach SEM guarda ainda corrompe (Governance)', () => {
    const src = `<?php
beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('só sqlite');
    }
    Schema::create('mcp_audit_log', function (Blueprint $t) { $t->id(); });
});
afterEach(function () {
    Schema::dropIfExists('mcp_audit_log');
});
it('algo', function () { $this->assertTrue(true); });`;
    const r = classifySource(src, 'X/GovTest.php');
    expect(r).not.toBeNull();
    expect(r.corruptsOnMysql).toBe(true);       // teardown-sem-guarda roda no MySQL
    expect(r.quarantined).toBe(false);          // NÃO pode ser marcado seguro
    expect(r.reasons.join(' ')).toMatch(/teardown/);
  });
});

describe('sqlite-corruptors — ESPECIFICIDADE (não acusa inocente)', () => {
  it('guardado dos DOIS lados (beforeEach skip + afterEach return) → seguro', () => {
    const src = `<?php
beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('só sqlite');
    }
    Schema::create('rb_plans', function (Blueprint $t) { $t->id(); });
});
afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }
    Schema::dropIfExists('rb_plans');
});
it('algo', function () { $this->assertTrue(true); });`;
    const r = classifySource(src, 'X/Wave6Test.php');
    expect(r).not.toBeNull();
    expect(r.corruptsOnMysql).toBe(false);
    expect(r.quarantined).toBe(true);           // efetivamente guardado
  });

  it('dual-mode if(=== sqlite){DDL} else {row-delete} → seguro (DDL só no sqlite)', () => {
    const src = `<?php
beforeEach(function () {
    if (config('database.default') === 'sqlite') {
        Schema::dropIfExists('nfe_emissoes');
        Schema::create('nfe_emissoes', function (Blueprint $t) { $t->id(); });
    } else {
        DB::table('nfe_emissoes')->whereIn('business_id', [1, 99])->delete();
    }
});
it('algo', function () { $this->assertTrue(true); });`;
    const r = classifySource(src, 'X/NfeDualModeTest.php');
    expect(r).not.toBeNull();
    expect(r.corruptsOnMysql).toBe(false);
  });

  it('guarda por variável-flag $isSqliteMemory (skip + afterEach return) → seguro', () => {
    const src = `<?php
beforeEach(function () {
    $isSqliteMemory = config('database.default') === 'sqlite';
    if (! $isSqliteMemory) {
        $this->markTestSkipped('só sqlite');
    }
    Schema::dropIfExists('fin_titulos');
    Schema::create('fin_titulos', function (Blueprint $t) { $t->id(); });
});
afterEach(function () {
    $isSqliteMemory = config('database.default') === 'sqlite';
    if (! $isSqliteMemory) {
        return;
    }
    Schema::dropIfExists('fin_titulos');
});
it('algo', function () { $this->assertTrue(true); });`;
    const r = classifySource(src, 'X/MultiTenantComprehensiveTest.php');
    expect(r).not.toBeNull();
    expect(r.corruptsOnMysql).toBe(false);
  });

  it('source-reader (DDL dentro de toContain) NÃO é corruptor → null', () => {
    const src = `<?php
it('migration cria tabela', function () {
    $src = file_get_contents('database/migrations/x.php');
    expect($src)->toContain("Schema::create('anotacoes'");
    expect($src)->toContain("Schema::dropIfExists('anotacoes')");
});`;
    expect(classifySource(src, 'X/SourceReaderTest.php')).toBeNull();
  });

  it('DB::purge sem DDL (gate de browser) NÃO é corruptor → null', () => {
    const src = `<?php
beforeEach(function () {
    DB::purge('mysql');
});
it('smoke', function () { $this->assertTrue(true); });`;
    expect(classifySource(src, 'tests/Browser/CoreScreens/X.php')).toBeNull();
  });

  it('create idempotente (if !hasTable) sem nenhum drop NÃO corrompe', () => {
    const src = `<?php
beforeEach(function () {
    if (! Schema::hasTable('crm_deals')) {
        Schema::create('crm_deals', function (Blueprint $t) { $t->id(); });
    }
});
it('algo', function () { $this->assertTrue(true); });`;
    const r = classifySource(src, 'X/CrmIdempotentTest.php');
    // tem DDL manual (create), mas nunca dropa → não corrompe downstream no MySQL
    expect(r === null || r.corruptsOnMysql === false).toBe(true);
  });
});
