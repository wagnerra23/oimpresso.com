#!/usr/bin/env node
// licoes-code-two-strikes.mjs — SessionStart (PORTE cross-plataforma do .ps1, advisory).
// Alarma quando uma classe de erro repetiu (Ocorrências ≥ threshold) e ainda NÃO virou
// defesa mecânica (Gate: none) — o gatilho do loop de aprendizado de código.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// memory/LICOES_CODE.md (lista viva LC-*) + ADR 0256 (derivado+enforçado sobrevive):
// erro que reincide sem gate = candidato a virar defesa mecânica. Origem: sessão
// 2026-06-06 (Wagner: "quando deve ser acionado o aprendizado?").
//
// O ledger cobre erro de CÓDIGO **e de PROCESSO/comportamento de agente** (medição,
// derivação, oráculo errado) — o tema é "reincidência→defesa mecânica", processo é
// instância disso igual código (proposal two-strikes-cobre-processo, raio-X 2026-07-20).
// Cobertura só-ADVISORY (nudge/warn que NÃO bloqueia) conta como "sem defesa mecânica":
// a doutrina two-strikes exige defesa MECÂNICA (bloqueia/morde), não nudge que vaza.
// Declare `Gate: advisory — <hooks>` e a classe segue alarmando até virar sonda que morde.
// EXCEÇÃO (ADR 0224): advisory que é a decisão FINAL by-design → declare
// `Gate: advisory-terminal (0224) — <hook>`; o marcador terminal/by-design/0224 sai do alarme.
// Fonte: ADR 0344 (two-strikes cobre processo), raio-X 2026-07-20.
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o alarme
// evapora em silêncio. Supersede licoes-code-two-strikes.ps1 (triagem #13, lote A).
//
// ADVISORY: exit 0 SEMPRE (nunca bloqueia). Fail-open em qualquer erro.
// Env: OIMPRESSO_LICOES_CODE_PATH (override do arquivo), OIMPRESSO_LICOES_THRESHOLD (default 2).
// Selftest: node .claude/hooks/licoes-code-two-strikes.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { readFileSync, existsSync } from 'node:fs';

/** caminho do LICOES_CODE.md (override por env pra teste). */
export function licoesPath(env = process.env) {
  if (env.OIMPRESSO_LICOES_CODE_PATH) return env.OIMPRESSO_LICOES_CODE_PATH;
  const repo = dirname(dirname(dirname(fileURLToPath(import.meta.url))));
  return join(repo, 'memory', 'LICOES_CODE.md');
}

export function threshold(env = process.env) {
  const v = String(env.OIMPRESSO_LICOES_THRESHOLD || '');
  return /^\d+$/.test(v) ? parseInt(v, 10) : 2;
}

/**
 * "sem defesa MECÂNICA" = vazio, none, nenhum, -, n/a
 * OU a entrada declara EXPLICITAMENTE que a cobertura é só advisory/parcial/insuficiente
 * (nudge/warn não bloqueiam → doutrina two-strikes ainda não satisfeita → segue alarmando).
 * EXCEÇÃO (ADR 0224 · ADR 0344): advisory declarado terminal/by-design é a decisão FINAL
 * válida pra a classe (não é furo) → NÃO alarma. Marca-se com `terminal`/`by-design`/`0224`.
 * Um nome-de-gate real ("mutation-gate (advisory, ...)") NÃO casa — só o prefixo declarado.
 */
export function semGate(g) {
  if (!g) return true;
  const s = String(g).trim();
  if (/^(none|nenhum|nenhuma|-|n\/a|na)$/i.test(s)) return true;
  if (/^(advisory|parcial|insuficiente)\b/i.test(s)) return !/\b(terminal|by-design|0224)\b/i.test(s);
  return false;
}

/** parser PURO do markdown → lista de {id, titulo, ocorr, gate}. */
export function parseLicoes(text) {
  const licoes = [];
  let cur = null;
  for (const ln of String(text || '').split('\n')) {
    const h = /^##\s+(LC-\S+)\s*[-—]?\s*(.*)$/.exec(ln);
    if (h) {
      if (cur) licoes.push(cur);
      cur = { id: h[1], titulo: h[2].trim(), ocorr: 0, gate: '' };
      continue;
    }
    if (!cur) continue;
    const oc = /\*\*Ocorr.*?(\d+)/.exec(ln);
    const gt = /\*\*Gate.*?:\s*(.+?)\s*$/.exec(ln);
    if (oc) cur.ocorr = parseInt(oc[1], 10);
    else if (gt) cur.gate = gt[1].replace(/\*\*/g, '').trim();
  }
  if (cur) licoes.push(cur);
  return licoes;
}

