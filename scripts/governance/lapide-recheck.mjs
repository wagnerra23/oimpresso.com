#!/usr/bin/env node
// @ts-check
/**
 * lapide-recheck.mjs — re-verificação de FRESCOR das lápides §5 (memory/proibicoes.md,
 * "Ideias avaliadas e DESCARTADAS"). O registro de rejeição também apodrece: uma lápide
 * matou uma ideia citando âncoras concretas (um script, um workflow, um arquivo, uma
 * "defesa mecânica que agora existe"); se essas âncoras DRIFTARAM (foram deletadas/
 * renomeadas), a premissa que a matou PODE ter mudado — e ninguém re-lê.
 *
 * ── O QUE FAZ (e o rótulo HONESTO — senão vira o próprio LC-08 "afirmar-sem-medir") ──
 * A DETECÇÃO do drift de âncora é MECÂNICA (existsSync sobre o repo VIVO); o JULGAMENTO
 * — "a premissa ainda vale?" — segue HUMANO. É PROXY, não veredito: uma lápide cujo
 * script/gate citado sumiu NÃO está "stale" automaticamente — está marcada `revisar`, pra
 * um humano re-ler. Espelha EXATAMENTE o auto-feed §5↔ledger (licoes-code-two-strikes): a
 * máquina surfaça, o humano decide.
 *
 * ── O QUE NÃO É (as lápides §5 que este script poderia virar, e não vira) ────────────
 *   • NÃO apaga/edita nada: §5 é append-only Tier 0 (só surfaça pra revisão humana).
 *   • NÃO é presence-gate: não checa "campo presente / seção não-vazia" (lápides §5
 *     07-01/07-09/07-16). Resolve o CONTEÚDO citado contra o repo real (existsSync).
 *   • NÃO é catraca sobre campo auto-declarado: NÃO grava `verificado_em`/`last_validated`
 *     em lugar nenhum (lápides §5 07-01/07-09). Re-deriva do estado do repo a cada corrida —
 *     sem watermark que o próprio agente escreve.
 *   • NÃO bloqueia (report-only, exit 0 SEMPRE — não existe `--check` que morde aqui): a
 *     tarefa proíbe virar gate. Frescor de registro de rejeição é insumo de revisão, não portão.
 *   • NÃO duplica staleness consolidado: `briefing-code-staleness` mede BRIEFING↔código por
 *     mtime; este resolve ÂNCORAS CITADAS do §5 (corpus e sinal diferentes) — extensão do
 *     tema "conhecimento derivado apodrece" pra um corpus novo, não um 3º motor de mtime.
 *
 * Determinístico (sem Date.now/Math.random): `--sample N [--seed K]` seleciona por passada
 * fixa (offset K, espaçamento uniforme), nunca aleatório. Node puro (fs). Sem deps.
 *
 * USO (raiz do repo):
 *   node scripts/governance/lapide-recheck.mjs            # relatório advisory (exit 0)
 *   node scripts/governance/lapide-recheck.mjs --json     # JSON determinístico
 *   node scripts/governance/lapide-recheck.mjs --sample 8 [--seed 0]   # amostra determinística
 *   node scripts/governance/lapide-recheck.mjs --selftest
 */
