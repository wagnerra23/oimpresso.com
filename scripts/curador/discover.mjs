#!/usr/bin/env node
// Curador — Fase 1: DISCOVER
// Enumera arquivos sob --source, calcula MD5 streaming, popula db/files.jsonl.
// Idempotente: pula arquivos já vistos pelo path (não rehash).
//
// Uso:
//   node scripts/curador/discover.mjs --source "D:\Conhecimento" --user wagner
//   node scripts/curador/discover.mjs --stats

import { promises as fs, createReadStream } from 'node:fs';
import { resolve, extname, basename, dirname, join } from 'node:path';
import { createHash } from 'node:crypto';
import { userInfo } from 'node:os';
import { fileURLToPath } from 'node:url';
import { appendJsonl, readJsonl, nowIso } from './lib/db.mjs';

const SCRIPT_DIR = dirname(fileURLToPath(import.meta.url));
const DB_FILES = join(SCRIPT_DIR, 'db', 'files.jsonl');
const DB_CONSENT = join(SCRIPT_DIR, 'db', 'consent.jsonl');

function parseArgs(argv) {
  const args = { source: null, user: 'wagner', stats: false, dryRun: false };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--source') args.source = argv[++i];
    else if (a === '--user') args.user = argv[++i];
    else if (a === '--stats') args.stats = true;
    else if (a === '--dry-run') args.dryRun = true;
  }
  return args;
}

async function md5OfFile(path) {
  return new Promise((resolveP, rejectP) => {
    const hash = createHash('md5');
    const stream = createReadStream(path);
    stream.on('data', (chunk) => hash.update(chunk));
    stream.on('end', () => resolveP(hash.digest('hex')));
    stream.on('error', rejectP);
  });
}

async function* walk(dir) {
  let entries;
  try {
    entries = await fs.readdir(dir, { withFileTypes: true });
  } catch (err) {
    console.error(`[discover] skip dir ${dir}: ${err.message}`);
    return;
  }
  for (const entry of entries) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      yield* walk(full);
    } else if (entry.isFile()) {
      yield full;
    }
  }
}

async function checkConsent(user) {
  // Agent E (security review) 2026-05-10: cross-check OS username pra evitar
  // que outro dev (ex: Maiara no PC dela) rode `--user wagner` e bypass LGPD.
  const osUser = userInfo().username.toLowerCase();
  const wagnerAliases = new Set(['wagne', 'wagner', 'wagnerra']);

  if (user === 'wagner') {
    if (!wagnerAliases.has(osUser)) {
      console.error(`ERROR: --user wagner mas OS user é "${osUser}" — refusing scan.`);
      console.error(`Pra outro dev scanear como wagner, registrar consent log explícito.`);
      return false;
    }
    return true;
  }
  const consents = await readJsonl(DB_CONSENT);
  // Agent E: também valida que OS user matches o user pedido (consent precisa ser
  // do dono da máquina, não outra pessoa).
  return consents.some(
    (c) =>
      c.user === user &&
      c.granted_by === user &&
      c.os_user &&
      c.os_user.toLowerCase() === osUser
  );
}

async function loadKnownPaths() {
  const records = await readJsonl(DB_FILES);
  const seen = new Set();
  for (const r of records) seen.add(r.path);
  return seen;
}

async function showStats() {
  const records = await readJsonl(DB_FILES);
  const byUser = {};
  const byExt = {};
  for (const r of records) {
    byUser[r.owner_user] = (byUser[r.owner_user] || 0) + 1;
    byExt[r.extension || '(none)'] = (byExt[r.extension || '(none)'] || 0) + 1;
  }
  console.log(`Total files discovered: ${records.length}`);
  console.log(`\nBy owner_user:`);
  for (const [u, c] of Object.entries(byUser).sort((a, b) => b[1] - a[1])) {
    console.log(`  ${u}: ${c}`);
  }
  console.log(`\nTop 15 extensions:`);
  const exts = Object.entries(byExt).sort((a, b) => b[1] - a[1]).slice(0, 15);
  for (const [e, c] of exts) console.log(`  ${e}: ${c}`);
}

async function main() {
  const args = parseArgs(process.argv);

  if (args.stats) {
    await showStats();
    return;
  }

  if (!args.source) {
    console.error('ERROR: --source <path> required (or use --stats).');
    process.exit(2);
  }

  const sourceAbs = resolve(args.source);
  const sourceStat = await fs.stat(sourceAbs).catch(() => null);
  if (!sourceStat || !sourceStat.isDirectory()) {
    console.error(`ERROR: --source "${sourceAbs}" not a directory.`);
    process.exit(2);
  }

  const consented = await checkConsent(args.user);
  if (!consented) {
    console.error(`ERROR: scanning user "${args.user}" requires consent log.`);
    console.error(`  Add entry to db/consent.jsonl OR run via: /curador consent ${args.user}`);
    process.exit(2);
  }

  console.log(`[discover] source=${sourceAbs} user=${args.user} dry-run=${args.dryRun}`);
  const known = await loadKnownPaths();
  console.log(`[discover] ${known.size} files already known — will skip`);

  let added = 0;
  let skipped = 0;
  let errored = 0;
  let bytesProcessed = 0;
  const startMs = Date.now();

  for await (const filePath of walk(sourceAbs)) {
    if (known.has(filePath)) {
      skipped++;
      continue;
    }
    try {
      const st = await fs.stat(filePath);
      const md5 = await md5OfFile(filePath);
      const record = {
        path: filePath,
        size_bytes: st.size,
        md5,
        mtime: st.mtime.toISOString(),
        extension: extname(filePath).toLowerCase(),
        basename: basename(filePath),
        owner_user: args.user,
        discovered_at: nowIso(),
        discovered_by: 'discover.mjs',
      };
      if (!args.dryRun) {
        await appendJsonl(DB_FILES, record);
      }
      added++;
      bytesProcessed += st.size;
      if (added % 500 === 0) {
        const elapsed = ((Date.now() - startMs) / 1000).toFixed(1);
        console.log(`[discover] ${added} files added (${(bytesProcessed / 1024 / 1024).toFixed(1)} MB) in ${elapsed}s...`);
      }
    } catch (err) {
      errored++;
      console.error(`[discover] err ${filePath}: ${err.message}`);
    }
  }

  const elapsed = ((Date.now() - startMs) / 1000).toFixed(1);
  console.log(`\n[discover] DONE in ${elapsed}s`);
  console.log(`  added=${added} skipped=${skipped} errored=${errored}`);
  console.log(`  bytes=${(bytesProcessed / 1024 / 1024).toFixed(1)} MB`);
  console.log(`\nNext: node scripts/curador/classify.mjs`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