/** classifica em alarme (≥threshold sem gate) e watch (<threshold sem gate). */
export function classificar(licoes, th) {
  const alarme = licoes.filter((l) => l.ocorr >= th && semGate(l.gate));
  const watch = licoes.filter((l) => l.ocorr < th && semGate(l.gate));
  return { alarme, watch };
}

const toAscii = (s) => String(s).replace(/[^\x20-\x7E]/g, '.');

export function formatBanner(alarme, watch, th) {
  if (alarme.length === 0 && watch.length === 0) return '';
  const out = ['', '=== LICOES [CODE] - gatilho two-strikes (audit loop de aprendizado) ==='];
  if (alarme.length) {
    out.push(`  [!] ${alarme.length} classe(s) repetiram (>= ${th}x) e NAO tem gate. PROMOVER A DEFESA MECANICA:`);
    for (const a of alarme) out.push(`      ${a.id} - ${toAscii(a.titulo)}  (${a.ocorr}x, sem gate)`);
    out.push('  ACAO: avise o Wagner e proponha o gate/hook/baseline que mata essa classe.');
    out.push("  (Quando criar o gate, troque 'Gate: none' pelo nome dele em LICOES_CODE.md - o alarme some.)");
  }
  if (watch.length) out.push(`  [.] ${watch.length} classe(s) em WATCH (sem gate, < ${th}x). Se reincidirem, viram alarme.`);
  out.push('');
  return out.join('\n');
}

// ═══════════════════════════════════════════════════════════════════════════════
// AUTO-FEED (proposal auto-feed-ledger-aprendizado) — reconciliação §5 ↔ ledger.
//
// O QUE É: um SURFACE advisory, no SessionStart, das RECORRÊNCIAS que o AUTOR já
// DECLAROU no §5 de memory/proibicoes.md ("reincidência / mesma família / EMENDA da
// lápide / …") e que NENHUMA classe LC do ledger ainda conta. Torna MECÂNICA a
// DETECÇÃO da recorrência-declarada; o julgamento (vira LC? qual? é ruído?) e o passo
// a montante (alguém ESCREVER a lápide no §5) seguem HUMANOS.
//
// O QUE NÃO É (rótulo honesto — senão vira o próprio LC-08 "afirmar-sem-medir"): NÃO
// "lê o erro real de fontes reais", NÃO "fecha o loop", NÃO auto-classifica. Reconcilia
// DOIS docs curados à mão (o §5-prosa e o ledger-contador) no estado-atual. O elo
// erro→lápide continua humano; isto só encolhe [detectar + julgar + registrar] para
// [julgar + registrar] — a DETECÇÃO da recorrência-DECLARADA passa a ser mecânica.
//
// POR QUE SOBREVIVE AO §5 (workflow adversarial 2026-07-20 — cético matou o resto):
//  · Mede DIFERENÇA DE CONJUNTOS (recorrências declaradas ∖ datas que o ledger cita),
//    conteúdo derivado — não "a seção existe" (≠ presence-gate: lápides 07-01/07-09/07-16).
//  · frontier = max(data do §5 que o ledger CITA) — DERIVADO, não watermark auto-escrito
//    (≠ last_validated/verificado_em: lápides 07-01/07-09). Só avança quando um humano
//    adiciona um recibo citando lápide mais nova — i.e. quando reconciliou de verdade.
//  · NÃO constrói cadeia/edge entre lápides nem resolve qual lápide o marcador aponta
//    (datas do §5 NÃO são únicas) — evita "achar a raiz" (07-15) e match sintático frágil
//    (allowlist-de-pasta 06-30 / guard @scope 07-09).
//  · forward-only: só surfaça pós-frontier + cap; backlog pré-frontier vira 1 linha de
//    contagem (≠ big-bang de backfill de legado: lápide 07-12 + ADR 0344 §Alternativas).
//  · marcador é AUTHOR-DECLARED (grep da palavra do autor), não inferência do agente.
// Advisory por construção (hook exit-0 SEMPRE) — NUNCA required (guarda-corpo ADR 0344).
// S1 (Ocorrências == nº-recibos, igualdade estrita) foi CORTADO: FP no LC-08 no dia 1
// (a linha dá 3 contagens por 3 regras) + cego ao under-count (lápides 07-01/07-09).
// ═══════════════════════════════════════════════════════════════════════════════

