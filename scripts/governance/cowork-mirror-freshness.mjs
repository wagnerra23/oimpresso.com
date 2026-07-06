#!/usr/bin/env node
// @ts-check
/**
 * cowork-mirror-freshness.mjs — sentinela de FRESCOR do espelho Cowork.
 *
 * Fecha o PONTO CEGO #2 do `anchor-content-check.mjs` (session 2026-07-06): aquele prova
 * que a âncora aponta pro arquivo CERTO do repo (não shell/fantasma) — CORREÇÃO da âncora.
 * NÃO prova que a cópia do repo (`prototipo-ui/cowork/<arq>`) ainda BATE com o design VIVO
 * no Cowork (claude.ai/design). "Protótipo de bubble velha" — o arquivo existe, tem conteúdo
 * do módulo, passa no anchor-content — mas é design ANTIGO. Só um diff byte-a-byte pega.
 * Este sentinela é esse diff: md5(repo) vs md5(vivo). Divergiu = espelho do repo está STALE
 * (re-exportar do Cowork). Medido 2026-07-06: financeiro-page.jsx = ae3a2cfe… em ambos = SYNC.
 *
 * LEI (ADR 0315 + 0299 + 0324): claude.ai/design NÃO é fonte canônica — git/Cowork é. Este
 * sentinela usa SÓ os métodos de LEITURA da integração DesignSync (`list_projects`/`get_file`,
 * livres por 0315 Eixo B) e NUNCA escreve/puxa design PRA dentro do git. Ele só GRITA "o
 * espelho divergiu" — humano decide re-exportar. Nunca o inverso (nuvem → git).
 *
 * POR QUE NÃO É GATE DE PR: a leitura viva exige `/design-login` (auth claude.ai) que não
 * roda em CI headless (ADR 0315 §Furos, tentativa 2). Logo o `--compare` é ROTINA local/
 * dispatch conduzida por um AGENTE (que chama DesignSync); só o `--selftest` roda no CI.
 * Nasce ADVISORY (ADR 0314) — wirado no design-memory-gate.yml ao lado do anchor-content.
 *
 * ── ARQUITETURA (o node não fala MCP; o split é honesto) ──────────────────────
 *   1. LOCAL (puro, roda em qualquer lugar): `--manifest` enumera os arquivos-âncora do
 *      espelho (mesmo conjunto do anchor-content, via `anchorFile`), calcula md5 do repo,
 *      e emite a "lista de compras" JSON.
 *   2. VIVO (agente/dispatch, FORA do CI): o agente lê o manifesto, chama
 *      `DesignSync.get_file` por arquivo (LEITURA — livre por 0315), calcula md5 do conteúdo,
 *      e monta o snapshot `{ "<basename>": "<md5-hex>" }` (null/"" = não achou no projeto vivo).
 *   3. COMPARE (puro): `--compare snapshot.json` reclassifica cada arquivo contra o snapshot.
 *
 * Classificação (determinística, zero LLM):
 *   SYNC        — md5(repo) === md5(vivo). Espelho fresco.
 *   STALE       — md5 diferem. Espelho DIVERGIU do vivo → re-exportar. (o sinal DURO)
 *   LIVE-ABSENT — o agente buscou e o arquivo NÃO existe no projeto vivo (rename/delete upstream
 *                 OU mapa de projeto errado). Aviso, NÃO stale — remediação diferente.
 *   UNCHECKED   — o arquivo do manifesto não veio no snapshot (o agente não buscou). Aviso.
 *                 NUNCA vira SYNC no silêncio ("a suite não mente").
 *
 * Com `--check` sai 1 SÓ em STALE (o único sinal byte-provado). UNCHECKED/LIVE-ABSENT = warn
 * (exit 0) — são "não deu pra verificar", não "está podre".
 *
 * Uso:
 *   node scripts/governance/cowork-mirror-freshness.mjs --manifest            # lista de compras (stdout JSON)
 *   node scripts/governance/cowork-mirror-freshness.mjs --manifest --all      # todo .jsx/.html do espelho
 *   node scripts/governance/cowork-mirror-freshness.mjs --compare snap.json   # relatório
 *   node scripts/governance/cowork-mirror-freshness.mjs --compare snap.json --check   # exit 1 se STALE
 *   node scripts/governance/cowork-mirror-freshness.mjs --selftest            # contrato (roda no CI)
 */

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join } from 'node:path';
import { anchorFile } from './anchor-content-check.mjs'; // fonte única de "como extrair o arquivo do related_prototype"

const ROOT = process.cwd();

/** md5 hex de bytes (Buffer) ou string utf8 — casa com `git cat-file -p | md5sum` e com o md5
 *  do conteúdo utf8 devolvido por DesignSync.get_file. Freshness = identidade de bytes. */
export function md5(bufOrStr) {
  return createHash('md5').update(bufOrStr).digest('hex');
}

/** Classificação 3-vias (pura, testável) a partir dos dois hashes. */
export function classifyMirror({ repoMd5, liveMd5 }) {
  if (liveMd5 == null || liveMd5 === '') return 'LIVE-ABSENT';
  return repoMd5 === liveMd5 ? 'SYNC' : 'STALE';
}

/** Veredito de UM arquivo do manifesto contra o snapshot do vivo. Distingue "não buscado"
 *  (UNCHECKED) de "buscado e não achado" (LIVE-ABSENT) — o snapshot marca ausência com null. */
