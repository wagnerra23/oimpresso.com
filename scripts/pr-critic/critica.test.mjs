#!/usr/bin/env node
// @ts-check
/**
 * critica.test.mjs — selftest das partes DETERMINÍSTICAS do passe crítico
 * (split de diff, trava de citação, montagem do comentário). A chamada ao
 * agente não é testada aqui (é LLM; a trava mecânica é a defesa).
 * Node puro, sem rede.
 */

import assert from 'node:assert/strict';
import { filtrarPorCitacao, montarComentario, normaliza, separarDiffPorArquivo } from './critica.mjs';

let passou = 0;
function t(nome, fn) { fn(); passou++; console.log(`  ok - ${nome}`); }

t('separarDiffPorArquivo divide unified diff por path do lado b/', () => {
  const diff = [
    'diff --git a/resources/js/Pages/X/Index.tsx b/resources/js/Pages/X/Index.tsx',
    'index 111..222 100644',
    '--- a/resources/js/Pages/X/Index.tsx',
    '+++ b/resources/js/Pages/X/Index.tsx',
    '@@ -1 +1 @@',
    '-a',
    '+b',
    'diff --git a/Modules/Y/Z.php b/Modules/Y/Z.php',
    '@@ -2 +2 @@',
    '-c',
    '+d',
  ].join('\n');
  const blocos = separarDiffPorArquivo(diff);
  assert.equal(blocos.size, 2);
  assert.ok(blocos.get('resources/js/Pages/X/Index.tsx').includes('+b'));
  assert.ok(blocos.get('Modules/Y/Z.php').includes('+d'));
  assert.ok(!blocos.get('Modules/Y/Z.php').includes('+b'), 'hunk de um arquivo vazou pro outro');
});

t('trava de citação: mantém citação literal, descarta alucinada/curta/de contrato errado', () => {
  const contratos = new Map([
    ['charter.md', 'O primary do header é "Novo título" e abre TituloCreateSheet.\nSidebar permanece light.'],
  ]);
  const base = { arquivo: 'a.tsx', achado: 'x', severidade: 'alta', confianca: 'alta' };
  const { validos, descartados } = filtrarPorCitacao([
    // literal (whitespace/caixa diferentes — normaliza cobre)
    { ...base, contrato: 'charter.md', citacao_contrato: 'o primary do header é "novo título"   e abre TituloCreateSheet.' },
    // alucinada — não existe no contrato
    { ...base, contrato: 'charter.md', citacao_contrato: 'O header deve usar gradiente roxo em telas de venda.' },
    // curta demais pra ancorar
    { ...base, contrato: 'charter.md', citacao_contrato: 'header' },
    // contrato inexistente
    { ...base, contrato: 'outro.md', citacao_contrato: 'Sidebar permanece light.' },
  ], contratos);
  assert.equal(validos.length, 1);
  assert.equal(descartados.length, 3);
});

t('normaliza é estável pra comparação (whitespace + caixa)', () => {
  assert.equal(normaliza('  Foo\n\tBar  BAZ '), 'foo bar baz');
});

t('comentário carrega marcador, achados, e rodapé honesto (caps/descartes visíveis)', () => {
  const md = montarComentario({
    resultados: [{ grupo: 'resources/js/Pages/X', achados: [{ arquivo: 'a.tsx', contrato: 'charter.md', citacao_contrato: 'invariante Y', achado: 'diff remove Y', severidade: 'alta', confianca: 'media' }] }],
    semContrato: [{ id: 'resources/js/Pages/SemNada' }],
    descartadosCap: [{ id: 'resources/js/Pages/Cortado' }],
    totalDescartadosCitacao: 2,
    totalTruncados: 1,
    modelo: 'claude-teste',
  });
  assert.ok(md.includes('<!-- pr-critic-contrato -->'));
  assert.ok(md.includes('diff remove Y'));
  assert.ok(md.includes('SemNada'), 'grupo sem contrato precisa aparecer no rodapé');
  assert.ok(md.includes('Cortado'), 'grupo cortado pelo teto precisa aparecer no rodapé (no silent caps)');
  assert.ok(md.includes('2 achado(s) descartado(s) pela trava'));
  assert.ok(md.includes('Advisory'));
});

t('comentário sem achados declara coerência explícita', () => {
  const md = montarComentario({ resultados: [{ grupo: 'g', achados: [] }], semContrato: [], descartadosCap: [], totalDescartadosCitacao: 0, totalTruncados: 0, modelo: 'm' });
  assert.ok(md.includes('Nenhuma incoerência'));
});

console.log(`\ncritica.test.mjs: ${passou} teste(s) OK`);
