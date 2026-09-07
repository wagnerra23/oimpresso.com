#!/usr/bin/env node
// loop-fechar-check.mjs — SessionStart (PORTE cross-plataforma do .ps1, advisory).
// Rotina idempotente "Fechar o Loop do IA-OS": lê o manifesto, resolve o estado de cada
// item (feito? por arquivo/flag) e imprime o próximo pendente.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// AUDIT IA-OS 2026-05-29 (Wagner pediu rotina idempotente atrelada ao brief).
// Manifesto = fonte da verdade: .claude/loop-fechar-o-loop.json.
// REGRA DURA: NUNCA toca Brain B / autonomia ADS (decisão Wagner — 2º cérebro off).
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP a rotina
// evapora em silêncio. Supersede loop-fechar-check.ps1 (triagem #14, lote A).
//
// ADVISORY: exit 0 SEMPRE. Fail-open em qualquer erro.
// Selftest: node .claude/hooks/loop-fechar-check.mjs --selftest
//
// ── EXTENSÃO 2026-09-07 (ADR 0391 — regime de evolução por etapas MEDIDAS) ────
// O mesmo hook passa a ler QUALQUER manifesto (`--manifest <path>`) e ganha o detect
// `comando`: roda a PORTA VIVA da etapa (casos:report, screen-coverage, licoes…) e
// compara o NÚMERO com o alvo. É a resposta à LC-11 (presence-gate): `file_any` mede
// se o arquivo EXISTE; `comando` mede se o COMPORTAMENTO está lá. Tri-estado honesto:
// feito | pendente | nao_medido — "não consegui medir" NUNCA vira estado do objeto
// (§5 2026-07-29). Etapa `medir: 'sob_demanda'` só roda com `--medir`; fora disso o
// banner mostra a última medição (cache em .claude/run/, gitignored) com a data.
//   node .claude/hooks/loop-fechar-check.mjs --manifest .claude/regime-evolucao.json
//   node .claude/hooks/loop-fechar-check.mjs --manifest .claude/regime-evolucao.json --medir
//   … --json   (saída estruturada, pra teste/relatório)
// Estendido em vez de script novo: o dono de "manifesto com etapas + detect" é este
// hook (§5 2026-08-03 / LC-19 — não autorar máquina paralela ao dono).

import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, join, isAbsolute } from 'node:path';
import { readFileSync, existsSync, writeFileSync, mkdirSync } from 'node:fs';

const HOOK_DIR = dirname(fileURLToPath(import.meta.url));
export const MANIFEST = join(HOOK_DIR, '..', 'loop-fechar-o-loop.json');
const REPO = join(HOOK_DIR, '..', '..');
const ARGV = process.argv.slice(2);
const argValor = (flag) => { const i = ARGV.indexOf(flag); return i >= 0 ? ARGV[i + 1] : null; };

/** runner padrão do detect `comando` (shell, cwd = raiz do repo). Injetável nos testes. */
export function runnerPadrao(cmd, { cwd, timeout }) {
  const r = spawnSync(cmd, { shell: true, cwd, timeout, encoding: 'utf8', windowsHide: true });
  return { status: r.status, stdout: r.stdout || '', stderr: r.stderr || '', error: r.error };
}

/**
 * Mede UMA etapa `comando`. Pura se `runner` injetado.
 * Retorna { estado: 'feito'|'pendente'|'nao_medido', valor?, alvo?, motivo? }.
 * Contrato: exit≠0 sem `op:'exit0'` = nao_medido (não é "pendente" — é falha do medidor);
 * com `op:'exit0'`, só os códigos em `exit_pendente` (default [1]) viram pendente, o resto
 * é nao_medido (127 = comando ausente, crash = não mediu). Regex sem match = nao_medido,
 * salvo `regex_ausente_vale` numérico (pra banner que some quando o número é 0).
 * `alvo_grupo: N` = o alvo é o grupo N da própria regex (ex. "54/218" → alvo derivado,
 * nunca um número escrito à mão que apodrece quando a população muda).
 */