export function verdictFor(coworkKey, repoMd5, snapshot) {
  if (!(coworkKey in snapshot)) return 'UNCHECKED';
  return classifyMirror({ repoMd5, liveMd5: snapshot[coworkKey] });
}

/** O gate só morde em STALE — o único sinal byte-provado de divergência. */
export function shouldFail(verdicts) {
  return verdicts.some((v) => v === 'STALE');
}

/** Enumera os arquivos-âncora do espelho Cowork a partir dos charters (mesmo conjunto que o
 *  anchor-content-check enxerga — reusa `anchorFile`). Parametrizado por root pro fixture. */
export function buildManifest(root = ROOT, { all = false } = {}) {
  const PAGES = join(root, 'resources', 'js', 'Pages');
  const COWORK = join(root, 'prototipo-ui', 'cowork');
  const seen = new Map(); // basename → { cowork, repoPath, repoMd5, telas: [] }

  const add = (base, telas) => {
    const abs = join(COWORK, base);
    if (!existsSync(abs)) return; // só arquivos que existem no espelho (o MISSING é problema do anchor-content)
    if (!seen.has(base)) {
      seen.set(base, {
        cowork: base,
        repoPath: `prototipo-ui/cowork/${base}`,
        repoMd5: md5(readFileSync(abs)),
        telas: [],
      });
    }
    for (const t of telas) if (!seen.get(base).telas.includes(t)) seen.get(base).telas.push(t);
  };

  if (all) {
    walkCowork(COWORK).forEach((base) => add(base, []));
  }
  for (const charter of walkCharters(PAGES)) {
    const t = readFileSync(charter, 'utf8');
    const m = t.match(/^related_prototype:\s*(.+)$/m);
    if (!m) continue;
    const file = anchorFile(m[1].trim());
    if (!file) continue; // prosa não-resolvível — mesmo escopo que o anchor-content pula
    const base = file.split('/').pop();
    const tela = charter.slice(PAGES.length + 1).split('\\').join('/').replace(/\.charter\.md$/, '');
    add(base, [tela]);
  }
  return [...seen.values()].sort((a, b) => a.cowork.localeCompare(b.cowork));
}

function walkCharters(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir)) {
    const f = join(dir, e);
    if (statSync(f).isDirectory()) walkCharters(f, acc);
    else if (f.endsWith('.charter.md')) acc.push(f);
  }
  return acc;
}

function walkCowork(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir)) {
    const f = join(dir, e);
    if (statSync(f).isDirectory()) walkCowork(f, acc);
    else if (/\.(jsx|html)$/i.test(e)) acc.push(e);
  }
  return acc;
}

// ── CLI ───────────────────────────────────────────────────────────────────────
function main() {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) return; // o selftest vive no .test.mjs; aqui é no-op de conveniência
  const all = argv.includes('--all');
  const cmpIdx = argv.indexOf('--compare');

  const manifest = buildManifest(ROOT, { all });

  if (cmpIdx === -1) {
    // modo manifesto: emite a lista de compras (JSON) + resumo humano no stderr
    process.stdout.write(JSON.stringify({ generated: 'cowork-mirror-freshness manifest', files: manifest }, null, 2) + '\n');
    process.stderr.write(
      `\n  ${manifest.length} arquivo(s)-âncora do espelho Cowork pra conferir contra o vivo (claude.ai/design).\n` +
      `  Próximo passo (agente): DesignSync.get_file por arquivo → snapshot {basename: md5} → --compare snap.json.\n\n`,
    );
    return;
  }

  const snapPath = argv[cmpIdx + 1];
  if (!snapPath || !existsSync(snapPath)) {
    console.error(`✗ --compare exige um snapshot.json existente (do DesignSync.get_file). Recebi: ${snapPath || '(nada)'}`);
    process.exit(2);
  }
  const snapshot = JSON.parse(readFileSync(snapPath, 'utf8'));
  const strict = argv.includes('--check');

  const rows = manifest.map((f) => ({ ...f, veredito: verdictFor(f.cowork, f.repoMd5, snapshot) }));
  const stale = rows.filter((r) => r.veredito === 'STALE');
  const absent = rows.filter((r) => r.veredito === 'LIVE-ABSENT');
  const unchecked = rows.filter((r) => r.veredito === 'UNCHECKED');
  const sync = rows.filter((r) => r.veredito === 'SYNC');

  console.log(`\n  ESPELHO COWORK — frescor vs claude.ai/design (${rows.length} arquivo(s)-âncora)\n`);
  for (const r of stale) console.log(`  ⛔ STALE       ${r.cowork}  (repo divergiu do vivo — re-exportar)`);
  for (const r of absent) console.log(`  🟡 LIVE-ABSENT ${r.cowork}  (não achado no projeto vivo — rename/delete ou mapa errado)`);
  for (const r of unchecked) console.log(`  ⬜ UNCHECKED   ${r.cowork}  (agente não buscou — snapshot incompleto)`);
  console.log(`\n  ⛔ stale: ${stale.length} · 🟡 live-absent: ${absent.length} · ⬜ unchecked: ${unchecked.length} · ✓ sync: ${sync.length}\n`);

  if (strict && shouldFail(rows.map((r) => r.veredito))) {
    console.error(`✗ ${stale.length} arquivo(s) do espelho STALE — a cópia do repo divergiu do design vivo. Re-exporte do Cowork.`);
    process.exit(1);
  }
  console.log('✓ sem espelho STALE (divergência byte-provada).');
}

if (process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('cowork-mirror-freshness.mjs')) main();
