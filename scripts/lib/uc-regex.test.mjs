// @ts-check
/**
 * uc-regex.test.mjs — self-test da fonte única de UC-id (ADR 0264 · G-1/G-2/G-5/G-7).
 * Roda: node --test scripts/lib/uc-regex.test.mjs
 *
 * Testa LÓGICA PURA (o parser sobre conteúdo em string), não a árvore viva — assim o
 * teste não apodrece junto com o repo (padrão de module-surface.test.mjs).
 *
 * POR QUE NASCEU (2026-07-27): a lib matou "4 regex que drifaram", mas o USO dela (o
 * split por bloco `## ` obrigatório antes do `ucHeadRe()`) seguiu copiado em 6
 * consumidores e drifou pela mesma porta — `scripts/qa/exposicao-tier0.mjs` aplicava o
 * regex na LINHA CRUA e media 4 telas cobertas onde havia 29. A lib não tinha teste
 * nenhum; sem estes casos, o bug volta calado.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ucScanRe, ucHeadRe, ucsDeclaredInCasos } from './uc-regex.mjs';

// =====================================================================================
// ucsDeclaredInCasos — o parser (split por bloco + heading)
// =====================================================================================

test('reconhece heading markdown `## UC-XX ·` (o caso que quebrou a sentinela)', () => {
  // Fixture REAL: headings copiados de resources/js/Pages/Cliente/Edit.casos.md, que a
  // sentinela lia como zero UC declarado.
  const casos = [
    '---', 'charter: Edit.charter.md', '---', '', '# Casos — Cliente/Edit', '',
    '## UC-CEDI-01 · Abrir a edição já traz os dados fiscais preenchidos', 'Status: ✅', '',
    '## UC-CEDI-02 · Salvar a edição atualiza o cadastro e confirma', 'Status: ✅', '',
    '## UC-CEDI-03 · Não dá pra editar cliente de outro tenant (Tier 0)', 'Status: ✅', '',
    '## UC-CEDI-04 · Autosave inline no drawer valida o documento (mod 11)', 'Status: ✅',
  ].join('\n');

  assert.deepEqual(ucsDeclaredInCasos(casos), ['UC-CEDI-01', 'UC-CEDI-02', 'UC-CEDI-03', 'UC-CEDI-04']);
});

test('CONTROLE-NEGATIVO: aplicar ucHeadRe na linha crua NÃO casa (a raiz do bug)', () => {
  // Este é o comportamento que a sentinela tinha. Fica travado aqui pra que ninguém
  // "simplifique" ucsDeclaredInCasos de volta pro loop por linha achando que dá no mesmo.
  const linha = '## UC-CEDI-01 · Abrir a edição já traz os dados fiscais preenchidos';
  assert.equal(linha.match(ucHeadRe()), null, 'linha crua começa em "##", não em "UC-"');

  // E o mesmo texto, já como bloco (pós-split), casa:
  const bloco = linha.replace(/^##\s+/, '');
  assert.equal(bloco.match(ucHeadRe())?.[1], 'UC-CEDI-01');
});

test('prefixo com dígito não trunca (UC-KBV2-01..03 são 3 UCs, não 1)', () => {
  // Regressão de 2026-07-16 documentada na lib: `[A-Z]{0,6}` truncava pro token UC-KBV2
  // e os 9 headings viravam 1 no dedupe.
  const casos = ['## UC-KBV2-01 · a', '## UC-KBV2-02 · b', '## UC-KBV2-03 · c'].join('\n');
  assert.deepEqual(ucsDeclaredInCasos(casos), ['UC-KBV2-01', 'UC-KBV2-02', 'UC-KBV2-03']);
});

test('aceita as formas canônicas de UC-id documentadas na lib', () => {
  const casos = [
    '## UC-01 · sem prefixo',
    '## UC-F02 · prefixo 1 letra',
    '## UC-10b · sufixo letra',
    '## UC-IMP-01 · prefixo hifenado',
    '## UC-FORJA-01 · prefixo 5 letras',
  ].join('\n');
  assert.deepEqual(ucsDeclaredInCasos(casos), ['UC-01', 'UC-F02', 'UC-10B', 'UC-IMP-01', 'UC-FORJA-01']);
});

test('ignora heading que não é UC e prosa que menciona UC no meio da linha', () => {
  // Conservador de propósito (docblock da lib): só o heading conta como DECLARAÇÃO.
  const casos = [
    '## Contexto', 'Este bloco cita UC-CEDI-09 em prosa mas não o declara.', '',
    '## UC-CEDI-01 · de verdade', 'Status: ✅', '',
    '## Non-Goals', 'Nada aqui.',
  ].join('\n');
  assert.deepEqual(ucsDeclaredInCasos(casos), ['UC-CEDI-01']);
});

test('casos.md vazio / sem heading → lista vazia (não lança)', () => {
  assert.deepEqual(ucsDeclaredInCasos(''), []);
  assert.deepEqual(ucsDeclaredInCasos('# Título\ntexto solto\n'), []);
});

test('tolera CRLF (arquivo escrito no Windows)', () => {
  // Windows é a máquina de dev canônica do projeto; CRLF no .casos.md não pode zerar
  // a contagem de UC (a classe da lição CRLF em writes).
  const casos = '## UC-CEDI-01 · a\r\nStatus: ✅\r\n\r\n## UC-CEDI-02 · b\r\n';
  assert.deepEqual(ucsDeclaredInCasos(casos), ['UC-CEDI-01', 'UC-CEDI-02']);
});

// =====================================================================================
// ucScanRe — varredura de corpo (G-2: UC citado por teste)
// =====================================================================================

test('ucScanRe acha UC citado em nome de teste Pest/PHP', () => {
  const teste = "it('UC-CEDI-02 · salvar atualiza o cadastro', function () {});";
  assert.deepEqual([...teste.matchAll(ucScanRe())].map((m) => m[0]), ['UC-CEDI-02']);
});

test('ucScanRe devolve instância FRESCA (regex /g é stateful — footgun documentado)', () => {
  const corpo = 'UC-01 e UC-02';
  assert.equal([...corpo.matchAll(ucScanRe())].length, 2);
  // Se a instância fosse compartilhada, o lastIndex da 1ª chamada faria a 2ª perder match.
  assert.equal([...corpo.matchAll(ucScanRe())].length, 2);
});
