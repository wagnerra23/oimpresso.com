#!/usr/bin/env node
// charter-live-signal.mjs — gate de SINAL pra charter `status: live` (proposta SDD 2026-06-24).
//
// POR QUE EXISTE: na reconciliação do Cliente (2026-06-24) um charter foi promovido a
// `status: live` SEM prova de produção — as telas eram flag-gated (MWART_CLIENTE_*, default
// OFF, fallback Blade) e a máquina deu verde (o check de zumbi do anchor-lint é CEGO a flag:
// `existsSync`+render-graph dizem "existe", não "está live pra tenant real"). Quem pegou foi o
// adversário humano + o Wagner confirmando "biz=4 está no react". Conhecimento tribal, não sinal.
//
// O QUE FAZ: pra cada `*.charter.md` com `status: live`, exige um SINAL de que a tela está viva
// em produção — ou o `component` listado em governance/prod-flags.json `live` (>=1 business_id),
// ou hits>0 em governance/route-hits.json `pages` (execução REAL — ledger do middleware
// ContadorHitsRota, 2026-07-09), ou um campo `smoke:` no frontmatter (ref a um smoke datado).
// Sem nenhum → `live_sem_sinal`. `live = evidência, não palavra`. fs-puro (2 JSON + walk). Sem deps/DB/PHP.
//
// NÃO substitui anchor-lint (âncora spec<->código) nem doneness-lint (status:done × âncora no
// SPEC). Concern próprio (SoC): a HONESTIDADE do `status: live` do CHARTER contra prod.
//
// USO (na raiz do repo):
//   node scripts/governance/charter-live-signal.mjs              # full-tree, report humano (exit 0)
//   node scripts/governance/charter-live-signal.mjs --json       # JSON determinístico (sem timestamp/sha)
//   node scripts/governance/charter-live-signal.mjs <charter ...> # diff-aware: só os charters passados
//   node scripts/governance/charter-live-signal.mjs --check [<charter ...>]  # exit 1 se live_sem_sinal
//   node scripts/governance/charter-live-signal.mjs --check-frescor         # exit 1 se o hit estiver
//     FORA da janela que o proprio route-hits.json declara (`janela_dias`). OPT-IN: o --check normal
//     nao cobra frescor, so REPORTA — promover a cobranca e flip [W] (ADR 0275).
//
// ADVISORY DE NASCENÇA (ADR 0271/0275): no CI roda DIFF-AWARE (`--check` só nos charters TOCADOS
// no PR) — morde só `status: live` NOVO/TOCADO sem sinal (no-new-lie); os ~54 live legados sem
// sinal NÃO avermelham (grandfather por não-toque). Cron/full-tree = report (exit 0, dívida visível).
// Promoção a required = flip do Wagner por calendário (ADR 0275 §5). Teto ADR 0298: estende, não cria.

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join, resolve, relative } from 'node:path';
// FONTE ÚNICA da regra de sinal (3 fontes: prod-flags · route-hits · smoke:) — a MESMA que o
// `charter-promote-signal` (o inverso deste gate) e o `screen-coverage-map --screen` consomem.
// Era código DUPLICADO entre os dois primeiros, com drift já instalado (este lia `ultima_data`,
// o outro ignorava). A lib devolve DADO; o rótulo continua sendo formatado aqui.
import { frontmatterDe, campo, sinalDe, carregarFontes } from '../lib/charter-signal.mjs';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const CHECK = process.argv.includes('--check');
// OPT-IN: cobra a janela que o próprio route-hits.json declara (`janela_dias`). Separado do
// `--check` porque mudaria o veredito de charters que hoje passam — promover é flip [W].
const CHECK_FRESCOR = process.argv.includes('--check-frescor');
const PAGES = join(ROOT, 'resources', 'js', 'Pages');

// 3ª FONTE de sinal (2026-07-09 · grade v3 "verificação runtime"): ledger de
// execução real governance/route-hits.json (`php artisan route-hits:export
// --write` no prod — coleta middleware ContadorHitsRota). pages[<component>]
// com hits>0 na janela = a tela foi de fato SERVIDA — sinal mais forte que
// flag ligada (flag diz "pode servir"; hit diz "serviu"). Aditivo: só cria
// caminho NOVO pra live_ok, nunca avermelha o que hoje passa. Ausente = {}.
let FONTES;
try { FONTES = carregarFontes(ROOT); }
catch (e) { console.error(`charter-live-signal: ${e.message}`); process.exit(2); }

function walk(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) walk(p, acc);
    else if (e.name.endsWith('.charter.md')) acc.push(p);
  }
  return acc;
}

// seleção: args posicionais (diff-aware) ou full-tree
const args = process.argv.slice(2).filter((a) => !a.startsWith('--'));
const files = args.length
  ? args.map((a) => resolve(ROOT, a)).filter((p) => p.endsWith('.charter.md') && existsSync(p))
  : walk(PAGES);