export function medirComando(det, { repoRoot = REPO, runner = runnerPadrao } = {}) {
  const nao = (motivo) => ({ estado: 'nao_medido', motivo });
  if (!det || det.tipo !== 'comando' || !det.cmd) return nao('detect comando sem cmd');
  let r;
  try { r = runner(det.cmd, { cwd: repoRoot, timeout: det.timeout_ms ?? 120000 }); }
  catch (e) { return nao(`nao rodou: ${(e && e.message) || e}`); }
  if (!r || r.error) return nao(`nao rodou: ${(r && r.error && r.error.message) || 'runner sem resposta'}`);
  const saida = `${r.stdout}\n${r.stderr}`;
  if (det.op === 'exit0') {
    if (r.status === 0) return { estado: 'feito', valor: 0, alvo: 0 };
    const pend = Array.isArray(det.exit_pendente) ? det.exit_pendente : [1];
    if (pend.includes(r.status)) return { estado: 'pendente', valor: r.status, alvo: 0 };
    return nao(`exit ${r.status} nao e "pendente" (${pend.join(',')}) — medidor nao rodou`);
  }
  if (r.status !== 0 && !det.aceita_exit_nao_zero) return nao(`exit ${r.status}`);
  let valor; let m = null;
  if (det.contar_linhas) {
    valor = String(r.stdout).split(/\r?\n/).filter((l) => l.trim()).length;
  } else if (det.regex) {
    try { m = new RegExp(det.regex).exec(saida); } catch (e) { return nao(`regex invalida: ${e.message}`); }
    if (m) valor = Number(m[1]);
    else if (typeof det.regex_ausente_vale === 'number') valor = det.regex_ausente_vale;
    else return nao('regex nao casou na saida');
  } else return nao('detect comando sem regex nem contar_linhas');
  let alvo = det.alvo;
  if (det.alvo_grupo) {
    if (!m || m[det.alvo_grupo] == null) return nao('alvo_grupo nao casou');
    alvo = Number(m[det.alvo_grupo]);
  }
  if (!Number.isFinite(valor) || !Number.isFinite(alvo)) return nao('valor/alvo nao numerico');
  const ok = det.op === '<=' ? valor <= alvo : det.op === '>=' ? valor >= alvo : det.op === '==' ? valor === alvo : null;
  if (ok === null) return nao(`op desconhecida: ${det.op}`);
  return { estado: ok ? 'feito' : 'pendente', valor, alvo };
}

/** cache da última medição (gitignored: .claude/run/). Nunca é fonte de verdade — é RETRATO datado. */
export function cachePath(manifest, repoRoot = REPO) {
  return join(repoRoot, '.claude', 'run', `${(manifest && manifest.slug) || 'loop'}.medicao.json`);
}
export function lerCache(manifest, repoRoot = REPO) {
  try { return JSON.parse(readFileSync(cachePath(manifest, repoRoot), 'utf8')); } catch { return null; }
}
export function gravarCache(manifest, itens, repoRoot = REPO) {
  const p = cachePath(manifest, repoRoot);
  const prev = lerCache(manifest, repoRoot) || { itens: {} };
  const out = { medido_em: new Date().toISOString(), itens: { ...(prev.itens || {}) } };
  for (const i of itens) {
    if (i.tipoDetect !== 'comando' || i.deCache || i.estado === 'nao_medido') continue;
    out.itens[i.id] = { estado: i.estado, valor: i.valor, alvo: i.alvo, medido_em: out.medido_em };
  }
  try { mkdirSync(dirname(p), { recursive: true }); writeFileSync(p, JSON.stringify(out, null, 2)); } catch { /* fail-open */ }
  return out;
}

