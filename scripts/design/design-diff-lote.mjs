#!/usr/bin/env node
// @ts-check
/**
 * design-diff-lote.mjs — DRIVER que transforma "ancorada" em "comparada por SONDA", em lote.
 *
 * Origem: item 4 autorizado por [W] em 2026-09-06 (branch `claude/recibos-ci-em-massa`).
 * O `application-report.json` tinha 62 telas em `anchored` — fonte e alvo conhecidos, comparação
 * nenhuma — e a comparação por sonda vinha sendo feita UMA tela por vez, à mão, colando a sonda
 * em duas abas do Chrome. Este arquivo faz o MESMO gesto, com a MESMA sonda, para N telas, e
 * grava o resultado como dado — sem escrever prosa.
 *
 * ── O QUE É ────────────────────────────────────────────────────────────────────────────
 *   Instrumento de MEDIÇÃO sob demanda. Para cada tela selecionada:
 *     (a) renderiza o PROTÓTIPO do espelho (`prototipo-ui/cowork/Wagner/oimpresso.com.html`, servido por
 *         http local; o shell roteia pelo `localStorage["oimpresso.route"]`, e o token da rota é
 *         DERIVADO da tabela `if (route === "x") content = <window.YPage/>` do `app.jsx`, cruzada
 *         com o `window.YPage =` que o `-page.jsx` da âncora define) e injeta a sonda canônica
 *         (`design-diff.mjs --probe`) → `governance/design/targets/medidas/<Mod--Tela>/design.json`;
 *     (b) renderiza o VIVO em `--base-url` (default `http://127.0.0.1:8000`, o app que o
 *         `visual-regression.yml` sobe), logado pela rota env-guarded `/_visreg-login/{id}?to=<rota>`
 *         que o CI já usa (routes/web.php — inexistente em produção), injeta a MESMA sonda →
 *         `prod.json`. A rota da tela viva vem do `page:` do charter ao lado do `.tsx`;
 *     (c) roda `design-diff.mjs --compare prod.json design.json --json --check [--contrato]`
 *         (PROD PRIMEIRO — a ordem é contrato do dono, e ele morde se inverter) e grava
 *         `resultado.json` + uma linha em `governance/design/targets/medidas/RESUMO.md`.
 *
 * ── O QUE **NÃO** É (lê antes de ligar em qualquer lugar) ──────────────────────────────
 *   • NÃO é gate. Não há workflow, hook nem exit que bloqueie merge. A comparação prod×proto
 *     por render pareado NÃO-HERMÉTICO como gate de CI está MORTA (§5 2026-07-09 "render-diff
 *     prod×proto em CI"; lápide canônica ADR 0290). O que é permitido — e é só isto que este
 *     arquivo faz — é MEDIR e REGISTRAR (LC-06: comparação é MEDIDA, nunca no olho).
 *   • NÃO substitui o `status.mjs --mark-compared`: pela D-3 da ADR 0384, `compared` exige um
 *     `*.map.json` relacionando fonte e alvo. Este driver produz o par de snapshots + veredito
 *     por dimensão; o mapa semântico continua sendo trabalho de quem aplica a tela.
 *   • NÃO tem sonda própria. A sonda é a do dono (`design-diff.mjs --probe`, por subprocesso —
 *     o módulo é CLI-only e importá-lo dispara o CLI). O que este arquivo acrescenta é a
 *     ORQUESTRAÇÃO (seleção · rota do shell · login · loop · resumo), que o dono não cobre.
 *   • NÃO afirma "igual". O `--check` do dono sai 2 (NÃO MEDI) quando o lado design veio do
 *     espelho local e a última rodada de frescor não cobriu o espelho inteiro — e é o estado
 *     de hoje (2026-09-06: 7 de 258 medidos). Esse 2 é registrado como veredito, não engolido.
 *
 * ── COMO RODAR ─────────────────────────────────────────────────────────────────────────
 *   node scripts/design/design-diff-lote.mjs --selftest            # partes puras, hermético
 *   node scripts/design/design-diff-lote.mjs --dry                 # imprime o PLANO (sem browser)
 *   node scripts/design/design-diff-lote.mjs --dry --tela Compras/Index
 *   node scripts/design/design-diff-lote.mjs                       # todas as `anchored` c/ âncora resolvível
 *   node scripts/design/design-diff-lote.mjs --tela Compras/Index --base-url http://127.0.0.1:8000
 *   node scripts/design/design-diff-lote.mjs --tema dark           # força o mesmo tema nos 2 lados
 *   node scripts/design/design-diff-lote.mjs --lado design --tela superadmin/Negocios/Index
 *     só o protótipo (sem app vivo, sem login); `--lado prod` = só o vivo. Um lado só NÃO compara:
 *     o resultado.json registra o lado e o sha do arquivo gravado.
 *
 *   Rota do protótipo POR TELA (A-LOTE, 2026-10-06): a tabela `ROTAS` da âncora (`"tok": { page:
 *   "Mod/Tela" }`) ganha da rota do grupo. Duas telas do mesmo módulo no MESMO token saem do plano
 *   (estático); duas com `design.json` byte-idêntico saem do resultado com exit 2 (medido).
 *
 *   Pré-requisitos do render (o `--dry` e o `--selftest` NÃO precisam de nenhum):
 *     · `npm ci` + `npm run e2e:install` (playwright + chromium);
 *     · o app vivo no ar em `--base-url`, com `.env` não-produção (a rota `/_visreg-login` só
 *       existe fora de produção — é o mesmo boot do `visual-regression.yml`: migrate + seeds
 *       `Visreg*` + `npm run build:inertia` + `php artisan serve`);
 *     · rede para o shell do Cowork: o `oimpresso.com.html` carrega React/Babel/Tailwind de CDN.
 *   Alvo em STAGING: a rota existe lá desde 2026-09-08, mas exige segredo (`?t=…`) porque o host
 *   é público na internet. Exporte `VISREG_LOGIN_TOKEN` com o valor do `.env` do staging — sem ele
 *   a rota responde 404 e o login cai em /login (o erro abaixo diz isso). Em `local`/`testing` a
 *   env não existe e nada muda.
 *   Login sem senha: `--user-id N` (default 1, o admin que o `VisregTenantSeeder` cria). Se o app
 *   alvo não expõe `/_visreg-login`, passe a sessão por env `DESIGN_DIFF_COOKIE="nome=valor"`
 *   (cookie de sessão já autenticada, obtido por quem tem acesso — NUNCA credencial em arquivo).
 *
 *   Overrides por tela (opcional, JSON — configuração, não prosa):
 *     governance/design/targets/roles/<Mod--Tela>.json
 *       { "token": "cmp-pedidos",                       // rota do shell, quando a derivação falha
 *         "roles": { "prod": {...}, "design": {...} },  // __DD_ROLES por lado (papel → seletor)
 *         "shell": { "prod": {...}, "design": {...} },  // __SB_ROLES por lado
 *         "contrato": "governance/design/contracts/x.contract.json" }
 *
 * ── LIMITES CONHECIDOS (medidos, não supostos) ─────────────────────────────────────────
 *   1. LOGIN DO VIVO — depende de `/_visreg-login` (env-guarded) ou do cookie por env. Produção
 *      real fica FORA: lá a rota não existe e a sessão é interativa (ADR 0315 no eixo design;
 *      aqui o mesmo princípio no eixo app).
 *   2. TETO DO ESPELHO — o lado design é o espelho `prototipo-ui/cowork/Wagner/`, não o Cowork vivo.
 *      O `--dry` imprime, por âncora, o estado de frescor lido do ledger (`verificado` / `stale`
 *      / `nunca` / `sem veredito novo`) e conta quantas âncoras selecionadas estão SEM veredito.
 *      Enquanto esse número for > 0, todo `IGUAL` deste driver é "igual a uma cópia de frescor
 *      desconhecido" — e o dono já traduz isso em exit 2. Medir: `cowork-mirror-freshness --sla`.
 *   3. ROTA DO SHELL — a derivação cobre `route === "x"` exato, o `window.X =` do próprio arquivo
 *      da âncora, o basename (`vendas-page.jsx` → `vendas`) e o adaptador de prefixo
 *      (`PG_XPage` → `XPage`). Mockup roteado SÓ por prefixo (`cmp-*`, `est-*`) sem token exato
 *      sai como "sem rota derivável" → override. Medido no `--dry` de 2026-09-06: 1 de 62
 *      (`Sells/Create`, cujo charter tampouco declara fonte).
 *   4. IDENTIDADE DA VIEW (D0) — só quando existe `governance/design/contracts/*.contract.json` com
 *      `tela` igual ao id da tela E com `copy[]` preenchida em alguma seção (ver
 *      `contratoProvaIdentidade`: esqueleto sem copy não prova identidade, e aceitá-lo faria do
 *      D0 um teste de presença de arquivo). Sem contrato, a âncora compartilhada (ex.:
 *      `repair-page.jsx` serve 7 telas) pode renderizar OUTRA view e o veredito sai plausível.
 *      O `--dry` marca.
 *   5. PAPÉIS (`__DD_ROLES`) — os defaults são heurísticos por lado (KpiCard canon na prod,
 *      classes `-stat`/`.kpi` no protótipo). Papel que não casa elemento sai SEM-DADO no dono,
 *      nunca "igual". Ajuste por tela no override.
 *   6. ROTA PARAMETRIZADA — `page:` com `{id}` não tem instância derivável; sai do lote com
 *      motivo, e entra só por `--tela X --url /rota/concreta`.
 *   7. Duplicação DECLARADA (§5 2026-08-02): `esperarEstavel`/`servir` reimplementam o que
 *      `scripts/design-sync/alvo.mjs` já tem — aquele módulo executa `main()` no import e não é
 *      importável. Unificar é PR próprio do dono, não deste.
 *
 * Exit: 0 = tudo medido, sem bug · 1 = medido e ≥1 DIVERGE(bug) · 2 = ≥1 tela NÃO MEDIDA.
 */