const rows = [];
for (const f of files.sort()) {
  const fm = frontmatterDe(readFileSync(f, 'utf8'));
  if (campo(fm, 'status') !== 'live') continue; // só status:live entra
  const s = sinalDe(fm, FONTES);
  const signal = s.fonte === 'prod-flags' ? `prod-flags:biz=${s.biz.join(',')}`
    : s.fonte === 'route-hits' ? `route-hits:${s.hits}hit@${s.ultima_data}`
    : s.fonte === 'smoke' ? `smoke:${s.smoke}`
    : null;
  rows.push({ rel: relative(ROOT, f).replace(/\\/g, '/'), key: s.key, signal, state: signal ? 'live_ok' : 'live_sem_sinal', ultima_data: s.ultima_data, frescor: s.frescor || null });
}
const semSinal = rows.filter((r) => r.state === 'live_sem_sinal');

if (JSON_OUT) {
  process.stdout.write(JSON.stringify({
    _meta: { lint: 'charter status:live precisa de sinal de prod (prod-flags.json, route-hits.json ou smoke:)', generator: 'scripts/governance/charter-live-signal.mjs', regra: 'live_ok = component em governance/prod-flags.json `live` (>=1 biz) OU hits>0 em governance/route-hits.json `pages` (execução REAL na janela do export — middleware ContadorHitsRota) OU campo smoke:. live_sem_sinal = nenhum. Fontes aditivas: route-hits só cria caminho novo pra live_ok, nunca avermelha. fs-puro, sem timestamp/sha (re-run sem mudança = diff vazio).', escopo: args.length ? 'diff-aware (args)' : 'full-tree' },
    summary: { live_total: rows.length, live_ok: rows.length - semSinal.length, live_sem_sinal: semSinal.length },
    rows,
  }, null, 2) + '\n');
  process.exit(0);
}

console.log(`\n  CHARTER LIVE SIGNAL — \`status: live\` precisa de sinal de prod (governance/prod-flags.json · governance/route-hits.json · \`smoke:\`) · escopo: ${args.length ? 'diff-aware' : 'full-tree'}`);
console.log(`  live: ${rows.length} · com sinal: ${rows.length - semSinal.length} · SEM sinal: ${semSinal.length}\n`);
for (const r of semSinal) console.log(`  ⚠️ ${r.rel} (${r.key}): \`status: live\` SEM sinal de prod → adicione \`${r.key}\` em governance/prod-flags.json \`live\`, OU campo \`smoke:\` datado no charter, OU gere o ledger de hits (route-hits:export) com a tela servida.`);
if (!semSinal.length) console.log('  ✓ todo charter `status: live` carrega sinal de prod (prod-flags.json, route-hits.json ou smoke).');
console.log(`\n  live_sem_sinal = "diz live mas a máquina não tem prova de prod" — o buraco que deixou promover charter a live sem evidência (reconciliação Cliente 2026-06-24). Nunca afirmar live sem sinal.\n`);

// ── FRESCOR DO LEDGER (2026-08-23) — reportado sempre, cobrado só com --check-frescor ────
//
// O `route-hits.json` declara `janela_dias` (hoje 30) e este gate SEMPRE ignorou: bastava
// `hits > 0`, para sempre. Medido em 2026-08-23: a `ultima_data` mais nova tinha 29 dias —
// o ledger expirava no dia seguinte e 4 charters continuariam `live_ok` indefinidamente.
// O `cron-watchdog` vigia o arquivo, mas com limite genérico de 60d (o DOBRO da janela que
// o próprio ledger declara), então entre o dia 30 e o 60 ninguém diz nada.
//
// NÃO muda o veredito de `live_ok` de propósito: avermelhar 4 charters num gate que já roda
// é decisão [W] (proibicoes.md §Sempre-fazer #6 — nasce advisory, forward-only). Aqui é
// relato; `--check-frescor` é o opt-in de quem quiser cobrar.
const vencidos = rows.filter((r) => r.frescor && r.frescor.fora);
const porVencer = rows.filter((r) => r.frescor && !r.frescor.fora);

if (porVencer.length || vencidos.length) {
  console.log('  ── frescor do ledger route-hits (janela declarada pelo próprio arquivo) ──');
  for (const r of [...vencidos, ...porVencer]) {
    const f = r.frescor;
    const marca = f.fora ? '🔴 VENCIDO' : (f.idade_dias >= f.janela_dias - 3 ? '🟠 vence em breve' : '✓');
    console.log(`  ${marca}  ${r.key} — hit de ${r.ultima_data} (${f.idade_dias}d de ${f.janela_dias}d)`);
  }
  if (vencidos.length) {
    console.log(`\n  ${vencidos.length} charter(s) seguem \`live_ok\` com hit FORA da janela que o ledger declara.`);
    console.log('  Sinal fora da janela não é sinal — regere com `php artisan route-hits:export --write` em prod.');
  }
  console.log('');
}

if (CHECK_FRESCOR && vencidos.length > 0) process.exit(1);
if (CHECK && semSinal.length > 0) process.exit(1);
process.exit(0);
