#!/usr/bin/env node
// Curador — Fase 3: REPORT
// Gera relatório markdown por batch (default: 500 itens) em D:\Conhecimento\_TRIAGEM\YYYY-MM-DD-batch-NNN.md
// Cada batch fica registrado em db/batches.jsonl pra apply.mjs encontrar depois.
//
// Uso:
//   node scripts/curador/report.mjs [--batch-size 500] [--out "D:\Conhecimento\_TRIAGEM"]

import { promises as fs } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { appendJsonl, readJsonl, nowIso } from './lib/db.mjs';

const SCRIPT_DIR = dirname(fileURLToPath(import.meta.url));
const DB_FILES = join(SCRIPT_DIR, 'db', 'files.jsonl');
const DB_CLASS = join(SCRIPT_DIR, 'db', 'classifications.jsonl');
const DB_BATCHES = join(SCRIPT_DIR, 'db', 'batches.jsonl');

function parseArgs(argv) {
  const args = { batchSize: 500, out: 'D:\\Conhecimento\\_TRIAGEM' };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--batch-size') args.batchSize = parseInt(argv[++i], 10);
    else if (a === '--out') args.out = argv[++i];
  }
  return args;
}

function fmtSize(bytes) {
  if (bytes < 1024) return `${bytes}B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)}KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)}MB`;
}

function mdEscape(s) {
  return String(s).replace(/\|/g, '\\|').replace(/\n/g, ' ');
}

function batchFileName(date, n) {
  const pad = String(n).padStart(3, '0');
  return `${date}-batch-${pad}.md`;
}

function renderBatch(batchId, items, files, classMap) {
  const today = new Date().toISOString().slice(0, 10);
  const byBucket = {};
  for (const it of items) {
    byBucket[it.bucket] = (byBucket[it.bucket] || 0) + 1;
  }

  let md = `# Curador — Batch ${batchId}\n\n`;
  md += `**Data:** ${today}\n`;
  md += `**Total itens:** ${items.length}\n\n`;
  md += `## Resumo por bucket\n\n`;
  for (const [b, c] of Object.entries(byBucket).sort((a, b) => b[1] - a[1])) {
    md += `- **${b}**: ${c}\n`;
  }
  md += `\n## Como aprovar\n\n`;
  md += `1. Lê a tabela abaixo\n`;
  md += `2. Marca \`[x]\` na coluna **Approve** pros que quer executar\n`;
  md += `3. Salva esse arquivo\n`;
  md += `4. Roda \`node scripts/curador/apply.mjs --batch ${batchId} --approved\`\n\n`;
  md += `> **Não aprovar = pula no apply.** Default é seguro.\n\n`;
  md += `## Itens\n\n`;

  // Group by bucket for readability
  const groups = {};
  for (const it of items) {
    const fileRec = files.find((f) => f.path === it.path);
    if (!groups[it.bucket]) groups[it.bucket] = [];
    groups[it.bucket].push({ ...it, fileRec });
  }

  for (const bucket of ['sensitive', 'discard', 'memory', 'user', 'spec', 'ambiguous']) {
    if (!groups[bucket]) continue;
    md += `### Bucket: \`${bucket}\` (${groups[bucket].length})\n\n`;
    md += `| Approve | Path | Size | Rule | Destination | Flags |\n`;
    md += `|:-:|---|--:|---|---|---|\n`;
    for (const g of groups[bucket]) {
      const flags = (g.sensitive_flags || []).join(', ');
      md += `| [ ] | \`${mdEscape(g.path)}\` | ${fmtSize(g.fileRec?.size_bytes || 0)} | ${mdEscape(g.rule_matched)} | ${mdEscape(g.sub_destination || '-')} | ${mdEscape(flags)} |\n`;
    }
    md += `\n`;
  }

  return md;
}

async function main() {
  const args = parseArgs(process.argv);
  const files = await readJsonl(DB_FILES);
  const classifications = await readJsonl(DB_CLASS);

  if (classifications.length === 0) {
    console.error('No classifications. Run classify.mjs first.');
    process.exit(2);
  }

  // Already-batched paths shouldn't appear again
  const existingBatches = await readJsonl(DB_BATCHES);
  const alreadyBatched = new Set();
  for (const b of existingBatches) {
    for (const p of b.paths || []) alreadyBatched.add(p);
  }

  const pending = classifications.filter((c) => !alreadyBatched.has(c.path));
  console.log(`[report] ${pending.length} pending items (out of ${classifications.length} total)`);
  if (pending.length === 0) {
    console.log('Nothing to report. All classified items already in batches.');
    return;
  }

  const outAbs = resolve(args.out);
  await fs.mkdir(outAbs, { recursive: true });
  const today = new Date().toISOString().slice(0, 10);

  // Find next batch number for today
  const todays = existingBatches.filter((b) => b.id.startsWith(today)).length;
  let batchNum = todays + 1;

  let written = 0;
  for (let i = 0; i < pending.length; i += args.batchSize) {
    const slice = pending.slice(i, i + args.batchSize);
    const batchId = `${today}-${String(batchNum).padStart(3, '0')}`;
    const fileName = batchFileName(today, batchNum);
    const fullPath = join(outAbs, fileName);

    const md = renderBatch(batchId, slice, files, null);
    await fs.writeFile(fullPath, md, 'utf8');

    await appendJsonl(DB_BATCHES, {
      id: batchId,
      file: fullPath,
      created_at: nowIso(),
      status: 'pending',
      item_count: slice.length,
      paths: slice.map((c) => c.path),
    });

    console.log(`[report] wrote ${fullPath} (${slice.length} items)`);
    batchNum++;
    written++;
  }

  console.log(`\n[report] DONE. ${written} batch(es) written to ${outAbs}`);
  console.log(`\nNext steps:`);
  console.log(`  1. Open each batch.md in ${outAbs}`);
  console.log(`  2. Mark [x] on rows you want to apply`);
  console.log(`  3. Run: node scripts/curador/apply.mjs --batch <id> --approved`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
