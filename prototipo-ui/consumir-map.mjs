#!/usr/bin/env node
// consumir-map.mjs — o CONSUMO do <tela>.map.json na FASE 4 (sessão limpa de aplicação).
//
// Fecha o furo do RUNBOOK-aplicar-prototipo-orquestracao.md que estava só em PROSA desde
// 2026-07-01: "o map.json faz a sessão de aplicação ler só os trechos (economia real) e
// permite invalidar o gap quando o protótipo re-exporta (Fase 4 aborta se o sha mudou →
// regenera)". gerar-map.mjs GERA e design-code-map-check.mjs VERIFICA (sentinela CI global);
// faltava quem USASSE o map dentro da Fase 4 — este script:
//
//   1. PORTÃO DE FRESCOR (bite): recomputa o sha do(s) arquivo(s)-fonte do protótipo no MESMO
//      formato do salvo (sha256: = contentHash normalizado ADR 0324 · legado = git-sha) via
//      shaAtualPara de gerar-map.mjs (fonte única). Divergiu → exit 3 com a ordem de ABORTAR
//      e regenerar (`gerar-map.mjs --atualizar` preserva o preenchido). A sessão de aplicação
//      NUNCA trabalha sobre gap/map de um protótipo que re-exportou.
//   2. PLANO DE LEITURA (release): fresco → emite, por parte, os DOIS ranges (protótipo e
//      vivo) + status + ação, marcando o que ABRIR (ação ≠ no-op/rejeitar). A sessão abre SÓ
//      esses ranges (Read offset/limit) — economia de token real; a tela inteira, NUNCA.
//
// NÃO re-verifica âncora estável/schema (isso é do design-code-map-check.mjs — 1 papel por
// script, mesma separação gerar-contrato × contrato-de-tela) e NÃO recria copy-check (o
// contrato-de-tela é o gate da região; o map só REFERENCIA as regiões pelo mesmo id).
//
// Uso:
//   node prototipo-ui/consumir-map.mjs <Mod/Tela|caminho.map.json> [--todas] [--json] [--root <path>]
//   node prototipo-ui/consumir-map.mjs --selftest        # hermético (bite/release, sem git)
//
// Exit: 0 = fresco (plano emitido) | 1 = map não encontrado/ilegível | 2 = uso | 3 = STALE (ABORTAR)

import { readFileSync, existsSync, mkdtempSync, writeFileSync, rmSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { resolveGap } from './gerar-contrato.mjs';
import { shaAtualPara, shaIndeterminado } from './gerar-map.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..');

// <Mod/Tela> → memory/requisitos/<Mod>/<tela>.map.json (irmão do -gap.md, mesma resolução
// do gerar-map — 1 convenção de path, não 2); caminho direto pra .map.json também vale.
export function resolveMap(arg, { root = REPO } = {}) {
  if (String(arg).endsWith('.map.json')) return existsSync(arg) ? arg : (existsSync(join(root, arg)) ? join(root, arg) : null);
  const gap = resolveGap(arg);
  if (!gap) return null;
  const map = gap.replace(/-gap\.md$/, '.map.json');
  return existsSync(map) ? map : null;
}

/** Portão de frescor: {fresco, indeterminado, salvo, atual}. Indeterminado NUNCA bloqueia
 *  (sha sentinela/arquivos TODO) — mesma filosofia do checker: ausência declarada ≠ punição. */
export function verificarFrescor(mapa, { root = REPO } = {}) {
  const salvo = mapa?.prototipo_sha;
  const arquivos = [...new Set((mapa?.partes || []).map((p) => p?.prototipo?.arquivo).filter((a) => a && a !== 'TODO' && a !== 'n/a'))];
  if (shaIndeterminado(salvo) || !arquivos.length) return { fresco: true, indeterminado: true, salvo, atual: null };
  const atual = shaAtualPara(salvo, arquivos, root);
  if (shaIndeterminado(atual)) return { fresco: true, indeterminado: true, salvo, atual };
  return { fresco: atual === salvo, indeterminado: false, salvo, atual };
}

