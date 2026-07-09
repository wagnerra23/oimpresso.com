#!/usr/bin/env node
// gerar-contrato.mjs — W1 do processo região-a-região: deriva o ESQUELETO do
// <tela>.contract.json a partir do <tela>-gap.md, NÃO "no olho".
//
// Parseia o frontmatter (tela/tela_viva/prototipo) + a tabela "## Comparação por PARTE"
// (Parte | mudou | porquê | Esforço | Risco | Ação) e emite 1 SEÇÃO por PARTE ACIONÁVEL
// (Ação ≠ "Nada/Nenhuma/Não ressuscitar"), com id = slug(parte). O humano depois (a) preenche
// copy[] com a copy literal da região e (b) adiciona a âncora `data-contract="<id>"` no .tsx.
// O gate que verifica é o contrato-de-tela.mjs --contract (catraca existente).
//
// Por que (Wagner 2026-06-30): a decomposição em regiões da Fase 1 (gap.md) era PROSA; W1
// a torna um artefato VERSIONADO e revisável (o contrato), derivado da tabela — não da cabeça.
//
// A VERIFICAÇÃO (seção tem âncora data-contract + copy no alvo?) NÃO é deste script — é do
// `contrato-de-tela.mjs --contract` (ADR 0286, gate canônico), rodado DEPOIS que o humano
// preenche copy[] e ancora. gerar-contrato só DERIVA o esqueleto (auditoria 2026-06-30: não
// duplicar o anchor-check).
//
// Uso:
//   node prototipo-ui/gerar-contrato.mjs <gap.md|Mod/Tela>   # emite o esqueleto JSON (stdout)
//   node prototipo-ui/gerar-contrato.mjs --selftest           # fixture hermético
//
// Exit: 0 = ok | 1 = gap.md não-parseável | 2 = uso

import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join, resolve, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..');

export function slug(s) {
  return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/\([^)]*\)/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
export const fmVal = (fm, key) => { const m = fm.match(new RegExp('^' + key + ':\\s*(.+)$', 'im')); return m ? m[1].trim() : null; };
export function frontmatterBlock(md) { const m = md.match(/^---\r?\n([\s\S]*?)\r?\n---/); return m ? m[1] : ''; }

// 1º path de alvo (resources/js/Pages/... do repo, ou fixtures no selftest) → DIR
function pagesPath(text) {
  const m = String(text || '').match(/((?:resources\/js\/Pages|prototipo-ui\/fixtures)\/[\w./-]+)/);
  return m ? m[1].replace(/\/[\w.-]+\.(tsx|jsx)$/, '') : null;
}

export function ehAcionavel(acao) {
  const a = String(acao || '').replace(/\*\*/g, '').trim();
  if (!a) return false;
  return !/^(Nada|Nenhuma|N[ÃA]O\s*RESSUSCITAR|N[ãa]o\s+ressuscitar|N[ãa]o\b)/i.test(a);
}

// Acha a TABELA-DE-PARTES pelo CABEÇALHO da tabela (col "Parte" + col "Ação"), NÃO pelo título
// da seção — que varia ("Comparação por PARTE" / "Tabela de partes" / "Tabela de gaps por PARTE"
// / nenhum). Mapeia colunas por NOME (ordem/contagem podem variar). Robusto aos 6 gap.md reais.
const norm = (s) => String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
export function parsePartes(md) {
  const lines = md.split(/\r?\n/);
  let hdr = -1, col = null;
  for (let i = 0; i < lines.length; i++) {
    const t = lines[i].trim();
    if (!t.startsWith('|')) continue;
    const cells = t.replace(/^\||\|$/g, '').split('|').map((c) => norm(c.replace(/\*/g, '')));
    const iParte = cells.findIndex((c) => c === 'parte' || c === 'camada');
    const iAcao = cells.findIndex((c) => c === 'acao');
    if (iParte >= 0 && iAcao >= 0) {
      hdr = i; col = { parte: iParte, acao: iAcao, esforco: cells.findIndex((c) => c === 'esforco'), risco: cells.findIndex((c) => c.startsWith('risco')) };
      break;
    }
  }
  if (hdr < 0) return null;
  const rows = [];
  for (let i = hdr + 1; i < lines.length; i++) {
    const t = lines[i].trim();
    if (!t.startsWith('|')) break;                            // fim da tabela
    const cells = t.replace(/^\||\|$/g, '').split('|').map((c) => c.trim());
    if (/^[-: ]+$/.test((cells[col.parte] || '').replace(/\|/g, ''))) continue; // separador
    if (cells.length <= col.acao) continue;
    rows.push({
      parte: (cells[col.parte] || '').replace(/\*\*/g, '').trim(),
      acao: cells[col.acao] || '',
      esforco: col.esforco >= 0 ? (cells[col.esforco] || '') : '',
      risco: col.risco >= 0 ? (cells[col.risco] || '') : '',
    });
  }
  return rows.length ? rows : null;
}

