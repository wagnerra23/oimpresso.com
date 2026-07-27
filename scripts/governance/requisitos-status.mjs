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
import { fileURLToPath } from 'node:url';

// Guard IS_MAIN (padrao do doc-freshness-score): os extratores sao EXPORTADOS pra teste;
// sem isto, um `import` do modulo dispara o CLI e polui a saida de quem so queria a funcao.
const IS_MAIN = process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1];

const ROOT = process.cwd();
const args = process.argv.slice(2);
const ler = (p) => { try { return readFileSync(join(ROOT, p), 'utf8'); } catch { return ''; } };

// ── extratores (puros — o selftest exercita cada um) ──────────────────────────
/**
 * US com o `status:` da linha de metadata logo abaixo do heading.
 *
 * POR QUE O STATUS IMPORTA AQUI (falso-positivo corrigido 2026-07-26): a 1ª versão listava
 * TODA US sem caso como lacuna. Rodado no Produto, 6 das 10 "lacunas" eram US `status: todo`
 * — feature que **ainda não existe**. Escrever UC para código inexistente cria UC órfão, que
 * o casos-gate G-2 pune e que BLOQUEIA o merge de quem for implementar (lápide §5 2026-07-16:
 * UC não é canal de pedido). Lacuna de verdade é **US entregue SEM contrato** (`done`/`doing`
 * sem caso). US `todo` sem caso é o estado NORMAL do backlog, não dívida.
 */
