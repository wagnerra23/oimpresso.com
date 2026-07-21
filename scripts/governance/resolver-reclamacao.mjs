#!/usr/bin/env node
// @ts-check
/**
 * resolver-reclamacao.mjs — resolvedor reclamação → cadeia de responsabilidade.
 *
 * ENTRADA: uma reclamação em linguagem natural (PT-BR), ex:
 *   "quando dou desconto o total da fatura vem inflado"
 *
 * SAÍDA: a cadeia de artefatos responsáveis, quando resolvível:
 *   módulo → tópico → tela / rota / controller / função / model → teste que cobre
 *
 * ─── O QUE ESTE RESOLVEDOR É (e o que NÃO é) ────────────────────────────────
 *
 * É um LEITOR/COMPOSITOR read-only sobre índices DERIVADOS que já existem
 * (ADR 0256: derivado sobrevive, escrito à mão apodrece). NÃO cria índice novo,
 * NÃO computa cobertura/nota/gate, NÃO toca dado de tenant (só metadados em git).
 * Fontes:
 *   - memory/governance/catalog.json          — grafo módulo↔tabela↔componente↔api↔adr
 *   - memory/requisitos/<Mod>/topicos/*.md     — TÓPICO já carrega a cadeia (anchors)
 *   - memory/requisitos/<Mod>/SUPERFICIE.md    — telas/controllers/models por papel
 *
 * INSIGHT CENTRAL: um TÓPICO já É a cadeia. Seu bloco `anchors` (ADR 0345 +
 * topico.schema.json) tem screens/routes/controllers/functions/models/tests.
 * Então o trabalho do resolvedor é (1) ROTEAR a reclamação pro módulo/tópico
 * certo e (2) LER as âncoras. Quando não há tópico, cai pro derivado da
 * SUPERFÍCIE — com confiança MENOR e dizendo isso.
 *
 * ─── A CAMADA DE HONESTIDADE (o valor de verdade) ──────────────────────────
 *
 * O valor NÃO é acertar sempre — é ser honesto sobre o que NÃO resolve. O
 * matcher v0 é lexical determinístico (transparente, testável, custo zero, sem
 * alucinação). Ele NÃO "entende" a reclamação; casa tokens contra bag-of-words
 * derivada e RECUSA abaixo de um piso. Os vereditos possíveis:
 *   - resolvido     : tópico casou + a cadeia tem teste
 *   - sem-cobertura : cadeia achada mas anchors.tests vazio → não dá pra travar
 *   - parcial       : só o módulo casou (derivado da SUPERFÍCIE, confiança baixa)
 *   - ambiguo       : ≥2 candidatos dentro da margem → LISTA todos, escolhe NENHUM
 *   - incerto       : nada acima do piso → RECUSA rotear (não chuta no escuro)
 *
 * (Upgrade futuro, fora deste v0: crítico LLM PROPÕE módulo/tópico com
 * evidência, reconciliado por síntese central + aprovação humana — ADR 0345.
 * Fica adiado de propósito; v0 é o protótipo read-only pra validar a espinha.)
 *
 * USO:
 *   node scripts/governance/resolver-reclamacao.mjs "o total da fatura vem errado"
 *   node scripts/governance/resolver-reclamacao.mjs --json "..."   # saída p/ máquina
 *   node scripts/governance/resolver-reclamacao.mjs --demo         # 4 casos de demonstração
 *   node scripts/governance/resolver-reclamacao.mjs --selftest     # provas puras (CI)
 */