/** resolve o done-state de UM item (puro se exists injetado). */
export function itemDone(it, repoRoot = REPO, exists = existsSync) {
  if (!it || !it.detect) return false;
  if (it.detect.tipo === 'manual') return Boolean(it.done);
  // VETO MANUAL (2026-07-27) — `done: false` EXPLÍCITO reabre o item mesmo que os
  // arquivos do detect existam. Sem isso o escape valve que o PRÓPRIO banner anuncia
  // ("Para reabrir um item, mude 'done' no manifesto") era letra morta nos itens
  // file_any — 3 dos 4. Consequência medida: o item #6 (LGPD purge) tinha
  // `"done": false` no manifesto e o banner imprimia `[OK]` + "LOOP FECHADO", porque
  // `RetentionPurgeCommand.php` EXISTE. Mas o DoD do #6 não é o arquivo: é
  // `JANA_RETENTION_ENABLED=true` em prod após canary 7d + sign-off [W] (nota_aprovacao
  // do próprio item), e a flag segue false — confirmado por 4 fontes independentes
  // (manifesto · SPEC US-COPI-115 checklist · AUDITORIA-IA-OS-2026-06-06 · o BLOCKER
  // §3.1 do EVIDENCE-retention-purge-dry-run-2026-07-12).
  // Classe LC-11 (presence-gate: mede PRESENÇA, não COMPORTAMENTO) em produção.
  // Escopo do veto: só o `false` EXPLÍCITO derruba. `done` ausente ou true → o detect
  // decide, como antes (arquivo sumir continua reabrindo o item sozinho).
  if (it.done === false) return false;
  if (it.detect.tipo === 'comando') return false; // resolvido em resolverItens (precisa de runner/cache)
  if (it.detect.tipo === 'file_any' && Array.isArray(it.detect.paths)) {
    return it.detect.paths.some((p) => exists(join(repoRoot, p)));
  }
  return false;
}

/**
 * TERCEIRO ESTADO (2026-07-27): `descartado: true` = decisão [W] de NÃO fazer.
 *
 * Sem ele o manifesto só sabia pendente|feito, e um item que o dono descartou não
 * tinha como ser representado: marcar `done` imprimiria `[OK]`, que lê como
 * "implementado" — a mesma mentira que o veto do `done:false` consertou hoje de
 * manhã. Item descartado NÃO é item feito; some da fila sem fingir entrega.
 *
 * Caso que criou a necessidade — #6 (LGPD purge): [W] 2026-07-27 decidiu que num
 * ERP não se apaga PII (o conteúdo pode conter dado do cliente do cliente) e que o
 * controle é por permissão de acesso, não por retenção. O código continua no repo,
 * inerte atrás da flag; o que morreu foi a intenção de ligá-lo.
 */
export function itemDescartado(it) {
  return Boolean(it && it.descartado);
}

