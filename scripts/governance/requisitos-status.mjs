#!/usr/bin/env node
/**
 * requisitos-status.mjs — a CADEIA DE RASTREABILIDADE de um módulo, derivada e com STATUS.
 *
 * O QUE RESPONDE (nenhuma porta atual responde isto):
 *
 *     US (SPEC)  →  CU (SDD §6)  →  UC (casos.md)  →  teste  →  veredito
 *
 * O `casos-coverage-guard` (required) vê **UC↔teste**. O `anchor-lint` (required) vê
 * **US↔código**. Ninguém percorre a CADEIA INTEIRA nem diz, por requisito, ONDE ela quebra.
 * Régua da indústria (Jama Live Traceability · PTC · trace.space, 2026): *"coverage é a % de
 * requisitos com cadeia completa da ORIGEM à EVIDÊNCIA — é isso que separa rastreabilidade
 * auditável de incompleta"*. Este script é essa medida, para dentro do vocabulário do projeto.
 *
 * FRONTEIRA (não duplica régua — proibicoes §5 2026-07-09):
 *   · casos-coverage-guard = o UC tem teste que o cita? (elo UC→teste, e é REQUIRED)
 *   · anchor-lint          = a US aponta código vivo?   (elo US→código, e é REQUIRED)
 *   · ESTE                 = a cadeia US→CU→UC→teste FECHA? onde quebra? o que falta escrever?
 *   Nenhum status aqui re-julga o que aqueles dois já julgam — este COMPÕE e aponta o BURACO.
 *
 * O SISTEMA CRESCE POR AQUI ([W] 2026-07-26: "mantenha o sistema crescente indicando mais
 * requerimentos e proibições e status dos requisitos"). Cada elo que falta É o próximo
 * requisito a escrever — a lista de LACUNAS é a fila de trabalho, derivada, não inventada.
 *
 * STATUS É DERIVADO, NUNCA ESCRITO À MÃO (ADR 0256 · proibicoes §5 "campo auto-declarado"):
 *   ⬜ orfao      — US sem nenhum CU/UC que a cite  → escrever o caso
 *   📝 sem_teste  — UC existe, nenhum teste o cita  → escrever o teste (ou virar [BACKLOG])
 *   🧪 sem_prova  — teste existe mas não executa (test.fixme) ou nunca rodou
 *   ✅ provado    — teste real cita o UC e a lane publicou verde
 *   ❌ refutado   — teste cita o UC e FALHOU: é ACHADO com recibo, não pendência
 *
 * Uso:
 *   node scripts/governance/requisitos-status.mjs <Modulo>            # relatório
 *   node scripts/governance/requisitos-status.mjs <Modulo> --write    # grava _STATUS-GENERATED.md
 *   node scripts/governance/requisitos-status.mjs <Modulo> --check    # gerado × commitado
 *   node scripts/governance/requisitos-status.mjs --selftest          # bite-test
 */

