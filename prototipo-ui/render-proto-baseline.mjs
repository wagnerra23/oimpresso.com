#!/usr/bin/env node
// render-proto-baseline.mjs — o DESIGN vira BASELINE versionado (roubo #7 da pesquisa de mercado,
// régua Applitools Eyes 2026): o frame de design é a baseline; o build vivo é comparado contra a
// INTENÇÃO, não só contra o próprio passado (o visual-regression required compara prod×baseline-
// própria — pega REGRESSÃO; este compara prod×PROTÓTIPO — pega DRIFT DE INTENÇÃO).
//
// O que mecaniza (antes era manual — sessão 2026-07-08 17:05 rendou o proto na mão em :8799):
// dado <Mod/Tela>, (1) resolve a âncora via ancora.mjs (NUNCA no olho — incidente #7 2026-06-30),
// (2) serve o bundle Cowork local (shell oimpresso.com.html + app.jsx roteando por
// localStorage['oimpresso.route']), (3) captura o fingerprint do PROTO em matriz dual-theme ×
// viewports com a âncora ASSADA na captura (trava do --compare, ADR 0326), (4) salva
// memory/requisitos/<Mod>/<tela>.proto-baseline.json versionado, carimbado com o git-sha do
// protótipo (padrão do <tela>.map.json — sha muda = baseline STALE, regenerar).
//
// FRONTEIRA ADR 0290 (render-diff EM CI foi REJEITADO — passa verde quando os DOIS lados quebram):
//   · --gerar   = render LOCAL/dispatch logado, SÓ local. RECUSA sob CI (exit 4).
//   · --check   = HERMÉTICO (schema + âncora re-resolvida + freshness por sha) — é O QUE roda em
//                 CI (design-memory-gate, advisory). Zero browser, zero rede: só o JSON commitado.
//   · --extract = tira 1 célula do baseline como proto.json → o --compare EXISTENTE
//                 (style-fingerprint.mjs --compare proto.json prod.json --tela <Mod/Tela>) roda
//                 prod×proto-baseline com a trava fail-closed de sempre.
//
// DIREÇÃO NÃO É UNIFORME (PROD_A_FRENTE nunca regride): o compare REPORTA, humano DECIDE.
// O proto pode estar ATRÁS do prod (ex.: primário oklch 0.55 canon ADR 0190 × proto 0.7) —
// aplicar cego REGRIDE. Cada DIVERGE exige julgamento (prod-fora-do-canon × proto-atrasado ×
// ruído-de-dado). Ver RUNBOOK-fidelidade-fingerprint.md §Cobertura.
//
// USO:
//   node prototipo-ui/render-proto-baseline.mjs --gerar Financeiro/Unificado
//        [--staging <dir>] [--porta 8799] [--viewports 1280,1440] [--themes light,dark]
//        [--route <id>] [--out <path.json>]
//     · --gerar RECUSA (exit 3) se o id de rota não existir no roteador do app.jsx — rota
//       desconhecida ⇒ o shell renderiza o placeholder "Módulo legado" (ModuleStub) e o baseline
//       nasceria com o DOM ERRADO passando por íntegro (dogfood 2026-07-17). A recusa lista os
//       ids válidos. Atenção: `rotaDoAnchor` deriva a rota do NOME do arquivo (forja-page.jsx →
//       "forja") e isso NEM SEMPRE é o id real ("projects") — quando divergir, passe --route.
//   node prototipo-ui/render-proto-baseline.mjs --check [arquivo.json ...]   # sem args: varre tudo
//   node prototipo-ui/render-proto-baseline.mjs --extract <baseline.json> <1280|dark> [--out proto.json]
//   node prototipo-ui/render-proto-baseline.mjs --selftest                   # hermético, morde/libera
//
// Reusa (1 fato = 1 lugar): resolveAncora (ancora.mjs) · SNIPPET (style-fingerprint.mjs, a MESMA
// string do --snippet) · computeGitSha (gerar-map.mjs) · acharBundleRoot (importar-bundle.mjs) ·
// STAGING_DIR (protocolo.config.mjs) · chaveCelula (fingerprint-harness.mjs).

