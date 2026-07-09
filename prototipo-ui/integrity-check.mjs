#!/usr/bin/env node
// integrity-check.mjs — Testes de Integridade · PROCESSO_MEMORIA_CC.md §15
//
// Diferente do DS-GUARD (§8, valida uma build) e da Bateria (§9, valida uma
// evolucao): aqui valida que a PROPRIA MEMORIA nao corrompeu. Rodar no fim de
// sessao / antes de formalizar.
//
//   IT1 Espinha existe: STATUS · PROCESSO_MEMORIA_CC · MEMORY_INDEX · LICOES_CC
//   IT2 Todo *.charter.md tem tela viva (.tsx irmao) — ADVISORY (5 orfaos historicos)
//   IT3 STATUS aponta pra PROCESSO_MEMORIA_CC (ponteiro vivo)
//   IT4 LICOES_CC: L-NN contiguo, sem buraco/duplicata
//   IT5 Benchmark (§11) tem >=1 linha de sessao
//   IT6 DS-GUARD limpo nos arquivos canonicos do DS (advisory — files de token)
//   IT7 Docs do espinha (§14, coluna "alvo no git") existem (sem link morto)
//
// Veredito: qualquer IT DURO falho => estrutura COMPROMETIDA (exit 1).
// IT6 e' ADVISORY (tokens.css/design-system.css DEFINEM tokens) — reporta, nao falha.
// IT2 e' ADVISORY tambem: 5 charters legados sem .tsx irmao (kb/Node·Paths·Troubleshooter ·
// OficinaAuto/Os/Create · Orcamento/Index) fariam duro=vermelho-por-construcao. Reporta os
// orfaos; quando zerarem, pode subir a duro pela catraca (§12).
//
// Uso: node prototipo-ui/integrity-check.mjs

import { readFile, readdir, stat } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, resolve, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url)); // prototipo-ui/
const ROOT = resolve(HERE, '..');                     // repo root
const PROTO = HERE;
const MEMORY = join(ROOT, 'memory');

const rel = (p) => relative(ROOT, p).replace(/\\/g, '/');
const results = []; // { id, hard, ok, msg }
const add = (id, hard, ok, msg) => results.push({ id, hard, ok, msg });

const read = async (p) => { try { return await readFile(p, 'utf8'); } catch { return null; } };

async function walk(dir, out = []) {
  let entries;
  try { entries = await readdir(dir, { withFileTypes: true }); } catch { return out; }
  const skip = new Set(['_arquivo', '_BACKUP-NAO-USAR', 'node_modules', '.git']);
  for (const e of entries) {
    if (skip.has(e.name)) continue;
    const full = join(dir, e.name);
    if (e.isDirectory()) await walk(full, out);
    else out.push(full);
  }
  return out;
}

// ---- IT1 — espinha existe -------------------------------------------------
const spine = {
  STATUS: join(PROTO, 'STATUS.md'),
  PROCESSO_MEMORIA_CC: join(PROTO, 'PROCESSO_MEMORIA_CC.md'),
  MEMORY_INDEX: join(PROTO, 'MEMORY_INDEX.md'),
  LICOES_CC: join(MEMORY, 'LICOES_CC.md'),
};
{
  const miss = Object.entries(spine).filter(([, p]) => !existsSync(p)).map(([k]) => k);
  add('IT1', true, miss.length === 0, miss.length ? ('faltando: ' + miss.join(', ')) : 'STATUS · PROCESSO · MEMORY_INDEX · LICOES_CC presentes');
}

// ---- IT2 — todo charter tem tela viva (.tsx irmao) · ADVISORY -------------
// Mede a invariante REAL: o contrato da tela e' o trio .tsx + .charter.md + .casos.md,
// e o charter mora ao lado da tela em resources/js/Pages/<Mod>/. Charter sem .tsx irmao =
// lei sem tela viva (candidato a lapide L-22). NAO parear com .decisoes.md: 0 no repo, era
// vacuo (fonte-unica violada — §14 apontava pro lugar errado). Advisory ate os 5 orfaos
// historicos zerarem; ai sobe a duro pela catraca (§12).
{
  const files = await walk(join(ROOT, 'resources', 'js', 'Pages'));
  const charters = files.filter((f) => f.endsWith('.charter.md'));
  const orphans = charters
    .filter((f) => !existsSync(f.slice(0, -'.charter.md'.length) + '.tsx'))
    .map((f) => rel(f));
  const vivos = charters.length - orphans.length;
  add('IT2', false, orphans.length === 0,
    charters.length === 0 ? 'nenhum charter em resources/js/Pages (ok — vacuo)'
      : orphans.length ? (orphans.length + ' charter(s) sem .tsx irmao: ' + orphans.join(' · '))
      : (vivos + ' charters com tela viva (.tsx) ok'));
}

// ---- IT3 — STATUS aponta pra PROCESSO -------------------------------------
{
  const s = await read(spine.STATUS);
  const ok = !!s && /PROCESSO_MEMORIA_CC/.test(s);
  add('IT3', true, ok, ok ? 'STATUS referencia PROCESSO_MEMORIA_CC' : 'STATUS NAO aponta pra PROCESSO (always-read quebrado)');
}

