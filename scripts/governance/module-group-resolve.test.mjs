#!/usr/bin/env node
// @ts-check
/**
 * module-group-resolve.test.mjs — selftest do resolver de grupo de memória.
 *
 * O que este teste tem que provar (e não só exercitar):
 *   1. MORDE  — módulo sem os arquivos do papel resolve VAZIO (não inventa corpus).
 *   2. LIBERA — módulo com os arquivos resolve os arquivos certos.
 *   3. A ARMADILHA — Pages/kb ↔ requisitos/KB e Pages/team-mcp ↔ requisitos/TeamMcp
 *      resolvem no MESMO grupo. Casamento string-exata perderia os dois em silêncio;
 *      foi assim que um nudge de CI ficou morto (§5 2026-07-17).
 *   4. CONTROLE NEGATIVO — normalizar case/separador NÃO pode fazer módulos
 *      diferentes colidirem (Compras ≠ Compra, KB ≠ KBX).
 *   5. O contrato bate com o schema que diz descrevê-lo.
 *
 * Uso: node scripts/governance/module-group-resolve.test.mjs
 */

import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { normalizaModulo, expandeGlob, resolveGrupo } from './module-group-resolve.mjs';

let falhas = 0;
/** @param {boolean} cond @param {string} msg */
const ok = (cond, msg) => {
  if (cond) console.log(`  ✓ ${msg}`);
  else { console.log(`  ✗ ${msg}`); falhas++; }
};

/** Cria uma árvore-fixture e devolve a raiz temporária. */
function fixture() {
  const raiz = mkdtempSync(join(tmpdir(), 'mgr-'));
  const escreve = (/** @type {string} */ rel) => {
    const abs = join(raiz, rel);
    mkdirSync(join(abs, '..'), { recursive: true });
    writeFileSync(abs, '# fixture\n');
  };
  // módulo "cheio", com o case DIVERGENTE de propósito (a armadilha real)
  escreve('memory/requisitos/KB/SPEC.md');
  escreve('memory/requisitos/KB/BRIEFING.md');
  escreve('Modules/KB/module.json');
  escreve('resources/js/Pages/kb/Index.charter.md');   // ← minúsculo
  escreve('resources/js/Pages/kb/Index.casos.md');
  // módulo com separador divergente
  escreve('memory/requisitos/TeamMcp/BRIEFING.md');
  escreve('Modules/Forja/Resources/js/Pages/team-mcp/forja.charter.md'); // ← hífen
  // módulo vazio (só existe a pasta do backend)
  escreve('memory/requisitos/Vazio/README.md');
  // vizinho de nome parecido — controle negativo de colisão
  escreve('memory/requisitos/KBX/SPEC.md');
  // {mod} DENTRO do nome do arquivo (não segmento inteiro) — o caso que a v1 errou:
  // comparar o segmento cru dava 'kb.md' ≠ 'kb' e o papel sumia CALADO.
  escreve('memory/dominio/kb.md');
  escreve('memory/dominio/kbx.md'); // vizinho, pra provar que não vaza
  return raiz;
}

const CONTRATO = {
  versao: '1.0.0',
  resolucao: { case_insensitive: true, normaliza_separador: true },
  papeis: [
    { papel: 'contrato', pergunta: 'o que promete', onde: ['memory/requisitos/{mod}/SPEC.md'], cardinalidade: 'zero-ou-um' },
    { papel: 'estado', pergunta: 'em que pe esta', onde: ['memory/requisitos/{mod}/BRIEFING.md'], cardinalidade: 'zero-ou-um' },
    { papel: 'responsabilidade', pergunta: 'fronteira', onde: ['memory/requisitos/{mod}/SCOPE.md'], cardinalidade: 'zero-ou-um' },
    { papel: 'telas', pergunta: 'que telas tem', onde: ['resources/js/Pages/{mod}/**/*.charter.md', 'resources/js/Pages/{mod}/**/*.casos.md'], cardinalidade: 'zero-ou-muitos' },
    { papel: 'dominio', pergunta: 'palavras de significado fixo', onde: ['memory/dominio/{mod}.md'], cardinalidade: 'zero-ou-um' },
  ],
};