import { readFileSync, existsSync, readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const args = process.argv.slice(2);
const ler = (p) => { try { return readFileSync(join(ROOT, p), 'utf8'); } catch { return ''; } };

// ── extratores (puros — o selftest exercita cada um) ──────────────────────────
export function extrairUS(specSrc) {
  return [...specSrc.matchAll(/^###\s+(US-[A-Z]{2,8}-\d{3,4})\s*·?\s*(.*)$/gm)]
    .map((m) => ({ id: m[1], titulo: m[2].trim() }));
}
export function extrairCU(sddSrc) {
  return [...sddSrc.matchAll(/^####\s+(CU-[A-Z]{2,8}-\d{2,4})\s*—?\s*([^`\n]*)/gm)]
    .map((m) => ({ id: m[1], titulo: m[2].trim() }));
}
export function extrairUC(casosSrc) {
  const ids = new Set();
  for (const m of casosSrc.matchAll(/\b(UC-[A-Z0-9]{2,10}-\d{2,3})\b/g)) ids.add(m[1]);
  return [...ids];
}
/** Um UC é "citado por teste" se aparece em qualquer arquivo de teste do repo. */
export function ucCitadoPorTeste(uc, corpusTestes) {
  return corpusTestes.some((t) => t.src.includes(uc));
}
/** `test.fixme`/`it.skip` na mesma linha do UC = existe mas não executa. */
export function ucSoStub(uc, corpusTestes) {
  const linhas = corpusTestes.flatMap((t) => t.src.split(/\r?\n/).filter((l) => l.includes(uc)));
  if (!linhas.length) return false;
  return linhas.every((l) => /\b(test\.fixme|it\.skip|xdescribe|markTestSkipped)\b/.test(l));
}

function listarTestes() {
  const out = [];
  const walk = (rel) => {
    let ents; try { ents = readdirSync(join(ROOT, rel), { withFileTypes: true }); } catch { return; }
    for (const e of ents) {
      const p = `${rel}/${e.name}`;
      if (e.isDirectory()) { if (!['node_modules', 'vendor', '.git'].includes(e.name)) walk(p); }
      else if (/\.(php|ts|tsx|js|mjs)$/.test(e.name)) out.push({ path: p, src: ler(p) });
    }
  };
  walk('tests'); walk('e2e');
  return out;
}

function casosDoModulo(mod) {
  const dir = `resources/js/Pages/${mod}`;
  let ents; try { ents = readdirSync(join(ROOT, dir), { withFileTypes: true }); } catch { return []; }
  return ents.filter((e) => e.isFile() && e.name.endsWith('.casos.md'))
    .map((e) => ({ tela: e.name.replace('.casos.md', ''), path: `${dir}/${e.name}`, src: ler(`${dir}/${e.name}`) }));
}
function telasDoModulo(mod) {
  const dir = `resources/js/Pages/${mod}`;
  let ents; try { ents = readdirSync(join(ROOT, dir), { withFileTypes: true }); } catch { return []; }
  return ents.filter((e) => e.isFile() && e.name.endsWith('.tsx')).map((e) => e.name.replace('.tsx', ''));
}
function sddDoModulo(mod) {
  const dir = `memory/requisitos/${mod}`;
  let ents; try { ents = readdirSync(join(ROOT, dir), { withFileTypes: true }); } catch { return null; }
  const f = ents.find((e) => e.isFile() && /^SDD.*\.md$/.test(e.name));
  return f ? `${dir}/${f.name}` : null;
}

// ── SELFTEST ──────────────────────────────────────────────────────────────────
if (args.includes('--selftest')) {
  let f = 0;
  const ok = (nome, cond) => { console.log(`${cond ? '  ok  ' : ' FALHA'} ${nome}`); if (!cond) f++; };

  ok('extrai US do SPEC', extrairUS('### US-PROD-020 · Governança\ntexto\n### US-PROD-021 · Outra').length === 2);
  ok('não confunde US com prosa', extrairUS('falamos de US-PROD-020 no meio do texto').length === 0);
  ok('extrai CU do SDD', extrairCU('#### CU-PROD-01 — Cadastrar `[must]`\n#### CU-PROD-02 — Variável').length === 2);
  ok('extrai UC do casos.md (dedup)', extrairUC('| UC-PSHOW-01 | x |\n UC-PSHOW-01 de novo\n UC-PSHOW-02').length === 2);

  const corpus = [{ path: 't.php', src: "it('UC-PSHOW-01 · faz algo', function () {});\ntest.fixme('UC-PSHOW-04 · stub');" }];
  ok('UC citado por teste → true', ucCitadoPorTeste('UC-PSHOW-01', corpus) === true);
  ok('UC não citado → false', ucCitadoPorTeste('UC-PSHOW-99', corpus) === false);
  ok('UC só em test.fixme → stub', ucSoStub('UC-PSHOW-04', corpus) === true);
  ok('UC em teste real → NÃO stub', ucSoStub('UC-PSHOW-01', corpus) === false);

  console.log(f === 0 ? '\n✅ selftest 8/8 — extratores e classificador provados' : `\n❌ ${f} falha(s)`);
  process.exit(f === 0 ? 0 : 1);
}

// ── relatório ─────────────────────────────────────────────────────────────────
const mod = args.find((a) => !a.startsWith('--'));
if (!mod) { console.log('uso: requisitos-status.mjs <Modulo> [--write|--check]'); process.exit(0); }

const specPath = `memory/requisitos/${mod}/SPEC.md`;
const sddPath = sddDoModulo(mod);
const us = extrairUS(ler(specPath));
const cu = sddPath ? extrairCU(ler(sddPath)) : [];
const casos = casosDoModulo(mod);
const telas = telasDoModulo(mod);
const testes = listarTestes();

// DEDUPE por id: um UC é citado em mais de um casos.md (referência cruzada entre telas
// irmãs). A tela DONA é a que o declara na tabela de rastreabilidade (linha `| UC-… |`);
// citação em prosa de outra tela não cria um segundo requisito.
const donoDe = new Map();
for (const c of casos) {
  for (const m of c.src.matchAll(/^\|\s*(UC-[A-Z0-9]{2,10}-\d{2,3})\s*\|/gm)) {
    if (!donoDe.has(m[1])) donoDe.set(m[1], c.tela);
  }
}
for (const c of casos) for (const id of extrairUC(c.src)) if (!donoDe.has(id)) donoDe.set(id, c.tela);

const ucs = [...donoDe.entries()].map(([id, tela]) => ({ id, tela }));
const statusUC = ucs.map((u) => {
  if (!ucCitadoPorTeste(u.id, testes)) return { ...u, status: '📝 sem_teste' };
  if (ucSoStub(u.id, testes)) return { ...u, status: '🧪 stub (não executa)' };
  // Tem teste REAL que o cita. O veredito (✅/❌) é da lane — este gerador nunca o afirma.
  return { ...u, status: '🧪 aguarda veredito da lane' };
});

// LACUNAS = a fila de crescimento (derivada, não inventada)
const telasSemCasos = telas.filter((t) => !casos.some((c) => c.tela === t));
const cuSemUC = cu.filter((c) => !casos.some((k) => k.src.includes(c.id)));
const usSemCaso = us.filter((u) => !casos.some((k) => k.src.includes(u.id)));

const linhas = [];
const P = (s = '') => linhas.push(s);
P(`<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.`);
P(`     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:`);
P(`     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->`);
P('');
P(`# Requisitos — ${mod} · status derivado`);
P('');
P(`> **Cadeia medida:** \`US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito\`.`);
P(`> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui`);
P(`> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).`);
P('');
P('## Placar da cadeia');
P('');
P('| Elo | Quantidade |');
P('|---|---:|');
P(`| US no SPEC | ${us.length} |`);
P(`| CU no SDD | ${cu.length} |`);
P(`| Telas (.tsx) | ${telas.length} |`);
P(`| Telas com \`casos.md\` | ${casos.length} |`);
P(`| UC declarados | ${ucs.length} |`);
P(`| UC com teste que os cita | ${statusUC.filter((u) => u.status !== '📝 sem_teste').length} |`);
P('');
P('## Onde a cadeia QUEBRA — esta é a fila de crescimento');
P('');
if (!telasSemCasos.length && !cuSemUC.length && !usSemCaso.length) {
  P('_Nenhuma lacuna estrutural: toda tela tem caso, todo CU é citado, toda US tem caso._');
} else {
  P('| Lacuna | O que falta escrever |');
  P('|---|---|');
  for (const t of telasSemCasos) P(`| Tela \`${t}\` sem \`casos.md\` | o contrato da tela (trio incompleto) |`);
  for (const c of cuSemUC) P(`| \`${c.id}\` sem UC | caso de uso que o exercite — ${c.titulo.slice(0, 70)} |`);
  for (const u of usSemCaso) P(`| \`${u.id}\` sem caso | UC que a atenda — ${u.titulo.slice(0, 70)} |`);
}
P('');
P('## UC por status');
P('');
P('| UC | Tela | Status |');
P('|---|---|---|');
for (const u of statusUC.sort((a, b) => a.id.localeCompare(b.id))) P(`| ${u.id} | ${u.tela} | ${u.status} |`);
P('');
P('---');
P('');
P('**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo');
P('requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?');
P('Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de');
P('`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar');
P('a lacuna aberta sem decisão é a única que não é.');

const saida = linhas.join('\n') + '\n';
const destino = `memory/requisitos/${mod}/_STATUS-GENERATED.md`;

if (args.includes('--write')) {
  writeFileSync(join(ROOT, destino), saida, 'utf8');
  console.log(`  ✓ ${destino} gravado (${us.length} US · ${cu.length} CU · ${ucs.length} UC)`);
  process.exit(0);
}
if (args.includes('--check')) {
  const atual = existsSync(join(ROOT, destino)) ? ler(destino) : null;
  if (atual === null) { console.log(`  ✗ ${destino} não existe — rode com --write`); process.exit(1); }
  if (atual.replace(/\r\n/g, '\n') !== saida) {
    console.log(`  ✗ ${destino} está DRIFADO vs a árvore. Rode: node scripts/governance/requisitos-status.mjs ${mod} --write`);
    process.exit(1);
  }
  console.log(`  ✓ ${destino} em dia.`);
  process.exit(0);
}
console.log(saida);