const NAO_ABRIR = /^(no-op|rejeitar)/i;
/** Plano de leitura: por parte, os 2 ranges + o veredito abrir/pular. */
export function plano(mapa) {
  return (mapa?.partes || []).map((p) => ({
    id: p.id,
    status: p.status,
    acao: p.acao || '',
    abrir: !NAO_ABRIR.test((p.acao || '').trim()) && p.vivo?.arquivo !== 'n/a',
    prototipo: p.prototipo,
    vivo: { arquivo: p.vivo?.arquivo, linhas: p.vivo?.linhas, ancora: p.vivo?.ancora ?? false },
  }));
}

function imprimir(mapa, itens, { todas = false } = {}) {
  const abrir = itens.filter((i) => i.abrir);
  console.log(`# PLANO DE LEITURA — ${mapa.tela} · sha ok (${mapa.prototipo_sha}) · ${itens.length} parte(s), ${abrir.length} pra ABRIR`);
  for (const i of (todas ? itens : abrir)) {
    const tag = i.abrir ? 'ABRIR ' : '(pular)';
    console.log(`${tag} ${i.id} [${i.status}]`);
    console.log(`        proto ${i.prototipo?.arquivo}:${i.prototipo?.linhas || '?'} · vivo ${i.vivo.arquivo}:${i.vivo.linhas || '?'}${i.vivo.ancora ? ' · ancora data-contract ✓' : ''}`);
    if (i.acao) console.log(`        ação: ${i.acao}`);
  }
  if (!todas && itens.length > abrir.length) console.log(`(+${itens.length - abrir.length} parte(s) no-op/rejeitar ocultas — use --todas)`);
  console.log(`\n→ Abra SÓ os ranges acima (Read offset/limit). A tela inteira, NUNCA — a economia da Fase 4 depende disso.`);
}

// ── selftest hermético: bite (stale → 3) / release (fresco → plano), SEM git ────
async function selftest() {
  let fails = 0; const t = (l, c) => { if (!c) fails++; console.log(`  [${c ? 'PASS' : 'FAIL'}] ${l}`); };
  const { computeProtoHash } = await import('./gerar-map.mjs');
  const root = mkdtempSync(join(tmpdir(), 'consumir-map-'));
  try {
    mkdirSync(join(root, 'proto'), { recursive: true });
    writeFileSync(join(root, 'proto', 'x-page.jsx'), 'export default () => <div>v1</div>\n');
    const mapa = (sha) => ({
      version: '1', tela: 'X/Tela', prototipo_sha: sha, partes: [
        { id: 'a', prototipo: { arquivo: 'proto/x-page.jsx', linhas: '1-3' }, vivo: { arquivo: 'resources/X.tsx', linhas: '5-9' }, status: 'gap', acao: 'aplicar-delta: foo' },
        { id: 'b', prototipo: { arquivo: 'proto/x-page.jsx', linhas: '4-6' }, vivo: { arquivo: 'resources/X.tsx', linhas: '10-20' }, status: 'paridade', acao: 'no-op' },
        { id: 'c', prototipo: { arquivo: 'proto/x-page.jsx', linhas: '7' }, vivo: { arquivo: 'n/a', linhas: '' }, status: 'artefato', acao: 'rejeitar (mock)' },
      ],
    });
    const shaOk = computeProtoHash(['proto/x-page.jsx'], root);

    // release: sha bate → fresco
    const fresco = verificarFrescor(mapa(shaOk), { root });
    t('release: sha por conteúdo bate → fresco (não indeterminado)', fresco.fresco === true && fresco.indeterminado === false);

    // bite: protótipo re-exportou (conteúdo mudou, SEM commit — git-sha seria cego) → stale
    writeFileSync(join(root, 'proto', 'x-page.jsx'), 'export default () => <div>v2 re-export</div>\n');
    const stale = verificarFrescor(mapa(shaOk), { root });
    t('bite: conteúdo mudou sem commit → STALE detectado (contentHash, não git)', stale.fresco === false && stale.atual !== shaOk);

    // release de novo: regenerar o sha solta o portão
    t('release pós-regenerar: sha novo bate de novo', verificarFrescor(mapa(computeProtoHash(['proto/x-page.jsx'], root)), { root }).fresco === true);

    // indeterminado nunca bloqueia
    t('sha sentinela (sem-arquivo) → indeterminado, não bloqueia', verificarFrescor(mapa('sem-arquivo'), { root }).fresco === true);
    t('legado git-sha em dir sem git → indeterminado, não bloqueia', verificarFrescor(mapa('4e3aacfc0f'), { root }).indeterminado === true);

    // plano: abrir só o que tem delta
    const p = plano(mapa(shaOk));
    t('plano: aplicar-delta ABRIR · no-op e rejeitar pulam · vivo n/a pula', p.find((i) => i.id === 'a').abrir === true && p.find((i) => i.id === 'b').abrir === false && p.find((i) => i.id === 'c').abrir === false);
    t('plano: carrega os DOIS ranges + ancora default false', p[0].prototipo.linhas === '1-3' && p[0].vivo.linhas === '5-9' && p[0].vivo.ancora === false);

    // CLI ponta-a-ponta: exit 3 no stale, 0 no fresco (o contrato que a Fase 4 scripta)
    const { spawnSync } = await import('node:child_process');
    const mapPath = join(root, 'tela.map.json');
    writeFileSync(mapPath, JSON.stringify(mapa(shaOk), null, 2)); // shaOk é do v1; disco está v2 → stale
    const rStale = spawnSync('node', [fileURLToPath(import.meta.url), mapPath, '--root', root], { encoding: 'utf8' });
    t('CLI: stale → exit 3 + manda regenerar com --atualizar', rStale.status === 3 && /--atualizar/.test(rStale.stderr));
    writeFileSync(mapPath, JSON.stringify(mapa(computeProtoHash(['proto/x-page.jsx'], root)), null, 2));
    const rOk = spawnSync('node', [fileURLToPath(import.meta.url), mapPath, '--root', root], { encoding: 'utf8' });
    t('CLI: fresco → exit 0 + plano com ABRIR e ranges', rOk.status === 0 && /ABRIR\s+a/.test(rOk.stdout) && /proto\/x-page\.jsx:1-3/.test(rOk.stdout));
  } finally { rmSync(root, { recursive: true, force: true }); }

  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — portão de frescor morde (exit 3) e solta (plano); Fase 4 nunca aplica sobre protótipo re-exportado.');
  process.exit(fails ? 1 : 0);
}