export function rodaSelftest() {
  console.log('\n  module-group-resolve — selftest\n');
  const raiz = fixture();

  try {
    // ── 1) normalização, isolada ───────────────────────────────────────────
    ok(normalizaModulo('team-mcp') === normalizaModulo('TeamMcp'), 'normaliza: team-mcp ≡ TeamMcp');
    ok(normalizaModulo('kb') === normalizaModulo('KB'), 'normaliza: kb ≡ KB');
    ok(normalizaModulo('KB') !== normalizaModulo('KBX'), 'CONTROLE NEGATIVO: KB ≢ KBX (normalizar não colide vizinho)');
    ok(normalizaModulo('Compras') !== normalizaModulo('Compra'), 'CONTROLE NEGATIVO: Compras ≢ Compra');

    // ── 2) glob mínimo ─────────────────────────────────────────────────────
    const charters = expandeGlob('resources/js/Pages/*/**/*.charter.md', raiz);
    ok(charters.length === 2, `glob ** encontra charters em subárvores (obtido ${charters.length}, esperado 2)`);
    ok(expandeGlob('memory/requisitos/*/NAOEXISTE.md', raiz).length === 0, 'glob sem match devolve vazio (ausência é resultado, não erro)');

    // ── 3) A ARMADILHA: case divergente entre as árvores ───────────────────
    const kb = resolveGrupo('KB', CONTRATO, raiz);
    ok(kb.papeis.contrato.arquivos.length === 1, 'KB resolve o contrato (requisitos/KB)');
    ok(
      kb.papeis.telas.arquivos.length === 2,
      `KB resolve as telas apesar de Pages/kb ser MINÚSCULO (obtido ${kb.papeis.telas.arquivos.length}, esperado 2) — é o caso que mata nudge em silêncio`,
    );

    const tm = resolveGrupo('TeamMcp', CONTRATO, raiz);
    ok(
      tm.papeis.telas.arquivos.length === 1,
      `TeamMcp resolve tela apesar de Pages/team-mcp ter HÍFEN (obtido ${tm.papeis.telas.arquivos.length}, esperado 1)`,
    );

    // ── 3-bis) {mod} DENTRO do nome do arquivo (regressão real da v1) ──────
    // memory/dominio/kb.md: comparar o segmento cru dava 'kb.md' ≠ 'kb', o papel
    // resolvia VAZIO e ninguém via. Achado rodando contra a árvore real, não no teste.
    ok(
      kb.papeis.dominio.arquivos.length === 1,
      `token dentro do NOME do arquivo resolve (memory/dominio/kb.md) — obtido ${kb.papeis.dominio.arquivos.length}, esperado 1`,
    );
    ok(
      !kb.papeis.dominio.arquivos.some((a) => a.includes('kbx')),
      'CONTROLE NEGATIVO: dominio do KB não engole kbx.md',
    );

    // ── 4) MORDE: módulo sem corpus resolve vazio, não inventa ─────────────
    const vazio = resolveGrupo('Vazio', CONTRATO, raiz);
    ok(vazio.papeis_resolvidos === 0, `MORDE: módulo sem artefatos resolve 0 papéis (obtido ${vazio.papeis_resolvidos})`);
    ok(
      Object.values(vazio.papeis).every((p) => p.arquivos.length === 0),
      'MORDE: nenhum papel devolve arquivo de outro módulo por engano',
    );

    // ── 5) CONTROLE NEGATIVO: vizinho de nome parecido não vaza ────────────
    ok(
      !kb.papeis.contrato.arquivos.some((a) => a.includes('KBX')),
      'CONTROLE NEGATIVO: grupo do KB não engole arquivo do KBX',
    );

    // ── 6) o contrato real bate com o schema que diz descrevê-lo ───────────
    const real = JSON.parse(readFileSync(join(process.cwd(), 'governance', 'module-group.json'), 'utf8'));
    const schema = JSON.parse(readFileSync(join(process.cwd(), 'scripts', 'memory-schemas', 'module-group.schema.json'), 'utf8'));
    const obrig = schema.required;
    ok(obrig.every((/** @type {string} */ k) => k in real), `contrato real tem os campos obrigatórios (${obrig.join(', ')})`);
    ok(real.resolucao?.case_insensitive === true, 'contrato real declara case_insensitive: true (o schema exige)');
    const slugs = real.papeis.map((/** @type {any} */ p) => p.papel);
    ok(new Set(slugs).size === slugs.length, 'papéis têm slug único');
    ok(
      real.papeis.every((/** @type {any} */ p) => typeof p.pergunta === 'string' && p.pergunta.length >= 10),
      'todo papel declara a PERGUNTA que só ele responde (impede papel decorativo)',
    );
    const perguntas = real.papeis.map((/** @type {any} */ p) => p.pergunta);
    ok(new Set(perguntas).size === perguntas.length, 'nenhum papel repete a pergunta de outro (dois iguais = duplicação)');
  } finally {
    rmSync(raiz, { recursive: true, force: true });
  }

  console.log(falhas === 0 ? '\n  OK — resolver morde, libera e não colide.\n' : `\n  ${falhas} FALHA(S)\n`);
  return falhas === 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  process.exit(rodaSelftest() ? 0 : 1);
}