/** normaliza a lista de itens do manifesto → [{ordem,gap,titulo,done,estado,descartado,…}]. */
export function resolverItens(manifest, repoRoot = REPO, exists = existsSync, opts = {}) {
  const runnerBase = opts.runner || runnerPadrao;
  // MEMO por comando dentro de UMA execução: E2/E3/E4 leem colunas do MESMO relatório —
  // rodar screen-coverage 3× é custo puro, e a saída é a mesma por construção.
  const memo = new Map();
  const runner = (cmd, o) => { if (!memo.has(cmd)) memo.set(cmd, runnerBase(cmd, o)); return memo.get(cmd); };
  const cache = opts.cache === undefined ? lerCache(manifest, repoRoot) : opts.cache;
  const agoraMs = opts.agoraMs ?? Date.now();
  const itens = (manifest && Array.isArray(manifest.itens) ? manifest.itens : []).map((it) => {
    const tipoDetect = it && it.detect ? it.detect.tipo : undefined;
    let estado; let valor; let alvo; let motivo; let deCache = null;
    if (tipoDetect === 'comando') {
      // Ordem: --medir força · cache dentro da validade (cache_ttl_h, default 24h) é RETRATO
      // aceitável e sai com a data · sob_demanda nunca roda sozinho · senão mede agora.
      const entrada = cache && cache.itens ? cache.itens[it.id] : null;
      const ttlMs = (Number.isFinite(it.detect.cache_ttl_h) ? it.detect.cache_ttl_h : 24) * 3600 * 1000;
      const idadeMs = entrada && entrada.medido_em ? agoraMs - Date.parse(entrada.medido_em) : Infinity;
      const cacheValido = Boolean(entrada) && Number.isFinite(idadeMs) && idadeMs >= 0 && idadeMs < ttlMs;
      let med;
      if (opts.medir) med = medirComando(it.detect, { repoRoot, runner });
      else if (cacheValido) { med = { ...entrada }; deCache = entrada.medido_em; }
      else if (it.detect.medir === 'sob_demanda') med = entrada ? { ...entrada, deCache: true } : { estado: 'nao_medido', motivo: 'sob demanda: rode --medir' };
      else med = medirComando(it.detect, { repoRoot, runner });
      if (med.deCache) { delete med.deCache; deCache = entrada.medido_em || 'cache (vencido)'; }
      // VETO MANUAL vale igual aqui: `done:false` explícito derruba um "feito" medido.
      if (it.done === false && med.estado === 'feito') med = { ...med, estado: 'pendente', motivo: 'veto manual done:false' };
      ({ estado, valor, alvo, motivo } = med);
    } else {
      estado = itemDone(it, repoRoot, exists) ? 'feito' : 'pendente';
    }
    return {
      id: it.id,
      ordem: parseInt(it.ordem, 10) || 0,
      gap: it.gap,
      titulo: it.titulo,
      done: estado === 'feito',
      estado,
      valor,
      alvo,
      motivo,
      deCache,
      tipoDetect,
      executor: it.executor,
      descartado: itemDescartado(it),
      razaoDescarte: it.razao_descarte,
      prio: it.prioridade,
      custo: it.custo_recorrente,
      aprova: Boolean(it.precisa_aprovacao_wagner),
      nota: it.nota_aprovacao,
    };
  });
  itens.sort((a, b) => a.ordem - b.ordem);
  return itens;
}

export function formatBanner(itens) {
  if (!itens.length) return '';
  // descartado NÃO entra na fila (nada a fazer) e NÃO conta como feito (nada entregue).
  const pendentes = itens.filter((i) => !i.done && !i.descartado);
  const out = ['', '[loop-fechar-check] === ROTINA: FECHAR O LOOP DO IA-OS (audit 2026-05-29) ==='];
  for (const i of itens) {
    const marca = i.descartado ? '[XX]' : i.done ? '[OK]' : '[--]';
    out.push(`  ${marca} #${i.gap} ${i.prio} - ${i.titulo}`);
    if (i.descartado) out.push(`       ^ DESCARTADO por decisao [W] - nao e divida: ${i.razaoDescarte ?? 'ver razao_descarte no manifesto'}`);
  }
  if (pendentes.length === 0) {
    const nDesc = itens.filter((i) => i.descartado).length;
    const resumo = nDesc
      ? `  NADA PENDENTE - ${itens.length - nDesc} entregue(s), ${nDesc} descartado(s) por decisao [W].`
      : '  LOOP FECHADO - nada a fazer. IA-OS com painel + alarme + LGPD no ar.';
    out.push('', resumo, "  (Para reabrir um item, mude 'done' no manifesto.)");
  } else {
    const next = pendentes[0];
    out.push('', `  PROXIMO PENDENTE: #${next.gap} - ${next.titulo}`, `  Custo recorrente: ${next.custo}`);
    if (next.aprova) out.push('  >> EXIGE APROVACAO DO WAGNER antes de avancar (custo/risco). Nota:', `     ${next.nota}`);
    out.push('', '  ACAO CLAUDE: avise o Wagner que ha item do loop pendente e pergunte',
      `  'quer que eu faca o #${next.gap} agora?'. NAO comece sem ele confirmar.`,
      '  NUNCA inclua Brain B / autonomia ADS nesta rotina (decisao Wagner).');
  }
  out.push('');
  return out.join('\n');
}