import { readFileSync, existsSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const REPO_ROOT = (() => {
  // raiz = dois níveis acima de scripts/governance/. Override por env pro teste.
  if (process.env.OIMPRESSO_REPO_ROOT) return process.env.OIMPRESSO_REPO_ROOT;
  return dirname(dirname(dirname(fileURLToPath(import.meta.url))));
})();
const PROIBICOES_DEFAULT = join(REPO_ROOT, 'memory', 'proibicoes.md');

// ── parsing do §5 (mantém o CORPO — diferente do parseTombstones do hook, que descarta) ──
/** região §5 = de "## Ideias avaliadas e DESCARTADAS" até o próximo "## " (não-###). */
export function parseTombstones(text) {
  const lines = String(text || '').split('\n');
  let start = -1;
  for (let i = 0; i < lines.length; i++) {
    if (/^##\s+Ideias avaliadas e DESCARTADAS/i.test(lines[i])) { start = i + 1; break; }
  }
  if (start === -1) return [];
  let end = lines.length;
  for (let i = start; i < lines.length; i++) {
    if (/^##\s+/.test(lines[i]) && !/^###/.test(lines[i])) { end = i; break; }
  }
  const tombs = [];
  let cur = null;
  const flush = () => { if (cur) tombs.push(cur); };
  for (let i = start; i < end; i++) {
    const h = /^###\s+(\d{4})-(\d{2})-(\d{2})\s*[—–-]?\s*(.*)$/.exec(lines[i]);
    if (h) { flush(); cur = { date: `${h[1]}-${h[2]}-${h[3]}`, mmdd: `${h[2]}-${h[3]}`, title: h[4].trim(), body: '' }; continue; }
    if (cur) cur.body += lines[i] + '\n';
  }
  flush();
  return tombs;
}

// ── extração de ÂNCORAS DE ARQUIVO concretas (backtick + markdown-link) ─────────
const KNOWN_EXT = /\.(mjs|cjs|mts|js|ts|tsx|jsx|php|json|ya?ml|md|css|scss|sh|blade\.php)$/i;
/** limpa um candidato: tira `:linha`, `?v=`, pontuação/fecha-parêntese/aspas, âncora #. */
function cleanRef(raw) {
  let s = String(raw).trim();
  s = s.replace(/[)\]`'".,;]+$/g, '');    // pontuação/fecho no fim
  s = s.replace(/#.*$/, '');               // âncora markdown (#secao)
  s = s.replace(/\?.*$/, '');              // query (?v=)
  s = s.replace(/:\d+(?:-\d+)?$/, '');     // :linha ou :linha-linha
  // `@memory/x.md` é sintaxe de IMPORT do CLAUDE.md, não parte do caminho. Sem tirar o
  // '@' o arquivo existe e o check grita "âncora sumida" (FP medido 2026-07-29).
  s = s.replace(/^@/, '');
  return s.trim();
}
/** Paths que NUNCA foram do repo — citá-los é referência, não âncora. Não resolver um
 *  desses não é sinal de drift: o alvo nunca esteve versionado aqui.
 *    · absoluto (`/tmp/wf-baseline.js`, `C:\...`) — artefato de sessão citado na narrativa
 *    · dependência (`vendor/`, `node_modules/`) — código de terceiro
 *  Medido 2026-07-29: 2 dos 4 "REVISAR" do repo vivo eram exatamente isto. */
const EXTERNO = /^(?:\/|[a-zA-Z]:[\\/])|(?:^|\/)(?:vendor|node_modules)\//;
/** um candidato é um PATH de arquivo real (não URL, não template, com extensão conhecida). */
function looksLikePath(s) {
  if (!s || /^https?:\/\//i.test(s) || /^mailto:/i.test(s)) return false;
  if (/[<>{}*\s…]/.test(s)) return false;   // template/placeholder (<Mod>, {id}, *, …) — não é arquivo real
  if (!s.includes('/')) return false;        // sem separador → não é path de repo
  if (EXTERNO.test(s)) return false;         // nunca foi do repo → não é âncora
  return KNOWN_EXT.test(s);
}
/** extrai as âncoras de arquivo únicas de um corpo de lápide. */
export function extractPaths(body) {
  const found = new Set();
  const t = String(body || '');
  // markdown links [txt](path)
  for (const m of t.matchAll(/\]\(([^)]+)\)/g)) { const c = cleanRef(m[1]); if (looksLikePath(c)) found.add(c); }
  // trechos em backtick `...`
  for (const m of t.matchAll(/`([^`]+)`/g)) {
    // um backtick pode conter várias coisas; pega o 1º token que pareça path
    for (const tok of m[1].split(/[\s,]+/)) { const c = cleanRef(tok); if (looksLikePath(c)) found.add(c); }
  }
  return [...found].sort();
}

/** resolve um ref por existência-de-arquivo nas DUAS bases (raiz do repo; e memory/ = dir de
 *  proibicoes.md, base dos markdown-links). Tira `../` líder (link com profundidade errada
 *  aponta pro alvo certo). Resolvido = existe sob QUALQUER caminho. Baixo falso-positivo. */
export function resolveRef(ref, { root, linkBase }) {
  const stripped = ref.replace(/^(?:\.\.?\/)+/, ''); // tira ./ e ../ líderes
  const cands = [resolve(root, stripped), resolve(linkBase, ref), resolve(root, ref)];
  return cands.some((p) => { try { return existsSync(p); } catch { return false; } });
}

/** índice de arquivos trackeados (git ls-files) — determinístico, ordem estável. null se git falhar. */
function gitFileIndex(root) {
  try {
    const out = execSync('git ls-files', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    return out.split('\n').map((s) => s.trim().replace(/\\/g, '/')).filter(Boolean);
  } catch { return null; }
}

/** resolver ROBUSTO pra o repo vivo. Combina 3 sinais, cada um matando uma classe de
 *  falso-positivo que o §5 condena (lápide 06-30 "guard sintático que barra o legítimo"):
 *    1. existência-de-arquivo (resolveRef) — pega full-path + markdown-link com `../` errado.
 *    2. suffix-match no git ls-files — pega SHORTHAND de backtick (`Sells/Index.casos.md`
 *       cujo caminho real é `resources/js/Pages/Sells/Index.casos.md`).
 *    3. ADR por NÚMERO — link com slug driftado (`decisions/0275-slug-velho.md`) resolve se
 *       QUALQUER `memory/decisions/0275-*.md` existe (ADR é endereçado por número; slug muda,
 *       e slug-drift NÃO é premissa-drift — ADRs são append-only, superseded continua no disco). */
export function makeResolverFromIndex({ root, linkBase, files }) {
  const byBase = new Map();
  const adrNums = new Set();
  if (files) for (const f of files) {
    const base = f.slice(f.lastIndexOf('/') + 1);
    (byBase.get(base) || byBase.set(base, []).get(base)).push(f);
    const m = /(?:^|\/)memory\/decisions\/(\d{4})-.*\.md$/.exec(f);
    if (m) adrNums.add(m[1]);
  }
  return (ref) => {
    if (resolveRef(ref, { root, linkBase })) return true;
    const stripped = ref.replace(/^(?:\.\.?\/)+/, '');
    if (files) {
      const base = stripped.slice(stripped.lastIndexOf('/') + 1);
      for (const f of (byBase.get(base) || [])) if (f === stripped || f.endsWith('/' + stripped)) return true;
    }
    const adr = /(?:^|\/)decisions\/(\d{4})-.*\.md$/.exec(stripped);
    if (adr && adrNums.has(adr[1])) return true;
    return false;
  };
}
export function makeRepoResolver({ root, linkBase }) {
  return makeResolverFromIndex({ root, linkBase, files: gitFileIndex(root) });
}

const CLAIMS_DEFESA = /agora\s+é\s+m[áa]quina|defesa\s+mec[âa]nica|vir(?:ou|a)\s+m[áa]quina|é\s+m[áa]quina[:,]|agora\s+é\s+lei/i;

/** classifica UMA lápide. Puro (recebe um resolver injetável pro teste). */
export function classifyTombstone(t, resolver) {
  const refs = extractPaths(t.body);
  const faltando = refs.filter((r) => !resolver(r));
  const reivindica = CLAIMS_DEFESA.test(t.body);
  // sem âncora de arquivo citada → não há sinal mecânico (não classifica como drift).
  //
  // ⚠️ REGRA MEDIDA (2026-07-29): path que não resolve só vira CHAMADO PRA REVISÃO quando a
  // lápide REIVINDICA defesa mecânica. Sem a reivindicação, a ausência frequentemente É o
  // estado desejado — a lápide de 07-23, por exemplo, registra `git revert` de um doc, e o
  // arquivo sumido é justamente o desfecho que ela celebra. Tratar isso como "a premissa
  // pode ter mudado" produziu 4 de 4 falso-positivo no repo vivo, e alarme 100% ruído é
  // alarme que se aprende a ignorar (mesma doença do gate-de-teatro).
  const veredito = refs.length === 0 ? 'sem-ancora-de-arquivo'
    : faltando.length === 0 ? 'ancoras-intactas'
    : reivindica ? 'revisar-drift-de-ancora'
    : 'citacao-nao-resolvida';
  return { date: t.date, mmdd: t.mmdd, title: t.title, ancoras: refs, ancoras_faltando: faltando, reivindica_defesa_mecanica: reivindica, veredito };
}

/** seleção DETERMINÍSTICA de amostra: ordena por data asc, pega N espaçados a partir do
 *  offset (seed % total). Sem aleatoriedade — mesma (N, seed) → mesma amostra. */
export function sampleDeterministic(items, n, seed = 0) {
  if (!n || n >= items.length) return items;
  const sorted = [...items].sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
  const total = sorted.length;
  const start = ((seed % total) + total) % total;
  const stride = Math.max(1, Math.floor(total / n));
  const out = [];
  for (let i = 0; i < n; i++) out.push(sorted[(start + i * stride) % total]);
  // dedup preservando ordem (stride pode colidir quando n≈total)
  const seen = new Set();
  return out.filter((x) => (seen.has(x.date + x.title) ? false : seen.add(x.date + x.title)));
}

/** roda o re-check. `resolver` injetável (teste); default = resolver robusto do repo vivo
 *  (git ls-files + suffix + ADR-por-número). Sem I/O de arquivo de saída. */
// ── MÉTRICA DE CORPUS (F5 da grade 2026-08-11 — o passo de ESCRITA) ────────────
// POR QUE EXISTE: o §5 protege, e ninguém mede o que ele CUSTA. Medido em 2026-08-11:
// ~106k tokens carregados em TODA sessão (CLAUDE.md importa proibicoes.md inteiro),
// 475 palavras por lápide, ~1,6 lápide/dia. Sem número, "a memória que protege vai
// começar a sufocar" é palpite; com número é acompanhável.
//
// O QUE ISTO NÃO É (as 3 lápides que ele poderia virar, e não vira):
//   • NÃO é nota agregada. A lápide C9 (2026-07-17) proíbe colapsar vereditos
//     incomensuráveis num índice — aqui saem NÚMEROS SEPARADOS, nunca um score.
//   • NÃO é presence-gate. Reporta CONTAGEM de partes ausentes pra leitura humana;
//     não reprova nada, não entra em required, o script segue exit 0 SEMPRE
//     (lápides §5 07-01/07-09/07-16 mataram "seção presente = ok" como GATE — medir
//     e publicar é outra coisa, e é o que a régua F5 pediu).
//   • NÃO declara teto. `teto_declarado: null` é honesto: ninguém declarou orçamento
//     de contexto pro §5, e inventar um seria régua nascida de agente. Ato [W].
//
// EMENDA/META fora do denominador de conformidade — MEDIDO, não presumido: as 8
// entradas cujo título começa com "EMENDA"/"Claims de superioridade" emendam uma
// lápide existente e por construção não repetem as 3 partes (7 das 8 não têm "O que
// foi tentado"). Contá-las como não-conformes seria falso-positivo — a doença dos 5
// guards sintáticos do §5. Entre as 96 NORMAIS, "O limite" tem 100% de conformidade.
export function medirCorpus(tombs, { charsArquivo = 0 } = {}) {
  const EMENDA = /^EMENDA\b|EMENDA da l[áa]pide|^Claims de superioridade/i;
  const emendas = tombs.filter((t) => EMENDA.test(t.title));
  const normais = tombs.filter((t) => !EMENDA.test(t.title));
  const palavras = tombs.map((t) => String(t.body).trim().split(/\s+/).filter(Boolean).length);
  const ord = [...palavras].sort((a, b) => a - b);
  const soma = palavras.reduce((a, b) => a + b, 0);
  const maiorIdx = palavras.indexOf(Math.max(...palavras, 0));
  const charsCorpos = tombs.reduce((a, t) => a + String(t.body).length, 0);
  const PARTES = { o_que_foi_tentado: /O que foi tentado/i, por_que_caiu: /Por que caiu/i, o_limite: /O limite/i };
  const conformidade = {};
  for (const [k, re] of Object.entries(PARTES)) {
    conformidade[k] = { ausente_em_normais: normais.filter((t) => !re.test(t.body)).length, ausente_em_emendas: emendas.filter((t) => !re.test(t.body)).length };
  }
  const datas = tombs.map((t) => t.date).filter(Boolean).sort();
  let ritmo = null;
  if (datas.length >= 2) {
    const dias = Math.round((Date.parse(datas[datas.length - 1] + 'T00:00:00Z') - Date.parse(datas[0] + 'T00:00:00Z')) / 86400000);
    ritmo = { primeira: datas[0], ultima: datas[datas.length - 1], dias, por_dia: dias > 0 ? Number((tombs.length / dias).toFixed(2)) : null };
  }
  return {
    lapides: tombs.length, normais: normais.length, emendas_meta: emendas.length,
    palavras: { total: soma, media: palavras.length ? Math.round(soma / palavras.length) : 0, mediana: ord.length ? ord[Math.floor(ord.length / 2)] : 0, maior: palavras.length ? { palavras: palavras[maiorIdx], date: tombs[maiorIdx].date, title: tombs[maiorIdx].title } : null },
    tamanho: { chars_corpos: charsCorpos, chars_arquivo: charsArquivo, pct_do_arquivo: charsArquivo ? Number((100 * charsCorpos / charsArquivo).toFixed(1)) : null, teto_declarado: null },
    conformidade_partes: conformidade, ritmo,
  };
}

export function recheck(proibicoesText, { root, linkBase, sample = 0, seed = 0, resolver } = {}) {
  const todos = parseTombstones(proibicoesText);
  let tombs = todos;
  const totalTombs = tombs.length;
  if (sample) tombs = sampleDeterministic(tombs, sample, seed);
  // métrica SEMPRE sobre o corpus INTEIRO (`todos`), nunca sobre a amostra — senão
  // `--sample 5` reportaria "5 lápides, 2 sem parte" como se fosse o §5.
  const metricas = medirCorpus(todos, { charsArquivo: String(proibicoesText || '').length });
  const resolve1 = resolver || makeRepoResolver({ root, linkBase });
  const results = tombs.map((t) => classifyTombstone(t, resolve1));
  const revisar = results.filter((r) => r.veredito === 'revisar-drift-de-ancora');
  const intactas = results.filter((r) => r.veredito === 'ancoras-intactas');
  const semAncora = results.filter((r) => r.veredito === 'sem-ancora-de-arquivo');
  // ordena o surface: drift primeiro, defesa-mecânica-reivindicada no topo, data desc
  revisar.sort((a, b) => (a.reivindica_defesa_mecanica !== b.reivindica_defesa_mecanica)
    ? (a.reivindica_defesa_mecanica ? -1 : 1)
    : (a.date < b.date ? 1 : -1));
  const citacoes = results.filter((r) => r.veredito === 'citacao-nao-resolvida');
  return {
    total_lapides_secao5: totalTombs, avaliadas: results.length,
    revisar, intactas: intactas.length, sem_ancora: semAncora.length,
    citacao_nao_resolvida: citacoes.length, resultados: results, metricas,
  };
}

// ═══════════════════════════════════════════════════════════════════════════════
const toAscii = (s) => String(s).replace(/[^\x20-\x7E]/g, '.');
const isMain = (() => { try { return process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href; } catch { return false; } })();

if (isMain && !process.argv.includes('--selftest')) {
  const a = process.argv.slice(2);
  const val = (n, d) => { const i = a.indexOf(n); return i >= 0 && a[i + 1] !== undefined ? a[i + 1] : d; };
  const json = a.includes('--json');
  const sample = parseInt(val('--sample', '0'), 10) || 0;
  const seed = parseInt(val('--seed', '0'), 10) || 0;
  const path = val('--proibicoes', PROIBICOES_DEFAULT);
  if (!existsSync(path)) { console.log(`lapide-recheck: proibicoes.md não encontrado (${path}) — nada a re-checar.`); process.exit(0); }
  const text = readFileSync(path, 'utf8');
  const r = recheck(text, { root: REPO_ROOT, linkBase: dirname(path), sample, seed });

  if (json) { console.log(JSON.stringify(r, null, 2)); process.exit(0); }
  console.log('\n  LÁPIDE-RECHECK — frescor do registro de rejeição §5 (proibicoes.md)\n');
  console.log(`  §5 tem ${r.total_lapides_secao5} lápide(s)${sample ? ` · amostra determinística de ${r.avaliadas} (seed ${seed})` : ` · avaliadas todas`}`);
  console.log(`  âncoras intactas: ${r.intactas} · sem âncora de arquivo: ${r.sem_ancora} · citação não resolvida: ${r.citacao_nao_resolvida} · REVISAR (drift de âncora): ${r.revisar.length}\n`);
  {
    const m = r.metricas;
    const c = m.conformidade_partes;
    console.log('  CUSTO DO CORPUS (medida, não veredito — nenhuma nota agregada, nada bloqueia)');
    console.log(`    tamanho  : ${m.tamanho.chars_corpos.toLocaleString('pt-BR')} chars nos corpos = ${m.tamanho.pct_do_arquivo}% do proibicoes.md · teto declarado: ${m.tamanho.teto_declarado ?? 'NENHUM'}`);
    console.log(`    por lápide: média ${m.palavras.media} palavras · mediana ${m.palavras.mediana} · maior ${m.palavras.maior?.palavras} (${m.palavras.maior?.date})`);
    if (m.ritmo) console.log(`    ritmo    : ${m.lapides} lápides em ${m.ritmo.dias} dias = ${m.ritmo.por_dia}/dia (${m.ritmo.primeira} → ${m.ritmo.ultima})`);
    console.log(`    estrutura: ${m.normais} normais + ${m.emendas_meta} emenda/meta (emenda não repete as 3 partes — fora do denominador, medido)`);
    console.log(`               entre as normais, sem "O que foi tentado": ${c.o_que_foi_tentado.ausente_em_normais} · sem "Por que caiu": ${c.por_que_caiu.ausente_em_normais} · sem "O limite": ${c.o_limite.ausente_em_normais}`);
    console.log('    (o §5 é importado inteiro pelo CLAUDE.md → este custo entra em TODA sessão.)\n');
  }
  if (r.citacao_nao_resolvida > 0) {
    console.log(`  (as ${r.citacao_nao_resolvida} "citação não resolvida" NÃO são chamado: a lápide não reivindica`);
    console.log('   defesa mecânica, e nesses casos a ausência do arquivo costuma ser o desfecho que ela registra.)\n');
  }
  if (r.revisar.length === 0) {
    console.log('  🟢 nenhuma lápide com âncora driftada — as premissas ancoradas resolvem no repo vivo.');
  } else {
    console.log('  As lápides abaixo citam arquivo(s) que NÃO resolvem mais no repo — a premissa que as');
    console.log('  matou PODE ter mudado. Um humano deve RE-LER e decidir (nada é apagado — §5 é append-only):\n');
    for (const t of r.revisar) {
      const flag = t.reivindica_defesa_mecanica ? ' ⚠ REIVINDICA "defesa mecânica/agora é máquina"' : '';
      console.log(`  🔎 ${t.date} — ${toAscii(t.title).slice(0, 72)}${flag}`);
      for (const f of t.ancoras_faltando) console.log(`        âncora sumida: ${f}`);
    }
    console.log('\n  A detecção do drift é mecânica; o julgamento (a premissa ainda vale?) é HUMANO.');
    console.log('  Se a lápide segue válida → deixa como está. Se a premissa mudou → NOVA lápide/emenda (ADR),');
    console.log('  nunca editar/apagar a antiga (§5 append-only Tier 0).');
  }
  console.log('');
  process.exit(0);
}

if (isMain && process.argv.includes('--selftest')) {
  const test = fileURLToPath(new URL('./lapide-recheck.test.mjs', import.meta.url));
  const { spawnSync } = await import('node:child_process');
  const res = spawnSync(process.execPath, [test], { stdio: 'inherit' });
  process.exit(res.status ?? 1);
}
