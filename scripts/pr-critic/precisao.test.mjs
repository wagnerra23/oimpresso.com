#!/usr/bin/env node
// @ts-check
/**
 * precisao.test.mjs — selftest do medidor de precisão do pr-critic.
 *
 * Fixtures-armadilha (padrão governance-script-tests): um achado cujo arquivo NÃO
 * foi tocado depois do comentário DEVE contar IGNORADO; um cujo arquivo foi tocado
 * DEVE contar AGIU. Se a classificação vazar (tudo agiu / tudo ignorado), o número
 * mente e o teste falha. Node puro — sem gh/rede/fs (exceto o teste do ledger, que
 * usa um arquivo temporário e limpa).
 */
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import {
  acharComentarioCritic,
  anexarLedger,
  buildReport,
  classificarPR,
  MARCADOR_DADOS,
  parseBlocoDados,
  taxa,
} from './precisao.mjs';

let passou = 0;
function t(nome, fn) { fn(); passou++; console.log(`  ok - ${nome}`); }

// ── parsing do bloco machine-readable ────────────────────────────────────────
t('parseBlocoDados extrai o JSON embutido; ignora corpo sem marcador', () => {
  const body = `## comentário\ntexto\n${MARCADOR_DADOS} {"v":1,"modelo":"m","achados":[{"id":"abc","arquivo":"a.tsx","severidade":"alta"}]} -->`;
  const d = parseBlocoDados(body);
  assert.equal(d.modelo, 'm');
  assert.equal(d.achados.length, 1);
  assert.equal(d.achados[0].arquivo, 'a.tsx');
  assert.equal(parseBlocoDados('sem marcador aqui'), null);
  assert.equal(parseBlocoDados(`${MARCADOR_DADOS} {json quebrado -->`), null);
});

t('acharComentarioCritic pega só o comentário com o bloco', () => {
  const c = acharComentarioCritic([
    { body: 'oi humano' },
    { body: `x ${MARCADOR_DADOS} {"v":1,"achados":[]} -->`, createdAt: '2026-07-01T00:00:00Z' },
  ]);
  assert.ok(c);
  assert.equal(c.createdAt, '2026-07-01T00:00:00Z');
  assert.equal(acharComentarioCritic([{ body: 'nada' }]), null);
});

// ── classificação agiu/ignorado (a armadilha) ────────────────────────────────
t('classificarPR: arquivo tocado depois = AGIU; não tocado = IGNORADO (bite)', () => {
  const cls = classificarPR({
    achados: [
      { id: '1', arquivo: 'a.tsx', severidade: 'alta' },
      { id: '2', arquivo: 'b.tsx', severidade: 'media' },
    ],
    touchedAfter: ['a.tsx'], // só a.tsx mudou depois do comentário
  });
  assert.equal(cls.agiu, 1);
  assert.equal(cls.ignorado, 1);
  assert.equal(cls.detalhe.find((d) => d.id === '1').acao, 'agiu');
  assert.equal(cls.detalhe.find((d) => d.id === '2').acao, 'ignorado');
});

t('classificarPR: nenhum commit posterior = tudo IGNORADO (não vaza pra agiu)', () => {
  const cls = classificarPR({ achados: [{ id: '1', arquivo: 'a.tsx', severidade: 'alta' }], touchedAfter: [] });
  assert.equal(cls.agiu, 0);
  assert.equal(cls.ignorado, 1);
});

t('taxa é null com denominador zero (honesto, não 0%)', () => {
  assert.equal(taxa(0, 0), null);
  assert.equal(taxa(1, 2), 50);
});

// ── agregação ────────────────────────────────────────────────────────────────
t('buildReport agrega precisão, first-pass e por-severidade; ignora PR sem achado', () => {
  const r = buildReport({
    nowIso: '2026-07-10',
    days: 60,
    prRecords: [
      // PR 1: 2 achados, 1 agiu → não é first-pass
      { number: 1, modelo: 'm', achados: [
        { id: 'a', arquivo: 'x.tsx', severidade: 'alta' },
        { id: 'b', arquivo: 'y.tsx', severidade: 'baixa' },
      ], touchedAfter: ['x.tsx'] },
      // PR 2: 1 achado, agiu → first-pass
      { number: 2, modelo: 'm', achados: [{ id: 'c', arquivo: 'z.tsx', severidade: 'alta' }], touchedAfter: ['z.tsx'] },
      // PR 3: critic disse "sem incoerência" (0 achados) → NÃO entra no denominador
      { number: 3, modelo: 'm', achados: [], touchedAfter: [] },
    ],
  });
  assert.equal(r.resumo.prs_com_achado, 2, 'PR sem achado não conta');
  assert.equal(r.resumo.achados, 3);
  assert.equal(r.resumo.agiu, 2);
  assert.equal(r.resumo.precisao_acao, round1((2 / 3) * 100));
  assert.equal(r.resumo.first_pass_prs, 50); // 1 de 2 PRs
  assert.equal(r.por_severidade.alta.total, 2);
  assert.equal(r.por_severidade.alta.agiu, 2);
  assert.equal(r.por_severidade.alta.taxa_acao, 100);
  assert.equal(r.por_severidade.baixa.taxa_acao, 0);
});

// ── ledger append-only ───────────────────────────────────────────────────────
t('anexarLedger grava 1 JSONL por PR com achado', () => {
  const dir = mkdtempSync(join(tmpdir(), 'precisao-'));
  const path = join(dir, 'ledger.jsonl');
  try {
    const r = buildReport({ nowIso: '2026-07-10', prRecords: [
      { number: 7, modelo: 'm', achados: [{ id: 'a', arquivo: 'x.tsx', severidade: 'alta' }], touchedAfter: ['x.tsx'] },
    ] });
    const n = anexarLedger(path, r);
    assert.equal(n, 1);
    const linha = JSON.parse(readFileSync(path, 'utf8').trim());
    assert.equal(linha.pr, 7);
    assert.equal(linha.precisao_acao, 100);
    assert.equal(linha.achados[0].acao, 'agiu');
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

function round1(v) { return Math.round(v * 10) / 10; }

console.log(`\nprecisao.test.mjs: ${passou} teste(s) OK`);