const argv = process.argv.slice(2);
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  if (argv.includes('--selftest')) await selftest();
  else {
    const iRoot = argv.indexOf('--root');
    const root = iRoot >= 0 && argv[iRoot + 1] ? resolve(argv[iRoot + 1]) : REPO;
    const alvo = argv.find((a, i) => !a.startsWith('--') && argv[i - 1] !== '--root');
    if (!alvo) { console.error('uso: node prototipo-ui/consumir-map.mjs <Mod/Tela|caminho.map.json> [--todas] [--json] [--root <path>] | --selftest'); process.exit(2); }
    const mapPath = resolveMap(alvo, { root });
    if (!mapPath) { console.error(`✗ .map.json não encontrado pra: ${alvo} — a Fase 1 gera com: node prototipo-ui/gerar-map.mjs <gap.md>`); process.exit(1); }
    let mapa;
    try { mapa = JSON.parse(readFileSync(mapPath, 'utf8')); }
    catch (e) { console.error(`✗ ${mapPath}: JSON inválido (${e.message})`); process.exit(1); }

    const f = verificarFrescor(mapa, { root });
    if (!f.fresco) {
      console.error(`⛔ ABORTAR Fase 4 — ${mapa.tela}: prototipo_sha salvo='${f.salvo}' · atual='${f.atual}'.`);
      console.error(`   O protótipo re-exportou depois deste map. NÃO aplique sobre gap/ranges velhos.`);
      console.error(`   Regenere preservando o preenchido: node prototipo-ui/gerar-map.mjs ${mapa.gap_fonte || '<gap.md>'} --atualizar`);
      process.exit(3);
    }
    if (f.indeterminado) console.error(`⚠️ frescor indeterminado (sha='${f.salvo}') — seguindo; ancore o map com prototipo_sha real via gerar-map.mjs.`);

    const itens = plano(mapa);
    if (argv.includes('--json')) console.log(JSON.stringify({ tela: mapa.tela, prototipo_sha: mapa.prototipo_sha, frescor: f, plano: itens }, null, 2));
    else imprimir(mapa, itens, { todas: argv.includes('--todas') });
    process.exit(0);
  }
}
