#!/usr/bin/env node
// @ts-check
/**
 * coleta.test.mjs — selftest do roteamento determinístico do pr-critic.
 *
 * Fixtures-armadilha (padrão governance-script-tests): a árvore em
 * fixtures/arvore/ tem um gap-spec que NÃO referencia o diff de teste
 * (outra-tela-gap.md) — se ele vazar pro manifesto, o roteamento por conteúdo
 * quebrou e o teste falha. Roda em qualquer OS (node puro, sem git/rede).
 */

import assert from 'node:assert/strict';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { coletar, ehAlvo, grupoDe, limitarGrupos, moduloDe, MAX_GRUPOS } from './coleta.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), 'fixtures', 'arvore');
let passou = 0;
function t(nome, fn) {
  fn();
  passou++;
  console.log(`  ok - ${nome}`);
}

// ── unidade: classificação ───────────────────────────────────────────────────
t('ehAlvo aceita Pages tsx e Modules php; rejeita docs/css/workflow', () => {
  assert.equal(ehAlvo('resources/js/Pages/Financeiro/Unificado/Index.tsx'), true);
  assert.equal(ehAlvo('Modules/OficinaAuto/Http/Controllers/BoardController.php'), true);
  assert.equal(ehAlvo('resources/js/Pages/Financeiro/Unificado/Index.charter.md'), false);
  assert.equal(ehAlvo('resources/css/cockpit.css'), false);
  assert.equal(ehAlvo('.github/workflows/ci.yml'), false);
  assert.equal(ehAlvo('resources/js/Components/shared/Button.tsx'), false);
});

t('moduloDe e grupoDe resolvem tela, _components e Modules', () => {
  assert.equal(moduloDe('resources/js/Pages/Financeiro/Unificado/Index.tsx'), 'Financeiro');
  assert.equal(moduloDe('Modules/OficinaAuto/Entities/ServiceOrder.php'), 'OficinaAuto');
  assert.equal(grupoDe('resources/js/Pages/Financeiro/Unificado/Index.tsx'), 'resources/js/Pages/Financeiro/Unificado');
  assert.equal(grupoDe('resources/js/Pages/Financeiro/Unificado/_components/FinBaixaSheet.tsx'), 'resources/js/Pages/Financeiro/Unificado');
  assert.equal(grupoDe('Modules/OficinaAuto/Entities/ServiceOrder.php'), 'Modules/OficinaAuto');
});

// ── integração: tela com charter+casos+gap+map ───────────────────────────────
t('tela com contrato completo resolve charter, casos, gap e map', () => {
  const m = coletar(['resources/js/Pages/Financeiro/Unificado/Index.tsx'], ROOT);
  assert.equal(m.grupos.length, 1);
  const g = m.grupos[0];
  assert.deepEqual(g.contratos.charter, ['resources/js/Pages/Financeiro/Unificado/Index.charter.md']);
  assert.deepEqual(g.contratos.casos, ['resources/js/Pages/Financeiro/Unificado/Index.casos.md']);
  assert.deepEqual(g.contratos.gap, ['memory/requisitos/Financeiro/unificado-gap.md']);
  assert.deepEqual(g.contratos.map, ['memory/requisitos/Financeiro/unificado.map.json']);
});

// ── armadilha: gap de OUTRA tela não pode vazar ──────────────────────────────
t('gap que não referencia o diff fica FORA (roteamento por conteúdo, não por pasta)', () => {
  const m = coletar(['resources/js/Pages/Financeiro/Unificado/Index.tsx'], ROOT);
  const gaps = m.grupos[0].contratos.gap;
  assert.equal(gaps.includes('memory/requisitos/Financeiro/outra-tela-gap.md'), false,
    'outra-tela-gap.md vazou — inclusão por pasta é regressão (lição âncora-no-olho)');
});

// ── _components sobe pro dir da tela e herda charters do diretório ───────────
t('mudança só em _components herda charter/casos do dir da tela + map por conteúdo', () => {
  const m = coletar(['resources/js/Pages/Financeiro/Unificado/_components/FinBaixaSheet.tsx'], ROOT);
  assert.equal(m.grupos.length, 1);
  const g = m.grupos[0];
  assert.equal(g.id, 'resources/js/Pages/Financeiro/Unificado');
  assert.deepEqual(g.contratos.charter, ['resources/js/Pages/Financeiro/Unificado/Index.charter.md']);
  assert.deepEqual(g.contratos.map, ['memory/requisitos/Financeiro/unificado.map.json']);
});

// ── Modules com charter de módulo ────────────────────────────────────────────
t('arquivo de Modules resolve charter de módulo', () => {
  const m = coletar(['Modules/OficinaAuto/Http/Controllers/BoardController.php'], ROOT);
  assert.equal(m.grupos.length, 1);
  assert.deepEqual(m.grupos[0].contratos.charter_modulo, ['memory/requisitos/OficinaAuto/OficinaAuto.charter.md']);
});

// ── sem contrato: honesto, não silencioso ────────────────────────────────────
t('tela sem nenhum artefato vai pra sem_contrato', () => {
  const m = coletar(['resources/js/Pages/Inexistente/Tela.tsx'], ROOT);
  assert.equal(m.grupos.length, 0);
  assert.equal(m.sem_contrato.length, 1);
  assert.equal(m.sem_contrato[0].id, 'resources/js/Pages/Inexistente');
});

// ── arquivos fora do escopo são listados em ignorados ────────────────────────
t('não-alvos vão pra ignorados (nada some calado)', () => {
  const m = coletar(['README.md', 'resources/js/Pages/Financeiro/Unificado/Index.tsx'], ROOT);
  assert.deepEqual(m.ignorados, ['README.md']);
});

// ── teto de grupos sem cap silencioso ────────────────────────────────────────
t('limitarGrupos corta pelo teto e devolve descartados', () => {
  const grupos = Array.from({ length: MAX_GRUPOS + 3 }, (_, i) => ({
    id: `resources/js/Pages/M${String(i).padStart(2, '0')}`,
    arquivos: Array.from({ length: i + 1 }, (_, j) => `f${j}.tsx`),
  }));
  const { mantidos, descartados } = limitarGrupos(grupos);
  assert.equal(mantidos.length, MAX_GRUPOS);
  assert.equal(descartados.length, 3);
  // mantém os maiores (mais arquivos tocados = mais risco)
  assert.equal(mantidos[0].arquivos.length, MAX_GRUPOS + 3);
  assert.equal(descartados.at(-1).arquivos.length, 1);
});

console.log(`\ncoleta.test.mjs: ${passou} teste(s) OK`);
