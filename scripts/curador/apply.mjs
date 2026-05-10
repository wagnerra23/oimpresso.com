#!/usr/bin/env node
// Curador — Fase 5: APPLY
// Lê batch.md, encontra rows com [x] na coluna Approve, executa ações:
//   - sensitive → move pra _VAULT-PENDING/<categoria>/
//   - discard   → move pra _DESCARTADO/
//   - memory    → copia pra <repo>/memory/requisitos/<Mod>/ + git add (NÃO commita)
//   - user      → copia pra <repo>/memory/users/<user>/
//
// NÃO commita git — Wagner commita pra preservar commit-discipline.
//
// Uso: node scripts/curador/apply.mjs --batch <id> --approved [--dry-run]

import { promises as fs } from 'node:fs';
import { dirname, join, basename, resolve, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { readJsonl, appendJsonl, rewriteJsonl, nowIso } from './lib/db.mjs';

const SCRIPT_DIR = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(SCRIPT_DIR, '..', '..');
const DB_BATCHES = join(SCRIPT_DIR, 'db', 'batches.jsonl');
const DB_CLASS = join(SCRIPT_DIR, 'db', 'classifications.jsonl');
const DB_APPLIED = join(SCRIPT_DIR, 'db', 'applied.jsonl');

const VAULT_PENDING_BASE = 'D:\\Conhecimento\\_VAULT-PENDING';
const DESCARTADO_BASE = 'D:\\Conhecimento\\_DESCARTADO';

function parseArgs(argv) {
  const args = { batch: null, approved: false, dryRun: false };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--batch') args.batch = argv[++i];
    else if (a === '--approved') args.approved = true;
    else if (a === '--dry-run') args.dryRun = true;
  }
  return args;
}

function parseApprovedRows(md) {
  // Match: | [x] | `path` | size | rule | destination | flags |
  // Approve column is `[x]` (case-insensitive).
  const approved = [];
  const re = /^\|\s*\[x\]\s*\|\s*`([^`]+)`\s*\|/gim;
  let m;
  while ((m = re.exec(md)) !== null) {
    approved.push(m[1]);
  }
  return approved;
}

async function safeMove(src, dst, dryRun) {
  if (dryRun) {
    console.log(`  [dry] move ${src} → ${dst}`);
    return;
  }
  await fs.mkdir(dirname(dst), { recursive: true });
  try {
    await fs.rename(src, dst);
  } catch (err) {
    if (err.code === 'EXDEV') {
      // cross-device: copy + delete
      await fs.copyFile(src, dst);
      await fs.unlink(src);
    } else {
      throw err;
    }
  }
}

async function safeCopy(src, dst, dryRun) {
  if (dryRun) {
    console.log(`  [dry] copy ${src} → ${dst}`);
    return;
  }
  await fs.mkdir(dirname(dst), { recursive: true });
  await fs.copyFile(src, dst);
}

function gitAdd(relPath, dryRun) {
  if (dryRun) {
    console.log(`  [dry] git add ${relPath}`);
    return;
  }
  const r = spawnSync('git', ['add', relPath], { cwd: REPO_ROOT, encoding: 'utf8' });
  if (r.status !== 0) {
    console.warn(`  git add failed: ${r.stderr}`);
  }
}

async function applyOne(classRec, dryRun) {
  const src = classRec.path;
  const bucket = classRec.bucket;
  const sub = classRec.sub_destination || '';

  if (bucket === 'sensitive') {
    const dst = join(VAULT_PENDING_BASE, sub.replace(/^_VAULT-PENDING\//, ''), basename(src));
    await safeMove(src, dst, dryRun);
    return { action: 'moved', from: src, to: dst };
  }
  if (bucket === 'discard') {
    const dst = join(DESCARTADO_BASE, sub.replace(/^_DESCARTADO\//, ''), basename(src));
    await safeMove(src, dst, dryRun);
    return { action: 'moved', from: src, to: dst };
  }
  if (bucket === 'memory' || bucket === 'user') {
    const subClean = sub.replace(/^memory\//, '');
    const dstRel = join('memory', subClean, basename(src));
    const dstAbs = join(REPO_ROOT, dstRel);
    await safeCopy(src, dstAbs, dryRun);
    gitAdd(dstRel, dryRun);
    return { action: 'copied+git-add', from: src, to: dstAbs };
  }
  if (bucket === 'spec' || bucket === 'ambiguous') {
    return { action: 'skipped', reason: `bucket=${bucket} requires manual handling`, from: src };
  }
  return { action: 'skipped', reason: `unknown bucket=${bucket}`, from: src };
}

async function main() {
  const args = parseArgs(process.argv);
  if (!args.batch) {
    console.error('ERROR: --batch <id> required.');
    process.exit(2);
  }
  if (!args.approved) {
    console.error('ERROR: --approved required (safety gate).');
    process.exit(2);
  }

  const batches = await readJsonl(DB_BATCHES);
  const batch = batches.find((b) => b.id === args.batch);
  if (!batch) {
    console.error(`ERROR: batch "${args.batch}" not found in db/batches.jsonl`);
    process.exit(2);
  }

  const md = await fs.readFile(batch.file, 'utf8');
  const approvedPaths = parseApprovedRows(md);
  console.log(`[apply] batch=${batch.id} approved=${approvedPaths.length}/${batch.item_count}`);

  if (approvedPaths.length === 0) {
    console.log('No rows marked [x]. Nothing to do.');
    return;
  }

  const classifications = await readJsonl(DB_CLASS);
  const classMap = new Map(classifications.map((c) => [c.path, c]));

  let actions = { moved: 0, 'copied+git-add': 0, skipped: 0 };
  const startMs = Date.now();

  for (const path of approvedPaths) {
    const c = classMap.get(path);
    if (!c) {
      console.warn(`  not in classifications: ${path}`);
      actions.skipped++;
      continue;
    }
    try {
      const result = await applyOne(c, args.dryRun);
      actions[result.action] = (actions[result.action] || 0) + 1;
      if (!args.dryRun) {
        await appendJsonl(DB_APPLIED, {
          ...result,
          batch_id: args.batch,
          bucket: c.bucket,
          rule_matched: c.rule_matched,
          applied_at: nowIso(),
        });
      }
    } catch (err) {
      console.error(`  ERR ${path}: ${err.message}`);
      actions.skipped++;
    }
  }

  // Update batch status
  if (!args.dryRun) {
    const updated = batches.map((b) =>
      b.id === args.batch ? { ...b, status: 'applied', applied_at: nowIso() } : b
    );
    await rewriteJsonl(DB_BATCHES, updated);
  }

  const elapsed = ((Date.now() - startMs) / 1000).toFixed(1);
  console.log(`\n[apply] DONE in ${elapsed}s ${args.dryRun ? '(dry-run)' : ''}`);
  for (const [a, c] of Object.entries(actions)) console.log(`  ${a}: ${c}`);
  console.log(`\nReminder: git add was used; YOU commit (not Curador). Run:`);
  console.log(`  git status`);
  console.log(`  git commit -m "feat(curador): batch ${args.batch} ingested\\n\\nRefs: curador-${args.batch}"`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
