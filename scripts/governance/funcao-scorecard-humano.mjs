#!/usr/bin/env node
// @ts-check
/**
 * Fecha a rodada humana do funcao-scorecard sem abrir o selado antes da hora.
 *
 *   --template                 imprime o JSON cego que [W] preenche
 *   --score <rotulos.json>     só então lê gabarito-SELADO.md e calcula K/9 + Cohen κ
 *
 * O script não grava o ledger: a comparação item-a-item continua revisão humana.
 */
import { existsSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { cohenKappa } from './funcao-scorecard-calibracao.mjs';

const ROUND = 'memory/reguas/2026-07-21-calibracao-funcao-scorecard-humano';
const SEALED = `${ROUND}/gabarito-SELADO.md`;
const VALUES = new Set(['concordo', 'discordo', 'incerto', 'n/a']);

export function template(count = 9) {
  return {
    lote_id: 'JUIZ-CAL-2026-07-funcao-scorecard-humano',
    rotulador: '[W]',
    cego: true,
    items: Object.fromEntries(Array.from({ length: count }, (_, i) => [String(i + 1), {
      veredito: null,
      fonte: null,
      nota: '',
    }])),
  };
}

export function parseSealedTable(markdown) {
  const out = {};
  for (const line of markdown.split(/\r?\n/)) {
    if (!/^\|\s*\d+\s*\|/.test(line)) continue;
    const cells = line.split('|').slice(1, -1).map((x) => x.trim());
    const id = cells[0];
    const raw = cells[3] || '';
    const verdict = (raw.match(/\b(concordo|discordo|incerto|n\/a)\b/i) || [])[1]?.toLowerCase();
    if (!verdict) throw new Error(`gabarito selado: item ${id} sem veredito reconhecível`);
    out[id] = verdict;
  }
  return out;
}

export function scoreHuman(labels, expected) {
  if (labels?.cego !== true || labels?.rotulador !== '[W]') {
    throw new Error('rótulos devem preservar rotulador="[W]" e cego=true');
  }
  const ids = Object.keys(expected).sort((a, b) => Number(a) - Number(b));
  if (ids.length !== 9) throw new Error(`gabarito deve conter 9 itens; recebeu ${ids.length}`);
  const pairs = [];
  const rows = [];
  let agreements = 0;
  for (const id of ids) {
    const item = labels.items?.[id];
    const observed = String(item?.veredito || '').toLowerCase();
    if (!VALUES.has(observed)) throw new Error(`item ${id}: veredito ausente/inválido`);
    if (!['canon', 'cabeca', 'nenhuma'].includes(String(item?.fonte || ''))) {
      throw new Error(`item ${id}: fonte deve ser canon|cabeca|nenhuma`);
    }
    const wanted = expected[id];
    const agree = wanted === observed;
    if (agree) agreements++;
    pairs.push([wanted, observed]);
    rows.push({ item: Number(id), humano: observed, juiz: wanted, concorda: agree, fonte_humana: item.fonte, nota: item.nota || '' });
  }
  return {
    lote_id: labels.lote_id,
    itens_rotulados: ids.length,
    concordancias: agreements,
    concordancia_pct: Math.round((agreements / ids.length) * 1000) / 10,
    kappa: cohenKappa(pairs),
    rows,
    ledger_entry: {
      tipo: 'juiz', lote_id: labels.lote_id, data: new Date().toISOString().slice(0, 10), pr: 0,
      juiz: 'funcao-scorecard (funções reais de risco — C1/C2/C3/C6/C7)',
      rotulador: '[W]', cego: true, itens_rotulados: ids.length,
      concordancias: agreements, concordancia_pct: Math.round((agreements / ids.length) * 1000) / 10,
      evidencia: `${ROUND}/`,
    },
  };
}

function main() {
  if (process.argv.includes('--template')) {
    console.log(JSON.stringify(template(), null, 2));
    return;
  }
  const i = process.argv.indexOf('--score');
  const path = i >= 0 ? process.argv[i + 1] : null;
  if (!path || !existsSync(path)) {
    console.error('Uso: --template | --score <rotulos-W.json>');
    process.exit(2);
  }
  const labels = JSON.parse(readFileSync(path, 'utf8'));
  // Ponto deliberado de abertura: o selado só é lido depois de um arquivo completo de rótulos existir.
  const expected = parseSealedTable(readFileSync(SEALED, 'utf8'));
  console.log(JSON.stringify(scoreHuman(labels, expected), null, 2));
}

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) main();
