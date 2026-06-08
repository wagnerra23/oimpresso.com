// JSONL append-only DB helpers for Curador (zero-deps, Node 24 built-ins only).
// Each line = 1 JSON record. Append-safe under crash. Read = full scan in-memory.
// Sufficient for ~1M records (~500MB). For larger, switch to SQLite.

import { promises as fs } from 'node:fs';
import { dirname } from 'node:path';

export async function appendJsonl(path, record) {
  await fs.mkdir(dirname(path), { recursive: true });
  const line = JSON.stringify(record) + '\n';
  await fs.appendFile(path, line, 'utf8');
}

export async function readJsonl(path) {
  try {
    const txt = await fs.readFile(path, 'utf8');
    return txt
      .split('\n')
      .filter((l) => l.trim().length > 0)
      .map((l) => JSON.parse(l));
  } catch (err) {
    if (err.code === 'ENOENT') return [];
    throw err;
  }
}

export async function rewriteJsonl(path, records) {
  await fs.mkdir(dirname(path), { recursive: true });
  const tmp = path + '.tmp';
  const body = records.map((r) => JSON.stringify(r)).join('\n') + '\n';
  await fs.writeFile(tmp, body, 'utf8');
  // Windows EBUSY retry (other process may hold handle briefly).
  let lastErr;
  for (let attempt = 0; attempt < 5; attempt++) {
    try {
      await fs.rename(tmp, path);
      return;
    } catch (err) {
      if (err.code !== 'EBUSY' && err.code !== 'EPERM') throw err;
      lastErr = err;
      await new Promise((r) => setTimeout(r, 100 * (attempt + 1)));
    }
  }
  throw lastErr;
}

export function nowIso() {
  return new Date().toISOString();
}