import { readFileSync, writeFileSync, existsSync, mkdirSync, readdirSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { join, resolve, dirname, extname, relative, sep } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

import { resolveAncora } from './ancora.mjs';
import { SNIPPET, rotulosDistintivos, overlapConteudo } from './style-fingerprint.mjs';
import { computeGitSha } from './gerar-map.mjs';
import { acharBundleRoot } from './importar-bundle.mjs';
import { STAGING_DIR, normalize, contentHash } from './protocolo.config.mjs';
import { chaveCelula } from './fingerprint-harness.mjs';

const HERE = dirname(fileURLToPath(import.meta.url)); // prototipo-ui/
const REPO = resolve(HERE, '..');
export const VERSION = 1;

// ── puras (testáveis herméticas) ────────────────────────────────────────────────

// o related_prototype do charter pode vir com prosa depois do path
// ("prototipo-ui/cowork/financeiro-page.jsx (design real; corrigido...)") — a âncora é o 1º token,
// igual a regex do style-fingerprint::resolverAncora faz.
export function primeiroToken(v) { return String(v || '').trim().split(/\s+/)[0]; }

// rota do shell derivada da âncora: app.jsx roteia por id (financeiro-page.jsx → 'financeiro').
// Heurística documentada; --route sobrepõe quando a tela não segue o padrão <id>-page.jsx.
export function rotaDoAnchor(ancora) {
  const base = String(ancora || '').split('/').pop() || '';
  const m = base.match(/^(.+?)-page\.jsx$/i);
  return m ? m[1] : null;
}

// normModulo — a chave de módulo é o MESMO fato escrito com convenções diferentes nas 2 árvores:
// `Pages/kb` × `memory/requisitos/KB`; `Pages/team-mcp` × `memory/requisitos/TeamMcp`. Comparar
// string-exata (o que o telasAfetadas fazia) deixa o nudge MORTO justo nesses módulos — provado
// 2026-07-17 com controle-negativo: `Pages/kb/Index.tsx` → "nenhum módulo com baseline", mesmo
// com o baseline commitado; `Pages/Sells` (case casa por sorte) disparava. Os 3 baselines antigos
// só funcionavam porque Sells/Compras/Financeiro batem exato. Normaliza: caixa + separadores.
export function normModulo(s) { return String(s || '').toLowerCase().replace(/[^a-z0-9]/g, ''); }

// resolveDirModulo — o dir CANÔNICO de memory/requisitos pro módulo (o que já existe manda: é a
// convenção viva). Sem match (ou sem árvore legível) usa o nome do Pages/ — dir novo, honesto.
export function resolveDirModulo(mod, repoRoot = REPO) {
  try {
    const base = join(repoRoot, 'memory', 'requisitos');
    const alvo = normModulo(mod);
    for (const d of readdirSync(base, { withFileTypes: true })) {
      if (d.isDirectory() && normModulo(d.name) === alvo) return d.name;
    }
  } catch { /* árvore ausente (fixture/selftest) → cai no nome do Pages/ */ }
  return mod;
}

// destino canônico do baseline — imita o naming do map.json (memory/requisitos/Financeiro/
// unificado.map.json): Mod = 1º segmento após Pages/, tela = dirs seguintes minus Index, lowercase.
// O DIR sai do resolveDirModulo (canônico > derivado) pra não criar `team-mcp/` ao lado de `TeamMcp/`.
export function destinoBaseline(telaViva, repoRoot = REPO) {
  const norm = String(telaViva || '').replace(/\\/g, '/');
  const m = norm.match(/resources\/js\/Pages\/([^/]+)\/(.+)\.tsx$/);
  if (!m) return null;
  const mod = m[1];
  const partes = m[2].split('/').filter((p) => p.toLowerCase() !== 'index');
  const slug = (partes.length ? partes : [mod]).join('-').toLowerCase();
  return join(repoRoot, 'memory', 'requisitos', resolveDirModulo(mod, repoRoot), `${slug}.proto-baseline.json`);
}

export function montarBaseline({ tela, charter, ancora, prototipo_sha, shell, celulas }) {
  return {
    _doc: 'PROTO-BASELINE — fingerprint do PROTÓTIPO renderizado (a INTENÇÃO de design), matriz viewport×tema. Gerado LOCALMENTE por prototipo-ui/render-proto-baseline.mjs --gerar (render em CI = REJEITADO, ADR 0290); o CI só verifica este JSON commitado (--check hermético). prototipo_sha invalida quando o protótipo re-exportar (padrão do map.json). Compare prod×este via --extract + style-fingerprint --compare --tela (trava de âncora ADR 0326). Direção NÃO é uniforme: o compare reporta, humano decide (PROD_A_FRENTE nunca regride).',
    version: VERSION,
    tela,
    charter,
    ancora,
    prototipo_sha,
    shell,
    gerado_em: new Date().toISOString(),
    celulas,
  };
}

// verificação HERMÉTICA (o --check do CI): recebe os fatos já resolvidos (ancoraAtual/shaAtual)
// pra ser testável sem git/charter — a CLI resolve os fatos reais e injeta aqui.
export function verificarBaseline(b, { ancoraAtual = null, shaAtual = null } = {}) {
  const drift = [];
  const warn = [];
  for (const campo of ['version', 'tela', 'ancora', 'prototipo_sha', 'celulas']) {
    if (b?.[campo] === undefined || b?.[campo] === null) drift.push(`schema: campo obrigatório ausente: ${campo}`);
  }
  if (drift.length) return { ok: false, drift, warn };
  const cells = Object.keys(b.celulas || {});
  if (!cells.length) drift.push('celulas vazio — baseline sem nenhuma captura (render falhou?)');
  for (const cell of cells) {
    if (!/^\d{3,4}\|[a-z]+$/.test(cell)) drift.push(`célula com chave inválida (esperado "<viewport>|<tema>"): ${cell}`);
    const fp = b.celulas[cell];
    if (!fp || !Array.isArray(fp.elementos)) { drift.push(`célula ${cell}: fingerprint sem elementos[]`); continue; }
    if (!fp.elementos.length) drift.push(`célula ${cell}: 0 elementos capturados — render provavelmente quebrou (não commite baseline vazio)`);
    if (fp.ancora !== b.ancora) drift.push(`célula ${cell}: âncora da captura ("${fp.ancora}") ≠ âncora do baseline ("${b.ancora}") — captura não foi assada por esta máquina?`);
    // Tier 0 (proibicoes.md): valor BRL não pousa em memory/ nem como mock — a máquina redige
    // (redigirSensiveis) antes de gravar; baseline com R$ = gerado por fora/versão velha → drift.
    const textos = [...fp.elementos, ...(fp.compostos || [])];
    const comBRL = textos.some((e) => /R\$\s?\d/.test(e.texto || ''));
    if (comBRL) drift.push(`célula ${cell}: texto com valor BRL (R$ …) — Tier 0 proíbe em memory/; regenere com --gerar (redige pra <BRL>)`);
    // LGPD (pii-scan.sh, mesma regex): CPF/CNPJ literal — mesmo mock — bloqueia PR no CI.
    const comPII = textos.some((e) => /\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{3}\.\d{3}\.\d{3}-\d{2}/.test(e.texto || ''));
    if (comPII) drift.push(`célula ${cell}: texto com CPF/CNPJ literal — pii-scan bloqueia (LGPD); regenere com --gerar (redige pra <CPF>/<CNPJ>)`);
  }
  if (ancoraAtual != null && ancoraAtual !== b.ancora) {
    drift.push(`ÂNCORA DIVERGENTE: baseline carimba "${b.ancora}", mas o charter de "${b.tela}" resolve pra "${ancoraAtual}" — regenerar (--gerar) contra a âncora atual`);
  }
  if (shaAtual != null && b.prototipo_sha !== 'sem-historico') {
    if (shaAtual === 'sem-historico') warn.push('staleness indeterminada: protótipo sem histórico git rastreável agora');
    else if (shaAtual !== b.prototipo_sha) drift.push(`STALE: prototipo_sha salvo='${b.prototipo_sha}' · atual='${shaAtual}' — o protótipo re-exportou; regenerar via --gerar`);
  }
  return { ok: drift.length === 0, drift, warn };
}

// ── ROTA VÁLIDA: a trava do --gerar (2026-07-17) ───────────────────────────────
// O BUG: quando a rota não existe no shell, o app.jsx cai no `ModuleStub` (a página-placeholder
// "Módulo legado": Roadmap MWART / "Ver no Blade atual") — e o baseline nascia com o DOM ERRADO,
// carimbado âncora ✓ / sha ✓. O --check hermético chamava de ÍNTEGRO porque nenhuma pergunta dele
// (schema · âncora-STRING · sha) é sobre PROCEDÊNCIA do conteúdo: a âncora podre entrava pela porta
// que a ADR 0326 fechou só na saída. Pego no dogfood team-mcp/Forja/Cockpit: `rotaDoAnchor` deriva
// a rota do NOME do arquivo (forja-page.jsx → "forja"), mas o id real no roteador é "projects".
//
// POR QUE AQUI E NÃO NO OVERLAP DE CONTEÚDO (F5): a 1ª tentativa foi reusar `overlapConteudo` do
// --compare (os rótulos da âncora têm que aparecer no DOM). MEDIDO em 7 telas reais: kb 52% ·
// Jana 51% · Concil 30% · Impostos 27% · DRE 18% · **Fluxo 0%** · **Forja(rota certa) 7%** →
// 2 de 7 REPROVAM sendo LEGÍTIMAS = 29% de falso-positivo. A causa é estrutural e o próprio
// style-fingerprint já a confessa ("rótulos vindos de dados (outro arquivo) não contam"): tela
// chart-only (Fluxo = saldo/entrada/saída + projeção BRL) e tela data-driven (Forja = FORJA-141/
// ADR0253 vindos de mock) pontuam baixo POR ESTAREM CERTAS. Guard que bloqueia o legítimo é a
// lápide do `@scope` (proibicoes §5, 2026-07-09) se repetindo. Então o overlap vira AVISO.
//
// A trava real é um FATO, não heurística: o id de rota está no roteador do app.jsx? Rota inválida
// ⇒ ModuleStub garantido ⇒ recusa ANTES de renderizar (barato) e diz os ids válidos. Fail-OPEN se
// o parse não reconhecer o roteador (<5 ids): o que não dá pra medir não reprova.
const RE_ROTA_IF = /route\s*===\s*["']([\w-]+)["']/g;              // if (route === "kb")
const RE_ROTA_LISTA = /\[([^\]]*?)\]\s*\.\s*includes\(\s*route\s*\)/gs; // ["crm","inbox",…].includes(route)
export function rotasValidas(appJsxSrc) {
  const ids = new Set();
  for (const m of String(appJsxSrc || '').matchAll(RE_ROTA_IF)) ids.add(m[1]);
  for (const m of String(appJsxSrc || '').matchAll(RE_ROTA_LISTA)) {
    for (const s of m[1].matchAll(/["']([\w-]+)["']/g)) ids.add(s[1]);
  }
  return ids;
}
export function verificarRota(route, appJsxSrc) {
  const ids = rotasValidas(appJsxSrc);
  if (ids.size < 5) return { ok: true, motivo: `rota NÃO checada (roteador do app.jsx não reconhecido: ${ids.size} ids) — fail-open` };
  if (!ids.has(route)) {
    const perto = [...ids].filter((i) => i.includes(route) || route.includes(i)).slice(0, 4);
    return { ok: false, motivo: `rota "${route}" NÃO existe no roteador do app.jsx → o shell cairia no placeholder "Módulo legado" (ModuleStub) e o baseline nasceria com o DOM ERRADO.${perto.length ? ` Próximas: ${perto.join(' · ')}.` : ''} ids válidos (${ids.size}): ${[...ids].sort().join(' · ')}` };
  }
  return { ok: true, motivo: `rota ✓ "${route}" existe no roteador do app.jsx` };
}

// AVISO (não trava): o DOM capturado tem a copy da âncora? Sinal útil, mas NÃO decide — ver o
// bloco acima (29% de FP em telas legítimas chart-only/data-driven). Reusa a F5 do --compare.
export function verificarConteudo(srcAncora, celulas) {
  if (srcAncora == null) return { ok: true, motivo: 'conteúdo NÃO checado (âncora ilegível) — fail-open, igual ao --compare', achados: 0, total: 0 };
  const textos = [];
  for (const fp of Object.values(celulas || {})) {
    for (const e of fp?.elementos || []) textos.push(e.texto);
    for (const k of fp?.compostos || []) textos.push(k.texto);
  }
  return overlapConteudo(rotulosDistintivos(srcAncora), textos);
}

// extrai 1 célula como fingerprint standalone — o formato que o style-fingerprint --compare
// consome como proto.json (com a âncora assada, a trava passa).
export function extrairCelula(b, cell) {
  const fp = b?.celulas?.[cell];
  if (!fp) {
    const disp = Object.keys(b?.celulas || {}).join(', ') || '—';
    throw new Error(`célula "${cell}" não existe no baseline (disponíveis: ${disp})`);
  }
  return { ...fp, ancora: fp.ancora || b.ancora };
}

// Tier 0 (proibicoes.md "NUNCA commitar valores BRL em memory/" + LGPD/pii-scan "CPF/CNPJ
// literal bloqueia PR"): o texto capturado do proto carrega mock "R$ 1.234,56" e CNPJ/CPF
// fictício (formato NN.NNN.NNN/NNNN-NN) — mesmo sendo mock, as regras são cegas
// (pii-scan pegou os 8 CNPJ mock do compras-page no PR #4042). Redige ANTES de gravar.
// SEGURO pro matching: chave()/chaveComposto() aplicam normTexto nos DOIS lados (que já troca
// R$ por <BRL> — idempotente), diffElemento não compara `texto`, e um CNPJ/CPF mock NUNCA
// bateria com o dado real da prod por texto de qualquer jeito. F5/overlap usa rótulos de UI,
// não valores/documentos. Perda: zero; risco Tier 0/LGPD: zero.
// Regex CPF/CNPJ = as MESMAS do .github/scripts/pii-scan.sh (a régua que morde no CI).
const REDACOES = [
  [/R\$\s?[\d.,]+/g, '<BRL>'],
  [/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/g, '<CNPJ>'],
  [/\d{3}\.\d{3}\.\d{3}-\d{2}/g, '<CPF>'],
];
export function redigirSensiveis(celulas) {
  const limpa = (s) => REDACOES.reduce((acc, [rx, sub]) => acc.replace(rx, sub), String(s || ''));
  const out = {};
  for (const [cell, fp] of Object.entries(celulas || {})) {
    out[cell] = {
      ...fp,
      elementos: (fp.elementos || []).map((e) => ({ ...e, texto: limpa(e.texto) })),
      compostos: (fp.compostos || []).map((c) => ({ ...c, texto: limpa(c.texto) })),
    };
  }
  return out;
}

// guard da fronteira ADR 0290: render NUNCA em CI (env injetável pra teste).
export function renderPermitido(env = process.env) {
  return !(env.CI || env.GITHUB_ACTIONS);
}

// ── nudge (o COMPARE possível em CI): PR tocou Pages/ de módulo COM baseline? ──
// O compare real proto×prod exige render dos DOIS lados = LOCAL por lei (ADR 0290). O que o CI
// mecaniza sem render: (a) o --check hermético e (b) ESTE mapeamento — arquivos da PR → baselines
// commitados do MESMO módulo (inclui _components/: mexer neles re-renderiza a tela) → aponta o
// comando local exato. Pura (files×baselines → afetados), testável hermética.
export function telasAfetadas(files, baselines) {
  const mods = new Set();
  for (const f of files || []) {
    const m = String(f).replace(/\\/g, '/').match(/^resources\/js\/Pages\/([^/]+)\//);
    if (m) mods.add(m[1]);
  }
  // normalizado nos 2 lados (Pages/kb × requisitos/KB · Pages/team-mcp × requisitos/TeamMcp) —
  // string-exata deixava o nudge morto justo nesses módulos (ver normModulo).
  const modsN = new Set([...mods].map(normModulo));
  return (baselines || []).filter((b) => {
    const m = String(b).replace(/\\/g, '/').match(/(?:^|\/)memory\/requisitos\/([^/]+)\//);
    return m && modsN.has(normModulo(m[1]));
  });
}

// FURO fechado fail-closed: o sha é carimbado do proto no REPO (SSOT prototipo-ui/cowork/),
// mas o render serve o STAGING (~/Downloads) — se o staging driftou do espelho, a captura seria
// de OUTRA versão que o sha declara (baseline mentiroso). Identidade normalizada (ADR 0324:
// contentHash sobre normalize — mesma régua do cowork-mirror-freshness).
export function conferirIdentidadeProto(srcRepo, srcStaging) {
  if (srcStaging == null) return { ok: false, motivo: 'âncora NÃO existe no bundle staging — bundle incompleto/velho? Rode a Fase −1 (DesignSync pull / importar-bundle).' };
  if (srcRepo == null) return { ok: false, motivo: 'âncora NÃO existe no SSOT do repo (prototipo-ui/cowork/) — sync do espelho pendente (importar-bundle --sync-cowork).' };
  if (contentHash(normalize(srcRepo)) !== contentHash(normalize(srcStaging))) {
    return { ok: false, motivo: 'staging ≠ SSOT do repo pra esta âncora — o render capturaria uma versão diferente da que o prototipo_sha declara. Sincronize (Fase −1) antes de gerar; override logado: --permitir-drift-staging <razão>.' };
  }
  return { ok: true, motivo: 'identidade ✓ staging == SSOT do repo (hash normalizado)' };
}

// ── resolver âncora (fatos reais, usados pela CLI) ─────────────────────────────
async function resolverFatos(tela) {
  const r = await resolveAncora(tela, { repoRoot: REPO });
  if (!r.ok) throw new Error(`ancora.mjs: ${r.motivo}`);
  const anc = r.ancoras.find((a) => a.tipo.startsWith('related_prototype'));
  if (!anc) throw new Error(`charter de "${tela}" sem related_prototype — registre o protótipo antes de gerar baseline`);
  const ancora = primeiroToken(anc.valor);
  return { ancora, charter: r.charter, telaViva: r.telaViva };
}

// ── servidor estático mínimo (serve a raiz do bundle; MIME básico) ─────────────
const MIME = {
  '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
  '.jsx': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8', '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp',
  '.woff': 'font/woff', '.woff2': 'font/woff2', '.ico': 'image/x-icon',
};
function servirEstatico(root, porta) {
  const srv = createServer((req, res) => {
    try {
      const urlPath = decodeURIComponent(String(req.url || '/').split('?')[0]);
      let alvo = resolve(root, '.' + urlPath.replace(/\/+$/, '') || '.');
      if (!alvo.startsWith(resolve(root))) { res.writeHead(403); res.end(); return; }
      if (existsSync(alvo) && statSync(alvo).isDirectory()) alvo = join(alvo, 'oimpresso.com.html');
      if (!existsSync(alvo)) { res.writeHead(404); res.end('404'); return; }
      res.writeHead(200, { 'Content-Type': MIME[extname(alvo).toLowerCase()] || 'application/octet-stream' });
      res.end(readFileSync(alvo));
    } catch (e) { res.writeHead(500); res.end(String(e.message)); }
  });
  return new Promise((ok, err) => { srv.on('error', err); srv.listen(porta, '127.0.0.1', () => ok(srv)); });
}

// ── captura da matriz no PROTO servido (Playwright — espelha fingerprint-harness) ──
async function capturarProto({ url, route, ancora, viewports, temas }) {
  const { chromium } = await import('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const celulas = {};
  try {
    const context = await browser.newContext();
    // a rota do shell é lida de localStorage NO BOOT do app.jsx — tem que estar lá ANTES.
    await context.addInitScript((r) => { try { localStorage.setItem('oimpresso.route', r); } catch {} }, route);
    const page = await context.newPage();
    for (const vp of viewports) {
      await page.setViewportSize({ width: vp, height: 900 });
      for (const tema of temas) {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
        // tema direto no <html> — tokens.css chaveia por [data-theme]; o effect do app só re-roda
        // em tweak-change, então o atributo setado pós-render fica (padrão validado do harness).
        await page.evaluate((t) => { document.documentElement.setAttribute('data-theme', t); }, tema);
        await page.waitForTimeout(500); // restyle reativo assenta
        // assa a âncora ANTES do SNIPPET (mesma mecânica do --snippet <tela>): a captura DECLARA
        // contra o que é comparável — sem isso o --compare recusa (fail-closed, ADR 0326).
        await page.evaluate((a) => { window.__ANCORA__ = a; }, ancora);
        const json = await page.evaluate(SNIPPET.trim());
        celulas[chaveCelula(vp, tema)] = JSON.parse(json);
        console.error(`  ✓ célula ${chaveCelula(vp, tema)} capturada (${celulas[chaveCelula(vp, tema)].elementos.length} elementos)`);
      }
    }
    await context.close();
  } finally { await browser.close(); }
  return celulas;
}

// ── modos CLI ───────────────────────────────────────────────────────────────────
async function cmdGerar(args) {
  if (!renderPermitido()) {
    console.error('⛔ render do protótipo em CI é REJEITADO (ADR 0290 — render pareado passa verde quando os dois lados quebram). --gerar roda LOCAL/dispatch logado; o CI só compara/verifica JSONs commitados (--check).');
    process.exit(4);
  }
  const tela = args._[0];
  if (!tela) { console.error('uso: --gerar <Mod/Tela> [--staging <dir>] [--porta 8799] [--viewports 1280,1440] [--themes light,dark] [--route <id>] [--out <path>]'); process.exit(2); }
  const { ancora, charter, telaViva } = await resolverFatos(tela);
  console.error(`# âncora (via ancora.mjs): ${ancora}`);
  const staging = args.staging || STAGING_DIR;
  if (!existsSync(staging)) {
    console.error(`⛔ staging do bundle Cowork ausente: ${staging}\n   O render precisa do shell completo (oimpresso.com.html + app.jsx + deps). Rode a Fase −1 (DesignSync pull / importar-bundle.mjs) antes — ver protocolo.config.mjs.`);
    process.exit(2);
  }
  const root = acharBundleRoot(staging);
  if (!existsSync(join(root, 'oimpresso.com.html'))) {
    console.error(`⛔ shell oimpresso.com.html não achado sob ${staging} (raiz detectada: ${root}) — bundle incompleto?`);
    process.exit(2);
  }
  // identidade staging×repo da âncora (fail-closed): o sha declara o proto do REPO; o render
  // serve o STAGING — driftou = baseline mentiroso. Procura o arquivo da âncora no bundle.
  const nomeAnc = ancora.split('/').pop();
  const candStaging = [join(root, nomeAnc), join(root, 'prototipo-ui', 'cowork', nomeAnc)].find((p) => existsSync(p));
  const pathRepo = resolve(REPO, ancora);
  const ident = conferirIdentidadeProto(
    existsSync(pathRepo) ? readFileSync(pathRepo, 'utf8') : null,
    candStaging ? readFileSync(candStaging, 'utf8') : null,
  );
  if (!ident.ok && !args['permitir-drift-staging']) { console.error(`⛔ ${ident.motivo}`); process.exit(2); }
  if (!ident.ok) console.error(`# ⚠ OVERRIDE drift staging — razão: ${args['permitir-drift-staging']} (logado; o baseline pode não corresponder ao prototipo_sha)`);
  else console.error(`# ${ident.motivo}`);

  const route = args.route || rotaDoAnchor(ancora);
  if (!route) { console.error(`⛔ não sei derivar a rota do shell pra âncora "${ancora}" (não segue <id>-page.jsx) — passe --route <id> (ids em app.jsx::MODULES)`); process.exit(2); }
  // TRAVA (2026-07-17): rota inexistente ⇒ ModuleStub ⇒ baseline com o DOM ERRADO passando por
  // íntegro. Checa contra o roteador REAL (fato, não heurística) ANTES de subir o browser.
  const appJsx = join(root, 'app.jsx');
  const vr = verificarRota(route, existsSync(appJsx) ? readFileSync(appJsx, 'utf8') : null);
  if (!vr.ok) { console.error(`⛔ ${vr.motivo}`); process.exit(3); }
  console.error(`# ${vr.motivo}`);
  const porta = parseInt(args.porta || '8799', 10);
  const viewports = String(args.viewports || '1280,1440').split(',').map((s) => parseInt(s.trim(), 10)).filter(Boolean);
  const temas = String(args.themes || 'light,dark').split(',').map((s) => s.trim()).filter(Boolean);

  const srv = await servirEstatico(root, porta);
  console.error(`# proto servido: http://127.0.0.1:${porta}/oimpresso.com.html (raiz ${root}) · rota "${route}" · ${viewports.join('/')}×${temas.join('/')}`);
  let celulas;
  try {
    celulas = await capturarProto({ url: `http://127.0.0.1:${porta}/oimpresso.com.html`, route, ancora, viewports, temas });
  } finally { srv.close(); }

  // AVISO de conteúdo (NÃO trava — 29% de FP medido em telas chart-only/data-driven; a trava é a
  // rota, acima). Serve de pista quando algo cheira errado: overlap alto = confiança extra.
  const srcAncora = existsSync(resolve(REPO, ancora)) ? readFileSync(resolve(REPO, ancora), 'utf8') : null;
  const vc = verificarConteudo(srcAncora, celulas);
  const motivoVc = String(vc.motivo).replace(/\s*Override:.*$/, ''); // a cauda oferece a flag do --compare
  console.error(vc.ok ? `# ${motivoVc}` : `# ⚠ ${motivoVc}\n#   (aviso, não trava: tela chart-only/data-driven pontua baixo por estar CERTA. A rota já foi validada contra o roteador.)`);

  const prototipo_sha = computeGitSha([ancora], REPO);
  celulas = redigirSensiveis(celulas); // Tier 0 + LGPD: zero R$ / CPF / CNPJ em memory/, mesmo mock
  const baseline = montarBaseline({ tela, charter, ancora, prototipo_sha, shell: relative(staging, root).replace(/\\/g, '/') || '.', celulas });
  const v = verificarBaseline(baseline, {}); // auto-verifica ANTES de gravar — baseline vazio não pousa
  if (!v.ok) { console.error('⛔ baseline recém-gerado NÃO passa no --check — não vou gravar:\n - ' + v.drift.join('\n - ')); process.exit(1); }

  const out = args.out ? resolve(args.out) : destinoBaseline(telaViva || '', REPO);
  if (!out) { console.error(`⛔ não sei derivar o destino (charter sem tela viva .tsx) — passe --out <path>`); process.exit(2); }
  mkdirSync(dirname(out), { recursive: true });
  writeFileSync(out, JSON.stringify(baseline, null, 1) + '\n', 'utf8'); // sem BOM (node utf8 puro)
  console.error(`✓ baseline gravado: ${relative(REPO, out).replace(/\\/g, '/')} · sha=${prototipo_sha} · ${Object.keys(celulas).length} células`);
  console.error(`  compare com a prod: node prototipo-ui/render-proto-baseline.mjs --extract "${relative(REPO, out).replace(/\\/g, '/')}" "1280|dark" --out proto.json`);
  console.error(`                      node prototipo-ui/style-fingerprint.mjs --compare proto.json prod.json --tela ${tela}`);
  console.error('  ⚠ direção NÃO é uniforme: o compare reporta, humano decide (PROD_A_FRENTE nunca regride).');
}

function acharBaselines(dir) {
  const out = [];
  if (!existsSync(dir)) return out;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) out.push(...acharBaselines(p));
    else if (e.name.endsWith('.proto-baseline.json')) out.push(p);
  }
  return out;
}

async function cmdCheck(args) {
  const files = args._.length ? args._.map((f) => resolve(f)) : acharBaselines(join(REPO, 'memory', 'requisitos'));
  if (!files.length) { console.log('✓ nenhum *.proto-baseline.json no repo — nada a verificar (0 baselines não é drift).'); process.exit(0); }
  let totalDrift = 0, totalWarn = 0;
  for (const f of files) {
    let b;
    const rel = relative(REPO, f).replace(/\\/g, '/');
    try { b = JSON.parse(readFileSync(f, 'utf8')); } catch (e) { console.error(`✗ ${rel}: JSON ilegível (${e.message})`); totalDrift++; continue; }
    let ancoraAtual = null;
    try { ancoraAtual = (await resolverFatos(b.tela)).ancora; } catch (e) { console.error(`✗ ${rel}: âncora não re-resolvível — ${e.message}`); totalDrift++; }
    const shaAtual = b.ancora ? computeGitSha([b.ancora], REPO) : null;
    const v = verificarBaseline(b, { ancoraAtual, shaAtual });
    for (const d of v.drift) { console.error(`✗ ${rel}: ${d}`); totalDrift++; }
    for (const w of v.warn) { console.error(`⚠ ${rel}: ${w}`); totalWarn++; }
    if (v.ok && ancoraAtual != null) console.log(`✓ ${rel} — íntegro (âncora ✓ · sha ✓ · ${Object.keys(b.celulas).length} células)`);
  }
  if (totalDrift) { console.error(`\n✗ ${totalDrift} drift(s) em ${files.length} baseline(s).`); process.exit(1); }
  console.log(`\n✓ ${files.length} baseline(s) íntegro(s)${totalWarn ? ` (${totalWarn} aviso(s))` : ''}.`);
  process.exit(0);
}

// advisory SEMPRE (exit 0): imprime o dever-de-casa local; quem decide é o humano no merge.
function cmdNudge(args) {
  const files = args._.length ? args._ : readFileSync(0, 'utf8').split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
  const baselines = acharBaselines(join(REPO, 'memory', 'requisitos')).map((p) => relative(REPO, p).replace(/\\/g, '/'));
  const afetados = telasAfetadas(files, baselines);
  if (!afetados.length) { console.log('✓ nudge: nenhum arquivo da PR toca módulo com proto-baseline — nada a apontar.'); return; }
  console.log('⚠ FIDELIDADE DE INTENÇÃO (advisory): a PR toca `Pages/` de módulo com proto-baseline commitado.');
  console.log('  O compare proto×prod NÃO roda em CI (render = local por lei, ADR 0290). Antes do merge, rode LOCAL:');
  for (const b of afetados) {
    let tela = '<Mod/Tela>';
    try { tela = JSON.parse(readFileSync(join(REPO, b), 'utf8')).tela || tela; } catch {}
    console.log(`\n  · **${tela}** — baseline \`${b}\``);
    console.log(`      node prototipo-ui/render-proto-baseline.mjs --extract "${b}" "1280|dark" --out proto.json`);
    console.log(`      node prototipo-ui/style-fingerprint.mjs --snippet ${tela}   # colar na tela viva (MESMO tema) → prod.json`);
    console.log(`      node prototipo-ui/style-fingerprint.mjs --compare proto.json prod.json --tela ${tela}`);
  }
  console.log('\n  ⚠ direção NÃO é uniforme: o compare REPORTA, humano DECIDE (PROD_A_FRENTE nunca regride).');
}

function cmdExtract(args) {
  const [file, cell] = args._;
  if (!file || !cell) { console.error('uso: --extract <baseline.json> <viewport|tema> [--out proto.json]'); process.exit(2); }
  let fp;
  try { fp = extrairCelula(JSON.parse(readFileSync(resolve(file), 'utf8')), cell); }
  catch (e) { console.error(`⛔ ${e.message}`); process.exit(1); }
  const out = args.out ? resolve(args.out) : null;
  if (out) {
    writeFileSync(out, JSON.stringify(fp, null, 1) + '\n', 'utf8');
    console.error(`✓ célula ${cell} → ${out} (âncora assada: ${fp.ancora}). Agora: node prototipo-ui/style-fingerprint.mjs --compare ${out} prod.json --tela <Mod/Tela>`);
  } else console.log(JSON.stringify(fp, null, 1));
}

// ── selftest hermético (morde E libera — L-31) ─────────────────────────────────
function selftest() {
  let fails = 0;
  const t = (label, cond) => { const ok = !!cond; if (!ok) fails++; console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label}`); };

  const ANC = 'prototipo-ui/cowork/financeiro-page.jsx';
  const fpOk = { tema: 'dark', ancora: ANC, elementos: [{ tag: 'button', texto: 'Salvar' }], divisorias: [], containers: [], compostos: [], sombras: [] };
  const bom = montarBaseline({ tela: 'Financeiro/Unificado', charter: 'x.charter.md', ancora: ANC, prototipo_sha: 'abc1234', shell: 'project', celulas: { '1280|dark': fpOk, '1440|light': { ...fpOk, tema: 'light' } } });

  // libera: baseline íntegro passa (schema + âncora + sha batendo)
  t('libera: baseline íntegro passa', verificarBaseline(bom, { ancoraAtual: ANC, shaAtual: 'abc1234' }).ok === true);
  // morde: sha atual mudou (protótipo re-exportou) = STALE
  const stale = verificarBaseline(bom, { ancoraAtual: ANC, shaAtual: 'def5678' });
  t('morde: sha mudou → STALE', stale.ok === false && stale.drift.some((d) => d.includes('STALE')));
  // morde: charter re-resolve pra âncora diferente
  const ancDiv = verificarBaseline(bom, { ancoraAtual: 'prototipo-ui/cowork/outra-page.jsx', shaAtual: 'abc1234' });
  t('morde: âncora do charter divergiu', ancDiv.ok === false && ancDiv.drift.some((d) => d.includes('ÂNCORA DIVERGENTE')));
  // morde: célula sem elementos (render quebrado não vira baseline)
  const vazio = montarBaseline({ tela: 't', charter: 'c', ancora: ANC, prototipo_sha: 'a', shell: '.', celulas: { '1280|dark': { ...fpOk, elementos: [] } } });
  t('morde: célula com 0 elementos (render quebrou)', verificarBaseline(vazio, {}).ok === false);
  // morde: célula com âncora não-assada / divergente da do baseline
  const naoAssada = montarBaseline({ tela: 't', charter: 'c', ancora: ANC, prototipo_sha: 'a', shell: '.', celulas: { '1280|dark': { ...fpOk, ancora: null } } });
  t('morde: captura sem âncora assada', verificarBaseline(naoAssada, {}).ok === false);
  // morde: schema (campo obrigatório ausente / celulas vazio)
  t('morde: sem celulas', verificarBaseline({ version: 1, tela: 't', ancora: ANC, prototipo_sha: 'a' }, {}).ok === false);
  t('morde: celulas vazio', verificarBaseline({ ...bom, celulas: {} }, {}).ok === false);
  // warn (não drift): staleness indeterminada
  const indet = verificarBaseline(bom, { ancoraAtual: ANC, shaAtual: 'sem-historico' });
  t('warn: sha atual sem-historico = aviso, não drift', indet.ok === true && indet.warn.length === 1);
  // sha salvo 'sem-historico' não trava (fixture sem git dedicado — mesmo contrato do map.json)
  const semHist = montarBaseline({ ...bom, prototipo_sha: 'sem-historico', tela: 't', charter: 'c', ancora: ANC, shell: '.', celulas: bom.celulas });
  t('libera: sha salvo sem-historico não é drift', verificarBaseline(semHist, { ancoraAtual: ANC, shaAtual: 'zzz' }).ok === true);

  // ── TRAVA DE ROTA (2026-07-17): rota fora do roteador ⇒ ModuleStub ⇒ baseline com DOM errado.
  // Fixture = o shape REAL do app.jsx (if-chain + lista .includes(route)) com os ids que o dogfood
  // usou. Morde a rota derivada-do-nome ("forja") E libera a real ("projects") — os 2 lados do bug.
  const appFake = `
    if (route === "chat") content = <JanaCockpit />;else
    if (route === "kb") content = <KBPage />;else
    if (route === "fin-fluxo") content = <FinanceiroPage initialTela="fluxo" />;else
    if (route === "projects" || route === "teammcp") content = <ForjaPage />;else
    if (["crm", "inbox", "vestuario"].includes(route)) content = <MockupPage route={route} />;else
    content = <ModuleStub routeId={route} />;`;
  const ids = rotasValidas(appFake);
  t('rotas: extrai o if-chain', ids.has('chat') && ids.has('kb') && ids.has('fin-fluxo') && ids.has('projects') && ids.has('teammcp'));
  t('rotas: extrai também a lista .includes(route)', ids.has('crm') && ids.has('inbox') && ids.has('vestuario'));
  t('rotas: NÃO inventa id que não está no roteador', !ids.has('forja') && !ids.has('ModuleStub'));
  t('morde: rota derivada-do-nome "forja" não existe → recusa (o bug real do dogfood)', verificarRota('forja', appFake).ok === false);
  t('morde: a recusa DIZ os ids válidos (acionável, não só "não")', /ids válidos/.test(verificarRota('forja', appFake).motivo) && /projects/.test(verificarRota('forja', appFake).motivo));
  t('libera: rota real "projects" passa', verificarRota('projects', appFake).ok === true);
  t('libera: rota real "fin-fluxo" passa (tela chart-only não é punida)', verificarRota('fin-fluxo', appFake).ok === true);
  t('fail-open: roteador não reconhecido (<5 ids) não reprova', verificarRota('qualquer', 'const x = 1;').ok === true);

  // ── conteúdo = AVISO, nunca trava (29% de FP medido: Fluxo 0% e Forja 7% são LEGÍTIMAS).
  const jsxAnc = `const t='Fluxo de caixa'; a='Saldo previsto'; b='A receber'; c='A pagar'; d='Novo título'; e='Visão unificada'; f='Conciliação bancária';`;
  const celReal = { '1280|dark': { elementos: [{ texto: 'Fluxo de caixa' }, { texto: 'Saldo previsto' }, { texto: 'A receber' }, { texto: 'A pagar' }], compostos: [{ texto: 'Visão unificada' }] } };
  const vcReal = verificarConteudo(jsxAnc, celReal);
  t('aviso: captura com a copy da âncora reporta overlap alto', vcReal.ok === true && vcReal.achados >= 5);
  t('fail-open: âncora ilegível não reprova (igual ao --compare)', verificarConteudo(null, celReal).ok === true);

  // extract: célula existente sai com âncora; ausente lança com as disponíveis
  t('extract: célula existente sai com âncora assada', extrairCelula(bom, '1280|dark').ancora === ANC);
  let lancou = false; try { extrairCelula(bom, '9999|sepia'); } catch (e) { lancou = /não existe/.test(e.message) && /1280\|dark/.test(e.message); }
  t('extract: célula ausente lança listando as disponíveis', lancou);

  // rota derivada da âncora (heurística <id>-page.jsx)
  t('rota: financeiro-page.jsx → financeiro', rotaDoAnchor('prototipo-ui/cowork/financeiro-page.jsx') === 'financeiro');
  t('rota: vendas-page.jsx → vendas', rotaDoAnchor('vendas-page.jsx') === 'vendas');
  t('rota: shell/na-padrão → null (exige --route)', rotaDoAnchor('oimpresso.com.html') === null);

  // 1º token (related_prototype com prosa)
  t('primeiroToken corta a prosa do charter', primeiroToken('prototipo-ui/cowork/financeiro-page.jsx (design real; corrigido)') === 'prototipo-ui/cowork/financeiro-page.jsx');

  // destino imita o map.json (unificado.map.json → unificado.proto-baseline.json)
  const dest = destinoBaseline('resources/js/Pages/Financeiro/Unificado/Index.tsx', '/repo');
  t('destino: Financeiro/Unificado → memory/requisitos/Financeiro/unificado.proto-baseline.json',
    String(dest).replace(/\\/g, '/') === '/repo/memory/requisitos/Financeiro/unificado.proto-baseline.json');
  t('destino: tela sem .tsx resolvível → null', destinoBaseline('') === null);

  // fronteira ADR 0290: render recusado sob CI
  t('ADR 0290: render PERMITIDO fora de CI', renderPermitido({}) === true);
  t('ADR 0290: render RECUSADO sob CI', renderPermitido({ CI: 'true' }) === false);
  t('ADR 0290: render RECUSADO sob GITHUB_ACTIONS', renderPermitido({ GITHUB_ACTIONS: 'true' }) === false);

  // Tier 0 BRL + LGPD PII: redigirSensiveis limpa elementos+compostos; --check morde cru
  const cRs = redigirSensiveis({ '1280|dark': { ...fpOk, elementos: [{ tag: 'b', texto: 'Total R$ 1.234,56 hoje' }], compostos: [{ tag: 'div', texto: 'R$ 99,90' }] } });
  t('redigirSensiveis: R$ vira <BRL> em elementos e compostos',
    cRs['1280|dark'].elementos[0].texto === 'Total <BRL> hoje' && cRs['1280|dark'].compostos[0].texto === '<BRL>');
  const cPii = redigirSensiveis({ '1280|dark': { ...fpOk, elementos: [{ tag: 'td', texto: 'CNPJ 12.345.678/0001-90' }], compostos: [{ tag: 'div', texto: 'CPF 123.456.789-01' }] } }); // pii-allowlist (fixture mock — prova a redação)
  t('redigirSensiveis: CNPJ/CPF mock viram <CNPJ>/<CPF> (pii-scan é cego a mock)',
    cPii['1280|dark'].elementos[0].texto === 'CNPJ <CNPJ>' && cPii['1280|dark'].compostos[0].texto === 'CPF <CPF>');
  const comBRL = montarBaseline({ tela: 't', charter: 'c', ancora: ANC, prototipo_sha: 'a', shell: '.', celulas: { '1280|dark': { ...fpOk, ancora: ANC, elementos: [{ tag: 'b', texto: 'R$ 10,00' }] } } });
  t('morde: baseline commitado com R$ cru (Tier 0)', verificarBaseline(comBRL, {}).ok === false && verificarBaseline(comBRL, {}).drift.some((d) => d.includes('BRL')));
  const comPII = montarBaseline({ tela: 't', charter: 'c', ancora: ANC, prototipo_sha: 'a', shell: '.', celulas: { '1280|dark': { ...fpOk, ancora: ANC, elementos: [{ tag: 'td', texto: '12.345.678/0001-90' }] } } }); // pii-allowlist (fixture mock — prova o --check morder)
  t('morde: baseline commitado com CNPJ/CPF cru (LGPD · pii-scan)', verificarBaseline(comPII, {}).ok === false && verificarBaseline(comPII, {}).drift.some((d) => d.includes('CPF/CNPJ')));

  // identidade staging×repo (furo do sha-mentiroso): igual passa, drift/ausente morde
  t('identidade: staging == repo → passa', conferirIdentidadeProto('const a=1;\n', 'const a=1;\r\n').ok === true); // normalize iguala EOL
  t('identidade: staging driftou → morde', conferirIdentidadeProto('const a=1;', 'const a=2;').ok === false);
  t('identidade: âncora ausente no staging → morde', conferirIdentidadeProto('x', null).ok === false);
  t('identidade: âncora ausente no repo (espelho) → morde', conferirIdentidadeProto(null, 'x').ok === false);

  // nudge (o compare possível em CI): PR→módulo com baseline; _components conta; resto silencia
  const BLS = ['memory/requisitos/Sells/sells.proto-baseline.json', 'memory/requisitos/Compras/compras.proto-baseline.json'];
  t('nudge: Page do módulo com baseline dispara', telasAfetadas(['resources/js/Pages/Sells/Index.tsx'], BLS).length === 1);
  // case/separador: as 2 árvores escrevem o MESMO módulo diferente (Pages/kb × requisitos/KB;
  // Pages/team-mcp × requisitos/TeamMcp). String-exata deixava o nudge MORTO — regressão de 2026-07-17
  // pega por controle-negativo no repo real. Trava os 2 lados: casa o equivalente, NÃO casa o alheio.
  const BLS2 = ['memory/requisitos/KB/kb.proto-baseline.json', 'memory/requisitos/TeamMcp/forja-cockpit.proto-baseline.json'];
  t('nudge: Pages/kb casa requisitos/KB (case difere)', telasAfetadas(['resources/js/Pages/kb/Index.tsx'], BLS2).length === 1);
  t('nudge: Pages/team-mcp casa requisitos/TeamMcp (separador difere)', telasAfetadas(['resources/js/Pages/team-mcp/Forja/Cockpit.tsx'], BLS2).length === 1);
  t('nudge: módulo alheio NÃO casa (normalização não vira coringa)', telasAfetadas(['resources/js/Pages/Produto/Index.tsx'], BLS2).length === 0);
  t('normModulo: kb/KB e team-mcp/TeamMcp colapsam; Produto não vira KB', normModulo('KB') === normModulo('kb') && normModulo('team-mcp') === normModulo('TeamMcp') && normModulo('Produto') !== normModulo('KB'));
  t('nudge: _components do módulo também dispara (re-renderiza a tela)',
    telasAfetadas(['resources/js/Pages/Sells/_components/VdRow.tsx'], BLS)[0] === BLS[0]);
  t('nudge: módulo SEM baseline silencia', telasAfetadas(['resources/js/Pages/Whatsapp/Index.tsx'], BLS).length === 0);
  t('nudge: fora de Pages/ silencia (proto/backend não é dever-de-casa de compare)',
    telasAfetadas(['prototipo-ui/cowork/vendas-page.jsx', 'Modules/Sells/Http/X.php'], BLS).length === 0);
  t('nudge: path windows (backslash) normaliza', telasAfetadas(['resources\\js\\Pages\\Compras\\Index.tsx'], BLS).length === 1);
  t('nudge: 2 módulos afetados → 2 baselines', telasAfetadas(['resources/js/Pages/Sells/Index.tsx', 'resources/js/Pages/Compras/Index.tsx'], BLS).length === 2);

  // integração: o SNIPPET importado é a fonte única (mesmo vetor do style-fingerprint)
  t('SNIPPET é a fonte única (window.__ANCORA__ presente no vetor)', typeof SNIPPET === 'string' && SNIPPET.includes('__ANCORA__'));

  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — baseline morde (stale/âncora/vazio) e libera (íntegro); fronteira 0290 respeitada.');
  process.exit(fails ? 1 : 0);
}

// ── main ────────────────────────────────────────────────────────────────────────
function parseArgs(argv) {
  const a = { _: [] };
  for (let i = 0; i < argv.length; i++) {
    const k = argv[i];
    if (k.startsWith('--')) {
      const flag = k.slice(2);
      if (['gerar', 'check', 'extract', 'nudge', 'selftest'].includes(flag)) { a.modo = flag; continue; }
      const v = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : true;
      a[flag] = v;
    } else a._.push(k);
  }
  return a;
}

const ehEntrypoint = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (ehEntrypoint) {
  const args = parseArgs(process.argv.slice(2));
  if (args.modo === 'selftest') selftest();
  else if (args.modo === 'gerar') await cmdGerar(args);
  else if (args.modo === 'check') await cmdCheck(args);
  else if (args.modo === 'extract') cmdExtract(args);
  else if (args.modo === 'nudge') cmdNudge(args);
  else {
    console.log('uso: --gerar <Mod/Tela> [--staging <dir>] [--porta N] [--viewports 1280,1440] [--themes light,dark] [--route <id>] [--out <path>]');
    console.log('   | --check [baseline.json ...]      (hermético — o modo do CI, advisory)');
    console.log('   | --extract <baseline.json> <1280|dark> [--out proto.json]');
    console.log('   | --nudge [arquivo ...]            (ou stdin: git diff --name-only — aponta o compare LOCAL pra PR)');
    console.log('   | --selftest');
    process.exit(2);
  }
}