/** Banner de manifesto `tipo: 'programa'` (etapas medidas). [OK] feito · [--] pendente · [??] NÃO MEDIDO · [XX] descartado. */
export function formatBannerPrograma(itens, manifest = {}, { cmdMedir = '' } = {}) {
  if (!itens.length) return '';
  const out = ['', `[loop-fechar-check] === PROGRAMA: ${manifest._titulo || manifest.slug || 'programa'} ===`];
  let feitos = 0; let pend = 0; let naoMed = 0; let desc = 0;
  for (const i of itens) {
    let marca; let detalhe = '';
    if (i.descartado) { marca = '[XX]'; desc++; }
    else if (i.estado === 'nao_medido') { marca = '[??]'; naoMed++; detalhe = ` · NAO MEDIDO (${i.motivo || 'sem motivo'})`; }
    else if (i.done) { marca = '[OK]'; feitos++; }
    else { marca = '[--]'; pend++; }
    if (i.valor != null && i.estado !== 'nao_medido') detalhe = ` · ${i.valor}/${i.alvo}`;
    if (i.deCache) detalhe += ` (cache ${String(i.deCache).slice(0, 10)})`;
    out.push(`  ${marca} ${i.gap} ${i.prio} - ${i.titulo}${detalhe}`);
    if (i.descartado) out.push(`       ^ DESCARTADO por decisao [W]: ${i.razaoDescarte ?? 'ver razao_descarte no manifesto'}`);
  }
  out.push('', `  RESUMO: ${feitos} feita(s) · ${pend} pendente(s) · ${naoMed} nao medida(s) · ${desc} descartada(s) de ${itens.length}.`);
  if (naoMed) out.push('  [??] NAO MEDIDO nao e pendente nem feito — e ausencia de medicao (§5 2026-07-29).');
  const prox = itens.find((i) => !i.done && !i.descartado && i.estado !== 'nao_medido');
  if (prox) out.push(`  PROXIMA ETAPA: ${prox.gap} - ${prox.titulo}`, `  Executor: ${prox.executor || 'ver manifesto'}`);
  else if (!pend && !naoMed) out.push('  PROGRAMA CUMPRIDO nas etapas medidas.');
  if (cmdMedir) out.push(`  Medir tudo agora: ${cmdMedir}`);
  out.push('');
  return out.join('\n');
}

async function main() {
  try {
    const arg = argValor('--manifest');
    const manifestPath = arg ? (isAbsolute(arg) ? arg : join(REPO, arg)) : MANIFEST;
    if (!existsSync(manifestPath)) process.exit(0);
    let manifest;
    try { manifest = JSON.parse(readFileSync(manifestPath, 'utf8')); } catch { process.exit(0); }
    if (!manifest || !Array.isArray(manifest.itens)) process.exit(0);
    const medir = ARGV.includes('--medir');
    const itens = resolverItens(manifest, REPO, existsSync, { medir });
    if (itens.some((i) => i.tipoDetect === 'comando' && !i.deCache && i.estado !== 'nao_medido')) gravarCache(manifest, itens);
    if (ARGV.includes('--json')) { process.stdout.write(JSON.stringify({ slug: manifest.slug, medido: medir, itens }, null, 2) + '\n'); process.exit(0); }
    const cmdMedir = arg ? `node .claude/hooks/loop-fechar-check.mjs --manifest ${arg} --medir` : '';
    const banner = manifest.tipo === 'programa' ? formatBannerPrograma(itens, manifest, { cmdMedir }) : formatBanner(itens);
    if (banner) process.stdout.write(banner + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./loop-fechar-check.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