import { readFileSync, readdirSync, existsSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import assert from 'node:assert/strict';
import matter from 'gray-matter';
import { isPageScreenPath } from '../qa/page-path.mjs';

const ROOT = process.cwd();
const CATALOG = join(ROOT, 'memory', 'governance', 'catalog.json');
const REQ_DIR = join(ROOT, 'memory', 'requisitos');

// ─── Knobs de roteamento (revisáveis no diff — como CORE_APP_MODULES no surface) ──
export const KNOBS = {
  MATCH_FLOOR: 3.0, // score mínimo do 1º colocado pra não ser `incerto`
  AMBIG_RATIO: 0.6, // se score(2º)/score(1º) ≥ isto (e 2º ≥ FLOOR) → `ambiguo`
  W: { table: 3.0, prefix: 2.5, topicTitle: 3.0, topicClaim: 1.5, purpose: 1.0, superficie: 1.5, lexicon: 2.5 },
};

// ─── Léxico de domínio (curado, pequeno, transparente) ──────────────────────
// Traduz termo de NEGÓCIO PT-BR → dica de módulo. É a ÚNICA parte "escrita à
// mão" — mantida curta e revisável no diff. NÃO é o roteador (o grosso vem dos
// índices derivados); é só um empurrão pra jargão que o `purpose` não cobre.
// Formato: token/frase-normalizada → [modulos candidatos].
export const LEXICO = {
  boleto: ['Financeiro', 'RecurringBilling', 'PaymentGateway'],
  cobranca: ['Financeiro', 'RecurringBilling'],
  assinatura: ['RecurringBilling'],
  mensalidade: ['RecurringBilling'],
  pix: ['PaymentGateway', 'Financeiro'],
  remessa: ['PaymentGateway'],
  inadimplente: ['Financeiro'],
  receber: ['Financeiro'],
  pagar: ['Financeiro'],
  fatura: ['Financeiro', 'Produto', 'Sells'],
  nota: ['NfeBrasil', 'Fiscal', 'NFSe'],
  nfe: ['NfeBrasil'],
  nfce: ['NfeBrasil'],
  nfse: ['NFSe'],
  sefaz: ['NfeBrasil', 'Fiscal'],
  imposto: ['Fiscal', 'Produto'],
  estoque: ['Produto', 'Sells'],
  saldo: ['Produto'],
  variacao: ['Produto'],
  grade: ['Produto', 'Vestuario'],
  preco: ['Produto', 'Sells'],
  desconto: ['Produto', 'Sells'],
  total: ['Produto', 'Sells'],
  venda: ['Sells'],
  pdv: ['Sells'],
  caixa: ['Sells'],
  compra: ['Compras'],
  fornecedor: ['Compras'],
  ordem: ['OficinaAuto', 'Repair'],
  servico: ['OficinaAuto', 'Repair', 'NFSe'],
  veiculo: ['OficinaAuto'],
  oficina: ['OficinaAuto'],
  reparo: ['Repair'],
  whatsapp: ['Whatsapp'],
  mensagem: ['Whatsapp', 'Jana'],
  jana: ['Jana'],
  cliente: ['Crm', 'Sells'],
  proposta: ['Crm'],
  tarefa: ['ProjectMgmt'],
  ponto: ['Ponto'],
  marcacao: ['Ponto'],
  arquivo: ['Arquivos'],
  anexo: ['Arquivos'],
};

const STOP = new Set(
  ('a o e de da do das dos que nao não em um uma para pra por com os as no na se me meu minha esta este isso quando onde qual quais ' +
    'esta está sendo fica ficou vem vindo veio ta tá to tô ao aos à às pelo pela num numa mais menos muito pouco toda todo todos ' +
    'sempre nunca ainda já ja depois antes agora aqui ali la lá ele ela eles elas eu voce você nos nós vcs')
    .split(/\s+/),
);

// ─── Puros: normalização + tokenização ──────────────────────────────────────

/** stripAccents — remove diacríticos, lowercase. PURO. */
export function stripAccents(s) {
  return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

/** tokenize — palavras ≥3 chars, sem acento, sem stopword. PURO. */
export function tokenize(text) {
  return stripAccents(text)
    .split(/[^a-z0-9]+/)
    .filter((t) => t.length >= 3 && !STOP.has(t));
}

/** splitTableName — `transaction_sell_lines` → ['transaction','sell','lines']. PURO. */
export function splitTableName(t) {
  return stripAccents(t).split(/[^a-z0-9]+/).filter((x) => x.length >= 3 && !STOP.has(x));
}

// ─── Índice derivado (impuro: lê git-canon; ZERO dado de tenant) ─────────────

/** Lê catalog.json e monta {module → {purpose, prefix, tables[], components[], apis[]}}. */
export function loadModulesFromCatalog(catalogPath = CATALOG) {
  const c = JSON.parse(readFileSync(catalogPath, 'utf8'));
  const mods = {};
  for (const n of c.nodes) {
    if (n.type !== 'module') continue;
    mods[n.module] = {
      module: n.module,
      purpose: n.purpose || '',
      prefix: (n.permission_prefix || '').replace(/[.*]/g, ''),
      charter_adr: n.charter_adr || null,
      tables: [],
      components: [],
      apis: [],
    };
  }
  for (const e of c.edges) {
    const mod = e.from.replace('module:', '');
    if (!mods[mod]) continue;
    if (e.type === 'ownsTable' || e.type === 'consumesTable') mods[mod].tables.push(e.to.replace('table:', ''));
    if (e.type === 'hasComponent') mods[mod].components.push(e.to.replace('component:', ''));
    if (e.type === 'providesApi') mods[mod].apis.push(e.to.replace('api:', ''));
  }
  return mods;
}

/**
 * requisitoModules — módulos que têm SUPERFICIE.md ou topicos/, mesmo se NÃO forem
 * nó do catalog.json. Necessário porque módulos CLASSE B (Produto/Sells vivem no
 * núcleo UltimatePOS) aparecem em requisitos mas o catálogo usa outro nome
 * (ex: catálogo tem `ProductCatalogue`, requisitos tem `Produto`). Sem esta união o
 * roteador PERDERIA o sinal de tópico do módulo class-B — foi o bug pego na 1ª demo.
 * Também puxa `tabelas_dominio` da frontmatter da SUPERFÍCIE como sinal de tabela.
 */
export function requisitoModules(reqDir = REQ_DIR) {
  if (!existsSync(reqDir)) return [];
  return readdirSync(reqDir).filter((d) => {
    const p = join(reqDir, d);
    return statSync(p).isDirectory() && (existsSync(join(p, 'SUPERFICIE.md')) || existsSync(join(p, 'topicos')));
  }).map((mod) => {
    let tabelas = [];
    const sup = join(reqDir, mod, 'SUPERFICIE.md');
    if (existsSync(sup)) {
      try { tabelas = matter(readFileSync(sup, 'utf8')).data?.tabelas_dominio || []; } catch { /* ignore */ }
    }
    return { module: mod, tabelas };
  });
}

/** Glob de todos os tópicos + parse do frontmatter. Retorna lista de tópicos. */
export function loadTopicos(reqDir = REQ_DIR) {
  const out = [];
  if (!existsSync(reqDir)) return out;
  for (const mod of readdirSync(reqDir)) {
    const tdir = join(reqDir, mod, 'topicos');
    if (!existsSync(tdir) || !statSync(tdir).isDirectory()) continue;
    for (const f of readdirSync(tdir)) {
      if (!f.endsWith('.md')) continue;
      const path = join(tdir, f);
      try {
        const fm = matter(readFileSync(path, 'utf8')).data || {};
        out.push({
          id: fm.id || f.replace(/\.md$/, ''),
          module: fm.module || mod,
          title: fm.title || fm.id || f,
          kind: fm.kind || '',
          status: fm.status || '',
          anchors: fm.anchors || {},
          claimsText: (fm.claims || []).map((c) => c.text || '').join(' '),
          path: relative(ROOT, path).replace(/\\/g, '/'),
          review: fm.review || {},
        });
      } catch {
        /* frontmatter inválido → ignora (o gate de schema é dono disso, não este resolvedor) */
      }
    }
  }
  return out;
}

/** Lê a SUPERFÍCIE de um módulo → papéis {controllers[], models[], telas[], motor[]}. Best-effort. */
export function loadSuperficie(mod, reqDir = REQ_DIR) {
  const path = join(reqDir, mod, 'SUPERFICIE.md');
  if (!existsSync(path)) return null;
  const body = readFileSync(path, 'utf8');
  const fm = matter(body).data || {};
  const roles = {};
  let cur = null;
  for (const line of body.split(/\r?\n/)) {
    const h = line.match(/^##\s+(.+?)\s+—\s+\d+/);
    if (h) {
      cur = stripAccents(h[1]);
      roles[cur] = [];
      continue;
    }
    const b = line.match(/^\s*-\s+\[([^\]]+)\]\(([^)]+)\)/);
    if (b && cur) roles[cur].push({ label: b[1], path: b[2].replace(/^(\.\.\/)+/, '') });
  }
  const pick = (rx) => Object.entries(roles).filter(([k]) => rx.test(k)).flatMap(([, v]) => v);
  return {
    tabelas_dominio: fm.tabelas_dominio || [],
    controllers: pick(/controller/),
    models: pick(/model|entit/),
    telas: pick(/tela|inertia|react/),
    motor: pick(/motor|util|dominio/),
  };
}

// ─── Scoring (puro dado o índice) ───────────────────────────────────────────

/**
 * scoreModules — pontua cada módulo contra os tokens da reclamação.
 * Retorna lista ordenada [{module, score, hits[]}]. PURO (recebe índice pronto).
 * `hits` é a EVIDÊNCIA (qual token casou onde) — transparência é obrigatória.
 */
export function scoreModules(tokens, mods, topicos, lexico = LEXICO, W = KNOBS.W) {
  const set = new Set(tokens);
  const scored = [];
  const topicByMod = {};
  for (const t of topicos) (topicByMod[t.module] ??= []).push(t);

  for (const m of Object.values(mods)) {
    const hits = [];
    let score = 0;
    const add = (pts, where, token) => { score += pts; hits.push({ token, where, pts }); };

    // tabelas do módulo (sinal forte — tabela é o "substantivo" do domínio)
    const tableWords = new Set(m.tables.flatMap(splitTableName));
    for (const tok of set) if (tableWords.has(tok)) add(W.table, 'tabela', tok);
    // prefixo de permissão (ex: "financeiro", "produto")
    if (m.prefix && set.has(stripAccents(m.prefix))) add(W.prefix, 'prefixo', stripAccents(m.prefix));
    // purpose (texto livre do catálogo)
    const purposeWords = new Set(tokenize(m.purpose));
    for (const tok of set) if (purposeWords.has(tok)) add(W.purpose, 'purpose', tok);
    // títulos + claims dos tópicos do módulo (sinal de tópico)
    for (const t of topicByMod[m.module] || []) {
      const titleWords = new Set(tokenize(t.title + ' ' + t.id.replace(/-/g, ' ')));
      for (const tok of set) if (titleWords.has(tok)) add(W.topicTitle, `topico:${t.id}`, tok);
      const claimWords = new Set(tokenize(t.claimsText));
      for (const tok of set) if (claimWords.has(tok)) add(W.topicClaim, `topico-claim:${t.id}`, tok);
    }

    if (score > 0) scored.push({ module: m.module, score, hits });
  }

  // léxico de domínio: empurra módulos citados por jargão de negócio
  for (const tok of set) {
    for (const mod of lexico[tok] || []) {
      let row = scored.find((s) => s.module === mod);
      if (!row) { row = { module: mod, score: 0, hits: [] }; scored.push(row); }
      row.score += W.lexicon;
      row.hits.push({ token: tok, where: 'lexico', pts: W.lexicon });
    }
  }

  return scored.sort((a, b) => b.score - a.score);
}

/** scoreTopicos — dado o módulo escolhido, ranqueia seus tópicos pela reclamação. PURO. */
export function scoreTopicos(tokens, topicos, W = KNOBS.W) {
  const set = new Set(tokens);
  return topicos
    .map((t) => {
      let score = 0;
      const hits = [];
      const titleWords = new Set(tokenize(t.title + ' ' + t.id.replace(/-/g, ' ')));
      for (const tok of set) if (titleWords.has(tok)) { score += W.topicTitle; hits.push(tok); }
      const claimWords = new Set(tokenize(t.claimsText));
      for (const tok of set) if (claimWords.has(tok)) { score += W.topicClaim; hits.push(tok); }
      return { topico: t, score, hits: [...new Set(hits)] };
    })
    .filter((r) => r.score > 0)
    .sort((a, b) => b.score - a.score);
}

/**
 * classifyRanking — dado o ranking de módulos, decide o VEREDITO de roteamento.
 * PURO. É o núcleo da honestidade: detecta `incerto` (abaixo do piso) e `ambiguo`
 * (2º colado no 1º). O selftest tem controle-negativo pros dois.
 */
export function classifyRanking(scored, knobs = KNOBS) {
  if (!scored.length || scored[0].score < knobs.MATCH_FLOOR) {
    return { verdict: 'incerto', candidatos: scored.slice(0, 3) };
  }
  const top = scored[0];
  const second = scored[1];
  if (second && second.score >= knobs.MATCH_FLOOR && second.score / top.score >= knobs.AMBIG_RATIO) {
    // empate dentro da margem: lista os que estão dentro da margem, escolhe NENHUM
    const dentro = scored.filter((s) => s.score >= knobs.MATCH_FLOOR && s.score / top.score >= knobs.AMBIG_RATIO);
    return { verdict: 'ambiguo', candidatos: dentro };
  }
  return { verdict: 'ok', candidatos: [top] };
}

// ─── Montagem da cadeia + honestidade final (impuro: lê SUPERFÍCIE) ──────────

/** chainFromTopico — a cadeia É os anchors do tópico. PURO. */
export function chainFromTopico(t) {
  const a = t.anchors || {};
  return {
    fonte: 'topico',
    confianca: 'alta',
    topico: { id: t.id, title: t.title, status: t.status, path: t.path, review: t.review },
    telas: a.screens || [],
    rotas: a.routes || [],
    controllers: a.controllers || [],
    funcoes: a.functions || [],
    models: a.models || [],
    tabelas: a.tables || [],
    testes: a.tests || [],
    adrs: a.adrs || [],
  };
}

/** chainFromSuperficie — sem tópico: candidatos derivados da SUPERFÍCIE (confiança baixa). */
export function chainFromSuperficie(mod, reqDir = REQ_DIR) {
  const s = loadSuperficie(mod, reqDir);
  if (!s) return { fonte: 'superficie', confianca: 'baixa', vazio: true, telas: [], controllers: [], funcoes: [], models: [], testes: [] };
  return {
    fonte: 'superficie',
    confianca: 'baixa',
    // telas REAIS só (dropa _components e .charter.md via page-path.mjs — mesma régua do screen-coverage)
    telas: s.telas.map((x) => x.path).filter((p) => isPageScreenPath(p)),
    controllers: s.controllers.map((x) => x.path),
    funcoes: s.motor.map((x) => x.path),
    models: s.models.map((x) => x.path),
    tabelas: s.tabelas_dominio,
    testes: [], // a SUPERFÍCIE não linka teste-por-tópico → LACUNA honesta
    adrs: [],
  };
}

/** finalVerdict — refina o veredito de roteamento com o estado da CADEIA (cobertura/lacuna). PURO. */
export function finalVerdict(routingVerdict, chain) {
  if (routingVerdict === 'incerto') return 'incerto';
  if (routingVerdict === 'ambiguo') return 'ambiguo';
  if (chain.fonte === 'superficie') return 'parcial'; // só módulo, derivado
  // tópico casou:
  if (!chain.testes || chain.testes.length === 0) return 'sem-cobertura';
  return 'resolvido';
}

// ─── Orquestração de 1 reclamação ───────────────────────────────────────────

export function resolve(reclamacao, opts = {}) {
  const reqDir = opts.reqDir || REQ_DIR;
  const catalogPath = opts.catalogPath || CATALOG;
  const tokens = tokenize(reclamacao);
  const mods = loadModulesFromCatalog(catalogPath);
  // União: módulos class-B (Produto/Sells) vivem em requisitos, não no catálogo.
  for (const { module: rm, tabelas } of requisitoModules(reqDir)) {
    if (!mods[rm]) mods[rm] = { module: rm, purpose: '', prefix: '', charter_adr: null, tables: [], components: [], apis: [] };
    for (const t of tabelas) if (!mods[rm].tables.includes(t)) mods[rm].tables.push(t);
  }
  const topicos = loadTopicos(reqDir);

  const scored = scoreModules(tokens, mods, topicos);
  const routing = classifyRanking(scored, opts.knobs || KNOBS);

  const ambiguidades = [];
  const lacunas = [];
  const resultados = [];

  if (routing.verdict === 'incerto') {
    lacunas.push('Nenhum módulo passou do piso de confiança — reclamação vaga ou vocabulário fora dos índices. Refinar com o cliente ou ampliar o léxico.');
    return { reclamacao, tokens, verdict: 'incerto', ambiguidades, lacunas, candidatos_fracos: scored.slice(0, 3), resultados };
  }

  if (routing.verdict === 'ambiguo') {
    ambiguidades.push(
      `A reclamação casa com ${routing.candidatos.length} módulos dentro da margem: ` +
        routing.candidatos.map((c) => `${c.module} (${c.score.toFixed(1)})`).join(' · ') +
        '. O resolvedor NÃO escolhe — precisa de desambiguação humana ou mais contexto.',
    );
  }

  // pra cada módulo candidato, tenta descer pro tópico e montar a cadeia
  for (const cand of routing.candidatos) {
    const modTopicos = topicos.filter((t) => t.module === cand.module);
    const topRank = scoreTopicos(tokens, modTopicos);
    let chain;
    if (topRank.length && topRank[0].score >= KNOBS.W.topicTitle) {
      chain = chainFromTopico(topRank[0].topico);
      // ambiguidade DE TÓPICO dentro do módulo
      if (topRank[1] && topRank[1].score / topRank[0].score >= KNOBS.AMBIG_RATIO) {
        ambiguidades.push(
          `Dentro de ${cand.module}, ≥2 tópicos casam: ` +
            topRank.slice(0, 2).map((r) => `${r.topico.id} (${r.score.toFixed(1)})`).join(' · ') + '.',
        );
      }
    } else {
      chain = chainFromSuperficie(cand.module, reqDir);
      if (modTopicos.length === 0) {
        lacunas.push(`${cand.module} ainda NÃO tem tópico — cadeia derivada da SUPERFÍCIE (confiança baixa). Criar tópico trava a âncora.`);
      } else {
        lacunas.push(`${cand.module} tem tópico(s) mas nenhum casou a reclamação — pode faltar tópico pro tema específico.`);
      }
    }

    const verdict = finalVerdict(routing.verdict === 'ambiguo' ? 'ambiguo' : 'ok', chain);
    if (verdict === 'sem-cobertura') {
      lacunas.push(`Tópico "${chain.topico.id}" achado, mas SEM teste ancorado → a reclamação não pode ser travada por regressão hoje. Escrever teste é o próximo passo.`);
    }
    resultados.push({ module: cand.module, score: cand.score, evidencia: cand.hits, verdict, chain });
  }

  const verdictGeral = routing.verdict === 'ambiguo' ? 'ambiguo' : (resultados[0] ? resultados[0].verdict : 'incerto');
  return { reclamacao, tokens, verdict: verdictGeral, ambiguidades, lacunas, resultados };
}

// ─── Render humano (PT-BR) ──────────────────────────────────────────────────

const ICON = { resolvido: '✅', 'sem-cobertura': '🟡', parcial: '🟠', ambiguo: '⚠️', incerto: '❓' };

function renderChain(chain, pad = '     ') {
  // tópico = âncora curada (mostra tudo); superfície = derivado ruidoso (capa em CAP).
  const CAP = chain.fonte === 'topico' ? Infinity : 6;
  const line = (label, arr) => {
    if (!arr || !arr.length) return `${pad}${label.padEnd(12)} —`;
    const shown = arr.slice(0, CAP);
    const extra = arr.length > CAP ? `\n${pad}${' '.repeat(12)}  (+${arr.length - CAP} mais — derivado; criar tópico afunila)` : '';
    return `${pad}${label.padEnd(12)} ${shown.join('\n' + pad + ' '.repeat(12) + ' ')}${extra}`;
  };
  const out = [];
  if (chain.fonte === 'topico') out.push(`${pad}tópico       ${chain.topico.id}  (${chain.topico.status}) → ${chain.topico.path}`);
  else out.push(`${pad}tópico       — (derivado da SUPERFÍCIE, confiança baixa)`);
  out.push(line('telas', chain.telas));
  out.push(line('rotas', chain.rotas));
  out.push(line('controllers', chain.controllers));
  out.push(line('funções', chain.funcoes));
  out.push(line('models', chain.models));
  out.push(line('tabelas', chain.tabelas));
  out.push(line('testes', chain.testes));
  if (chain.adrs && chain.adrs.length) out.push(line('ADRs', chain.adrs));
  return out.join('\n');
}

export function render(res) {
  const L = [];
  L.push(`\n📣 Reclamação: "${res.reclamacao}"`);
  L.push(`   tokens: ${res.tokens.join(', ') || '(nenhum)'}`);
  L.push(`   veredito: ${ICON[res.verdict] || ''} ${res.verdict.toUpperCase()}`);
  if (res.verdict === 'incerto') {
    L.push('\n   ❓ Não roteou com confiança. Candidatos fracos (abaixo do piso):');
    for (const c of res.candidatos_fracos || []) L.push(`      · ${c.module} (${c.score.toFixed(1)})`);
  }
  for (const r of res.resultados || []) {
    L.push(`\n   ${ICON[r.verdict] || ''} módulo ${r.module}  [score ${r.score.toFixed(1)} · ${r.verdict}]`);
    const ev = r.evidencia.slice(0, 6).map((h) => `${h.token}→${h.where}`).join(', ');
    L.push(`     por quê: ${ev}${r.evidencia.length > 6 ? ' …' : ''}`);
    L.push(renderChain(r.chain));
  }
  if (res.ambiguidades.length) {
    L.push('\n   ⚠️  AMBIGUIDADES (o resolvedor não escolhe no escuro):');
    for (const a of res.ambiguidades) L.push(`      · ${a}`);
  }
  if (res.lacunas.length) {
    L.push('\n   🕳️  LACUNAS (o que falta pra fechar a cadeia):');
    for (const g of res.lacunas) L.push(`      · ${g}`);
  }
  return L.join('\n');
}

// ─── Demos + selftest + CLI ─────────────────────────────────────────────────

const DEMOS = [
  'quando dou desconto o total da fatura vem inflado, cobrou errado', // → Produto/topico calculo-total-fatura
  'não consigo emitir o boleto da cobrança do cliente',                 // → ambíguo Financeiro/RecurringBilling/PaymentGateway
  'a ordem de serviço da oficina não salva o veículo',                  // → OficinaAuto (sem tópico → parcial)
  'o sistema está lento hoje',                                          // → incerto (recusa)
];

if (process.argv.includes('--selftest')) {
  // puros: tokenização
  assert.deepEqual(tokenize('O total da FATURA veio ERRADO!'), ['total', 'fatura', 'veio', 'errado'].filter((t) => !STOP.has(t)));
  assert.equal(stripAccents('Não Variação'), 'nao variacao');
  assert.deepEqual(splitTableName('transaction_sell_lines'), ['transaction', 'sell', 'lines']);

  // classifyRanking: piso (incerto) — CONTROLE-NEGATIVO
  assert.equal(classifyRanking([{ module: 'X', score: 1.0, hits: [] }]).verdict, 'incerto');
  assert.equal(classifyRanking([]).verdict, 'incerto');
  // classifyRanking: empate (ambiguo) — CONTROLE-NEGATIVO (sem isto, ambiguidade quebraria calada = teatro)
  assert.equal(
    classifyRanking([{ module: 'A', score: 6, hits: [] }, { module: 'B', score: 5, hits: [] }]).verdict,
    'ambiguo',
  );
  // classifyRanking: vencedor claro (ok) — 2º longe do 1º
  assert.equal(
    classifyRanking([{ module: 'A', score: 10, hits: [] }, { module: 'B', score: 3, hits: [] }]).verdict,
    'ok',
  );

  // finalVerdict: tópico sem teste → sem-cobertura; com teste → resolvido; superfície → parcial
  assert.equal(finalVerdict('ok', { fonte: 'topico', testes: [] }), 'sem-cobertura');
  assert.equal(finalVerdict('ok', { fonte: 'topico', testes: ['t.php'] }), 'resolvido');
  assert.equal(finalVerdict('ok', { fonte: 'superficie', testes: [] }), 'parcial');
  assert.equal(finalVerdict('ambiguo', { fonte: 'topico', testes: ['t.php'] }), 'ambiguo');

  // chainFromTopico: a cadeia É os anchors
  const ch = chainFromTopico({ id: 'x', title: 'X', status: 'ativo', path: 'p', anchors: { functions: ['f'], tests: ['t'] } });
  assert.deepEqual(ch.funcoes, ['f']);
  assert.deepEqual(ch.testes, ['t']);
  assert.equal(ch.fonte, 'topico');

  console.log('resolver-reclamacao selftest: tokenização + piso + ambiguidade + veredito + cadeia OK');
  process.exit(0);
}

if (process.argv.includes('--demo')) {
  const json = process.argv.includes('--json');
  const results = DEMOS.map((d) => resolve(d));
  if (json) console.log(JSON.stringify(results, null, 2));
  else for (const r of results) console.log(render(r));
  process.exit(0);
}

const args = process.argv.slice(2).filter((a) => !a.startsWith('--'));
const json = process.argv.includes('--json');
if (!args.length) {
  console.error('uso: node scripts/governance/resolver-reclamacao.mjs "<reclamação>"  [--json]');
  console.error('     node scripts/governance/resolver-reclamacao.mjs --demo   (4 casos)');
  console.error('     node scripts/governance/resolver-reclamacao.mjs --selftest');
  process.exit(2);
}
const res = resolve(args.join(' '));
console.log(json ? JSON.stringify(res, null, 2) : render(res));