/** marcadores de RECORRÊNCIA que o AUTOR escreve no §5. Extração ≠ classificação:
 *  lê a declaração do autor ("isto voltou / é da mesma família"), não adivinha a classe. */
export const RECUR_MARKERS = /reincid|mesma\s+fam[ií]lia|mesma\s+raiz|mesma\s+classe|mesma\s+doen[çc]a|\bvoltou\b|\bde\s+novo\b|\bre-?proposto\b|emenda\s+da\s+l[áa]pide|irm[ãa]\s+da\s+entrada|\bcomplementa\b|\becoa\b|genealogia|fam[ií]lia\s+d[oa]\b/i;

export function proibicoesPath(env = process.env) {
  if (env.OIMPRESSO_PROIBICOES_PATH) return env.OIMPRESSO_PROIBICOES_PATH;
  const repo = dirname(dirname(dirname(fileURLToPath(import.meta.url))));
  return join(repo, 'memory', 'proibicoes.md');
}

const markerOf = (body) => {
  const m = RECUR_MARKERS.exec(String(body || ''));
  return m ? m[0].replace(/\s+/g, ' ').trim().toLowerCase() : null;
};

/** parseia a REGIÃO §5 (entre "## Ideias avaliadas e DESCARTADAS" e o próximo "## ")
 *  → [{date:'YYYY-MM-DD', mmdd:'MM-DD', title, marker|null}]. Escopo obrigatório pra
 *  excluir "reincidi" FORA do §5. NÃO liga lápides entre si (lápide 07-15 "achar a raiz"). */