// resolve <gap.md real|Mod/Tela> → path do gap.md (best-effort, fonte única — gerar-map.mjs reusa).
export function resolveGap(arg) {
  if (existsSync(arg)) return arg;
  // Mod/Tela → memory/requisitos/<Mod>/<tela>-gap.md (best-effort)
  const hits = [];
  const reqRoot = join(REPO, 'memory', 'requisitos');
  const walk = (d) => { let es; try { es = readdirSync(d, { withFileTypes: true }); } catch { return; }
    for (const e of es) { const f = join(d, e.name); if (e.isDirectory()) walk(f); else if (e.name.endsWith('-gap.md')) hits.push(f); } };
  walk(reqRoot);
  const want = slug(arg);
  return hits.find((h) => slug(relative(reqRoot, h)).includes(want)) || hits.find((h) => slug(basename(h)).includes(want)) || null;
}

export function gerar(gapPath) {
  const md = readFileSync(gapPath, 'utf8');
  const fm = frontmatterBlock(md);
  const partes = parsePartes(md);
  if (!partes) return { erro: `gap.md sem tabela de partes com colunas "Parte" + "Ação": ${gapPath} — gaps de FUNDAÇÃO/DS-rollout (pageheader/sidebar: componentes cross-tela, "serialização fundação") ficam FORA do escopo região-a-região de tela única, de propósito.` };
  const alvo = pagesPath(fmVal(fm, 'tela_viva')) || pagesPath(md);
  const fonte = (fmVal(fm, 'prototipo') || '').split(/\s|\(/)[0] || null;
  const acionaveis = partes.filter((p) => ehAcionavel(p.acao));
  const secoes = acionaveis.map((p) => ({
    id: slug(p.parte), copy: ['TODO: copy literal da região (preencher do protótipo)'],
    _parte: p.parte, _acao: p.acao.replace(/\*\*/g, '').trim(), _esforco: p.esforco,
  }));
  return {
    contrato: {
      _nota: `ESQUELETO gerado por gerar-contrato.mjs de ${relative(REPO, gapPath).replace(/\\/g, '/')} — PREENCHA copy[] + adicione data-contract="<id>" no .tsx. Region-scoped (só partes acionáveis).`,
      tela: fmVal(fm, 'tela') || basename(gapPath).replace(/-gap\.md$/, ''),
      fonte, alvo: alvo ? [alvo] : [], secoes,
    },
    alvo, totalPartes: partes.length, acionaveis: acionaveis.length,
  };
}

// NOTA (auditoria 2026-06-30): o `--check` (verificar se a seção tem âncora data-contract no
// alvo) foi REMOVIDO — competia com `contrato-de-tela.mjs --contract` (ADR 0286), que JÁ faz
// essa checagem com o regex canônico. gerar-contrato PARA no esqueleto; a verificação de
// presença-de-âncora+copy é do gate canônico, rodado DEPOIS que o humano preenche/ancora.

function selftest() {
  let fails = 0; const t = (l, c) => { if (!c) fails++; console.log(`  [${c ? 'PASS' : 'FAIL'}] ${l}`); };
  t('slug normaliza acento+parênteses', slug('Thread (mensagens)') === 'thread' && slug('Header da página') === 'header-da-pagina');
  t('ehAcionavel: "Nada (vivo à frente)"=false · "Catch-up opcional"=true',
    ehAcionavel('**Nada** (vivo à frente)') === false && ehAcionavel('**Catch-up opcional**') === true && ehAcionavel('**NÃO RESSUSCITAR**') === false);
  const fx = join(HERE, 'fixtures', 'gerar-contrato');
  if (existsSync(join(fx, 'boa-gap.md'))) {
    const g = gerar(join(fx, 'boa-gap.md'));
    t('gera 2 seções das 2 partes acionáveis (3ª é "Nada")', !g.erro && g.contrato.secoes.length === 2);
    t('ids = slug das partes', g.contrato?.secoes?.[0]?.id === 'parte-a' && g.contrato?.secoes?.[1]?.id === 'parte-b');
    const semTab = gerar(join(fx, 'sem-tabela-gap.md'));
    t('gap sem tabela → erro (não crasha)', !!semTab.erro);
  } else { t('fixtures presentes', false); }
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — deriva o esqueleto do contrato do gap.md (verificação = contrato-de-tela --contract).');
  process.exit(fails ? 1 : 0);
}

const argv = process.argv.slice(2);
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  if (argv.includes('--selftest')) selftest();
  else {
    const gapArg = argv.find((a) => !a.startsWith('--'));
    if (!gapArg) { console.error('uso: node prototipo-ui/gerar-contrato.mjs <gap.md|Mod/Tela> | --selftest'); process.exit(2); }
    const gapPath = resolveGap(gapArg);
    if (!gapPath) { console.error(`gap.md não encontrado pra: ${gapArg}`); process.exit(1); }
    const g = gerar(gapPath);
    if (g.erro) { console.error(`✗ ${g.erro}`); process.exit(1); }
    console.error(`# ${g.acionaveis}/${g.totalPartes} partes acionáveis → ${g.contrato.secoes.length} seções (preencha copy[] + ancore)`);
    console.log(JSON.stringify(g.contrato, null, 2));
    process.exit(0);
  }
}
