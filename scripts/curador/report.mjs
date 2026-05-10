#!/usr/bin/env node
// Curador — Fase 3: REPORT
// Gera relatório markdown agrupado POR BUCKET em D:\Conhecimento\_TRIAGEM\.
// Ordem de prioridade (sensitive primeiro pra Wagner não perder secrets):
//   sensitive → memory → user → spec → ambiguous → discard
// Cada bucket vira N arquivos de até --batch-size itens cada.
// File naming: YYYY-MM-DD-<bucket>-NNN.md (ex: 2026-05-10-sensitive-001.md).
//
// Uso:
//   node scripts/curador/report.mjs [--batch-size 500] [--out "D:\Conhecimento\_TRIAGEM"]

import { promises as fs } from 'node:fs';
import { dirname, join, resolve, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { appendJsonl, readJsonl, nowIso } from './lib/db.mjs';

const SCRIPT_DIR = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(SCRIPT_DIR, '..', '..');
const DB_FILES = join(SCRIPT_DIR, 'db', 'files.jsonl');
const DB_CLASS = join(SCRIPT_DIR, 'db', 'classifications.jsonl');
const DB_BATCHES = join(SCRIPT_DIR, 'db', 'batches.jsonl');

const BUCKET_PRIORITY = ['sensitive', 'memory', 'user', 'spec', 'ambiguous', 'discard'];

const BUCKET_HEADER = {
  sensitive: '🚨 SENSITIVE — credenciais, certificados, PII. Mover pra Vaultwarden.',
  memory: '💎 MEMORY — material útil pro repo (memory/requisitos/<Mod>/).',
  user: '👤 USER — knowledge atribuído a um dev específico (memory/users/<user>/).',
  spec: '📋 SPEC — candidato a US (cliente paga + reporta).',
  ambiguous: '🤔 AMBIGUOUS — Claude deve revisar OU Wagner descarta.',
  discard: '🗑️ DISCARD — clones OSS, duplicatas, atas antigas (vai pra _DESCARTADO/).',
};

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

function batchFileName(date, bucket, n) {
  const pad = String(n).padStart(3, '0');
  return `${date}-${bucket}-${pad}.md`;
}

function renderBatch(batchId, bucket, items, filesById) {
  const today = new Date().toISOString().slice(0, 10);

  let md = `# Curador — Batch ${batchId}\n\n`;
  md += `**Data:** ${today}\n`;
  md += `**Bucket:** \`${bucket}\`\n`;
  md += `**Total itens:** ${items.length}\n\n`;
  md += `> ${BUCKET_HEADER[bucket] || bucket}\n\n`;
  md += `## Como aprovar\n\n`;
  md += `1. Lê a tabela abaixo\n`;
  md += `2. Marca \`[x]\` na coluna **Approve** pros que quer executar\n`;
  md += `3. Salva esse arquivo\n`;
  md += `4. Roda \`node scripts/curador/apply.mjs --batch ${batchId} --approved\`\n\n`;
  md += `> **Não aprovar = pula no apply.** Default é seguro.\n\n`;

  if (bucket === 'sensitive') {
    md += `## ⚠️ Atenção\n\n`;
    md += `Apply move pra \`D:\\Conhecimento\\_VAULT-PENDING\\<categoria>\\\` (não git).\n`;
    md += `Você precisa **mover manualmente pro Vaultwarden** depois — Curador não tem CLI Vaultwarden.\n\n`;
  }

  if (bucket === 'discard') {
    md += `## ℹ️ Quarentena\n\n`;
    md += `Apply move pra \`D:\\Conhecimento\\_DESCARTADO\\\` (não \`rm\`). Você deleta manualmente quando quiser.\n\n`;
  }

  md += `## Itens\n\n`;
  md += `| Approve | Path | Size | Rule | Destination | Flags |\n`;
  md += `|:-:|---|--:|---|---|---|\n`;

  for (const it of items) {
    const fileRec = filesById.get(it.path);
    const flags = (it.sensitive_flags || []).join(', ');
    md += `| [ ] | \`${mdEscape(it.path)}\` | ${fmtSize(fileRec?.size_bytes || 0)} | ${mdEscape(it.rule_matched)} | ${mdEscape(it.sub_destination || '-')} | ${mdEscape(flags)} |\n`;
  }
  md += `\n`;

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

  // Index files by path for size lookup
  const filesById = new Map(files.map((f) => [f.path, f]));

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

  // Group by bucket
  const byBucket = {};
  for (const c of pending) {
    if (!byBucket[c.bucket]) byBucket[c.bucket] = [];
    byBucket[c.bucket].push(c);
  }

  console.log(`[report] grouping by bucket:`);
  for (const b of BUCKET_PRIORITY) {
    if (byBucket[b]) console.log(`  ${b}: ${byBucket[b].length}`);
  }

  const outAbs = resolve(args.out);

  // Agent E (security review) 2026-05-10: LGPD timebomb — paths absolutos
  // do PC (incluindo C:\Users\<dev>\) ficam em batch.md. Se --out apontar
  // pra dentro do repo, vão pro git. Bloquear.
  const outRel = relative(REPO_ROOT, outAbs);
  if (!outRel.startsWith('..') && !/^[a-z]:/i.test(outRel) && outRel !== '') {
    console.error(`ERROR: --out "${outAbs}" está dentro do REPO_ROOT.`);
    console.error(`Batch.md contém paths absolutos do PC — risco LGPD se commitado.`);
    console.error(`Use --out fora do repositório (ex: D:\\Conhecimento\\_TRIAGEM\\).`);
    process.exit(2);
  }

  await fs.mkdir(outAbs, { recursive: true });
  const today = new Date().toISOString().slice(0, 10);

  let totalBatches = 0;

  // Iterate in priority order
  for (const bucket of BUCKET_PRIORITY) {
    const items = byBucket[bucket];
    if (!items || items.length === 0) continue;

    let batchNum = 1;
    for (let i = 0; i < items.length; i += args.batchSize) {
      const slice = items.slice(i, i + args.batchSize);
      const batchId = `${today}-${bucket}-${String(batchNum).padStart(3, '0')}`;
      const fileName = batchFileName(today, bucket, batchNum);
      const fullPath = join(outAbs, fileName);

      const md = renderBatch(batchId, bucket, slice, filesById);
      await fs.writeFile(fullPath, md, 'utf8');

      await appendJsonl(DB_BATCHES, {
        id: batchId,
        bucket,
        file: fullPath,
        created_at: nowIso(),
        status: 'pending',
        item_count: slice.length,
        paths: slice.map((c) => c.path),
      });

      console.log(`[report] wrote ${fullPath} (${slice.length} items)`);
      batchNum++;
      totalBatches++;
    }
  }

  console.log(`\n[report] DONE. ${totalBatches} batch(es) written to ${outAbs}`);
  console.log(`\nReview order (priority):`);
  for (const bucket of BUCKET_PRIORITY) {
    if (byBucket[bucket]) {
      const n = Math.ceil(byBucket[bucket].length / args.batchSize);
      console.log(`  ${bucket}: ${n} batch(es), ${byBucket[bucket].length} items`);
    }
  }
  console.log(`\nNext steps:`);
  console.log(`  1. Open ${today}-sensitive-*.md FIRST (priority)`);
  console.log(`  2. Mark [x] on rows you want to apply`);
  console.log(`  3. Run: node scripts/curador/apply.mjs --batch <id> --approved`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
