#!/usr/bin/env node
// Consolidador determinístico da worklist de auditoria paralela.
// Lê prototipo-ui/audit/reports/*.design-report.json (1 por tela, escrito pelos agentes)
// e emite CONSOLIDADO.md (placar worst-first) + CONSOLIDADO.json (rollup).
// Read-only sobre os reports; NÃO pontua nada (isso é dos agentes). Sem deps externas.
//
// Uso: node prototipo-ui/audit/consolidate.mjs
//      node prototipo-ui/audit/consolidate.mjs --dir <reports> --out <dir>

import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const argv = process.argv.slice(2);
const getArg = (flag, def) => {
  const i = argv.indexOf(flag);
  return i >= 0 && argv[i + 1] ? argv[i + 1] : def;
};
const REPORTS_DIR = getArg('--dir', join(HERE, 'reports'));
const OUT_DIR = getArg('--out', HERE);

const NIVEL_ORDER = ['Beginner', 'Developing', 'Advanced', 'Leader', 'Champion'];

function loadReports(dir) {
  if (!existsSync(dir)) {
    console.error(`[consolidate] reports dir não existe: ${dir}`);
    return [];
  }
  const files = readdirSync(dir).filter((f) => f.endsWith('.design-report.json'));
  const reports = [];
  for (const f of files) {
    try {
      const r = JSON.parse(readFileSync(join(dir, f), 'utf8'));
      if (!r.screen || typeof r.nota !== 'number' || !Array.isArray(r.rules)) {
        console.warn(`[consolidate] schema incompleto, pulando: ${f}`);
        continue;
      }
      reports.push({ _file: f, ...r });
    } catch (e) {
      console.warn(`[consolidate] JSON inválido, pulando: ${f} (${e.message})`);
    }
  }
  return reports;
}

const mechFails = (r) => r.rules.filter((x) => x.status === 'fail' && x.mechanized === true);
const judgedFails = (r) => r.rules.filter((x) => x.status === 'fail' && x.mechanized === false);
const dsTotal = (r) => (r.ds_violations && Number(r.ds_violations.total)) || 0;

function build(reports) {
  // worst-first: menor nota primeiro; desempate por mais regras mecanizadas falhadas, depois mais ds/*.
  const sorted = [...reports].sort(
    (a, b) =>
      a.nota - b.nota ||
      mechFails(b).length - mechFails(a).length ||
      dsTotal(b) - dsTotal(a) ||
      a.screen.localeCompare(b.screen)
  );

  const dist = { Champion: 0, Leader: 0, Advanced: 0, Developing: 0, Beginner: 0 };
  let dsSum = 0;
  for (const r of reports) {
    if (dist[r.nivel] !== undefined) dist[r.nivel] += 1;
    dsSum += dsTotal(r);
  }
  const media = reports.length ? Math.round(reports.reduce((s, r) => s + r.nota, 0) / reports.length) : 0;

  return { sorted, dist, media, dsSum };
}

function renderMd({ sorted, dist, media, dsSum }, sha) {
  const total = sorted.length;
  const below70 = sorted.filter((r) => r.nota < 70).length;
  const L = [];
  L.push('# CONSOLIDADO — placar da auditoria paralela de telas');
  L.push('');
  L.push('> **GERADO** por `consolidate.mjs` — não editar à mão. Estende [`DS_ADOCAO_INDICE.md`](DS_ADOCAO_INDICE.md).');
  L.push(`> Regra (GOLDEN-REFERENCE 10 + ds/*). Fechamento por evidência mecanizada, não opinião.`);
  L.push('');
  L.push(`**Telas pontuadas:** ${total} · **Média:** ${media}/100 · **< 70 (alvo):** ${below70} · **Σ ds/\\*:** ${dsSum}`);
  L.push('');
  L.push('## Distribuição');
  L.push('');
  L.push('| Nível | Faixa | Telas |');
  L.push('|---|---|--:|');
  L.push(`| 🏆 Champion | 95-100 | ${dist.Champion} |`);
  L.push(`| 🥈 Leader | 85-94 | ${dist.Leader} |`);
  L.push(`| Advanced | 70-84 | ${dist.Advanced} |`);
  L.push(`| Developing | 50-69 | ${dist.Developing} |`);
  L.push(`| 🥉 Beginner | 0-49 | ${dist.Beginner} |`);
  L.push('');
  L.push('## Placar (pior → melhor)');
  L.push('');
  L.push('| # | Tela | Nota | Nível | Regras mecanizadas ✗ | ds/* | Top gap |');
  L.push('|--:|---|--:|---|---|--:|---|');
  sorted.forEach((r, i) => {
    const mf = mechFails(r).map((x) => x.id).join(' ') || '—';
    const gap = (r.top_gaps && r.top_gaps[0] && r.top_gaps[0].fix) || '';
    const gapShort = gap.length > 80 ? gap.slice(0, 77) + '…' : gap;
    L.push(`| ${i + 1} | \`${r.screen}\` | ${r.nota} | ${r.nivel} | ${mf} | ${dsTotal(r)} | ${gapShort} |`);
  });
  L.push('');
  L.push(`_Consolidado contra HEAD \`${sha}\`. ${total} report(s) em \`reports/\`._`);
  L.push('');
  return L.join('\n');
}

function main() {
  const reports = loadReports(REPORTS_DIR);
  // sha: pega do report mais comum (todos deveriam bater); fallback 'unknown'.
  const shas = reports.map((r) => r.measured_against_sha).filter(Boolean);
  const sha = shas[0] || 'unknown';
  if (shas.length && new Set(shas).size > 1) {
    console.warn(`[consolidate] AVISO: reports medidos contra SHAs diferentes: ${[...new Set(shas)].join(', ')} (anti-stale).`);
  }

  const data = build(reports);
  const md = renderMd(data, sha);
  writeFileSync(join(OUT_DIR, 'CONSOLIDADO.md'), md, 'utf8');

  const rollup = {
    gerado_contra_sha: sha,
    total: data.sorted.length,
    media: data.media,
    abaixo_70: data.sorted.filter((r) => r.nota < 70).length,
    ds_sum: data.dsSum,
    distribuicao: data.dist,
    telas: data.sorted.map((r) => ({
      screen: r.screen,
      nota: r.nota,
      nivel: r.nivel,
      mech_fails: mechFails(r).map((x) => x.id),
      judged_fails: judgedFails(r).map((x) => x.id),
      ds_total: dsTotal(r),
      measured_against_sha: r.measured_against_sha,
    })),
  };
  writeFileSync(join(OUT_DIR, 'CONSOLIDADO.json'), JSON.stringify(rollup, null, 2), 'utf8');

  console.log(`[consolidate] ${data.sorted.length} tela(s) · média ${data.media} · ${rollup.abaixo_70} < 70 · Σ ds/* ${data.dsSum}`);
  console.log(`[consolidate] escrito: CONSOLIDADO.md + CONSOLIDADO.json em ${OUT_DIR}`);
}

main();