export function extrairUS(specSrc) {
  const linhas = specSrc.split(/\r?\n/);
  const out = [];
  linhas.forEach((ln, i) => {
    const m = ln.match(/^###\s+(US-[A-Z]{2,8}-\d{3,4})\s*·?\s*(.*)$/);
    if (!m) return;
    const meta = linhas.slice(i + 1, i + 4).join(' ');
    const st = meta.match(/\bstatus:\s*([a-z-]+)/i);
    out.push({ id: m[1], titulo: m[2].trim(), status: st ? st[1].toLowerCase() : 'desconhecido' });
  });
  return out;
}
/** Entregue = tem que ter contrato. `todo`/`backlog` = ainda não, e tudo bem. */
export const US_ENTREGUE = new Set(['done', 'doing', 'review', 'in-progress']);
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
if (IS_MAIN && args.includes('--selftest')) {
  let f = 0;
  const ok = (nome, cond) => { console.log(`${cond ? '  ok  ' : ' FALHA'} ${nome}`); if (!cond) f++; };

  ok('extrai US do SPEC', extrairUS('### US-PROD-020 · Governança\ntexto\n### US-PROD-021 · Outra').length === 2);
  ok('não confunde US com prosa', extrairUS('falamos de US-PROD-020 no meio do texto').length === 0);

  // O status separa LACUNA (entregue sem contrato) de BACKLOG (não entregue) — sem isto o
  // relatório empurra o autor a escrever UC órfão pra feature que não existe.
  const comStatus = extrairUS('### US-AB-001 · X\n> owner: w · status: done · type: story\n\n### US-AB-002 · Y\n> owner: w · status: todo');
  ok('lê status: done', comStatus[0].status === 'done');
  ok('lê status: todo', comStatus[1].status === 'todo');
  ok('done conta como entregue', US_ENTREGUE.has('done') === true);
  ok('todo NÃO conta como entregue', US_ENTREGUE.has('todo') === false);
  ok('sem linha de status → desconhecido', extrairUS('### US-AB-003 · Z\n\ntexto')[0].status === 'desconhecido');
  ok('extrai CU do SDD', extrairCU('#### CU-PROD-01 — Cadastrar `[must]`\n#### CU-PROD-02 — Variável').length === 2);
  ok('extrai UC do casos.md (dedup)', extrairUC('| UC-PSHOW-01 | x |\n UC-PSHOW-01 de novo\n UC-PSHOW-02').length === 2);

  const corpus = [{ path: 't.php', src: "it('UC-PSHOW-01 · faz algo', function () {});\ntest.fixme('UC-PSHOW-04 · stub');" }];
  ok('UC citado por teste → true', ucCitadoPorTeste('UC-PSHOW-01', corpus) === true);
  ok('UC não citado → false', ucCitadoPorTeste('UC-PSHOW-99', corpus) === false);
  ok('UC só em test.fixme → stub', ucSoStub('UC-PSHOW-04', corpus) === true);
  ok('UC em teste real → NÃO stub', ucSoStub('UC-PSHOW-01', corpus) === false);

  // ANTI-GAMING — achado do agent na corrida do BulkEdit, que testou e reportou honestamente:
  // com `includes` cru, citar o id em QUALQUER parágrafo fechava a lacuna sem contrato nenhum.
  // Presence-gate clássico (L-24: presença ≠ correção). Cobertura agora exige ÂNCORA estrutural.
  ok('NÃO cobre: id em prosa solta',
    citadoComoAncora('Falamos sobre CU-PROD-06 aqui no meio do texto.', 'CU-PROD-06') === false);
  ok('cobre: linha de tabela de rastreabilidade',
    citadoComoAncora('| UC-PBULK-01 | Editar em lote | must | CU-PROD-06 | teste |', 'CU-PROD-06') === true);
  ok('cobre: frontmatter related_us',
    citadoComoAncora('related_us: [US-PROD-023, US-PROD-020]', 'US-PROD-023') === true);
  ok('cobre: bullet de Âncora',
    citadoComoAncora('- **Âncora:** CU-PROD-06 do SDD §6.1', 'CU-PROD-06') === true);
  // A forma REAL do corpus — blockquote + backticks. A 1ª versão do regex não a aceitava e
  // acusou 3 CU legítimos (08/14/15). Este caso é o controle que faltava.
  ok('cobre: blockquote + backticks (forma real dos casos.md)',
    citadoComoAncora('> **Âncora:** `CU-PROD-14` (ficha/consulta) e `CU-PROD-10` `[T0]` do', 'CU-PROD-14') === true);
  ok('NÃO cobre: id parecido (não casa prefixo)',
    citadoComoAncora('| UC-X-01 | y | CU-PROD-061 | z |', 'CU-PROD-06') === false);

  const TOTAL = 19;
  console.log(f === 0 ? `\n✅ selftest ${TOTAL}/${TOTAL} — extratores, classificador e anti-gaming provados` : `\n❌ ${f} falha(s)`);
  process.exit(f === 0 ? 0 : 1);
}

// ── relatório ─────────────────────────────────────────────────────────────────
const mod = IS_MAIN ? args.find((a) => !a.startsWith('--')) : null;
if (IS_MAIN && !mod) { console.log('uso: requisitos-status.mjs <Modulo> [--write|--check]'); process.exit(0); }

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
/**
 * COBERTO = citado como ÂNCORA, não mencionado em prosa (correção 2026-07-26).
 *
 * A 1ª versão usava `src.includes(id)`. O agent da corrida do BulkEdit testou o gaming e
 * reportou honestamente: *"bastaria citar o id na prosa pra lacuna sumir do painel — fiz,
 * vi fechar, desfiz"*. Um `includes` cru transforma o painel em **presence-gate**: escrever
 * o id num parágrafo qualquer "fecha" a lacuna sem contrato nenhum. É a família L-24
 * (presença ≠ correção), a mesma que este projeto mata desde 2026-07-01.
 *
 * Âncora estrutural aceita — as formas MEDIDAS no corpus, não supostas:
 *   · linha de TABELA        → `| UC-X-01 | … | CU-PROD-06 | …`  (rastreabilidade)
 *   · campo de frontmatter   → `related_us: [US-PROD-023]` / `us:` / `âncora:`
 *   · declaração de Âncora   → `> **Âncora:** \`CU-PROD-14\` …`  ← forma REAL dos casos.md
 *                              (blockquote, bullet ou linha nua; id entre backticks ou não)
 * Menção solta em parágrafo NÃO cobre — e é isso que impede o painel de mentir.
 *
 * ⚠️ A 1ª versão desta regra só aceitava BULLET (`- **Âncora:**`) e deu falso-positivo em 3 CU
 * (`CU-PROD-08/14/15`): o corpus usa BLOCKQUOTE (`> **Âncora:**`). Medido contra os arquivos
 * reais antes de fechar — o padrão vem do que o projeto escreve, não do que eu imaginei.
 */
export function citadoComoAncora(src, id) {
  const esc = id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return new RegExp(
    `(^\\|[^\\n]*\\b${esc}\\b)`                                   // linha de tabela
    + `|(^\\s*(related_us|us|ancora|âncora|covers|cobre)\\s*:[^\\n]*\\b${esc}\\b)` // frontmatter
    + `|(^\\s*[>\\-*\\s]*\\*\\*(Âncora|Ancora|Cobre|Covers)[^\\n]*\\b${esc}\\b)`,  // declaração
    'im',
  ).test(src);
}
const cobreAncorado = (id) => casos.some((k) => citadoComoAncora(k.src, id));

const cuSemUC = cu.filter((c) => !cobreAncorado(c.id));
// Só é LACUNA a US já entregue e sem contrato. US `todo` sem caso é backlog normal —
// listá-la como dívida empurra o autor a escrever UC órfão (ver extrairUS).
const usSemContrato = us.filter((u) => US_ENTREGUE.has(u.status) && !cobreAncorado(u.id));
const usBacklog = us.filter((u) => !US_ENTREGUE.has(u.status) && !cobreAncorado(u.id));

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
if (!telasSemCasos.length && !cuSemUC.length && !usSemContrato.length) {
  P('_Nenhuma lacuna: toda tela tem caso, todo CU é citado, e toda US **entregue** tem contrato._');
} else {
  P('| Lacuna | O que falta escrever |');
  P('|---|---|');
  for (const t of telasSemCasos) P(`| Tela \`${t}\` sem \`casos.md\` | o contrato da tela (trio incompleto) |`);
  for (const c of cuSemUC) P(`| \`${c.id}\` sem UC | caso de uso que o exercite — ${c.titulo.slice(0, 70)} |`);
  for (const u of usSemContrato) P(`| \`${u.id}\` **entregue sem contrato** (\`status: ${u.status}\`) | UC que prove o que foi entregue — ${u.titulo.slice(0, 60)} |`);
}
P('');
P('### Backlog — NÃO é lacuna');
P('');
P('> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira');
P('> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar');
P('> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato');
P('> nasce **junto** com a implementação, não antes.');
P('');
if (!usBacklog.length) P('_Nenhuma._');
else {
  P('| US | status | Título |');
  P('|---|---|---|');
  for (const u of usBacklog) P(`| ${u.id} | \`${u.status}\` | ${u.titulo.slice(0, 80)} |`);
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
