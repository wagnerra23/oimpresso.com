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
//   node scripts/design/consumir-map.mjs <Mod/Tela|caminho.map.json> [--todas] [--json] [--root <path>]
//   node scripts/design/consumir-map.mjs --selftest        # hermético (bite/release, sem git)
//
// Exit: 0 = fresco (plano emitido) | 1 = map não encontrado/ilegível | 2 = uso | 3 = STALE (ABORTAR)

import { readFileSync, existsSync, mkdtempSync, writeFileSync, rmSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { resolveGap } from './gerar-contrato.mjs';
import { pageNamespacePath } from '../qa/page-path.mjs';
import { shaAtualPara, shaIndeterminado, shaBate } from './gerar-map.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '../..');

/** Fallback de resolução: o REGISTRO que já casa target↔map (`applications.json`).
 *
 *  POR QUE EXISTE (medido 2026-09-21): a convenção de path abaixo nomeia gap/map pela FONTE
 *  do protótipo (`vendas.map.json`, `compras.map.json`), enquanto quem digita o atalho usa a
 *  TELA (`Sells/Index`). São dois vocabulários e a derivação por nome não faz a ponte, então
 *  `consumir-map.mjs Sells/Index` saía exit 1 ("map não encontrado") com o map ÍNTegro e
 *  fresco no disco — o path direto do mesmo arquivo saía exit 0 com o plano completo.
 *  A ponte fonte↔tela↔mapa já existia e tem dono: é o `applications.json`, o mesmo registro
 *  que o `status.mjs --check-mapping` lê pra imprimir `mapa: <path>` em cada linha. Aqui ele
 *  é CONSULTADO em vez de a convenção ser adivinhada. Quanto isso valia, medido em 2026-09-21:
 *  **29 de 64** telas com map registrado eram inalcançáveis pelo atalho (35 resolviam pela
 *  convenção); com a 3ª perna, 64/64. O 64 é o nº de namespaces distintos — há 69 registros,
 *  5 deles duplicando alvo. Reproduzir:
 *    node --input-type=module -e "import{resolveMap}from'./scripts/design/consumir-map.mjs';
 *      import{pageNamespacePath}from'./scripts/qa/page-path.mjs';import{readFileSync}from'node:fs';
 *      const a=JSON.parse(readFileSync('scripts/design-sync/state/applications.json','utf8')).applications;
 *      const n=[...new Set(a.filter(x=>x?.comparison?.map&&x?.target).map(x=>pageNamespacePath(x.target).replace(/\.tsx$/,'')))];
 *      console.log(n.length, n.filter(t=>resolveMap(t)).length)"
 *  (⚠️ o docblock não repete o número por conta própria — §5 2026-07-17; ele vem com o comando
 *  que o recalcula, porque o registro cresce e um número escrito à mão apodrece.)
 *
 *  Não substitui a convenção — é a 3ª perna, só corre quando ela falha.
 */
export function mapDoRegistro(arg, { root = REPO } = {}) {
  const registro = join(root, 'scripts', 'design-sync', 'state', 'applications.json');
  if (!existsSync(registro)) return null;
  let apps;
  try { apps = JSON.parse(readFileSync(registro, 'utf8'))?.applications; } catch { return null; }
  if (!Array.isArray(apps)) return null;
  const alvo = String(arg).replace(/\.tsx$/, '').toLowerCase();
  for (const a of apps) {
    const map = a?.comparison?.map;
    if (!map || !a?.target) continue;
    const ns = pageNamespacePath(a.target).replace(/\.tsx$/, '').toLowerCase();
    if (ns !== alvo && !ns.endsWith('/' + alvo)) continue;
    const abs = join(root, map);
    if (existsSync(abs)) return abs;
  }
  return null;
}

// <Mod/Tela> → memory/requisitos/<Mod>/<tela>.map.json (irmão do -gap.md, mesma resolução
// do gerar-map — 1 convenção de path, não 2); caminho direto pra .map.json também vale.
// Falhando a convenção, cai no registro (mapDoRegistro) — ver o porquê no docblock dele.
export function resolveMap(arg, { root = REPO } = {}) {
  if (String(arg).endsWith('.map.json')) return existsSync(arg) ? arg : (existsSync(join(root, arg)) ? join(root, arg) : null);
  const gap = resolveGap(arg);
  if (gap) {
    const map = gap.replace(/-gap\.md$/, '.map.json');
    if (existsSync(map)) return map;
  }
  return mapDoRegistro(arg, { root });
}

/** Portão de frescor: {fresco, indeterminado, salvo, atual}. Indeterminado NUNCA bloqueia
 *  (sha sentinela/arquivos TODO) — mesma filosofia do checker: ausência declarada ≠ punição. */
export function verificarFrescor(mapa, { root = REPO } = {}) {
  const salvo = mapa?.prototipo_sha;
  const arquivos = [...new Set((mapa?.partes || []).map((p) => p?.prototipo?.arquivo).filter((a) => a && a !== 'TODO' && a !== 'n/a'))];
  if (shaIndeterminado(salvo) || !arquivos.length) return { fresco: true, indeterminado: true, salvo, atual: null };
  const atual = shaAtualPara(salvo, arquivos, root);
  if (shaIndeterminado(atual)) return { fresco: true, indeterminado: true, salvo, atual };
  // shaBate, não `===`: git-sha legado pode estar abreviado com outra largura (ver gerar-map).
  return { fresco: shaBate(salvo, atual), indeterminado: false, salvo, atual };
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

    // resolveMap pelo ATALHO <Mod/Tela> — o caminho que o humano digita, e o que estava
    // quebrado: até 2026-09-21 os 9 asserts daqui só exercitavam path DIRETO, então o
    // selftest saía OK enquanto `consumir-map.mjs Sells/Index` saía exit 1 com o map no
    // disco. Controle negativo primeiro: sem registro, o atalho NÃO resolve.
    const mapRegistrado = join(root, 'memory', 'requisitos', 'ZZFake', 'fonte-com-outro-nome.map.json');
    mkdirSync(dirname(mapRegistrado), { recursive: true });
    writeFileSync(mapRegistrado, JSON.stringify(mapa(shaOk), null, 2));
    t('atalho <Mod/Tela> SEM registro → não resolve (controle negativo)', resolveMap('ZZFake/Tela', { root }) === null);

    const stateDir = join(root, 'scripts', 'design-sync', 'state');
    mkdirSync(stateDir, { recursive: true });
    writeFileSync(join(stateDir, 'applications.json'), JSON.stringify({
      schema: 'oimpresso-design-applications/2',
      applications: [
        {
          source: 'fonte-com-outro-nome.jsx',
          target: 'resources/js/Pages/ZZFake/Tela.tsx',
          comparison: { map: 'memory/requisitos/ZZFake/fonte-com-outro-nome.map.json' },
        },
        // Registro que CASA o namespace mas cujo map NÃO existe no disco. Sem esta entrada o
        // assert de baixo era decorativo: `ZZFake/Sumiu` não casava registro nenhum, então o
        // `null` vinha do laço esgotando e NÃO do `existsSync` — mutação que removia o
        // `existsSync` sobrevivia ao selftest (achado do adversário, 2026-09-21; é a §5
        // 2026-09-05: valor esperado coincidindo com o que a mutação produz).
        {
          source: 'map-que-sumiu-do-disco.jsx',
          target: 'resources/js/Pages/ZZFake/Sumiu.tsx',
          comparison: { map: 'memory/requisitos/ZZFake/ESTE-MAP-NAO-EXISTE.map.json' },
        },
      ],
    }, null, 2));
    // O map é nomeado pela FONTE e o atalho usa a TELA: só resolve consultando o registro.
    t('atalho <Mod/Tela> COM registro → resolve pelo applications.json (o bug de 2026-09-21)',
      resolveMap('ZZFake/Tela', { root }) === mapRegistrado);
    // Este é o assert que o `existsSync` final sustenta — o registro CASA e o map não está lá.
    t('registro CASA mas map ausente do disco → não inventa path', mapDoRegistro('ZZFake/Sumiu', { root }) === null);
    t('namespace sem registro nenhum → null (laço esgota)', mapDoRegistro('ZZFake/NemRegistrado', { root }) === null);

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
    if (!alvo) { console.error('uso: node scripts/design/consumir-map.mjs <Mod/Tela|caminho.map.json> [--todas] [--json] [--root <path>] | --selftest'); process.exit(2); }
    const mapPath = resolveMap(alvo, { root });
    if (!mapPath) { console.error(`✗ .map.json não encontrado pra: ${alvo} — a Fase 1 gera com: node scripts/design/gerar-map.mjs <gap.md>`); process.exit(1); }
    let mapa;
    try { mapa = JSON.parse(readFileSync(mapPath, 'utf8')); }
    catch (e) { console.error(`✗ ${mapPath}: JSON inválido (${e.message})`); process.exit(1); }

    const f = verificarFrescor(mapa, { root });
    if (!f.fresco) {
      console.error(`⛔ ABORTAR Fase 4 — ${mapa.tela}: prototipo_sha salvo='${f.salvo}' · atual='${f.atual}'.`);
      console.error(`   O protótipo re-exportou depois deste map. NÃO aplique sobre gap/ranges velhos.`);
      console.error(`   Regenere preservando o preenchido: node scripts/design/gerar-map.mjs ${mapa.gap_fonte || '<gap.md>'} --atualizar`);
      process.exit(3);
    }
    if (f.indeterminado) console.error(`⚠️ frescor indeterminado (sha='${f.salvo}') — seguindo; ancore o map com prototipo_sha real via gerar-map.mjs.`);

    const itens = plano(mapa);
    if (argv.includes('--json')) console.log(JSON.stringify({ tela: mapa.tela, prototipo_sha: mapa.prototipo_sha, frescor: f, plano: itens }, null, 2));
    else imprimir(mapa, itens, { todas: argv.includes('--todas') });
    process.exit(0);
  }
}