// ---- IT4 — LICOES_CC: L-NN contiguo, sem buraco/duplicata -----------------
{
  const s = await read(spine.LICOES_CC);
  if (!s) add('IT4', true, false, 'LICOES_CC ilegivel');
  else {
    const nums = [...s.matchAll(/^##\s*L-(\d+)\b/gm)].map((m) => parseInt(m[1], 10));
    const dups = nums.filter((n, i) => nums.indexOf(n) !== i);
    const max = nums.length ? Math.max(...nums) : 0;
    const expected = Array.from({ length: max }, (_, i) => i + 1);
    const gaps = expected.filter((n) => !nums.includes(n));
    const ok = nums.length > 0 && gaps.length === 0 && dups.length === 0;
    const msg = ok
      ? ('L-01..L-' + String(max).padStart(2, '0') + ' contiguo (' + nums.length + ' licoes)')
      : ('buracos: ' + (gaps.join(',') || '-') + ' · dups: ' + (dups.join(',') || '-'));
    add('IT4', true, ok, msg);
  }
}

// ---- IT5 — Benchmark (§11) tem linha de sessao E nao esta STALE ------------
{
  const s = await read(spine.PROCESSO_MEMORIA_CC);
  // procura linha de tabela comecando com | YYYY-MM-DD na area do Benchmark
  const rows = s ? [...s.matchAll(/^\|\s*(\d{4}-\d{2}-\d{2})/gm)] : [];
  // Staleness (auditoria dos guias 2026-07-09): a versao anterior aceitava linha de
  // QUALQUER data — benchmark parado 5+ semanas ficava VERDE pra sempre (o proprio
  // "verde no vacuo" que o metodo condena). Agora a linha mais recente precisa ter
  // <= N dias (default 30, tunavel via OIMPRESSO_BENCHMARK_STALE_DIAS).
  const DIAS = Number(process.env.OIMPRESSO_BENCHMARK_STALE_DIAS) || 30;
  const ultima = rows.map((m) => m[1]).sort().at(-1) || null;
  const idade = ultima ? Math.round((Date.now() - Date.parse(ultima + 'T00:00:00Z')) / 86400000) : null;
  const ok = rows.length > 0 && idade !== null && idade <= DIAS;
  add('IT5', true, ok,
    !rows.length ? 'sem medicao no Benchmark (Sobrevivencia #1)'
    : ok ? (rows.length + ' linha(s), ultima ' + ultima + ' (' + idade + 'd)')
    : ('benchmark STALE: ultima medicao ' + ultima + ' (' + idade + 'd > ' + DIAS + 'd) — rode o Benchmark do §11 e logue a linha'));
}

// ---- IT6 — DS-GUARD limpo nos arquivos canonicos do DS (ADVISORY) ---------
{
  const dsFiles = ['tokens.css', 'design-system.css'].map((f) => join(PROTO, f)).filter((p) => existsSync(p));
  if (!dsFiles.length) add('IT6', false, true, 'arquivos canonicos do DS nao encontrados no protótipo (advisory)');
  else {
    let unreadable = 0;
    for (const f of dsFiles) { if ((await read(f)) === null) unreadable++; }
    add('IT6', false, unreadable === 0,
      unreadable ? (unreadable + ' arquivo(s) de DS ilegivel') : ('DS canonico legivel (' + dsFiles.map((f) => basename(f)).join(', ') + ') · paleta = rode ds-guard.mjs --all'));
  }
}

// ---- IT7 — alvos do espinha (§14 coluna git) existem ----------------------
{
  // escopado aos alvos-git do espinha (§14); refs Cowork-only nao contam (evita falso-positivo)
  const targets = [
    join(PROTO, 'PROCESSO_MEMORIA_CC.md'),
    join(MEMORY, 'LICOES_CC.md'),
    join(PROTO, 'PROTOCOL.md'),
    join(PROTO, 'REGISTRY_DS_COMPONENTES.md'),
    join(PROTO, 'ARQUITETURA.md'),
  ];
  const miss = targets.filter((p) => !existsSync(p)).map((p) => rel(p));
  add('IT7', true, miss.length === 0, miss.length ? ('link morto: ' + miss.join(', ')) : 'alvos-git do espinha presentes');
}

// ---- veredito -------------------------------------------------------------
console.log('# Testes de Integridade (PROCESSO_MEMORIA_CC §15) · raiz=' + rel(ROOT) + (rel(ROOT) ? '' : '.'));
let hardFail = 0;
for (const r of results) {
  const tag = r.ok ? 'PASS' : (r.hard ? 'FAIL' : 'WARN');
  if (!r.ok && r.hard) hardFail++;
  console.log(`  [${tag}] ${r.id} — ${r.msg}`);
}
console.log(hardFail
  ? `\nESTRUTURA COMPROMETIDA: ${hardFail} IT duro(s) falho(s) — parar e consertar antes de evoluir (§12).`
  : '\nestrutura sa (todos os IT duros passaram).');
process.exit(hardFail ? 1 : 0);
