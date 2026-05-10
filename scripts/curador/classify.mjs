#!/usr/bin/env node
// Curador — Fase 2: CLASSIFY
// Aplica 18 heurísticas de lib/rules.mjs em arquivos discovered ainda não classificados.
// Detecta duplicatas por hash MD5 (2ª+ ocorrência → bucket=discard).
// Idempotente: pula arquivos já em classifications.jsonl.
//
// Uso: node scripts/curador/classify.mjs [--reclassify]

import { dirname, join, dirname as pathDirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { appendJsonl, readJsonl, rewriteJsonl, nowIso } from './lib/db.mjs';
import { classifyFile, RULE_COUNT } from './lib/rules.mjs';

const SCRIPT_DIR = dirname(fileURLToPath(import.meta.url));
const DB_FILES = join(SCRIPT_DIR, 'db', 'files.jsonl');
const DB_CLASS = join(SCRIPT_DIR, 'db', 'classifications.jsonl');

function parseArgs(argv) {
  return { reclassify: argv.includes('--reclassify') };
}

async function main() {
  const args = parseArgs(process.argv);
  const files = await readJsonl(DB_FILES);
  console.log(`[classify] ${files.length} files in db/files.jsonl`);
  console.log(`[classify] ${RULE_COUNT} rules loaded from lib/rules.mjs`);

  if (files.length === 0) {
    console.error('No files. Run discover.mjs first.');
    process.exit(2);
  }

  // Index by md5 for dedupe (first occurrence wins).
  const md5Seen = new Map(); // md5 → first path
  for (const f of files) {
    if (!md5Seen.has(f.md5)) md5Seen.set(f.md5, f.path);
  }

  // Already classified?
  const existing = args.reclassify ? [] : await readJsonl(DB_CLASS);
  const classifiedPaths = new Set(existing.map((c) => c.path));

  if (args.reclassify) {
    await rewriteJsonl(DB_CLASS, []);
    console.log('[classify] --reclassify: cleared db/classifications.jsonl');
  }

  let processed = 0;
  const buckets = {};
  const startMs = Date.now();

  for (const f of files) {
    if (classifiedPaths.has(f.path)) continue;

    const isDuplicate = md5Seen.get(f.md5) !== f.path;
    const duplicateOf = isDuplicate ? md5Seen.get(f.md5) : null;

    const classification = classifyFile({
      path: f.path,
      sizeBytes: f.size_bytes,
      mtime: f.mtime,
      extension: f.extension,
      basename: f.basename,
      // Agent E (security review) 2026-05-10: f.path.replace(f.basename, '') quebra
      // se basename aparece duas vezes no path (D:\foo\foo\foo.txt vira D:\\foo.txt).
      dirname: pathDirname(f.path),
      md5: f.md5,
      isDuplicate,
      duplicateOf,
    });

    const record = {
      path: f.path,
      md5: f.md5,
      bucket: classification.bucket,
      sub_destination: classification.subDestination,
      sensitive_flags: classification.sensitiveFlags,
      rule_matched: classification.ruleMatched,
      confidence: classification.confidence,
      classified_at: nowIso(),
      classified_by: 'classify.mjs',
    };
    await appendJsonl(DB_CLASS, record);

    buckets[classification.bucket] = (buckets[classification.bucket] || 0) + 1;
    processed++;
    if (processed % 1000 === 0) {
      console.log(`[classify] ${processed} processed...`);
    }
  }

  const elapsed = ((Date.now() - startMs) / 1000).toFixed(1);
  console.log(`\n[classify] DONE in ${elapsed}s`);
  console.log(`  processed=${processed} (${args.reclassify ? 'all' : 'new only'})`);
  console.log(`\nBy bucket:`);
  for (const [b, c] of Object.entries(buckets).sort((a, b) => b[1] - a[1])) {
    console.log(`  ${b}: ${c}`);
  }
  const total = files.length;
  const auto = total - (buckets.ambiguous || 0);
  const pctAuto = ((auto / total) * 100).toFixed(1);
  console.log(`\nAuto-classified: ${auto}/${total} (${pctAuto}%)`);
  if (parseFloat(pctAuto) < 70) {
    console.warn(`⚠️  pct_auto_classified < 70% — heuristics may be weak. Review ambiguous items.`);
  }
  console.log(`\nNext: node scripts/curador/report.mjs --batch-size 500`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
