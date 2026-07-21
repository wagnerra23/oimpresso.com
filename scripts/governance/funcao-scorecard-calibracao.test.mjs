// @ts-check
/**
 * funcao-scorecard-calibracao.test.mjs — self-test do runner de calibração.
 * Roda: node --test scripts/governance/funcao-scorecard-calibracao.test.mjs
 * Prova que o `pontuar` MORDE: só libera quando o juiz de fato discrimina.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { blindOrder, pack, pontuar, stripTells, translateBlind } from './funcao-scorecard-calibracao.mjs';

// selado mínimo sintético (não lê o disco — teste puro).
const SEL = {
  't-c1':   { criterio_salient: 'C1', veredito: 'discordo', familia: 'multi-tenant' },
  't-c2':   { criterio_salient: 'C2', veredito: 'discordo', familia: 'valor-estoque' },
  't-c3':   { criterio_salient: 'C3', veredito: 'discordo', familia: 'dado-ausente' },
  't-bom':  { criterio_salient: 'C1', veredito: 'concordo', familia: null },
  't-ctrl': { criterio_salient: 'controle', veredito: 'sem-discordo', familia: null },
  't-inc':  { criterio_salient: 'C3', veredito: 'incerto', familia: null },
};

test('juiz perfeito → CALIBRADO', () => {
  const v = {
    't-c1': { C1: 'discordo' }, 't-c2': { C2: 'discordo' }, 't-c3': { C3: 'discordo' },
    't-bom': { C1: 'concordo' }, 't-ctrl': { C1: 'concordo' }, 't-inc': { C3: 'incerto' },
  };
  assert.equal(pontuar(v, SEL).pass, true);
});

test('juiz-carimbo (discorda de tudo) → NÃO calibrado (overflag no controle)', () => {
  const v = {
    't-c1': { C1: 'discordo' }, 't-c2': { C2: 'discordo' }, 't-c3': { C3: 'discordo' },
    't-bom': { C1: 'discordo' }, 't-ctrl': { C1: 'discordo' }, 't-inc': { C3: 'discordo' },
  };
  const r = pontuar(v, SEL);
  assert.equal(r.pass, false);
  assert.equal(r.overflagControle, 1);
});

test('esqueceu o incerto → NÃO calibrado', () => {
  const v = {
    't-c1': { C1: 'discordo' }, 't-c2': { C2: 'discordo' }, 't-c3': { C3: 'discordo' },
    't-bom': { C1: 'concordo' }, 't-ctrl': { C1: 'concordo' }, 't-inc': { C3: 'discordo' },
  };
  const r = pontuar(v, SEL);
  assert.equal(r.pass, false);
  assert.equal(r.incertoOk, false);
});

test('só 1 família achada (< 2) → NÃO calibrado', () => {
  const v = {
    't-c1': { C1: 'discordo' }, 't-c2': { C2: 'concordo' }, 't-c3': { C3: 'concordo' },
    't-bom': { C1: 'concordo' }, 't-ctrl': { C1: 'concordo' }, 't-inc': { C3: 'incerto' },
  };
  assert.equal(pontuar(v, SEL).pass, false);
});

test('falso-discordo num twin BOM → NÃO calibrado', () => {
  const v = {
    't-c1': { C1: 'discordo' }, 't-c2': { C2: 'discordo' }, 't-c3': { C3: 'discordo' },
    't-bom': { C1: 'discordo' }, 't-ctrl': { C1: 'concordo' }, 't-inc': { C3: 'incerto' },
  };
  const r = pontuar(v, SEL);
  assert.equal(r.pass, false);
  assert.equal(r.falsoDiscordoBom, 1);
});

test('pack cego usa IDs opacos e não vaza nomes dos arquivos/vereditos', () => {
  const out = pack('twins', true);
  assert.match(out, /## L01/);
  assert.doesNotMatch(out, /atomicidade-bad|eagerload-ok|docblock-mente|null-silencioso|incident-/i);
});

test('stripTells remove prosa narrativa de docblock e preserva contrato mínimo', () => {
  const code = `/**\n * Incidente conhecido: isto entrega a resposta.\n * @return int|null Quantidade ou null.\n * @transactional CallerService envolve em transaction.\n * @covered-by GoldenQueEntregaRespostaTest\n */\nfunction x(): ?int { return null; }`;
  const clean = stripTells(code);
  assert.doesNotMatch(clean, /Incidente|entrega a resposta|GoldenQueEntrega/);
  assert.match(clean, /@return int\|null/);
  assert.match(clean, /@transactional CallerService/);
});

test('pontuar aceita IDs cegos sem expor o mapa ao juiz', () => {
  const byReal = {
    't-c1': { C1: 'discordo' }, 't-c2': { C2: 'discordo' }, 't-c3': { C3: 'discordo' },
    't-bom': { C1: 'concordo' }, 't-ctrl': { C1: 'concordo' }, 't-inc': { C3: 'incerto' },
  };
  const blind = Object.fromEntries(blindOrder(Object.keys(SEL)).map(({ label, id }) => [label, byReal[id]]));
  assert.equal(pontuar(translateBlind(blind, Object.keys(SEL)), SEL).pass, true);
});