import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync, statSync, createReadStream } from 'node:fs';
import { createServer } from 'node:http';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { join, resolve, dirname, basename, extname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { frontmatter } from './_lib-charter.mjs';
import { dsRuntimeRelPath } from '../governance/cowork-mirror-freshness.mjs';

const argv = process.argv.slice(2);
const flag = (n) => argv.includes(n);
const val = (n, d = null) => { const i = argv.indexOf(n); return i >= 0 && argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : d; };

const HERE = dirname(fileURLToPath(import.meta.url));            // prototipo-ui/
const ROOT = resolve(HERE, '../..');
// `--espelho <dir>`: o lado design e o espelho DAQUELE dono, nao sempre o do Wagner.
// Nasceu em 2026-09-22. A familia Fabricacao ancorou no handoff do Felipe (#7696) e este
// driver ficou CEGO nas 5 telas, por duas vias que a constante fixa causava: `frescorDaFonte`
// devolvia `'fora do espelho'` (o `relative(COWORK_DIR, abs)` sai com `..`) e `servirEspelho`
// 404ava o mockup (ele guarda a raiz de proposito -- ver o selftest `nao sai da raiz`).
// Default INALTERADO: sem a flag, segue `Wagner`. Medido antes de ligar: `cowork/Felipe` e
// auto-contido para o shell (`oimpresso.com.html` + `app.jsx` + `styles.css` + os
// `manufacturing-*`); os 64 arquivos que so existem no Wagner sao docs `COLAR-NO-CODE-*.md`.
const COWORK_DIR = resolve(ROOT, val('--espelho', 'prototipo-ui/cowork/Wagner'));
const SHELL_HTML = 'oimpresso.com.html';
const APP_JSX = join(COWORK_DIR, 'app.jsx');
const DESIGN_DIFF = join(HERE, 'design-diff.mjs');
const REPORT = join(ROOT, 'scripts', 'design-sync', 'state', 'application-report.json');
const DIR_MEDIDAS = resolve(HERE, '../../governance/design/targets/medidas');
const DIR_ROLES = resolve(HERE, '../../governance/design/targets/roles');
const DIR_CONTRATO = resolve(HERE, '../../governance/design/contracts');
const RESUMO = join(DIR_MEDIDAS, 'RESUMO.md');
const LOGIN_PATH = '/_visreg-login';

/* ═══════════════════════════════════════════════════════════════════════════════════════
 * PARTE PURA — selecionável, derivável, testável sem browser
 * ═════════════════════════════════════════════════════════════════════════════════════ */

/** `resources/js/Pages/Compras/Index.tsx` → `Compras/Index`; módulo nWidart idem após `/Pages/`. */
export function idDaTela(target) {
  const t = String(target || '').replace(/\\/g, '/');
  const i = t.lastIndexOf('/Pages/');
  if (i < 0) return null;
  return t.slice(i + '/Pages/'.length).replace(/\.tsx$/i, '');
}

/** `Compras/Index` → `Compras--Index` (nome de pasta em `alvos/medidas/`). */
export function slugDaTela(id) {
  return String(id || '').replace(/\//g, '--').replace(/[^A-Za-z0-9_.-]/g, '-');
}

/** Charter canônico: ao lado do `.tsx`, mesmo nome. */
export function charterDe(target) {
  return String(target || '').replace(/\.tsx$/i, '.charter.md');
}

/** `page:` do charter → rota viva. Parametrizada (`{id}`, `:id`) não tem instância derivável. */
export function rotaViva(page) {
  const rota = String(page || '').trim();
  if (!rota) return { rota: null, parametrizada: false };
  return { rota, parametrizada: /[{:*]/.test(rota) };
}

/**
 * Telas do application-report que entram no lote: `lifecycleState === 'anchored'`.
 * `filtro` aceita `Mod/Tela`, o path do `.tsx`, ou sufixo de qualquer um dos dois.
 */
export function selecionarTelas(report, filtro = null, opts = {}) {
  const screens = Array.isArray(report?.screens) ? report.screens : [];
  const f = filtro ? String(filtro).replace(/\\/g, '/').replace(/\.tsx$/i, '') : null;
  return screens
    // `anchored` = ainda nao comparada, e e a fila NATURAL deste driver. `compared` sai por
    // desenho -- mas RE-MEDIR tela ja comparada e legitimo quando a ancora dela MUDOU (#7696)
    // ou quando a medicao anterior saiu invalida (ex.: `sameTheme:false`). Opt-in por
    // `--incluir-comparadas`; sem a flag o comportamento e byte-a-byte o de antes.
    .filter((s) => s && (opts.incluirComparadas ? ['anchored', 'compared'] : ['anchored']).includes(s.lifecycleState) && s.target)
    .filter((s) => {
      if (!f) return true;
      const id = idDaTela(s.target) || '';
      const alvo = String(s.target).replace(/\.tsx$/i, '');
      return id === f || alvo === f || id.endsWith('/' + f) || alvo.endsWith('/' + f);
    })
    .map((s) => ({ source: s.source, target: s.target, module: s.module, id: idDaTela(s.target) }))
    .sort((a, b) => String(a.target).localeCompare(String(b.target)));
}

/**
 * Tabela de rotas do shell, DERIVADA do `app.jsx`:
 *   `if (route === "backup") content = <window.BackupPage .../>`
 *   `if (route === "fila" || route === "acabamento") content = <window.ProducaoPage />`
 *   `if (typeof route === "string" && route.indexOf("cmp-") === 0) content = <window.ComprasExtrasPage .../>`
 * Devolve [{ componente, tokens[], prefixos[] }]. Não é lista escrita à mão (ADR 0256).
 */
export function mapaRotasDoShell(appSrc) {
  const out = [];
  const re = /if \(([^\n]*?)\)\s*content = <window\.([A-Za-z0-9_]+)/g;
  for (const m of String(appSrc || '').matchAll(re)) {
    const cond = m[1], componente = m[2];
    // `(?<!typeof )`: sem isto, `typeof route === "string"` vira token "string" (pego no selftest).
    const tokens = [...cond.matchAll(/(?<!typeof )route === "([^"]+)"/g)].map((x) => x[1]);
    const lista = cond.match(/\[((?:"[^"]+",?\s*)+)\]\.indexOf\(route\)/);
    if (lista) for (const x of lista[1].matchAll(/"([^"]+)"/g)) tokens.push(x[1]);
    const prefixos = [...cond.matchAll(/route\.indexOf\("([^"]+)"\) === 0/g)].map((x) => x[1]);
    if (tokens.length || prefixos.length) out.push({ componente, tokens, prefixos });
  }
  return out;
}

/**
 * Nomes que um `.jsx` do espelho publica em `window` — pelas DUAS formas que o espelho usa:
 *   (a) `window.X = …`                     — atribuição direta
 *   (b) `Object.assign(window, { X, Y })`  — publicação em bloco (shorthand ou `X: valor`)
 *
 * ⚠️ A forma (b) era INVISÍVEL até 2026-09-18, e o custo foi medido: das 134 telas do
 * `RESUMO.md`, **64 saíam `NÃO MEDI · sem rota derivável`** — entre elas as **7 da Jana**,
 * cujo `jana-merge.jsx:1140` publica exatamente assim (`Object.assign(window, { JanaPage, … })`)
 * enquanto o `app.jsx:779` **monta** a tela (`if (route === "chat") … <window.JanaPage/>`).
 * Provado por controle positivo antes do conserto (§5 2026-08-01): `definicoesDoMockup` devolvia
 * `["matchMedia"]` para o `jana-merge.jsx` e 8 nomes para o `app.jsx` — a função rodava, só não
 * enxergava a forma. Medido no espelho: **16** dos 199 `.jsx` publicam SÓ por `Object.assign`,
 * e 5 usam as duas.
 *
 * Isto NÃO alarga o critério de decisão: `tokenDoMockup` segue cruzando os nomes com as rotas
 * REAIS do shell (`r.componente`), então um nome publicado que o shell não roteia continua sem
 * token. O conserto amplia o que se ENXERGA, não o que se aceita.
 */
export function definicoesDoMockup(src) {
  const s = String(src || '');
  const nomes = [...s.matchAll(/window\.([A-Za-z0-9_]+)\s*=/g)].map((m) => m[1]);
  for (const bloco of s.matchAll(/Object\.assign\(\s*window\s*,\s*\{([^}]*)\}/g)) {
    for (const parte of String(bloco[1]).split(',')) {
      // `X` (shorthand) ou `X: valor` — a CHAVE é o que vira `window.X`
      const m = /^\s*([A-Za-z_$][A-Za-z0-9_$]*)\s*(?::|$)/.exec(parte);
      if (m) nomes.push(m[1]);
    }
  }
  return [...new Set(nomes)];
}

/**
 * Token de rota do shell para a âncora `source`.
 *   1. componente definido no PRÓPRIO arquivo da âncora e roteado por token exato;
 *   2. basename sem `-page.jsx` que seja token exato (ex.: `vendas-page.jsx` → `vendas`, roteado
 *      por `VendasModule`, que vive em outro arquivo);
 *   3. componente do shell servido por ADAPTADOR de prefixo — o arquivo define `PG_XPage` e o
 *      shell roteia `<window.XPage>` (pg-shell-adapters.jsx re-exporta). Só prefixo `MAIÚSCULAS_`,
 *      nunca sufixo solto (`VendaBladePage` NÃO casa `BladePage`);
 *   4. nada → `null` (override em `alvos/roles/<slug>.json` ou "sem rota derivável").
 */
export function tokenDoMockup(source, { rotas, mockupSrc }) {
  const defs = new Set(definicoesDoMockup(mockupSrc));
  for (const r of rotas) {
    if (defs.has(r.componente) && r.tokens.length) return { token: r.tokens[0], via: `window.${r.componente}` };
  }
  const base = basename(String(source || '')).replace(/-page\.jsx$/i, '').replace(/\.jsx$/i, '');
  for (const r of rotas) if (r.tokens.includes(base)) return { token: base, via: 'basename' };
  for (const r of rotas) {
    const adaptado = [...defs].find((d) => new RegExp(`^[A-Z0-9]+_${r.componente}$`).test(d));
    if (adaptado && r.tokens.length) return { token: r.tokens[0], via: `window.${adaptado} → adaptador → window.${r.componente}` };
  }
  const soPrefixo = [...rotas].find((r) => defs.has(r.componente) && r.prefixos.length);
  if (soPrefixo) return { token: null, via: `só prefixo ${soPrefixo.prefixos.join('|')}* (window.${soPrefixo.componente}) — declare o token no override` };
  return { token: null, via: 'sem rota derivável' };
}

/**
 * Rota POR TELA declarada pelo próprio protótipo: a tabela `const ROTAS = { "tok": { page: "Mod/Tela", … } }`
 * do `-page.jsx` (hoje só o `repair-page.jsx`, thread 00 do playbook Repair). Devolve `Map page → token`
 * (o primeiro token de cada page), ou `null` quando a âncora não tem a tabela.
 *
 * Nasceu na thread A-LOTE do playbook Placar (2026-10-06). Sem isto o lote abria a rota do GRUPO
 * (`repair`, `superadmin`, `officeimpresso`) para toda tela da âncora e gravou o MESMO `design.json`
 * em Repair (6), Officeimpresso Logs (2) e Superadmin (4) — medido no `_saida-A1` do Superadmin.
 */
export function rotasDaAncora(src) {
  const s = String(src || '');
  const ini = s.search(/\bconst ROTAS\s*=\s*\{/);
  if (ini < 0) return null;
  const fim = s.indexOf('\n};', ini);
  const bloco = s.slice(ini, fim < 0 ? undefined : fim);
  const mapa = new Map();
  for (const m of bloco.matchAll(/"([^"]+)"\s*:\s*\{\s*page:\s*"([^"]+)"/g)) if (!mapa.has(m[2])) mapa.set(m[2], m[1]);
  return mapa;
}

/**
 * Token do shell para UMA tela. Ordem: override → `ROTAS` da âncora (por `page`) → derivação do
 * `app.jsx` (`tokenDoMockup`). Âncora COM `ROTAS` mas sem a `page` desta tela devolve `null`:
 * cair na rota do grupo seria repetir o defeito que a tabela existe para evitar.
 */
export function tokenDaTela(tela, { rotas, mockupSrc, override = {} }) {
  if (override.token) return { token: override.token, via: 'override' };
  const tab = rotasDaAncora(mockupSrc);
  if (tab && tab.size) {
    const tok = tab.get(tela.id);
    return tok ? { token: tok, via: `ROTAS da âncora (page ${tela.id})` } : { token: null, via: `ROTAS da âncora não lista page ${tela.id}` };
  }
  return tokenDoMockup(tela.source, { rotas, mockupSrc });
}

/**
 * Sanidade ESTÁTICA: telas distintas que abrem o MESMO token do shell vão medir a mesma vista.
 * `itens = [{ id, token }]` → `Map id → { token, com: [ids] }`. Token nulo não colide.
 */
export function colisoesDeRota(itens) {
  const por = new Map();
  for (const i of itens || []) {
    if (!i || !i.token) continue;
    if (!por.has(i.token)) por.set(i.token, new Set());
    por.get(i.token).add(i.id);
  }
  const out = new Map();
  for (const [token, ids] of por) {
    if (ids.size < 2) continue;
    for (const id of ids) out.set(id, { token, com: [...ids].filter((x) => x !== id) });
  }
  return out;
}

/**
 * Sanidade MEDIDA: duas telas diferentes do mesmo módulo com `design.json` de hash igual não foram
 * medidas — foram a mesma vista duas vezes. `itens = [{ id, module, sha }]` → `Map id → [ids]`.
 */
export function colisoesDeHash(itens) {
  const por = new Map();
  for (const i of itens || []) {
    if (!i || !i.sha) continue;
    const k = `${i.module}#${i.sha}`;
    if (!por.has(k)) por.set(k, new Set());
    por.get(k).add(i.id);
  }
  const out = new Map();
  for (const ids of por.values()) if (ids.size > 1) for (const id of ids) out.set(id, [...ids].filter((x) => x !== id));
  return out;
}

/** `--lado design|prod|ambos` (default `ambos`). Valor fora da lista = `null` (o CLI sai 2). */
export function ladoDe(v) {
  const l = v == null ? 'ambos' : String(v);
  return ['design', 'prod', 'ambos'].includes(l) ? l : null;
}

/** Contrato de tela (D0) cujo `tela` é o id — `contratos = [{ path, tela }]`. */
export function contratoDe(id, contratos) {
  const c = (contratos || []).find((x) => x && x.tela === id);
  return c ? c.path : null;
}

/**
 * Um contrato só PROVA identidade se alguma seção carrega `copy[]` — a copy literal é o que
 * distingue esta view de outra servida pela MESMA âncora, que é o inteiro propósito do D0.
 * O esqueleto que o `gerar-contrato.mjs` emite nasce com `secoes[]` e `copy` VAZIO de propósito
 * (o docblock dele: "o humano depois preenche copy[]"), então aceitá-lo aqui transformaria o D0
 * num teste de PRESENÇA DE ARQUIVO — bastaria gerar esqueletos em massa pra silenciar o aviso
 * sem provar nada (o verde vazio do §5 2026-08-04, e a família presence-gate da LC-11).
 *
 * MEDIDO em 2026-09-18 nos 36 contratos do repo: 34 têm copy (130 de 148 seções) e 2 são
 * esqueleto — `essentials-metas` e `patrimonio-index`. O aperto custa 2, e os 2 são
 * genuinamente não-provados: não é falso-positivo, é dívida que estava invisível.
 */
export function contratoProvaIdentidade(j) {
  if (!j || typeof j.tela !== 'string') return false;
  const secoes = Array.isArray(j.secoes) ? j.secoes : [];
  return secoes.some((s) => Array.isArray(s && s.copy) && s.copy.some((c) => typeof c === 'string' && c.trim()));
}

/** Lê `governance/design/contracts/*.contract.json` → [{ path, tela }] (ilegível ou sem copy = ignorado, não inventa). */
export function lerContratos(dir = DIR_CONTRATO) {
  if (!existsSync(dir)) return [];
  const out = [];
  for (const f of readdirSync(dir)) {
    if (!/\.contract\.json$/.test(f) || f.startsWith('EXEMPLO')) continue;
    try {
      const j = JSON.parse(readFileSync(join(dir, f), 'utf8'));
      if (contratoProvaIdentidade(j)) out.push({ path: relative(ROOT, join(dir, f)).replace(/\\/g, '/'), tela: j.tela });
    } catch { /* contrato ilegível não vira identidade */ }
  }
  return out;
}

/**
 * Interpreta a saída de `design-diff --compare … --json --check`.
 *   rc 0 → IGUAL (0 bug, e a proveniência do espelho foi provada pelo dono)
 *   rc 1 → DIVERGE (bug)
 *   rc 2 + JSON → MEDIDO · 0 bug · frescor NÃO PROVADO (o dono recusa carimbar "igual")
 *   rc 2 sem JSON → NÃO MEDI (lados trocados / identidade da view não provada / arquivo ruim)
 * Nunca colapsa "não medi" em estado da tela (§5 2026-07-29).
 */
export function interpretarCompare({ status, stdout, stderr }) {
  let res = null;
  const s = String(stdout || '');
  const i = s.indexOf('{');
  if (i >= 0) { try { res = JSON.parse(s.slice(i)); } catch { res = null; } }
  const porDim = {};
  if (res && Array.isArray(res.rows)) {
    for (const r of res.rows) {
      const d = porDim[r.dim] || (porDim[r.dim] = { diverge: 0, bug: 0, semDado: 0, igual: 0 });
      const v = String(r.veredito || '');
      if (v.startsWith('DIVERGE')) { d.diverge++; if (v === 'DIVERGE (bug)') d.bug++; }
      else if (v === 'SEM-DADO') d.semDado++;
      else if (v === 'IGUAL') d.igual++;
    }
  }
  const err = String(stderr || '').replace(/\s+/g, ' ').trim();
  let veredito, motivo = '';
  if (status === 0 && res) veredito = 'IGUAL';
  else if (status === 1 && res) veredito = 'DIVERGE (bug)';
  else if (status === 2 && res) { veredito = 'MEDIDO · frescor não provado'; motivo = err.slice(0, 220); }
  else if (status === 2) { veredito = 'NÃO MEDI'; motivo = err.slice(0, 220) || 'design-diff saiu 2 sem relatório'; }
  else { veredito = 'ERRO'; motivo = (err || `rc=${status}`).slice(0, 220); }
  return {
    rc: status, veredito, motivo,
    bugs: res ? Number(res.bugs || 0) : null,
    shell: res ? Number(res.shell || 0) : null,
    sameTheme: res ? !!res.sameTheme : null,
    porDim, rows: res ? res.rows : null,
  };
}

const DIMS = ['D2', 'D4', 'D6', 'D8', 'D9', 'SHELL'];
const celulaDim = (d) => (!d ? '—' : d.bug ? `${d.bug} bug` : d.diverge ? `${d.diverge} div` : d.semDado && !d.igual ? 'sem-dado' : 'ok');

/** RESUMO.md — derivado. Regerar, nunca editar. */
export function gerarResumo(resultados, meta = {}) {
  const linhas = [
    '# Medidas design × vivo — RESUMO (DERIVADO)',
    '',
    `> Gerado por \`scripts/design/design-diff-lote.mjs\` em ${meta.geradoEm || new Date().toISOString()}. **Não edite à mão** — re-rode o comando.`,
    `> Base viva: \`${meta.baseUrl || '—'}\` · bundle: \`${meta.bundle || '—'}\` · telas: ${resultados.length}.`,
    '> Isto NÃO é gate. `IGUAL` só aparece quando o dono (`design-diff --check`) provou a proveniência do espelho; `MEDIDO · frescor não provado` = 0 bug contra uma cópia de frescor desconhecido.',
    '',
    '| Tela | Fonte | Rota shell | Veredito | bugs | shell | ' + DIMS.join(' | ') + ' | Frescor da âncora | Motivo |',
    '|---|---|---|---|---|---|' + DIMS.map(() => '---').join('|') + '|---|---|',
  ];
  for (const r of resultados) {
    const c = r.compare || {};
    linhas.push([
      r.id, basename(r.source || ''), r.token || '—', c.veredito || r.veredito || '—',
      c.bugs == null ? '—' : c.bugs, c.shell == null ? '—' : c.shell,
      ...DIMS.map((d) => celulaDim(c.porDim && c.porDim[d])),
      r.frescor || '—', (c.motivo || r.motivo || '').replace(/\|/g, '/'),
    ].map((x) => `| ${x} `).join('') + '|');
  }
  const cont = {};
  for (const r of resultados) { const v = (r.compare && r.compare.veredito) || r.veredito || '—'; cont[v] = (cont[v] || 0) + 1; }
  linhas.push('', '## Contagem por veredito', '');
  for (const [k, v] of Object.entries(cont).sort()) linhas.push(`- ${k}: ${v}`);
  linhas.push('');
  return linhas.join('\n');
}

/* ── papéis default por lado (heurísticos; override por tela em alvos/roles/<slug>.json) ── */
export const ROLES_PADRAO = {
  prod: {
    kpi: '.rounded-xl.border.bg-card.p-4.shadow-sm, [data-kpi], .fin-stat',
    title: 'h1',
    primary: 'button.cw-btn-primary, a.cw-btn-primary, button[data-primary], .btn-primary',
    filterControls: '[data-filterbar] > *, .filterbar > *',
    tableRow: 'main table tbody tr',
  },
  design: {
    kpi: '.kpi, .os-stat, .fin-stat, .cr-stat, .jc-kpis > *, .mp-kpi',
    title: 'h1',
    primary: '.btn.primary, button.primary, .cw-btn-primary',
    filterControls: '.filterbar > *, .os-filters > *, .toolbar > *',
    tableRow: 'main table tbody tr',
  },
};

/** Mapa de papéis do SHELL, lido do dono (`design-diff --shell-roles`) — nunca copiado à mão. */
export function extrairShellRoles(stdout) {
  const out = {};
  for (const m of String(stdout || '').matchAll(/\/\* (PROD|DESIGN) \*\/\s*window\.__SB_ROLES = (\{[\s\S]*?\});/g)) {
    try { out[m[1].toLowerCase()] = JSON.parse(m[2]); } catch { /* bloco ilegível = sem papel */ }
  }
  return out;
}

/** A sonda canônica vem do DONO por subprocesso (contrato público `--probe`). */
export function sondaCanonica() {
  const r = spawnSync(process.execPath, [DESIGN_DIFF, '--probe'], { encoding: 'utf8' });
  if (r.status !== 0 || !r.stdout || r.stdout.length < 100) {
    throw Object.assign(new Error(`design-diff --probe não respondeu: rc=${r.status} ${(r.stderr || '').slice(0, 200)}`), { naoMedi: true });
  }
  return r.stdout;
}

export function shellRolesDoDono() {
  const r = spawnSync(process.execPath, [DESIGN_DIFF, '--shell-roles'], { encoding: 'utf8' });
  return r.status === 0 ? extrairShellRoles(r.stdout) : {};
}

/** Override por tela (JSON) — ausente = `{}`; ilegível = erro, não silêncio. */
export function lerOverride(slug, dir = DIR_ROLES) {
  const p = join(dir, `${slug}.json`);
  if (!existsSync(p)) return {};
  return JSON.parse(readFileSync(p, 'utf8'));
}

/** Frescor da âncora no ledger — pelo dono (`ancora.mjs` + `cowork-mirror-freshness`). */
async function frescorDaFonte(caminhoAncoraRel) {
  const { frescorDoEspelho, ultimaRodada, entradasDoLedger } = await import('./ancora.mjs');
  const { ultimaVerificacaoDe } = await import('../governance/cowork-mirror-freshness.mjs');
  const { createHash } = await import('node:crypto');
  const abs = join(ROOT, caminhoAncoraRel);
  const rel = relative(COWORK_DIR, abs).replace(/\\/g, '/');
  if (!rel || rel.startsWith('..')) return 'fora do espelho';
  let hash = null;
  try { hash = createHash('sha256').update(readFileSync(abs)).digest('hex'); } catch { hash = null; }
  const f = frescorDoEspelho(rel, ultimaRodada(ROOT), hash);
  if (f.estado === 'verificado') return `verificado ${String(f.data).slice(0, 10)}`;
  if (f.estado === 'stale') return `STALE ${String(f.data).slice(0, 10)}`;
  if (f.estado === 'nunca') {
    const ant = ultimaVerificacaoDe(entradasDoLedger(ROOT), rel);
    return ant.data ? `sem veredito novo (última ${String(ant.data).slice(0, 10)})` : 'nunca verificado';
  }
  return 'sem ledger';
}

/* ═══════════════════════════════════════════════════════════════════════════════════════
 * PLANO — o que o lote VAI fazer, tela a tela (é o que `--dry` imprime)
 * ═════════════════════════════════════════════════════════════════════════════════════ */
export async function montarPlano({ filtro = null, urlForcada = null, baseUrl, lado = 'ambos' }) {
  const report = JSON.parse(readFileSync(REPORT, 'utf8'));
  const telas = selecionarTelas(report, filtro, { incluirComparadas: flag('--incluir-comparadas') });
  const rotas = mapaRotasDoShell(readFileSync(APP_JSX, 'utf8'));
  const contratos = lerContratos();
  const { resolveAncora, caminhoDaAncora, ehDeclaracaoNa } = await import('./ancora.mjs');
  const ancoraDe = async (target) => {
    const r = await resolveAncora(target, { repoRoot: ROOT, stagingDir: COWORK_DIR });
    let ancora = null, declarouNa = false;
    if (r.ok) {
      for (const a of r.ancoras) {
        if (ehDeclaracaoNa(a.valor)) { declarouNa = true; continue; }
        const c = caminhoDaAncora(a.valor, a.raiz || ROOT);
        if (c && existsSync(resolve(a.raiz || ROOT, c))) { ancora = relative(ROOT, resolve(a.raiz || ROOT, c)).replace(/\\/g, '/'); break; }
      }
    }
    return { r, ancora, declarouNa };
  };
  const tokenDe = (t, ancora, override) => {
    const mockupPath = ancora ? join(ROOT, ancora) : join(COWORK_DIR, basename(t.source));
    const mockupSrc = existsSync(mockupPath) ? readFileSync(mockupPath, 'utf8') : '';
    return tokenDaTela(t, { rotas, mockupSrc, override });
  };
  const plano = [];
  for (const t of telas) {
    const slug = slugDaTela(t.id);
    const item = { ...t, slug, dir: relative(ROOT, join(DIR_MEDIDAS, slug)).replace(/\\/g, '/'), problemas: [], problemasDesign: [], problemasProd: [] };
    // `l` diz qual lado o problema impede: com `--lado design` a rota viva não importa, e vice-versa.
    const prob = (l, msg) => { item.problemas.push(msg); if (l !== 'prod') item.problemasDesign.push(msg); if (l !== 'design') item.problemasProd.push(msg); };
    const override = lerOverride(slug);
    // charter → rota viva
    const ch = join(ROOT, charterDe(t.target));
    if (!existsSync(ch)) prob('ambos', 'sem charter ao lado do .tsx');
    const fm = existsSync(ch) ? frontmatter(readFileSync(ch, 'utf8')) : {};
    const rv = rotaViva(urlForcada || fm.page);
    item.rota = rv.rota;
    if (!rv.rota) prob('prod', 'charter sem `page:` — rota viva desconhecida');
    else if (rv.parametrizada) prob('prod', `rota parametrizada (${rv.rota}) — passe --tela X --url /rota/concreta`);
    // âncora resolvível pelo ancora.mjs (com o espelho como staging)
    const { r, ancora, declarouNa } = await ancoraDe(t.target);
    item.ancora = ancora;
    if (!ancora) {
      // Três ausências DIFERENTES — colapsá-las esconde qual é (§5 2026-07-29):
      prob('design', !r.ok ? 'sem charter resolvível pelo ancora.mjs'
        : declarouNa ? 'charter declara `n/a` (nasce do DS) — sem âncora POR DECISÃO; o report lista como anchored, o charter não'
        : !r.ancoras.length ? 'charter sem related_prototype/bundle_source — nada a renderizar do lado design'
        : 'âncora do charter não resolve em arquivo');
    }
    item.frescor = ancora ? await frescorDaFonte(ancora) : '—';
    // token do shell — POR TELA (ROTAS da âncora) antes da rota do grupo
    const tk = tokenDe(t, ancora, override);
    item.token = tk.token; item.tokenVia = tk.via;
    if (!tk.token) prob('design', `rota do shell: ${tk.via}`);
    // identidade (D0)
    item.contrato = override.contrato || contratoDe(t.id, contratos);
    if (!item.contrato) item.avisoD0 = 'sem contrato — identidade da view NÃO provada (âncora pode servir outra tela)';
    item.roles = { prod: { ...ROLES_PADRAO.prod, ...(override.roles?.prod || {}) }, design: { ...ROLES_PADRAO.design, ...(override.roles?.design || {}) } };
    item.shellOverride = override.shell || null;
    item.urlViva = item.rota ? `${baseUrl}${item.rota}` : null;
    plano.push(item);
  }
  // Sanidade estática: compara o token de cada tela com o das IRMÃS do mesmo módulo — inclusive as
  // que o `--tela` deixou de fora e as já `compared`, porque é contra elas que o design.json colidiria.
  const modulos = new Set(plano.map((p) => p.module));
  const irmas = [];
  for (const s of selecionarTelas(report, null, { incluirComparadas: true })) {
    if (!modulos.has(s.module) || plano.some((p) => p.id === s.id)) continue;
    const { ancora } = await ancoraDe(s.target);
    if (!ancora) continue; // sem âncora a irmã nunca renderiza — não é parceira de colisão
    irmas.push({ id: s.id, token: tokenDe(s, ancora, lerOverride(slugDaTela(s.id))).token });
  }
  const colisoes = colisoesDeRota([...plano.map((p) => ({ id: p.id, token: p.ancora ? p.token : null })), ...irmas]);
  for (const p of plano) {
    const c = colisoes.get(p.id);
    if (!c) continue;
    const msg = `rota do GRUPO: route=${c.token} também abre ${c.com.join(', ')} — o design.json sairia igual (declare \`ROTAS\` na âncora ou o token no override)`;
    p.problemas.push(msg); p.problemasDesign.push(msg);
  }
  for (const p of plano) {
    const pend = lado === 'design' ? p.problemasDesign : lado === 'prod' ? p.problemasProd : p.problemas;
    p.executavel = pend.length === 0;
  }
  return { plano, bundle: report?.bundle?.id || null, totalAnchored: telas.length };
}

function imprimirPlano({ plano, bundle, totalAnchored }, { baseUrl }) {
  console.log(`PLANO design-diff-lote — ${plano.length} tela(s) anchored selecionada(s) · bundle ${bundle ? bundle.slice(0, 12) : '—'} · vivo em ${baseUrl}\n`);
  for (const p of plano) {
    console.log(`${p.executavel ? '●' : '○'} ${p.id}`);
    console.log(`    fonte    ${p.source}${p.ancora && p.ancora !== 'prototipo-ui/cowork/Wagner/' + basename(p.source) ? `  (âncora: ${p.ancora})` : ''}`);
    console.log(`    frescor  ${p.frescor}`);
    console.log(`    shell    ${p.token ? `route=${p.token} (${p.tokenVia})` : `— ${p.tokenVia || ''}`}`);
    console.log(`    vivo     ${p.urlViva || '—'}`);
    console.log(`    D0       ${p.contrato || p.avisoD0}`);
    console.log(`    grava    ${p.dir}/{design,prod,resultado}.json`);
    for (const pr of p.problemas) console.log(`    ✗ ${pr}`);
  }
  const exec = plano.filter((p) => p.executavel).length;
  const semFrescor = plano.filter((p) => !/^verificado/.test(p.frescor)).length;
  const semD0 = plano.filter((p) => !p.contrato).length;
  console.log(`\nexecutáveis: ${exec}/${plano.length} · sem rota derivável: ${plano.filter((p) => !p.token).length} · rota parametrizada/sem rota viva: ${plano.filter((p) => !p.rota || rotaViva(p.rota).parametrizada).length}`);
  const arquivos = new Map();
  for (const p of plano) if (p.ancora) arquivos.set(p.ancora, p.frescor);
  const arqSemFrescor = [...arquivos.values()].filter((f) => !/^verificado/.test(f)).length;
  console.log(`telas cuja âncora está sem veredito de frescor: ${semFrescor}/${plano.length} · arquivos de âncora distintos sem veredito: ${arqSemFrescor}/${arquivos.size} · sem contrato D0: ${semD0}/${plano.length}`);
  console.log('(nada foi gravado — --dry)');
}

/* ═══════════════════════════════════════════════════════════════════════════════════════
 * RENDER — precisa de playwright + app vivo + rede (fica separado; `--dry` nunca entra aqui)
 * ═════════════════════════════════════════════════════════════════════════════════════ */
const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.jsx': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg' };

/** Servidor estático do espelho (o shell carrega `x.jsx?v=…` por caminho relativo). */
export function servirEspelho(dir = COWORK_DIR, porta = 0) {
  const raiz = resolve(dir);
  const raizDs = resolve(ROOT, 'prototipo-ui/design-system');
  const srv = createServer((req, res) => {
    const p = decodeURIComponent(String(req.url || '/').split('?')[0]);
    // ADR 0401 E2: o shell referencia `_ds/<slug>/X` (DS bound). O prefixo
    // `/design-system/` era a forma do shell reescrito pelo #7224, revertido pelo #7261 —
    // fica aceito por compatibilidade. A regra de caminho e do `dsRuntimeRelPath`.
    let dsRel = null;
    if (p.startsWith('/_ds/')) {
      try { dsRel = dsRuntimeRelPath(p.slice(1)); } catch { dsRel = null; }
      if (dsRel === null) { res.writeHead(404); res.end('404'); return; }
    } else if (p === '/design-system' || p.startsWith('/design-system/')) {
      dsRel = p.slice('/design-system'.length).replace(/^\//, '');
    }
    const base = dsRel !== null ? raizDs : raiz;
    const relativo = dsRel !== null ? '/' + dsRel : (p === '/' ? '/' + SHELL_HTML : p);
    const abs = resolve(base, '.' + relativo);
    if (!abs.startsWith(base) || !existsSync(abs) || !statSync(abs).isFile()) { res.writeHead(404); res.end('404'); return; }
    res.writeHead(200, { 'content-type': MIME[extname(abs).toLowerCase()] || 'application/octet-stream', 'cache-control': 'no-store' });
    createReadStream(abs).pipe(res);
  });
  return new Promise((ok, ko) => {
    srv.on('error', ko);
    srv.listen(porta, '127.0.0.1', () => ok({ porta: /** @type {any} */ (srv.address()).port, fechar: () => new Promise((r) => { srv.closeAllConnections?.(); srv.close(() => r(null)); }) }));
  });
}

async function esperarEstavel(page, { sinal = null, leituras = 25, passo = 200 } = {}) {
  await page.waitForLoadState('domcontentloaded');
  if (sinal) await page.waitForFunction(sinal, null, { timeout: 15000 }).catch(() => {});
  let anterior = -1;
  for (let i = 0; i < leituras; i++) {
    const atual = await page.evaluate(() => document.querySelectorAll('*').length);
    if (atual === anterior && atual > 0) return atual;
    anterior = atual;
    await page.waitForTimeout(passo);
  }
  throw Object.assign(new Error('DOM não estabilizou — a medida seria retrato de meio-caminho (§5 2026-08-24)'), { naoMedi: true });
}

async function abrirBrowser() {
  let chromium;
  try { ({ chromium } = await import('playwright')); }
  catch { try { ({ chromium } = await import('@playwright/test')); } catch { throw Object.assign(new Error('playwright indisponível — `npm ci` + `npm run e2e:install`'), { naoMedi: true }); } }
  try { return await chromium.launch(); }
  catch (e) { throw Object.assign(new Error(`chromium não instalado: ${String(e.message).slice(0, 160)}`), { naoMedi: true }); }
}

async function medirLado(page, { probe, roles, shell, tema }) {
  if (tema) { await page.evaluate((t) => { document.documentElement.setAttribute('data-theme', t); document.documentElement.classList.toggle('dark', t === 'dark'); }, tema); await page.waitForTimeout(300); }
  await page.evaluate(({ r, s }) => { window.__DD_ROLES = r; if (s) window.__SB_ROLES = s; }, { r: roles, s: shell || null });
  return page.evaluate(probe);
}

async function renderDesign(browser, { porta, token, idEsperado, probe, roles, shell, tema }) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  try {
    await ctx.addInitScript((t) => { try { localStorage.setItem('oimpresso.route', t); } catch { /* sem storage */ } }, token);
    const page = await ctx.newPage();
    await page.goto(`http://127.0.0.1:${porta}/${SHELL_HTML}`, { waitUntil: 'domcontentloaded' });
    await esperarEstavel(page, { sinal: () => window.__oiLazyDone === true });
    const rotaAtiva = await page.evaluate(() => { try { return localStorage.getItem('oimpresso.route'); } catch { return null; } });
    if (rotaAtiva !== token) throw Object.assign(new Error(`o shell não ficou em route=${token} (ficou em ${rotaAtiva})`), { naoMedi: true });
    // Vista que se declara (`data-page`) tem de ser ESTA tela — senão a rota abriu outra.
    const dp = await page.evaluate(() => document.querySelector('[data-page]')?.getAttribute('data-page') || null);
    if (dp && idEsperado && dp !== idEsperado) throw Object.assign(new Error(`a vista declara data-page="${dp}", não ${idEsperado}`), { naoMedi: true });
    return await medirLado(page, { probe, roles, shell, tema });
  } finally { await ctx.close(); }
}

async function renderVivo(browser, { baseUrl, rota, userId, cookie, probe, roles, shell, tema }) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  try {
    const page = await ctx.newPage();
    if (cookie) {
      const [name, ...rest] = String(cookie).split('=');
      await ctx.addCookies([{ name: name.trim(), value: rest.join('='), url: baseUrl }]);
      await page.goto(`${baseUrl}${rota}`, { waitUntil: 'domcontentloaded' });
    } else {
      // `&t=` só é exigido quando o alvo roda em `staging` (rota pública na internet —
      // routes/web.php). Em `local`/`testing` a env não existe e a URL sai igual à de antes.
      const segredo = process.env.VISREG_LOGIN_TOKEN || '';
      const qs = `?to=${encodeURIComponent(rota)}` + (segredo ? `&t=${encodeURIComponent(segredo)}` : '');
      await page.goto(`${baseUrl}${LOGIN_PATH}/${userId}${qs}`, { waitUntil: 'domcontentloaded' });
    }
    await page.waitForLoadState('networkidle').catch(() => {});
    await esperarEstavel(page);
    const url = page.url();
    if (/\/login(?:[/?#]|$)/.test(url)) throw Object.assign(new Error(`login falhou — caiu em ${url} (sem /_visreg-login no alvo? passe DESIGN_DIFF_COOKIE)`), { naoMedi: true });
    const componente = await page.evaluate(() => { try { return JSON.parse(document.getElementById('app')?.getAttribute('data-page') || 'null')?.component || null; } catch { return null; } });
    const snap = await medirLado(page, { probe, roles, shell, tema });
    return { snap, meta: { urlFinal: url, componenteInertia: componente } };
  } finally { await ctx.close(); }
}

function compararTela(dir, contrato) {
  const args = [DESIGN_DIFF, '--compare', join(dir, 'prod.json'), join(dir, 'design.json'), '--json', '--check'];
  if (contrato) args.push('--contrato', join(ROOT, contrato));
  const r = spawnSync(process.execPath, args, { encoding: 'utf8', cwd: ROOT });
  return interpretarCompare({ status: r.status, stdout: r.stdout, stderr: r.stderr });
}

async function rodarLote(planoObj, { baseUrl, userId, cookie, tema, lado = 'ambos' }) {
  const { plano, bundle } = planoObj;
  const fazDesign = lado !== 'prod', fazProd = lado !== 'design';
  const probe = sondaCanonica();
  const shellPadrao = shellRolesDoDono();
  const browser = await abrirBrowser();
  const espelho = fazDesign ? await servirEspelho() : null;
  const resultados = [];
  const sha = (txt) => createHash('sha256').update(txt).digest('hex');
  try {
    for (const p of plano) {
      const dir = join(DIR_MEDIDAS, p.slug);
      const item = { id: p.id, module: p.module, source: p.source, target: p.target, token: p.token, lado, frescor: p.frescor, contrato: p.contrato || null, compare: null, veredito: null, motivo: '', medido: false };
      if (!p.executavel) { item.veredito = 'NÃO MEDI'; item.motivo = (lado === 'design' ? p.problemasDesign : lado === 'prod' ? p.problemasProd : p.problemas).join('; '); resultados.push(item); console.log(`○ ${p.id} — ${item.motivo}`); continue; }
      mkdirSync(dir, { recursive: true });
      item.tentado = true;
      const shell = { prod: p.shellOverride?.prod || shellPadrao.prod || null, design: p.shellOverride?.design || shellPadrao.design || null };
      try {
        if (fazDesign) {
          const design = await renderDesign(browser, { porta: espelho.porta, token: p.token, idEsperado: p.id, probe, roles: p.roles.design, shell: shell.design, tema });
          const txt = JSON.stringify(design, null, 2) + '\n';
          writeFileSync(join(dir, 'design.json'), txt);
          item.designSha = sha(txt);
        }
        if (fazProd) {
          const { snap: prod, meta } = await renderVivo(browser, { baseUrl, rota: p.rota, userId, cookie, probe, roles: p.roles.prod, shell: shell.prod, tema });
          const txt = JSON.stringify(prod, null, 2) + '\n';
          writeFileSync(join(dir, 'prod.json'), txt);
          item.prodSha = sha(txt);
          item.meta = meta;
        }
        item.medido = true;
        if (fazDesign && fazProd) {
          item.compare = compararTela(dir, p.contrato);
          console.log(`${item.compare.rc === 0 ? '●' : item.compare.rc === 1 ? '✗' : '⚠'} ${p.id} — ${item.compare.veredito}${item.compare.bugs != null ? ` · bugs=${item.compare.bugs} shell=${item.compare.shell}` : ''}${item.compare.motivo ? ` — ${item.compare.motivo}` : ''}`);
        } else {
          // Um lado só: o arquivo do OUTRO lado em disco não é deste run — sem comparação, e o
          // resultado.json diz isso em vez de descrever um par que não foi medido junto (_saida-A1 §4).
          item.veredito = `MEDIDO · só ${lado} (sem comparação)`;
          console.log(`● ${p.id} — ${item.veredito} · sha ${(item.designSha || item.prodSha).slice(0, 12)}`);
        }
      } catch (e) {
        item.veredito = e.naoMedi ? 'NÃO MEDI' : 'ERRO';
        item.motivo = String(e.message).slice(0, 220);
        console.log(`○ ${p.id} — ${item.veredito}: ${item.motivo}`);
      }
      resultados.push(item);
    }
  } finally {
    await browser.close();
    if (espelho) await espelho.fechar();
  }
  // Sanidade MEDIDA (A-LOTE): duas telas do mesmo módulo com o mesmo design.json = não medi nenhuma.
  const iguais = colisoesDeHash(resultados.filter((r) => r.medido).map((r) => ({ id: r.id, module: r.module, sha: r.designSha })));
  for (const r of resultados) {
    const com = iguais.get(r.id);
    if (!com) continue;
    r.medido = false; r.compare = null; r.veredito = 'NÃO MEDI';
    r.motivo = `design.json byte-idêntico ao de ${com.join(', ')} — a rota abriu a mesma vista`;
    console.log(`○ ${r.id} — NÃO MEDI: ${r.motivo}`);
  }
  for (const r of resultados) {
    if (!r.tentado) continue;
    const { tentado, ...registro } = r;
    writeFileSync(join(DIR_MEDIDAS, slugDaTela(r.id), 'resultado.json'), JSON.stringify({ ...registro, medidoEm: new Date().toISOString(), baseUrl: fazProd ? baseUrl : null, bundle }, null, 2) + '\n');
  }
  mkdirSync(DIR_MEDIDAS, { recursive: true });
  writeFileSync(RESUMO, gerarResumo(resultados, { baseUrl, bundle, geradoEm: new Date().toISOString() }));
  console.log(`\nRESUMO: ${relative(ROOT, RESUMO).replace(/\\/g, '/')}`);
  const naoMedi = resultados.filter((r) => !r.medido || r.compare && r.compare.rc === 2 && !r.compare.rows).length;
  const bugs = resultados.filter((r) => r.compare && r.compare.rc === 1).length;
  return naoMedi ? 2 : bugs ? 1 : 0;
}

/* ═══════════════════════════════════════════════════════════════════════════════════════
 * SELFTEST — hermético (fixtures em memória + tmp), sem browser, sem app vivo
 * ═════════════════════════════════════════════════════════════════════════════════════ */
async function selftest() {
  const checks = [];
  const ok = (nome, cond, detalhe = '') => checks.push({ nome, ok: !!cond, detalhe });

  ok('idDaTela: núcleo', idDaTela('resources/js/Pages/Compras/Index.tsx') === 'Compras/Index');
  ok('idDaTela: módulo nWidart', idDaTela('Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx') === 'Forja/Trabalho/Index');
  ok('idDaTela: sem /Pages/ → null', idDaTela('resources/js/Components/x.tsx') === null);
  ok('slugDaTela: / vira --', slugDaTela('Forja/Trabalho/Index') === 'Forja--Trabalho--Index');
  ok('charterDe: ao lado do .tsx', charterDe('resources/js/Pages/Backup/Index.tsx') === 'resources/js/Pages/Backup/Index.charter.md');
  ok('rotaViva: rota simples', JSON.stringify(rotaViva('/compras')) === '{"rota":"/compras","parametrizada":false}');
  ok('rotaViva: {id} é parametrizada', rotaViva('/oficina-auto/os/{id}').parametrizada === true);
  ok('rotaViva: vazio → null', rotaViva('').rota === null);

  const report = { screens: [
    { source: 'a-page.jsx', target: 'resources/js/Pages/A/Index.tsx', module: 'A', lifecycleState: 'anchored' },
    { source: 'b-page.jsx', target: 'resources/js/Pages/B/Index.tsx', module: 'B', lifecycleState: 'applied' },
    { source: 'c-page.jsx', target: 'Modules/C/Resources/js/Pages/C/Sub/Index.tsx', module: 'C', lifecycleState: 'anchored' },
  ] };
  const sel = selecionarTelas(report).map((t) => t.id).sort().join();
  ok('selecionarTelas: só anchored (applied fica fora), núcleo + módulo', sel === 'A/Index,C/Sub/Index', sel);
  ok('selecionarTelas: filtro Mod/Tela', selecionarTelas(report, 'A/Index').length === 1 && selecionarTelas(report, 'A/Index')[0].id === 'A/Index');
  ok('selecionarTelas: filtro por sufixo de módulo', selecionarTelas(report, 'Sub/Index')[0]?.id === 'C/Sub/Index');
  ok('selecionarTelas: filtro que não casa → vazio', selecionarTelas(report, 'Z/Index').length === 0);

  const appFx = [
    'if (route === "backup") content = <window.BackupPage destino={x} />;else',
    'if (route === "fila" || route === "acabamento" || route === "expedicao") content = <window.ProducaoPage />;else',
    'if (route === "vendas") content = <window.VendasModule />;else',
    'if (typeof route === "string" && route.indexOf("cmp-") === 0) content = <window.ComprasExtrasPage view={route} />;else',
    'if (typeof route === "string" && ["fin-receber", "fin-pagar"].indexOf(route) >= 0) content = <window.FinanceiroLegadoPage view={route} />;else',
    'if (route === "repair" || (typeof route === "string" && route.indexOf("rep-") === 0)) content = <window.RepairPage view={route} />;else',
  ].join('\n');
  const rotas = mapaRotasDoShell(appFx);
  const porComp = Object.fromEntries(rotas.map((r) => [r.componente, r]));
  ok('mapaRotasDoShell: token exato', porComp.BackupPage?.tokens.join() === 'backup');
  ok('mapaRotasDoShell: vários tokens no OR', porComp.ProducaoPage?.tokens.join() === 'fila,acabamento,expedicao');
  ok('mapaRotasDoShell: só prefixo', porComp.ComprasExtrasPage?.tokens.length === 0 && porComp.ComprasExtrasPage?.prefixos.join() === 'cmp-');
  ok('mapaRotasDoShell: lista .indexOf(route)', porComp.FinanceiroLegadoPage?.tokens.join() === 'fin-receber,fin-pagar');
  ok('mapaRotasDoShell: token + prefixo juntos', porComp.RepairPage?.tokens.join() === 'repair' && porComp.RepairPage?.prefixos.join() === 'rep-');
  ok('definicoesDoMockup: pega window.X = e deduplica', definicoesDoMockup('window.BackupPage = () => 1; window.BackupPage = 2; window.Util = {}').join() === 'BackupPage,Util');
  // BITE-TEST do conserto de 2026-09-18 — a forma (b) cegava 64 de 134 telas, incl. as 7 da Jana.
  // Reverter o `definicoesDoMockup` pra só `window.X =` derruba os 3 asserts abaixo.
  ok('definicoesDoMockup: pega Object.assign(window, {…}) shorthand (o caso da Jana)',
    definicoesDoMockup('Object.assign(window, { JanaPage, JmTabs, JmMemoria });').join() === 'JanaPage,JmTabs,JmMemoria');
  ok('definicoesDoMockup: pega `X: valor` dentro do Object.assign',
    definicoesDoMockup('Object.assign(window, { APage: foo, BPage: () => 1 });').join() === 'APage,BPage');
  ok('definicoesDoMockup: as DUAS formas no mesmo arquivo, sem duplicar',
    definicoesDoMockup('window.APage = 1; Object.assign(window, { APage, BPage });').join() === 'APage,BPage');
  // CONTROLE NEGATIVO: `Object.assign` em OUTRO alvo não publica em window (seria FP).
  ok('definicoesDoMockup: CONTROLE — Object.assign(outroAlvo, …) NÃO conta',
    definicoesDoMockup('Object.assign(opts, { NaoEhPagina });').length === 0);
  ok('tokenDoMockup: pelo componente definido no arquivo', JSON.stringify(tokenDoMockup('backup-page.jsx', { rotas, mockupSrc: 'window.BackupPage = () => null;' })) === '{"token":"backup","via":"window.BackupPage"}');
  ok('tokenDoMockup: fallback basename (vendas-page → vendas, componente em OUTRO arquivo)', tokenDoMockup('vendas-page.jsx', { rotas, mockupSrc: 'window.VendasListPage = 1;' }).token === 'vendas');
  const soPref = tokenDoMockup('compras-extras.jsx', { rotas, mockupSrc: 'window.ComprasExtrasPage = 1;' });
  ok('tokenDoMockup: só prefixo NÃO inventa token', soPref.token === null && /prefixo cmp-/.test(soPref.via));
  ok('tokenDoMockup: nada → null com motivo', tokenDoMockup('zzz-page.jsx', { rotas, mockupSrc: '' }).via === 'sem rota derivável');
  const rotasPg = [...rotas, { componente: 'PaymentGatewaysPage', tokens: ['payment-gateways'], prefixos: [] }, { componente: 'BladePage', tokens: ['blade'], prefixos: [] }];
  ok('tokenDoMockup: adaptador de prefixo (PG_XPage → XPage)', tokenDoMockup('pg-payment-gateways-page.jsx', { rotas: rotasPg, mockupSrc: 'window.PG_PaymentGatewaysPage = 1;' }).token === 'payment-gateways');
  ok('CONTROLE adaptador: sufixo solto NÃO casa (VendaBladePage ≠ BladePage)', tokenDoMockup('venda-blade.jsx', { rotas: rotasPg, mockupSrc: 'window.VendaBladePage = 1;' }).token === null);
  // contra o app.jsx REAL: as duas formas que o lote precisa cobrir hoje
  const rotasReais = mapaRotasDoShell(readFileSync(APP_JSX, 'utf8'));
  ok('app.jsx real: backup-page.jsx → route=backup', tokenDoMockup('backup-page.jsx', { rotas: rotasReais, mockupSrc: readFileSync(join(COWORK_DIR, 'backup-page.jsx'), 'utf8') }).token === 'backup');
  ok('app.jsx real: vendas-page.jsx → route=vendas (por basename)', tokenDoMockup('vendas-page.jsx', { rotas: rotasReais, mockupSrc: readFileSync(join(COWORK_DIR, 'vendas-page.jsx'), 'utf8') }).token === 'vendas');
  ok('app.jsx real: ≥ 60 rotas derivadas', rotasReais.length >= 60, `n=${rotasReais.length}`);
  ok('app.jsx real: nenhum token "string" (typeof route === "string" não é rota)', !rotasReais.some((r) => r.tokens.includes('string')));
  ok('app.jsx real: pg-payment-gateways-page.jsx → payment-gateways (via adaptador)', tokenDoMockup('pg-payment-gateways-page.jsx', { rotas: rotasReais, mockupSrc: readFileSync(join(COWORK_DIR, 'pg-payment-gateways-page.jsx'), 'utf8') }).token === 'payment-gateways');

  // A-LOTE (2026-10-06): rota POR TELA, sanidade de rota e de hash, `--lado`.
  const rotasFx = 'const X = 1;\nconst ROTAS = {\n  "repair": { page: "R/Dash", aba: "p" },\n  "rep-painel": { page: "R/Dash" },\n  "rep-lista": { page: "R/Index", aba: "l" },\n};\nconst Y = { "fora": { page: "R/Fora" } };';
  const tabFx = rotasDaAncora(rotasFx);
  ok('rotasDaAncora: page → primeiro token', tabFx.get('R/Dash') === 'repair' && tabFx.get('R/Index') === 'rep-lista');
  ok('rotasDaAncora: não lê fora do bloco ROTAS', !tabFx.has('R/Fora'));
  ok('rotasDaAncora: âncora sem ROTAS → null', rotasDaAncora('window.SuperadminPage = 1;') === null);
  const rotasGrupo = [{ componente: 'SuperadminPage', tokens: ['superadmin', 'sa-negocios'], prefixos: [] }];
  ok('tokenDaTela: ROTAS ganha da rota do grupo', tokenDaTela({ id: 'R/Index', source: 'r-page.jsx' }, { rotas: [{ componente: 'RPage', tokens: ['repair'], prefixos: [] }], mockupSrc: 'window.RPage = 1;\n' + rotasFx }).token === 'rep-lista');
  ok('tokenDaTela MORDE: ROTAS sem a page desta tela → null (não cai no grupo)', tokenDaTela({ id: 'R/Outra', source: 'r-page.jsx' }, { rotas: [{ componente: 'RPage', tokens: ['repair'], prefixos: [] }], mockupSrc: 'window.RPage = 1;\n' + rotasFx }).token === null);
  ok('tokenDaTela: override ganha de tudo', tokenDaTela({ id: 'R/Index', source: 'r-page.jsx' }, { rotas: [], mockupSrc: rotasFx, override: { token: 'x' } }).token === 'x');
  ok('tokenDaTela: sem ROTAS → derivação do app.jsx (rota do grupo)', tokenDaTela({ id: 'superadmin/Negocios/Index', source: 'superadmin-page.jsx' }, { rotas: rotasGrupo, mockupSrc: 'window.SuperadminPage = 1;' }).token === 'superadmin');
  const colR = colisoesDeRota([{ id: 'S/A', token: 'superadmin' }, { id: 'S/B', token: 'superadmin' }, { id: 'S/C', token: 'sa-c' }, { id: 'S/D', token: null }]);
  ok('colisoesDeRota MORDE: duas telas no mesmo token', colR.get('S/A')?.com.join() === 'S/B' && colR.get('S/B')?.token === 'superadmin');
  ok('colisoesDeRota CONTROLE: token próprio e token nulo não colidem', !colR.has('S/C') && !colR.has('S/D'));
  ok('colisoesDeRota: mesma tela repetida não colide consigo', colisoesDeRota([{ id: 'S/A', token: 't' }, { id: 'S/A', token: 't' }]).size === 0);
  const colH = colisoesDeHash([{ id: 'M/A', module: 'M', sha: 'h1' }, { id: 'M/B', module: 'M', sha: 'h1' }, { id: 'M/C', module: 'M', sha: 'h2' }, { id: 'N/A', module: 'N', sha: 'h2' }]);
  ok('colisoesDeHash MORDE: mesmo módulo, mesmo sha', colH.get('M/A')?.join() === 'M/B' && colH.has('M/B'));
  ok('colisoesDeHash CONTROLE: sha diferente, ou outro módulo, não colide', !colH.has('M/C') && !colH.has('N/A'));
  ok('ladoDe: default ambos · aceita design/prod', ladoDe(null) === 'ambos' && ladoDe('design') === 'design' && ladoDe('prod') === 'prod');
  ok('ladoDe MORDE: valor fora da lista → null', ladoDe('vivo') === null);
  const repSrc = readFileSync(join(COWORK_DIR, 'repair-page.jsx'), 'utf8');
  const repTab = rotasDaAncora(repSrc);
  ok('espelho real: repair-page.jsx tem ROTAS por tela', !!repTab && repTab.get('Repair/Index') === 'rep-reparos' && repTab.get('Repair/Status/Index') === 'rep-status');
  ok('espelho real: as 6 telas medidas do Repair abrem 6 rotas distintas',
    new Set(['Repair/Dashboard/Index', 'Repair/DeviceModels/Index', 'Repair/Index', 'Repair/JobSheet/Index', 'Repair/ProducaoOficina/Index', 'Repair/Status/Index'].map((p) => repTab.get(p))).size === 6);

  ok('contratoDe: casa pelo campo tela',contratoDe('Backup/Index', [{ path: 'x.json', tela: 'Backup/Index' }]) === 'x.json');
  ok('contratoDe: sem casamento → null (não inventa D0)', contratoDe('Q/Index', [{ path: 'x.json', tela: 'Backup/Index' }]) === null);
  const contratosReais = lerContratos();
  ok('lerContratos real: Backup/Index tem contrato', contratoDe('Backup/Index', contratosReais) === 'governance/design/contracts/backup.contract.json');

  // D0 exige COPY, não só arquivo. Sem estes 4, gerar esqueleto em massa silenciaria o aviso
  // sem provar identidade — e o `gerar-contrato.mjs` emite exatamente esse esqueleto.
  ok('D0: contrato com copy prova identidade',
    contratoProvaIdentidade({ tela: 'X/Index', secoes: [{ id: 'topo', copy: ['Backup'] }] }) === true);
  ok('D0 MORDE: esqueleto (secoes sem copy) NÃO prova',
    contratoProvaIdentidade({ tela: 'X/Index', secoes: [{ id: 'topo' }, { id: 'lista', copy: [] }] }) === false);
  ok('D0 MORDE: copy só de espaço em branco NÃO prova',
    contratoProvaIdentidade({ tela: 'X/Index', secoes: [{ id: 'topo', copy: ['   ', ''] }] }) === false);
  ok('D0: sem secoes → não prova (e não estoura)',
    contratoProvaIdentidade({ tela: 'X/Index' }) === false);
  // CONTROLE NEGATIVO: o aperto não pode derrubar o caso bom que já existia acima.
  ok('D0 controle: os 2 esqueletos do repo saíram, o resto ficou',
    contratosReais.length > 0 && !contratosReais.some((c) => /essentials-metas|patrimonio-index/.test(c.path)));

  const jsonOk = JSON.stringify({ bugs: 0, shell: 0, sameTheme: true, rows: [{ dim: 'D2', veredito: 'IGUAL' }, { dim: 'D4', veredito: 'SEM-DADO' }] });
  const jsonBug = JSON.stringify({ bugs: 2, shell: 1, sameTheme: true, rows: [{ dim: 'D8', veredito: 'DIVERGE (bug)' }, { dim: 'D8', veredito: 'DIVERGE (bug)' }, { dim: 'SHELL', veredito: 'DIVERGE (a classificar)' }] });
  const c0 = interpretarCompare({ status: 0, stdout: jsonOk, stderr: '' });
  ok('interpretarCompare: rc0 → IGUAL, bugs 0, D2 igual, D4 sem-dado', c0.veredito === 'IGUAL' && c0.bugs === 0 && c0.porDim.D2.igual === 1 && c0.porDim.D4.semDado === 1);
  const c1 = interpretarCompare({ status: 1, stdout: jsonBug, stderr: '' });
  ok('interpretarCompare: rc1 → DIVERGE (bug), conta por dim', c1.veredito === 'DIVERGE (bug)' && c1.porDim.D8.bug === 2 && c1.porDim.SHELL.diverge === 1 && c1.porDim.SHELL.bug === 0);
  const c2 = interpretarCompare({ status: 2, stdout: jsonOk, stderr: '⛔ NÃO MEDI — o lado design veio do espelho local' });
  ok('interpretarCompare: rc2 COM relatório → medido, frescor não provado (não vira IGUAL)', c2.veredito === 'MEDIDO · frescor não provado' && c2.bugs === 0 && /espelho local/.test(c2.motivo));
  const c2s = interpretarCompare({ status: 2, stdout: '', stderr: '⛔ LADOS TROCADOS' });
  ok('interpretarCompare: rc2 SEM relatório → NÃO MEDI com motivo', c2s.veredito === 'NÃO MEDI' && /LADOS TROCADOS/.test(c2s.motivo) && c2s.bugs === null);
  ok('interpretarCompare: rc inesperado → ERRO', interpretarCompare({ status: 137, stdout: '', stderr: '' }).veredito === 'ERRO');
  ok('interpretarCompare: JSON precedido de prosa ainda parseia', interpretarCompare({ status: 0, stdout: 'aviso\n' + jsonOk, stderr: '' }).bugs === 0);

  const resumo = gerarResumo([
    { id: 'A/Index', source: 'a-page.jsx', token: 'a', frescor: 'nunca verificado', compare: c1 },
    { id: 'B/Index', source: 'b-page.jsx', token: null, veredito: 'NÃO MEDI', motivo: 'sem rota | derivável' },
  ], { baseUrl: 'http://x', bundle: 'abc', geradoEm: '2026-09-06T00:00:00Z' });
  ok('gerarResumo: linha por tela + veredito', /\| A\/Index \| a-page\.jsx \| a \| DIVERGE \(bug\) \| 2 \| 1 \|/.test(resumo));
  ok('gerarResumo: D8 mostra "2 bug" e SHELL "1 div"', /\| 2 bug \|/.test(resumo) && /\| 1 div \|/.test(resumo));
  ok('gerarResumo: tela não medida entra com motivo (pipe escapado)', /\| B\/Index \| b-page\.jsx \| — \| NÃO MEDI \|/.test(resumo) && /sem rota \/ derivável/.test(resumo));
  ok('gerarResumo: contagem por veredito', /- DIVERGE \(bug\): 1/.test(resumo) && /- NÃO MEDI: 1/.test(resumo));
  ok('gerarResumo: se declara DERIVADO e não-gate', /DERIVADO/.test(resumo) && /NÃO é gate/.test(resumo));

  const sr = extrairShellRoles('/* PROD */\nwindow.__SB_ROLES = {\n  "atalhoTopo": ".a"\n};\n\n/* DESIGN */\nwindow.__SB_ROLES = {\n  "atalhoTopo": ".b"\n};');
  ok('extrairShellRoles: lê os dois lados', sr.prod?.atalhoTopo === '.a' && sr.design?.atalhoTopo === '.b');
  const srReal = shellRolesDoDono();
  ok('shellRolesDoDono real: --shell-roles do design-diff parseia os 2 lados', !!srReal.prod?.atalhoTopo && !!srReal.design?.atalhoTopo);
  try { ok('sondaCanonica real: --probe do dono responde', sondaCanonica().length > 1000); } catch (e) { ok('sondaCanonica real', false, e.message); }

  // servidor do espelho — hermético num tmp com 2 arquivos
  const { mkdtempSync, rmSync } = await import('node:fs');
  const { tmpdir } = await import('node:os');
  const tmp = mkdtempSync(join(tmpdir(), 'ddl-'));
  writeFileSync(join(tmp, SHELL_HTML), '<!doctype html><title>fx</title>');
  writeFileSync(join(tmp, 'x.jsx'), 'window.X = 1;');
  const s = await servirEspelho(tmp);
  try {
    const r1 = await fetch(`http://127.0.0.1:${s.porta}/${SHELL_HTML}`);
    const r2 = await fetch(`http://127.0.0.1:${s.porta}/x.jsx?v=ab3`);
    const r3 = await fetch(`http://127.0.0.1:${s.porta}/nao-existe.jsx`);
    const r4 = await fetch(`http://127.0.0.1:${s.porta}/../design-diff-lote.mjs`);
    ok('servirEspelho: html 200 + mime', r1.status === 200 && /text\/html/.test(r1.headers.get('content-type') || ''));
    ok('servirEspelho: ?v= ignorado, jsx como script', r2.status === 200 && (await r2.text()) === 'window.X = 1;' && /javascript/.test(r2.headers.get('content-type') || ''));
    ok('servirEspelho: ausente → 404', r3.status === 404);
    ok('servirEspelho: não sai da raiz (../ → 404)', r4.status === 404);
  } finally { await s.fechar(); rmSync(tmp, { recursive: true, force: true }); }

  for (const c of checks) console.log(`${c.ok ? 'ok  ' : 'X   '}${c.nome}${c.detalhe ? ' — ' + c.detalhe : ''}`);
  const falhou = checks.filter((c) => !c.ok).length;
  console.log(`\n${checks.length - falhou}/${checks.length} ok · (partes puras — o render exige playwright + app vivo; veja --dry)`);
  return falhou ? 1 : 0;
}

/* ═══════════════════════════════════════════════════════════════════════════════════════
 * CLI
 * ═════════════════════════════════════════════════════════════════════════════════════ */
async function main() {
  if (flag('--selftest')) return selftest();
  const baseUrl = String(val('--base-url', 'http://127.0.0.1:8000')).replace(/\/+$/, '');
  const filtro = val('--tela');
  const urlForcada = val('--url');
  if (urlForcada && !filtro) { console.error('--url exige --tela (uma tela só)'); return 2; }
  const lado = ladoDe(val('--lado'));
  if (!lado) { console.error('--lado aceita design | prod | ambos'); return 2; }
  const planoObj = await montarPlano({ filtro, urlForcada, baseUrl, lado });
  if (!planoObj.plano.length) { console.error(`nenhuma tela anchored${filtro ? ` casa com "${filtro}"` : ''} no application-report`); return 2; }
  if (flag('--dry')) { imprimirPlano(planoObj, { baseUrl }); return 0; }
  return rodarLote(planoObj, {
    baseUrl,
    userId: Number(val('--user-id', '1')),
    cookie: process.env.DESIGN_DIFF_COOKIE || null,
    tema: val('--tema'),
    lado,
  });
}

const invocadoDireto = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invocadoDireto) {
  // `process.exitCode`, não `process.exit()`: no Windows, sair com sockets do servidor/fetch ainda
  // fechando estoura `Assertion failed: !(handle->flags & UV_HANDLE_CLOSING)` (medido no selftest).
  main()
    .then((rc) => { process.exitCode = rc; })
    .catch((e) => { console.error(`${e.naoMedi ? 'NÃO MEDI' : 'FALHOU'}: ${e.message}`); process.exitCode = e.naoMedi ? 2 : 1; });
}
