#!/usr/bin/env node
// maquinas-inventario.mjs — DERIVA um índice único e legível de TODAS as "máquinas"
// (workflows, gates, hooks, skills, agents, scripts, baselines JSON) a partir das
// PRÓPRIAS fontes — reusa os índices já gerados (hooks/skills/workflows) e preenche o
// gap não-catalogado (scripts/** + agents). NÃO escreve descrição à mão: cada linha vem
// do cabeçalho/frontmatter/_meta do próprio arquivo. Regerar: node scripts/governance/maquinas-inventario.mjs --write
import { readFileSync, writeFileSync, readdirSync, existsSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';

const ROOT = process.cwd();
const MODE = process.argv.includes('--write') ? 'write'
  : process.argv.includes('--check') ? 'check' : 'dry';

// --- helpers de extração (medido, nunca inventado) ---
const firstDescComment = (txt) => {
  // padrão canônico: `// nome.mjs — descrição`  OU  bloco `/** nome — descrição`
  const lines = txt.split('\n');
  for (const l of lines.slice(0, 12)) {
    let m = l.match(/^\s*(?:\/\/|\*)\s*[\w.-]+\.(?:mjs|js|cjs)\s*[—-]\s*(.+)$/);
    if (m) return m[1].trim();
    m = l.match(/^\s*(?:\/\/|\*)\s*[\w.-]+\s*[—-]\s*(.+)$/);
    if (m && !/^!/.test(l) && !/@ts-check|eslint|node/.test(l)) return m[1].trim();
  }
  // fallback: 1ª linha de comentário significativa (sem o prefixo `nome —`)
  for (const l of lines.slice(0, 15)) {
    const m = l.match(/^\s*(?:\/\/|\*)\s+(.{12,})$/);
    if (!m) continue;
    const s = m[1].trim();
    if (/^[!/*]|@ts-check|eslint-disable|^https?:|^usage:/i.test(s)) continue;
    return s.replace(/\*\/\s*$/, '').trim();
  }
  return '(sem descrição no cabeçalho)';
};
const frontmatterField = (txt, field) => {
  // block scalar PRIMEIRO: `field: |` seguido de linhas indentadas (senão o same-line captura o `|`)
  const block = txt.match(new RegExp(`^${field}:[ \\t]*[|>][-+]?[ \\t]*\\r?\\n((?:[ \\t]+.*\\r?\\n?)+)`, 'm'));
  if (block) {
    const firstLine = block[1].split('\n').map((s) => s.trim()).find((s) => s.length > 0);
    return firstLine || null;
  }
  // same-line: `field: valor` (agora seguro — o bloco já foi tratado)
  const same = txt.match(new RegExp(`^${field}:[ \\t]+(.+)$`, 'm'));
  if (same) return same[1].trim().replace(/^["']|["']$/g, '');
  return null;
};
const firstSentence = (s, max = 160) => {
  if (!s) return '';
  const cut = s.split(/(?<=[.!?])\s/)[0];
  return (cut.length > max ? cut.slice(0, max) + '…' : cut);
};
const trunc = (s, n = 150) => (s && s.length > n ? s.slice(0, n) + '…' : s || '');
const lsDir = (d) => (existsSync(join(ROOT, d)) ? readdirSync(join(ROOT, d)) : []);
// Célula de tabela markdown: `|` cru PARTE a tabela (e matcher de hook tem `|` —
// `Write|Edit|MultiEdit`). Escapa e achata quebras de linha.
const cell = (s) => String(s ?? '').split('|').join('\\|').split(/\r?\n/).join(' ');
const leSe = (p) => { try { return readFileSync(join(ROOT, p), 'utf8'); } catch { return ''; } };

const readTree = (dir, exts, acc = [], depth = 0, maxDepth = 4) => {
  if (!existsSync(join(ROOT, dir)) || depth > maxDepth) return acc;
  for (const e of readdirSync(join(ROOT, dir))) {
    const rel = `${dir}/${e}`;
    let st; try { st = statSync(join(ROOT, rel)); } catch { continue; }
    if (st.isDirectory()) readTree(rel, exts, acc, depth + 1, maxDepth);
    else if (exts.some((x) => e.endsWith(x))) {
      try { acc.push({ rel, txt: readFileSync(join(ROOT, rel), 'utf8') }); } catch {}
    }
  }
  return acc;
};

// ═══════════════════════════════════════════════════════════════════════════════
// EIXO **OWNER** — MEDIDO E RECUSADO (2026-08-16). Não existe coluna de owner aqui.
//
// `.github/CODEOWNERS` tem 20 regras com dono; TODAS as 20 resolvem para o MESMO
// handle (`@wagnerra23`) — as outras 4 pessoas do time aparecem só como `# TODO`,
// porque o próprio arquivo declara que ninguém além do [W] é colaborador do repo.
// E o único path de MÁQUINA que casa com alguma regra é `.github/`: 123 de 474
// (25,9%), todas na tabela 1, com valor 100% idêntico — 0 nas outras 7 tabelas.
// Coluna com um valor só não distingue nada: é decoração, e o passo de QC deste
// índice trata isso como ruído, não como cobertura.
//
// A fonte canônica de ownership (matriz §3 do TEAM.md, per ADR 0070) é por TIPO DE
// TASK e por MÓDULO — não há aresta mecânica de `scripts/governance/x.mjs` ou
// `.claude/hooks/y.mjs` pra linha nenhuma dela. E o `owner:` de frontmatter é
// boilerplate legado que o próprio TEAM.md §3.1 proíbe ler como posse.
//
// Reabre quando o CODEOWNERS cobrir `.claude/**` + `scripts/**` com handles
// DISTINTOS — aí owner passa a ser derivável de verdade, e a coluna nasce medida.
// ═══════════════════════════════════════════════════════════════════════════════

// ── EIXO **DOCUMENTO** (D0) — qual doc canônico cita esta máquina ──────────────
// Derivado por varredura do corpus `.md` de `memory/**` + `.claude/**` + `docs/**`.
// NÃO conta censo gerado (este arquivo, _HOOKS-INDEX, _SKILLS-INDEX, AUTOMATIONS,
// PAINEL-SISTEMA): ser listado por outro inventário não é "estar documentado" —
// contaria a si mesmo e daria 100% de cobertura falsa em toda tabela.
//
// A varredura é UMA passada tokenizando cada doc (3 regexes) e casando contra um
// mapa token→máquina. A forma ingênua (474 × 3.818 `includes` sobre 17,8 MB) mede o
// mesmo e custa ~9,6 s; esta custa uma fração porque é O(corpus), não O(corpus×máquinas).
const DOCS_GERADOS = new Set([
  'memory/reference/MAQUINAS-INVENTARIO.md',
  '.claude/hooks/_HOOKS-INDEX.md',
  '.claude/skills/_SKILLS-INDEX.md',
  'memory/governance/AUTOMATIONS.md',
  'memory/reference/PAINEL-SISTEMA.md',
]);
const corpusDocs = [
  ...readTree('memory', ['.md'], [], 0, 8),
  ...readTree('.claude', ['.md'], [], 0, 8),
  ...readTree('docs', ['.md'], [], 0, 8),
].filter((d) => !DOCS_GERADOS.has(d.rel));

// Precedência de canonicidade. Sessão/handoff são append-only e HISTÓRICOS: citação
// lá prova que a máquina existiu num dia, não que algum doc vivo a governa — por isso
// caem no fundo e viram um marcador próprio, nunca um ponteiro de "documento dono".
//
// O 2º nível é DERIVADO do próprio CLAUDE.md (os `@imports` que ele carrega em toda
// sessão = o canon always-on: proibicoes, how-trabalhar, regras-time…). Fixar essa
// lista à mão aqui apodreceria no dia em que o CLAUDE.md mudasse os imports.
// CONTROLE POSITIVO que motivou este nível: `block-test-fora-ct100.mjs` é citado
// NOMINALMENTE em `memory/proibicoes.md` (§Ambiente, com o hook pelo nome), e a v1
// desta precedência devolvia `requisitos/Forja/RUNBOOK-gantt.md` — porque punha todo
// `memory/requisitos/**` acima da raiz. Canon Tier-0 perdendo pra RUNBOOK de módulo.
const CANON_RAIZ = new Set(['CLAUDE.md']);
for (const m of leSe('CLAUDE.md').matchAll(/^@([\w./-]+\.md)\s*$/gm)) CANON_RAIZ.add(m[1]);
const rankDoc = (rel) =>
  rel.startsWith('memory/sessions/') || rel.startsWith('memory/handoffs/') ? 9
  : rel.startsWith('memory/decisions/proposals/') ? 4
  : rel.startsWith('memory/decisions/') ? 0
  : CANON_RAIZ.has(rel) ? 1
  : rel.startsWith('memory/requisitos/') ? 2
  : rel.startsWith('memory/reference/') ? 3
  : rel.startsWith('.claude/') ? 5
  : 6;

// token → máquinas. Nome de arquivo (com extensão) é preciso por construção. Nome NU
// (skill/agent) é ambíguo — `curador` casa 76 docs por ser palavra comum do PT — então
// só conta em forma DELIMITADA: entre crases ou como segmento `skills/x` / `agents/x`.
// Medido: solto=76 → crase=7. O estrito muda o total em 2 máquinas (418→416): o risco
// de FP é real mas concentrado, e a forma delimitada o elimina sem perder sinal.
// O mapa é construído SEM saber quem são as máquinas: cada doc é tokenizado e os
// tokens viram chaves. As tabelas consultam depois. Assim a enumeração das máquinas
// continua morando numa seção só (a que imprime a tabela) — sem lista paralela pra
// drifar, que é como um índice deste tipo apodrece.
const RX_ARQUIVO = /[\w.-]+\.(?:mjs|js|cjs|yml|yaml|json)/g;
const RX_CRASE = /`([a-z0-9][\w.-]*)`/gi;
const RX_PASTA = /(?:skills|agents)\/([\w.-]+)/g;

const citacoes = new Map(); // token -> { n, melhor:{rank, rel} }
for (const d of corpusDocs) {
  const r = rankDoc(d.rel);
  // conta OCORRÊNCIAS (não só presença): quantas vezes o doc fala da máquina é o
  // desempate honesto entre docs de mesma precedência — mede o quanto ele trata do
  // assunto. O critério que estava aqui antes (path mais curto) mede o tamanho do
  // nome do arquivo, não a relevância.
  const toks = new Map();
  const conta = (t) => toks.set(t, (toks.get(t) || 0) + 1);
  for (const m of d.txt.matchAll(RX_ARQUIVO)) conta(m[0]);
  for (const m of d.txt.matchAll(RX_CRASE)) conta(m[1]);
  for (const m of d.txt.matchAll(RX_PASTA)) conta(m[1]);
  for (const [t, c] of toks) {
    let e = citacoes.get(t);
    if (!e) { e = { docs: [] }; citacoes.set(t, e); }
    e.docs.push({ rel: d.rel, c, rank: r });
  }
}

// O nome NU (sem extensão) é como o canon de fato cita a máquina: a ADR 0347 escreve
// `deadlink-gate` 8× e `deadlink-gate.yml` ZERO vez — buscar só o nome-com-extensão
// devolvia uma proposta solta e perdia a ADR que existe PRA ela. Só entra quando tem
// hífen: `ci` e `deploy` (as 2 únicas sem hífen entre 374 máquinas nomeadas por arquivo)
// são palavras comuns e casariam qualquer crase do repo.
const nuDe = (nome) => {
  const b = nome.split('/').pop().replace(/\.(mjs|js|cjs|yml|yaml|json|md)$/, '');
  return b.includes('-') ? b : null;
};

// `self` = paths do próprio artefato: um doc não se cita como documentação de si.
// Sem isto, todo SKILL.md documentaria a própria skill e a coluna daria 100%.
//
// ⚠️ O QUE A COLUNA AFIRMA — e o que ela NÃO afirma. Ela devolve **o citador de maior
// precedência**, não "o doc DONO da máquina". Citação não prova posse, e quando N docs
// de mesma precedência citam, nenhum desempate é *correto* — só defensável. Limite
// MEDIDO (2026-08-16, `block-figma-without-optin.mjs`): a ADR 0299 (que existe pra
// regra que o hook aplica) cita em 3 formas tokenizáveis e a ADR 0315 em 4, então a
// densidade devolve a 0315. As duas são rank 0 e nenhuma leva o nome do hook no
// arquivo — não há sinal derivável que separe as duas. Por isso o `+N` fica na célula:
// ele é o aviso de que existe mais de um citador e a escolha foi por regra, não por
// autoridade. Quem precisa do dono lê o `+N` e vai olhar.
const documentoDe = (tokens, selfPrefixos = []) => {
  const proprio = (rel) => selfPrefixos.some((p) => rel === p || rel.startsWith(p.endsWith('/') ? p : `${p}/`));
  const alvos = tokens.filter(Boolean);
  // "about-ness": doc cujo NOME carrega o nome da máquina é um doc ESCRITO PRA ELA
  // (`0347-deadlink-gate-required-…md`), não um que a menciona de passagem. Desempata
  // dentro do mesmo rank — sem isto, 70 ADRs citando um baseline caem no alfabético,
  // que é sorteio com cara de critério.
  const sobre = (rel) => alvos.some((t) => rel.split('/').pop().includes(t));
  let melhor = null;
  const vistos = new Map(); // rel -> ocorrências somadas (token pode repetir o mesmo doc)
  for (const t of alvos) {
    const e = citacoes.get(t);
    if (!e) continue;
    for (const d of e.docs) {
      if (proprio(d.rel)) continue;
      vistos.set(d.rel, (vistos.get(d.rel) || 0) + d.c);
    }
  }
  for (const [rel, c] of vistos) {
    // precedência → é-sobre-a-máquina → densidade de citação → path curto → alfabético
    const cand = { rank: rankDoc(rel), sobre: sobre(rel) ? 0 : 1, c, len: rel.length, rel };
    if (!melhor) { melhor = cand; continue; }
    // menor é melhor em rank/sobre/len; MAIOR é melhor em c (densidade)
    const chaves = [cand.rank - melhor.rank, cand.sobre - melhor.sobre,
      melhor.c - cand.c, cand.len - melhor.len, cand.rel < melhor.rel ? -1 : 1];
    if (chaves.find((d) => d !== 0) < 0) melhor = cand;
  }
  const n = vistos.size;
  if (!melhor) return '—';
  // rank 9 = só sessão/handoff: existe rastro, mas nenhum doc VIVO governa a máquina.
  if (melhor.rank === 9) return `(só sessão/handoff · ${n})`;
  return `\`${melhor.rel}\`${n > 1 ? ` +${n - 1}` : ''}`;
};

// ── EIXO **EVIDÊNCIA** (D0) — esta máquina tem PROVA de que morde ──────────────
// Os donos do tema já existem; este índice só os LÊ (não abre um 3º medidor):
//   `selftest`   → o script é rodado pelo `gate-selftest.mjs` contra o par de fixtures
//                  boa/ruim de `tests/governance-fixtures/` (o caso ruim TEM que sair 1).
//   `bite-log`   → está no array do `design-gate-bites.mjs` (DR-2a da ADR 0336).
//   `test`       → tem `*.test.mjs` irmão no disco.
//   `hook-bites` → o dead man's switch de runtime (`hook-bites.mjs`) conhece o hook.
// `test (fora do CI)` é a distinção que o `selftest-registry-check` já faz e que
// importa aqui: o teste existe no disco mas nenhum workflow o invoca — verde na
// máquina do dev, nunca no CI. É cobertura narrada, não testada.
const SELFTEST_PATHS = new Set(
  [...leSe('scripts/governance/gate-selftest.mjs').matchAll(/script\('[^']+',\s*'([^']+)'\)/g)].map((m) => m[1]));
const BITELOG_PATHS = new Set(
  [...leSe('scripts/governance/design-gate-bites.mjs').matchAll(/cmd:\s*\['([^']+)'/g)].map((m) => m[1]));
const HOOK_BITES_TXT = leSe('scripts/governance/hook-bites.mjs');
const WF_YML = readTree('.github/workflows', ['.yml', '.yaml']);
const WF_TXT = WF_YML.map((w) => w.txt).join('\n');

const evidenciaDe = (rel) => {
  const tags = [];
  const irmao = rel.replace(/\.(mjs|js|cjs)$/, '.test.$1');
  if (SELFTEST_PATHS.has(rel)) tags.push('selftest');
  if (BITELOG_PATHS.has(rel)) tags.push('bite-log');
  if (/\.(mjs|js|cjs)$/.test(rel) && irmao !== rel && existsSync(join(ROOT, irmao))) {
    tags.push(WF_TXT.includes(irmao) ? 'test' : 'test (fora do CI)');
  }
  if (rel.startsWith('.claude/hooks/')) {
    const nu = rel.split('/').pop().replace(/\.mjs$/, '');
    if (HOOK_BITES_TXT.includes(nu)) tags.push('hook-bites');
  }
  return tags.length ? tags.join(' + ') : '—';
};

const out = [];
const P = (s = '') => out.push(s);

// Frontmatter: exigido pelo schema de `memory/reference/*.md` (name/description/type/
// authority) e é o que faz o doc ATRAVESSAR o filtro do DocumentacaoController —
// `type` ∈ {adr,reference,spec,runbook,feature} + `admin_only=false` + `scope_required`
// nulo. Sem ele o arquivo é indexado e some da página, calado. `authority: generated`
// é o mesmo par do PAINEL-SISTEMA.md (gerado, não curado).
P('---');
P('name: MAQUINAS-INVENTARIO — inventário derivado das máquinas do oimpresso');
P('description: Censo GERADO por scripts/governance/maquinas-inventario.mjs (workflows, hooks, skills, agents, scripts, baselines). NÃO editar à mão (regenera). Cada descrição vem do cabeçalho/frontmatter/_meta da própria máquina.');
P('type: reference');
P('authority: generated');
P('lifecycle: ativo');
P('---');
P('');
P('# Máquinas do oimpresso — inventário consolidado (DERIVADO)');
P('');
P('> ⚙️ **Auto-gerado** por `scripts/governance/maquinas-inventario.mjs` — cada descrição vem do');
P('> cabeçalho/frontmatter/`_meta` do PRÓPRIO arquivo (medido, não escrito à mão · ADR 0256).');
P('> Regerar: `node scripts/governance/maquinas-inventario.mjs --write` · drift de COBERTURA');
P('> acusado por `--check` (advisory em `governance-script-tests.yml`): morde quando uma máquina');
P('> é adicionada/removida sem regenerar. Bite-test: `maquinas-inventario.test.mjs`.');
P('>');
P('> **Donos canônicos** (esta página só CONSOLIDA — a fonte viva de cada eixo é):');
P('> - Hooks → `.claude/hooks/_HOOKS-INDEX.md` · Skills → `.claude/skills/_SKILLS-INDEX.md`');
P('> - Gates/Workflows → `scripts/governance/gates-registry.json` · Required → `governance/required-checks-baseline.json`');
P('');

// ===== 1. WORKFLOWS / GATES (reusa gates-registry.json) =====
const reg = JSON.parse(readFileSync(join(ROOT, 'scripts/governance/gates-registry.json'), 'utf8'));
const wf = reg.workflows || {};
const wfKeys = Object.keys(wf).sort();
let required = new Set();
try {
  const base = JSON.parse(readFileSync(join(ROOT, 'governance/required-checks-baseline.json'), 'utf8'));
  (base.classic_protection?.contexts || []).forEach((c) => required.add(c));
} catch {}
// Invocador de WORKFLOW = o gatilho `on:` do próprio YAML — é o runtime que responde
// "quem executa isto", não a leitura de quem o cita. Nenhum outro eixo da matriz
// responde por ele: `ci` (o valor usado nos scripts) seria tautológico aqui.
const ABREV_ON = {
  pull_request: 'pr', pull_request_target: 'pr-target', push: 'push', schedule: 'cron',
  workflow_dispatch: 'manual', workflow_call: 'chamado', issue_comment: 'comment',
  merge_group: 'merge-queue', repository_dispatch: 'dispatch',
};
const gatilhoDe = (relYml) => {
  if (!existsSync(join(ROOT, relYml))) return '?'; // registry aponta pra YAML ausente
  const y = readFileSync(join(ROOT, relYml), 'utf8');
  // bloco `on:` (até a próxima chave de coluna 0) OU forma inline `on: [a, b]`
  const blk = y.match(/^on:\s*\r?\n((?:[ \t].*\r?\n|\r?\n)*?)(?=^\S)/m);
  const src = blk ? blk[1] : (y.match(/^on:.*$/m)?.[0] || '');
  const evs = Object.keys(ABREV_ON)
    .filter((e) => new RegExp(`(^|[\\s\\[{,])${e}\\s*[:,\\]}]|^\\s+${e}\\s*$`, 'm').test(src))
    .map((e) => ABREV_ON[e]);
  return evs.length ? evs.join('+') : '—';
};
P(`## 1. Workflows / Gates de CI — ${wfKeys.length} (${required.size} contexts required)`);
P('');
P('> `Invocador` = gatilho `on:` do YAML · `Documento` = doc canônico de maior precedência que o cita.');
P('> **Evidência não é derivável aqui** (medido: 0 de 123): o `gate-selftest` prova que o SCRIPT morde,');
P('> nunca o workflow. Marcar o workflow como provado por causa do script dele seria medir outra coisa.');
P('');
P('| Workflow | Invocador | Documento | Descrição |');
P('|---|---|---|---|');
for (const k of wfKeys) {
  const nome = wf[k]?.nome || wf[k]?.description || '';
  P(`| \`${k}\` | ${gatilhoDe(`.github/workflows/${k}`)} | ${documentoDe([k, nuDe(k)], [`.github/workflows/${k}`])} | ${cell(trunc(nome, 170))} |`);
}
P('');

// ===== 2. HOOKS (aponta pro índice gerado + conta) =====
const hooks = lsDir('.claude/hooks').filter((f) => f.endsWith('.mjs') && !f.includes('.test.'));
P(`## 2. Hooks (PreToolUse/PostToolUse/SessionStart) — ${hooks.length} arquivos`);
P('');
P('> Fonte viva com evento×matcher×sinal-de-bloqueio: **`.claude/hooks/_HOOKS-INDEX.md`** (auto-gerado).');
P('>');
P('> `Invocador` = o par `evento(matcher)` que o `.claude/settings.json` registra — quem de fato');
P('> dispara o hook. `—` aqui significa hook NO DISCO E FORA DO WIRING: existe e nunca roda.');
P('');
// Wiring real: quem dispara cada hook é o settings.json, não o cabeçalho do arquivo.
const wiringHooks = new Map();
try {
  const st = JSON.parse(readFileSync(join(ROOT, '.claude/settings.json'), 'utf8'));
  for (const [ev, grupos] of Object.entries(st.hooks || {})) {
    for (const g of grupos || []) {
      for (const h of g.hooks || []) {
        for (const arq of (h.command || '').match(/[\w.-]+\.mjs/g) || []) {
          if (!wiringHooks.has(arq)) wiringHooks.set(arq, new Set());
          wiringHooks.get(arq).add(`${ev}(${trunc(g.matcher || '*', 44)})`);
        }
      }
    }
  }
} catch {}
P('| Hook | Invocador | Evidência | Documento | Descrição (cabeçalho) |');
P('|---|---|---|---|---|');
for (const f of hooks.sort()) {
  const rel = `.claude/hooks/${f}`;
  const txt = readFileSync(join(ROOT, rel), 'utf8');
  const inv = wiringHooks.has(f) ? [...wiringHooks.get(f)].sort().join(' ') : '—';
  P(`| \`${f}\` | ${cell(inv)} | ${cell(evidenciaDe(rel))} | ${documentoDe([f, nuDe(f)], [rel])} | ${cell(trunc(firstDescComment(txt), 160))} |`);
}
P('');

// ===== 3. SKILLS (aponta pro índice gerado) =====
const skillDirs = lsDir('.claude/skills').filter((d) => existsSync(join(ROOT, '.claude/skills', d, 'SKILL.md')));
P(`## 3. Skills — ${skillDirs.length}`);
P('');
P('> Fonte viva com Tier/auto_trigger: **`.claude/skills/_SKILLS-INDEX.md`** (auto-gerado do frontmatter).');
P('>');
P('> **Invocador não é derivável** aqui: skill dispara por casamento de `description` (semântico),');
P('> e o `Tier` já é o eixo de ativação. As 40 de 74 que aparecem em hook/workflow/script são');
P('> MENÇÃO (o hook nudga a skill), não invocação — chamar isso de invocador seria medir outra');
P('> propriedade. **Evidência** idem: não existe fixture boa/ruim de skill (medido: 0 de 74).');
P('');
P('| Skill | Tier | Documento | Descrição (início) |');
P('|---|---|---|---|');
for (const d of skillDirs.sort()) {
  const txt = readFileSync(join(ROOT, '.claude/skills', d, 'SKILL.md'), 'utf8');
  const desc = frontmatterField(txt, 'description') || '';
  const tier = frontmatterField(txt, 'tier') || frontmatterField(txt, 'x-tier') || '—';
  P(`| \`${d}\` | ${tier} | ${documentoDe([d], [`.claude/skills/${d}`])} | ${cell(trunc(firstSentence(desc, 999), 150))} |`);
}
P('');

// ===== 4. AGENTS (gap — deriva do frontmatter) =====
const agents = lsDir('.claude/agents').filter((f) => f.endsWith('.md') && !f.startsWith('_'));
P(`## 4. Agents (subagentes Task) — ${agents.length}`);
P('');
P('> **Invocador/Evidência não são deriváveis** aqui (mesma razão das skills — agente é spawnado por');
P('> intenção, e não há fixture de agente). Medido: 8 de 27 aparecem em script/workflow, todas como menção.');
P('');
P('| Agent | Documento | Descrição (início) |');
P('|---|---|---|');
for (const f of agents.sort()) {
  const nome = f.replace('.md', '');
  const txt = readFileSync(join(ROOT, '.claude/agents', f), 'utf8');
  const desc = frontmatterField(txt, 'description') || '';
  P(`| \`${nome}\` | ${documentoDe([nome], [`.claude/agents/${f}`])} | ${cell(trunc(firstSentence(desc, 999), 170))} |`);
}
P('');

// ===== 5. SCRIPTS (o GAP real — nenhum índice cobre scripts/**) =====

// --- eixo INVOCADOR (Trilha D · D0): quem EXECUTA cada script ---------------
// Derivado, nunca escrito à mão. Responde a pergunta que o censo sozinho não
// respondia: "esta máquina roda em algum lugar?" — a regra "LIGUE A MÁQUINA"
// item 2 trata medidor sem invocador como BUG, não como neutralidade.
//
// Duas distinções que a v1 desta medição errou (registradas para quem mexer):
//   (a) o corpus PRECISA incluir PHP — `generate-dxt.js` é chamado por um
//       comando artisan (`McpGenerateDxtCommand.php`), e sem PHP ele aparecia
//       como órfão. Medir com corpus incompleto é afirmar da fonte errada.
//   (b) CI rodar `x.test.mjs` NÃO é o mesmo que CI rodar `x.mjs`. O teste prova
//       a lógica numa fixture; o script pode nunca ser apontado para o repo.
//       Essa é a diferença entre "o mecanismo está correto" e "o mecanismo é
//       invocado" — marcada abaixo como `só .test`.
//
// NÃO julga: reporta o fato e o humano triaga. Um one-shot (codemod, probe,
// PoC) é órfão POR DESIGN e não deve ser "ligado"; um medidor órfão é dívida.
const FONTES_INVOC = [
  ['ci',     WF_YML],
  ['npm',    existsSync(join(ROOT, 'package.json')) ? [{ rel: 'package.json', txt: readFileSync(join(ROOT, 'package.json'), 'utf8') }] : []],
  ['agente', readTree('.claude', ['.json', '.md', '.js', '.mjs'])],
  ['script', readTree('scripts', ['.mjs', '.js', '.cjs', '.sh'])],
];
// Uma citação só conta como invocação do SCRIPT se sobrar depois de remover
// todas as ocorrências do nome do teste irmão.
const citaOScript = (txt, file, testFile) => txt.split(testFile).join('§').includes(file);

// Estágio 2 (só para quem deu zero no índice em memória): varredura ampla no
// versionado — pega PHP, blade, sh, qualquer consumidor fora das 4 fontes.
// rc 1 = "não achou" (fato); qualquer outro rc = o INSTRUMENTO falhou, e aí a
// resposta honesta é `?`, nunca `—` (que afirmaria ausência não medida).
const grepAmplo = (pattern) => {
  try {
    const r = execFileSync('git', ['grep', '-l', '-e', pattern, '--', '.'],
      { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'] });
    return { ok: true, files: r.split('\n').filter(Boolean) };
  } catch (e) {
    if (e.status === 1) return { ok: true, files: [] };
    return { ok: false, files: [] };
  }
};

// Este gerador DOCUMENTA máquinas — não invoca nenhuma. Se ele contasse como
// fonte, todo script citado num comentário daqui ganharia um invocador falso:
// foi o que aconteceu na 1ª versão (o comentário acima cita um `.js` como
// exemplo, e o script passou a se ver como invocado por este arquivo). É o
// auto-silenciamento já catalogado — o mecanismo casando com o próprio texto.
const SELF_REL = 'scripts/governance/maquinas-inventario.mjs';

const invocadorDe = (rel, file) => {
  const testFile = file.replace(/\.(mjs|js|cjs)$/, '.test.$1');
  const testRel = rel.replace(/\.(mjs|js|cjs)$/, '.test.$1');
  const kinds = new Set();
  let soTeste = false;
  for (const [kind, arr] of FONTES_INVOC) {
    for (const a of arr) {
      if (a.rel === rel) continue;
      if (a.rel === SELF_REL) continue; // documenta, não invoca (ver nota acima)
      if (a.rel === testRel) { soTeste = true; continue; }
      if (!a.txt.includes(file)) continue;
      if (citaOScript(a.txt, file, testFile)) kinds.add(kind);
      else soTeste = true;
    }
  }
  if (kinds.size === 0) {
    const g = grepAmplo(file);
    if (!g.ok) return '?'; // não medido — não afirmar ausência
    if (g.files.some((f) => f.endsWith('.php') && f !== rel)) kinds.add('php');
  }
  if (kinds.size) return [...kinds].sort().join(', ');
  return soTeste ? '— (só `.test`)' : '—';
};

const dumpScripts = (dir, titulo) => {
  const files = lsDir(dir).filter((f) => /\.(mjs|js|cjs)$/.test(f) && !f.includes('.test.'));
  P(`### ${titulo} — ${files.length}`);
  P('');
  P('| Script | Invocador | Evidência | Documento | Descrição (cabeçalho) |');
  P('|---|---|---|---|---|');
  for (const f of files.sort()) {
    const rel = `${dir}/${f}`;
    const txt = readFileSync(join(ROOT, rel), 'utf8');
    P(`| \`${f}\` | ${cell(invocadorDe(rel, f))} | ${cell(evidenciaDe(rel))} | ${documentoDe([f, nuDe(f)], [rel])} | ${cell(trunc(firstDescComment(txt), 160))} |`);
  }
  P('');
};
P('## 5. Scripts (`scripts/**`) — o gap sem índice-dono');
P('');
P('> **Coluna `Invocador` — DERIVADA** (Trilha D · D0). Quem de fato executa o script:');
P('> `ci` (workflow) · `npm` (package.json) · `agente` (`.claude/**`) · `script` (outro script) ·');
P('> `php` (comando artisan/serviço). `—` = nenhum invocador encontrado; `— (só \\`.test\\`)` = o');
P('> CI roda o **teste** dele, mas o script em si nunca é apontado para o repo; `?` = a varredura');
P('> falhou (não medido — nunca leia como ausência).');
P('>');
P('> ⚠️ `—` **não** significa "apagar": one-shot (codemod, probe, PoC de migração) é órfão **por');
P('> design**. O que é dívida é **medidor** órfão — a máquina existe, o teste prova que ela morde,');
P('> e nada a executa. A matriz reporta o fato; a triagem é humana.');
P('>');
P('> **Coluna `Evidência` — DERIVADA** (Trilha D · D0). Prova de que a máquina MORDE, lida dos donos');
P('> que já existem: `selftest` (par de fixtures boa/ruim rodado por `gate-selftest.mjs`) · `bite-log`');
P('> (array do `design-gate-bites.mjs`, DR-2a da ADR 0336) · `test` (`*.test.mjs` irmão, invocado por');
P('> algum workflow) · `test (fora do CI)` (o irmão existe mas nenhum YAML o roda — verde na máquina do');
P('> dev, nunca no CI) · `—` (nenhuma prova).');
P('>');
P('> **Coluna `Documento` — DERIVADA.** É o **citador de maior precedência**, e não "o doc dono" —');
P('> citação não prova posse. `+N` = quantos OUTROS docs também citam (é o aviso de que a escolha');
P('> foi por regra, não por autoridade: com `+143` o dono provavelmente é outro, vá olhar).');
P('> Precedência: `memory/decisions/` → canon raiz que o `CLAUDE.md` importa (`proibicoes.md` etc.) →');
P('> `memory/requisitos/` → `memory/reference/` → `decisions/proposals/` → `.claude/**` → resto.');
P('> Empate resolvido por: nome do arquivo carrega o nome da máquina → densidade de citação. Censo gerado');
P('> (este arquivo, `_HOOKS-INDEX`, `_SKILLS-INDEX`, `AUTOMATIONS`, `PAINEL-SISTEMA`) **não conta** —');
P('> ser listado por outro inventário não é estar documentado. `(só sessão/handoff · N)` = existe');
P('> rastro histórico, mas **nenhum doc vivo** governa a máquina. `—` = nenhum doc a cita.');
P('');
dumpScripts('scripts/governance', '5.1 `scripts/governance/`');
dumpScripts('scripts/tests', '5.2 `scripts/tests/`');
dumpScripts('scripts', '5.3 `scripts/` (raiz)');

// ===== 6. BASELINES / JSON de estado (deriva _meta quando existe) =====
const jsonDirs = ['governance', 'config', 'scripts'];
P('## 6. Baselines & JSON de estado');
P('');
P('> **Coluna `Leitor` — DERIVADA** (o eixo "invocador" desta tabela): baseline não é executado, é');
P('> LIDO — então quem o executa é quem o consome. `ci` (workflow) · `script` · `agente` (`.claude/**`).');
P('> `—` = **nenhum consumidor no versionado**: o arquivo é mantido e ninguém o lê. Evidência não é');
P('> derivável aqui (medido: 0 de 46 — baseline não tem fixture própria; quem tem é a catraca que o usa).');
P('');
// Leitor = mesma varredura do invocador de script, invertida: procura o baseline nos
// consumidores. Aceita path completo OU basename (todos os 46 basenames são únicos —
// se um dia colidirem, o path completo já casa antes e o basename não infla).
const leitorDe = (rel) => {
  const base = rel.split('/').pop();
  const kinds = new Set();
  for (const [kind, arr] of FONTES_INVOC) {
    for (const a of arr) {
      if (a.rel === rel || a.rel === SELF_REL) continue;
      if (a.txt.includes(rel) || a.txt.includes(base)) kinds.add(kind);
    }
  }
  return kinds.size ? [...kinds].sort().join(', ') : '—';
};
P('| Arquivo | Leitor | Documento | `_meta` / propósito |');
P('|---|---|---|---|');
const seen = new Set();
for (const d of jsonDirs) {
  for (const f of lsDir(d).filter((x) => x.endsWith('.json')).sort()) {
    // POSIX sempre: `join` usa o separador do SO, então no Windows isto virava
    // `governance\x.json` — e o `--check` no CI (Linux) lia como "fora do índice",
    // acusando 41 baselines que estavam lá. O índice é comparado string-exata entre
    // máquinas de SOs diferentes ([W] no Windows, o time e o CI em POSIX).
    const p = join(d, f).split('\\').join('/');
    if (seen.has(p)) continue;
    seen.add(p);
    let meta = '';
    try {
      const j = JSON.parse(readFileSync(join(ROOT, p), 'utf8'));
      meta = j._meta?.gate || j._meta?.baseline || j._meta?.nota || j._meta?.description || '';
    } catch {}
    P(`| \`${p}\` | ${leitorDe(p)} | ${documentoDe([p, p.split('/').pop(), nuDe(p)], [p])} | ${cell(trunc(meta, 150) || '(baseline/estado)')} |`);
  }
}
P('');
P(`> Total baselines JSON em governance/+config/+scripts: ${seen.size} · (mais ~5 dot-baselines na raiz + fixtures em tests/).`);
P('');

const md = out.join('\n');
// Mora em `memory/reference/` (era `governance/`) porque é ONDE O ACERVO ENXERGA: o
// IndexarMemoryGitParaDb varre `memory/reference` por RECURSÃO (não por glob — os globs
// não cobrem essa pasta) e o DocumentacaoController publica quem tem `type: reference`.
// De `governance/` o arquivo não aparecia em /documentacao: aquele path não é varrido
// nem por glob nem por recursão. Mesmo lugar e mesmo motivo do PAINEL-SISTEMA.md, que
// também é gerado. Tombstone no path antigo — backlinks em handoff/session são
// append-only e não podem ser relinkados.
const OUT = process.env.MAQUINAS_OUT || 'memory/reference/MAQUINAS-INVENTARIO.md'; // env: fixture no bite-test

// Identidade da máquina = token da 1ª coluna das tabelas (`| \`nome\` | …`). Comparar SÓ
// esse conjunto torna o --check insensível a mudança de descrição/contagem — ele morde
// apenas quando uma máquina é ADICIONADA ou REMOVIDA sem regenerar (o drift que importa;
// byte-exact viraria vermelho em quase todo PR de governança → ruído ignorado).
const extractNames = (text) => {
  const s = new Set();
  for (const m of text.matchAll(/^\| `([^`]+)` /gm)) s.add(m[1]);
  return s;
};

const OUT_PATH = resolve(ROOT, OUT); // resolve trata OUT absoluto (env fixture) sem manglar
if (MODE === 'write') {
  writeFileSync(OUT_PATH, md);
  console.log(`✅ escrito: ${OUT} (${md.split('\n').length} linhas)`);
} else if (MODE === 'check') {
  if (!existsSync(OUT_PATH)) {
    console.error(`✗ ${OUT} não existe — rode: node scripts/governance/maquinas-inventario.mjs --write`);
    process.exit(1);
  }
  const fresh = extractNames(md);
  const have = extractNames(readFileSync(OUT_PATH, 'utf8'));
  const missing = [...fresh].filter((n) => !have.has(n)).sort(); // no disco, FORA do índice
  const ghost = [...have].filter((n) => !fresh.has(n)).sort();    // no índice, SUMIU do disco
  if (missing.length || ghost.length) {
    console.error(`✗ ${OUT} DESATUALIZADO (cobertura) — rode: node scripts/governance/maquinas-inventario.mjs --write`);
    if (missing.length) console.error(`  ${missing.length} no disco fora do índice: ${missing.slice(0, 15).join(', ')}${missing.length > 15 ? '…' : ''}`);
    if (ghost.length) console.error(`  ${ghost.length} no índice que sumiram do disco: ${ghost.slice(0, 15).join(', ')}${ghost.length > 15 ? '…' : ''}`);
    process.exit(1);
  }
  console.log(`✅ ${OUT} cobre todas as ${fresh.size} máquinas (0 faltando · 0 ghost).`);
} else {
  process.stdout.write(md);
}