export function parseTombstones(text) {
  const lines = String(text || '').split('\n');
  let startIdx = -1;
  for (let i = 0; i < lines.length; i++) {
    if (/^##\s+Ideias avaliadas e DESCARTADAS/i.test(lines[i])) { startIdx = i + 1; break; }
  }
  if (startIdx === -1) return [];
  let endIdx = lines.length;
  for (let i = startIdx; i < lines.length; i++) {
    if (/^##\s+/.test(lines[i]) && !/^###/.test(lines[i])) { endIdx = i; break; }
  }
  const tombs = [];
  let cur = null;
  const flush = () => { if (cur) { cur.marker = markerOf(cur.body); delete cur.body; tombs.push(cur); } };
  for (let i = startIdx; i < endIdx; i++) {
    const h = /^###\s+(\d{4})-(\d{2})-(\d{2})\s*[—–-]?\s*(.*)$/.exec(lines[i]);
    if (h) { flush(); cur = { date: `${h[1]}-${h[2]}-${h[3]}`, mmdd: `${h[2]}-${h[3]}`, title: h[4].trim(), body: '' }; continue; }
    if (cur) cur.body += lines[i] + '\n';
  }
  flush();
  return tombs;
}

/** datas do §5 que CADA LC cita (só em linhas que declaram "§5"/"proibicoes") →
 *  {byLc:[{id, mmdds:[]}], allMmdd:[]}. Ignora data de sessão (ex.: LC-06 cita
 *  2026-07-06 SEM "§5" → fora). Base do frontier (S3) e do fact-anchor de recibo (S2). */
export function ledgerCitacoesSecao5(text) {
  const blocks = String(text || '').split(/^##\s+(?=LC-)/m).slice(1);
  const byLc = [];
  const allMmdd = new Set();
  for (const b of blocks) {
    const id = (b.match(/^LC-\S+/) || ['?'])[0];
    const mmdds = new Set();
    for (const ln of b.split('\n')) {
      // SÓ a linha de RECIBO — **Ocorrências:** que declara §5/proibicoes. O recibo datado
      // é a convenção do "Caminho único de atualização do count" (ADR 0344). Restringir à
      // Ocorrências (excluir Ref/corpo) evita contaminar o frontier com data de METADATA —
      // ex.: o Ref do LC-08 diz "raio-X 2026-07-20", que NÃO é recibo e falseava o frontier
      // pra 07-20 (bug pego pelo dry-run contado — a razão de o dry-run ser obrigatório).
      if (!/\*\*Ocorr/i.test(ln)) continue;
      if (!/§5|proibicoes/i.test(ln)) continue;
      for (const m of ln.matchAll(/(?:\d{4}-)?(\d{2})-(\d{2})\b/g)) {
        const mo = +m[1], da = +m[2];
        if (mo >= 1 && mo <= 12 && da >= 1 && da <= 31) { const k = `${m[1]}-${m[2]}`; mmdds.add(k); allMmdd.add(k); }
      }
    }
    if (mmdds.size) byLc.push({ id, mmdds: [...mmdds] });
  }
  return { byLc, allMmdd: [...allMmdd] };
}

/** frontier = a data REAL mais recente do §5 que o ledger cita (resolve mmdd→full via
 *  os tombstones, pegando a mais recente com aquele mmdd — trata o ano certo, não MM-DD
 *  solto). null se o ledger não cita §5 nenhum (sem baseline de reconciliação → não nag). */
export function computeFrontier(citedMmdd, tombs) {
  let front = null;
  for (const mmdd of citedMmdd) {
    for (const t of tombs) if (t.mmdd === mmdd && (!front || t.date > front)) front = t.date;
  }
  return front;
}

/** reconcilia ledger↔§5. Puro (sem I/O). Retorna dados; o formato fica no formatReconcile. */
export function reconcile(ledgerText, proibicoesText, { cap = 5 } = {}) {
  const tombs = parseTombstones(proibicoesText);
  // Sem §5 legível (arquivo vazio/ausente, ou §5 renomeada) → NADA a reconciliar. Não
  // inventa "recibo pendurado" (não há fonte-de-verdade pra checar) — padrão "sem fonte
  // → não inventa" do memory-health. Sandbox/temp-dir cai aqui e fica silencioso.
  const neutro = { frontier: null, surfaced: [], surfacedTotal: 0, semMarcadorPosFrontier: 0, backlogPreFrontier: 0, dangling: [], recibosOk: 0, recibosTotal: 0, tombsTotal: 0, marcadosTotal: 0, lcComRecibo: 0 };
  if (!tombs.length) return neutro;
  const { byLc, allMmdd } = ledgerCitacoesSecao5(ledgerText);
  const frontier = computeFrontier(allMmdd, tombs);
  const marcados = tombs.filter((t) => t.marker);
  // S2 — recibo PENDURADO: cada mmdd que o ledger cita resolve a ≥1 lápide do §5?
  // (existência-da-data, não identidade-do-tombstone: datas não são únicas. Verde = "a
  //  data existe no §5", NUNCA "o recibo/contagem está certo".)
  const mmddSet = new Set(tombs.map((t) => t.mmdd));
  const dangling = [];
  let recibosTotal = 0, recibosOk = 0;
  for (const lc of byLc) for (const mmdd of lc.mmdds) {
    recibosTotal++;
    if (mmddSet.has(mmdd)) recibosOk++; else dangling.push({ lc: lc.id, mmdd });
  }
  // S3 — recorrência DECLARADA além do frontier, fora do ledger. forward-only (só pós-frontier).
  let surfaced = [], semMarcadorPosFrontier = 0, backlogPreFrontier = 0;
  if (frontier) {
    surfaced = marcados.filter((t) => t.date > frontier).sort((a, b) => (a.date < b.date ? 1 : -1));
    semMarcadorPosFrontier = tombs.filter((t) => !t.marker && t.date > frontier).length;
    backlogPreFrontier = marcados.filter((t) => t.date <= frontier).length;
  }
  return {
    frontier, surfaced: surfaced.slice(0, cap), surfacedTotal: surfaced.length,
    semMarcadorPosFrontier, backlogPreFrontier, dangling, recibosOk, recibosTotal,
    tombsTotal: tombs.length, marcadosTotal: marcados.length, lcComRecibo: byLc.length,
  };
}

/** banner CONCISO — imprime SÓ quando há algo a agir (surface OU recibo pendurado);
 *  '' quando limpo (mesma filosofia "silêncio quando ok" do banner two-strikes). */
export function formatReconcile(recon) {
  if (!recon) return '';
  if (recon.surfaced.length === 0 && recon.dangling.length === 0) return '';
  const out = ['', '=== LICOES [CODE] - reconciliacao proibicoes-sec5 <-> ledger (advisory - auto-feed) ==='];
  if (recon.frontier) out.push(`  frontier ${recon.frontier} (data mais recente da sec5 que o ledger cita) - recibos ${recon.recibosOk}/${recon.recibosTotal} resolvem`);
  if (recon.dangling.length) {
    out.push(`  [!] ${recon.dangling.length} recibo(s) PENDURADO(s) (data citada sem lapide na sec5 - typo/recibo fabricado):`);
    for (const d of recon.dangling.slice(0, 8)) out.push(`      ${d.lc} cita ${d.mmdd} - sem "### *-${d.mmdd}" na sec5`);
  }
  if (recon.surfaced.length) {
    const extra = recon.surfacedTotal > recon.surfaced.length ? ` (+${recon.surfacedTotal - recon.surfaced.length} mais)` : '';
    out.push(`  sec5: ${recon.surfacedTotal} recorrencia(s) DECLARADA(s) pelo autor ALEM do frontier, fora do ledger${extra}:`);
    for (const t of recon.surfaced) out.push(`      ${t.date} - ${toAscii(t.title).slice(0, 66)}  (marcador: "${toAscii(t.marker)}")`);
    if (recon.semMarcadorPosFrontier) out.push(`  cauda nao coberta: ${recon.semMarcadorPosFrontier} lapide(s) pos-frontier SEM marcador (falso-negativo possivel)`);
    out.push('  ACAO: cada item vira classe LC nova, +1 numa LC, ou e ruido - VOCE decide e registra.');
    out.push('        (a DETECCAO da recorrencia-DECLARADA e mecanica; o passo erro->lapide e o julgamento seguem HUMANOS.)');
  }
  out.push('');
  return out.join('\n');
}

async function main() {
  try {
    const p = licoesPath();
    if (!existsSync(p)) process.exit(0);
    const text = readFileSync(p, 'utf8');
    const th = threshold();
    const { alarme, watch } = classificar(parseLicoes(text), th);
    const banner = formatBanner(alarme, watch, th);
    if (banner) process.stdout.write(banner + '\n');
    // AUTO-FEED: reconciliação §5↔ledger (surface advisory de recorrência declarada
    // não-contada). Fail-open próprio: proibicoes.md ausente (sandbox/temp-dir) → pula
    // silencioso, nunca quebra o SessionStart.
    try {
      const pp = proibicoesPath();
      if (existsSync(pp)) {
        const rb = formatReconcile(reconcile(text, readFileSync(pp, 'utf8')));
        if (rb) process.stdout.write(rb + '\n');
      }
    } catch { /* reconciliação nunca derruba a sessão */ }
    process.exit(0);
  } catch { process.exit(0); }
}

/** --reconcile: dry-run CONTADO da reconciliação contra os arquivos reais (evidência +
 *  ferramenta pro humano). Imprime as contagens SEMPRE (mesmo limpo), diferente do banner
 *  do SessionStart que é conciso-quando-há-algo. */
function reconcileCli() {
  const lp = licoesPath(), pp = proibicoesPath();
  if (!existsSync(lp) || !existsSync(pp)) { console.log('reconcile: ledger ou proibicoes ausente — nada a reconciliar.'); return; }
  const recon = reconcile(readFileSync(lp, 'utf8'), readFileSync(pp, 'utf8'));
  console.log(JSON.stringify({
    frontier: recon.frontier,
    tombstones_no_secao5: recon.tombsTotal,
    tombstones_com_marcador: recon.marcadosTotal,
    lc_que_citam_secao5: recon.lcComRecibo,
    recibos_resolvem: `${recon.recibosOk}/${recon.recibosTotal}`,
    recibos_pendurados_S2: recon.dangling,
    surface_S3_pos_frontier: recon.surfaced.map((t) => ({ date: t.date, marker: t.marker, title: t.title })),
    surface_S3_total: recon.surfacedTotal,
    pos_frontier_SEM_marcador: recon.semMarcadorPosFrontier,
    backlog_pre_frontier_marcado: recon.backlogPreFrontier,
  }, null, 2));
  const banner = formatReconcile(recon);
  console.log(banner ? '\n--- banner que o SessionStart imprimiria ---' + banner : '\n(limpo: SessionStart não imprimiria nada de reconciliação)');
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    // fileURLToPath (não url.pathname): no Windows o pathname vira "/D:/…" e o spawn dá
    // MODULE_NOT_FOUND. O CI (Linux) chama o .test.mjs direto, então o bug só mordia no
    // Windows do [W] — onde a validação local deste hook precisa rodar. Drive-by cross-plat.
    const test = fileURLToPath(new URL('./licoes-code-two-strikes.test.mjs', import.meta.url));
    const r = spawnSync(process.execPath, [test], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  if (process.argv.includes('--reconcile')) { reconcileCli(); process.exit(0); }
  main();
}
