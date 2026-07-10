#!/usr/bin/env node
// @ts-check
/**
 * comentar.mjs — upsert do comentário do pr-critic no PR (determinístico, GitHub API).
 *
 * Acha o comentário existente pelo marcador HTML e EDITA (1 comentário vivo por PR,
 * nunca empilha ruído a cada push). Sem comentário anterior, cria.
 *
 * Uso (CI): GITHUB_TOKEN=... GITHUB_REPOSITORY=owner/repo \
 *   node scripts/pr-critic/comentar.mjs --pr 123 --body storage/pr-critic/comentario.md
 */

import { readFileSync } from 'node:fs';
import { basename } from 'node:path';

export const MARCADOR = '<!-- pr-critic-contrato -->';

function argVal(flag) {
  const i = process.argv.indexOf(flag);
  return i !== -1 ? process.argv[i + 1] : null;
}

async function gh(caminho, opts = {}) {
  const res = await fetch(`https://api.github.com${caminho}`, {
    ...opts,
    headers: {
      authorization: `Bearer ${process.env.GITHUB_TOKEN}`,
      accept: 'application/vnd.github+json',
      'x-github-api-version': '2022-11-28',
      ...(opts.body ? { 'content-type': 'application/json' } : {}),
    },
  });
  if (!res.ok) throw new Error(`GitHub API ${res.status} em ${caminho}: ${(await res.text()).slice(0, 300)}`);
  return res.json();
}

async function acharComentarioExistente(repo, pr) {
  for (let page = 1; page <= 10; page++) {
    const lote = await gh(`/repos/${repo}/issues/${pr}/comments?per_page=100&page=${page}`);
    const hit = lote.find((c) => typeof c.body === 'string' && c.body.includes(MARCADOR));
    if (hit) return hit;
    if (lote.length < 100) return null;
  }
  return null;
}

async function main() {
  const repo = process.env.GITHUB_REPOSITORY;
  const pr = argVal('--pr');
  const body = readFileSync(argVal('--body'), 'utf8');
  if (!process.env.GITHUB_TOKEN || !repo || !pr) {
    console.error('[comentar] faltando GITHUB_TOKEN / GITHUB_REPOSITORY / --pr');
    process.exit(1);
  }
  const existente = await acharComentarioExistente(repo, pr);
  if (existente) {
    await gh(`/repos/${repo}/issues/comments/${existente.id}`, { method: 'PATCH', body: JSON.stringify({ body }) });
    console.log(`[comentar] comentário ${existente.id} atualizado no PR #${pr}`);
  } else {
    await gh(`/repos/${repo}/issues/${pr}/comments`, { method: 'POST', body: JSON.stringify({ body }) });
    console.log(`[comentar] comentário criado no PR #${pr}`);
  }
}

if (process.argv[1] && import.meta.url.endsWith(basename(process.argv[1]))) {
  main().catch((e) => { console.error(`[comentar] ERRO: ${e.message}`); process.exit(1); });
}
