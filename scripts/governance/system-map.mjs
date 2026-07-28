#!/usr/bin/env node
// system-map.mjs — a MATRIZ gerada do painel do sistema oimpresso.
//
// POR QUE EXISTE (Wagner 2026-07-12): "é chato ter que ficar lembrando; deveria
// ser a máquina matriz que não quebra e sempre mantém atualizado". O mapa/guia do
// sistema não pode ser um doc mantido À MÃO (drifta → você volta a ter que lembrar)
// nem um presence-gate sobre um campo auto-declarado (proibicoes.md §5, L-24).
//
// A FORMA CERTA é DERIVADA: este script LÊ as fontes canônicas que JÁ são a verdade
// e emite memory/reference/PAINEL-SISTEMA.md como um ÍNDICE que APONTA pros donos —
// nunca recopia o conteúdo deles (isso seria doc paralelo, §5). Editar o .md à mão é
// inútil: a máquina regenera. Frescor é REAL (git-mtime), não campo declarado.
//
// HONESTIDADE (o que dá e o que NÃO dá pra automatizar):
//   - DERIVÁVEL com confiança (estruturado): ADRs + lifecycle/supersedes, ideias
//     mortas (§5), Tier 0 gaps, frescor dos BRIEFINGs, nº de handoffs, scorecard SDD.
//   - CURADO (prosa nos donos): o status/narrativa de cada módulo. O painel LINKA o
//     dono + mostra o frescor; NÃO inventa um "status: X" que a prosa não declara.
//
// Node puro (fs + git via execSync). Sem deps, sem DB, sem PHP. Molde: sdd-scorecard.mjs.
// Uso (na raiz do repo):
//   node scripts/governance/system-map.mjs            # gera memory/reference/PAINEL-SISTEMA.md
//   node scripts/governance/system-map.mjs --stdout    # imprime, não escreve
//   node scripts/governance/system-map.mjs --check      # exit 1 se o .md commitado difere do gerado (CI)

import { readdirSync, readFileSync, existsSync, writeFileSync, statSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { pathToFileURL } from 'node:url';

const ROOT = process.cwd();
// só roda a geração/CI quando invocado DIRETO (node system-map.mjs). Importado
// (ex: onboarding-paths-check.mjs reusa deadLinks) NÃO dispara escrita nem process.exit.
const IS_DIRECT_RUN = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
const OUT = join(ROOT, 'memory', 'reference', 'PAINEL-SISTEMA.md');
const MODE_STDOUT = process.argv.includes('--stdout');
const MODE_CHECK = process.argv.includes('--check');

// ── helpers ──────────────────────────────────────────────────────────────────
const read = (p) => { try { return readFileSync(p, 'utf8'); } catch { return ''; } };
const ls = (p) => { try { return readdirSync(p); } catch { return []; } };
function frontmatter(txt) {
  const m = txt.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!m) return {};
  const fm = {};
  for (const line of m[1].split(/\r?\n/)) {
    const kv = line.match(/^([a-z_]+):\s*(.*)$/i);
    if (kv) fm[kv[1]] = kv[2].replace(/^["']|["']$/g, '').trim();
  }
  return fm;
}
// último commit que tocou um path (data ISO curta) — frescor REAL, não declarado
function gitLastDate(relPath) {
  try {
    const out = execSync(`git log -1 --format=%cs -- "${relPath}"`, {
      cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'],
    }).toString().trim();
    return out || null;
  } catch { return null; }
}
function daysSince(isoDate) {
  if (!isoDate) return null;
  const then = new Date(isoDate + 'T00:00:00Z').getTime();
  const now = new Date(NOW + 'T00:00:00Z').getTime();
  return Math.round((now - then) / 86400000);
}
// data de geração: passada por env pra ser determinística no CI; senão hoje
const NOW = process.env.SYSTEM_MAP_DATE || new Date().toISOString().slice(0, 10);

// ── fonte 1: ADRs (lifecycle + supersede) ────────────────────────────────────
function measureAdrs() {
  const dir = join(ROOT, 'memory', 'decisions');
  const files = ls(dir).filter((f) => /^\d{4}-.*\.md$/.test(f));
  const byStatus = {};
  const superseded = [];
  for (const f of files) {
    const fm = frontmatter(read(join(dir, f)));
    const st = (fm.status || 'sem-status').toLowerCase();
    byStatus[st] = (byStatus[st] || 0) + 1;
    if (fm.supersedes && fm.supersedes.replace(/[\[\]\s]/g, '')) {
      superseded.push({ novo: f.replace(/\.md$/, ''), supera: fm.supersedes.replace(/[\[\]]/g, '').trim() });
    }
  }
  return { total: files.length, byStatus, superseded };
}

// ── fonte 2: proibicoes.md §5 (ideias mortas) + Tier 0 gaps ───────────────────
function measureProibicoes() {
  const txt = read(join(ROOT, 'memory', 'proibicoes.md'));
  const section = (title) => {
    const i = txt.indexOf(title);
    if (i < 0) return '';
    const rest = txt.slice(i + title.length);
    const j = rest.search(/\n## [^\n]/); // próximo H2
    return j < 0 ? rest : rest.slice(0, j);
  };
  const headings = (block) => (block.match(/^### (.+)$/gm) || []).map((h) => h.replace(/^### /, '').trim());
  const descartadas = headings(section('## Ideias avaliadas e DESCARTADAS'));
  const tier0gaps = headings(section('## Tier 0 gaps catalogados'));
  return { descartadas, tier0gaps };
}

// ── fonte 3: módulos + frescor do BRIEFING (curado; linka o dono) ─────────────
function measureModules() {
  const modDir = join(ROOT, 'Modules');
  const mods = ls(modDir).filter((d) => { try { return statSync(join(modDir, d)).isDirectory(); } catch { return false; } });
  const rows = [];
  for (const m of mods) {
    const brief = `memory/requisitos/${m}/BRIEFING.md`; // forward-slash sempre (link markdown + git)
    const hasBrief = existsSync(join(ROOT, 'memory', 'requisitos', m, 'BRIEFING.md'));
    const date = hasBrief ? gitLastDate(brief) : null;
    rows.push({ modulo: m, brief: hasBrief ? brief : null, atualizado: date, dias: daysSince(date) });
  }
  return rows.sort((a, b) => a.modulo.localeCompare(b.modulo));
}

// ── fonte 4: scorecard SDD (já gerado por sdd-scorecard.mjs) ───────────────────
function measureScorecard() {
  const p = join(ROOT, 'governance', 'sdd-scorecard.json');
  if (!existsSync(p)) return null;
  try {
    const j = JSON.parse(read(p));
    const metrics = j.metrics || j;
    const measured = Object.values(metrics).filter((v) => v && v.status === 'measured').length;
    const total = Object.keys(metrics).length;
    return { measured, total, floor: metrics.full_suite_pass_rate?.value ?? metrics.full_suite?.value ?? null };
  } catch { return null; }
}

// ── fonte 5: contagens (handoffs, sessions recentes) ──────────────────────────
function measureCounts() {
  const handoffs = ls(join(ROOT, 'memory', 'handoffs')).filter((f) => f.endsWith('.md')).length;
  const sessions = ls(join(ROOT, 'memory', 'sessions'))
    .filter((f) => f.endsWith('.md') && /^\d{4}-\d{2}-\d{2}/.test(f)); // só logs datados, sem _TEMPLATE/_INDEX/README
  const recent = sessions.sort().slice(-6).reverse().map((f) => f.replace(/\.md$/, ''));
  return { handoffs, sessions: sessions.length, recent };
}

// ── fonte 6: auditorias & gates (censo + o que bloqueia) ──────────────────────
// Deriva de DUAS fontes JÁ versionadas (offline, determinístico — nada de gh api):
//   - scripts/governance/gates-registry.json    → censo (o que EXISTE, por classe;
//     cobrado por memory-health Check G/M — workflow fora do censo = 🔴).
//   - governance/required-checks-baseline.json  → o que BLOQUEIA merge (required
//     CONGELADO, vigiado por protection-drift.mjs contra demoção invisível · GT-G4).
// O baseline commitado É a fonte-única do "required"; divergência do vivo é sinalizada
// pelo protection-drift, NÃO reconciliada aqui (promoção = PR + ADR 0275 §5).
function measureGates() {
  let registry = {};
  try { registry = JSON.parse(read(join(ROOT, 'scripts', 'governance', 'gates-registry.json'))).workflows || {}; } catch { /* ausente */ }
  const byClass = {};
  for (const [file, w] of Object.entries(registry)) {
    const cls = (w && w.classe) || 'sem-classe';
    (byClass[cls] = byClass[cls] || []).push(file.replace(/\.ya?ml$/, ''));
  }
  for (const c of Object.keys(byClass)) byClass[c].sort();
  let required = [];
  let enforcement = null;
  let capturado = null;
  try {
    const bl = JSON.parse(read(join(ROOT, 'governance', 'required-checks-baseline.json')));
    // contagem canônica do protection-drift.mjs: classic + ruleset (ignorar o ruleset
    // subconta — "Governance Gate" vem de ruleset, não do required_status_checks clássico).
    const classic = (bl.classic_protection && bl.classic_protection.contexts) || [];
    const ruleset = (bl.rulesets && bl.rulesets.contexts) || [];
    required = [...classic, ...ruleset];
    enforcement = bl.enforcement_level || null;
    capturado = (bl._meta && bl._meta.capturado_em) || null;
  } catch { /* ausente */ }
  return { total: Object.keys(registry).length, byClass, required, enforcement, capturado };
}

// ── fonte 7: camada de IA (agentes · tools MCP · provedores) ──────────────────
// POR QUE EXISTE (2026-07-28): os números da camada de IA viviam ESCRITOS À MÃO num
// diagrama de arquitetura ("22 agentes", "39 tools", "16 provedores" — este último
// ERRADO: são 15, o bloco extra contado era `caching.embeddings`, que não é provider).
// É a lápide §5 2026-07-17 em pessoa: doc canônico NÃO repete número que outro sistema
// sabe melhor. Aqui o dono é a ÁRVORE, e o cron de system-map.yml mantém sozinho.
//
// O QUE ESTA SEÇÃO **NÃO** É — e a distinção importa (§5 LC-11, presence-gate):
// ela conta ARQUIVO QUE IMPLEMENTA CONTRATO, não capacidade. "4 agentes no ADS" não
// diz que o ADS é bom, nem que rodam; diz que existem 4 classes. Por isso a seção
// emitida não tem nota, não tem status e não tem veredito — só contagem + ponteiro.
//
// FONTE PREFERIDA = CONTRATO (`implements X`), não pasta. Varredura por diretório
// (`Modules/*/Ai/Agents/`) é a convenção de HOJE; um agente fora dela seria invisível.
// As duas medidas são cruzadas e a DIVERGÊNCIA é emitida — detector de drift de
// convenção de graça. Em 2026-07-28 as duas batem: 22 e 22.
const IA_CONTRATOS = {
  agente: /implements\s+Agent\b/,
  memoria: /implements\s+MemoriaContrato\b/,
  reranker: /implements\s+Reranker\b/,
};
/**
 * NÚCLEO PURO (testável hermético, molde de fact-anchor.mjs): classifica candidatos
 * já lidos por contrato. Exportado pra que o self-test prove a mordida com fixture
 * boa/ruim, sem git e sem FS.
 * @param {{rel:string,txt:string}[]} arquivos
 */
export function classificarIa(arquivos) {
  const out = { agente: [], memoria: [], reranker: [] };
  for (const { rel, txt } of arquivos) {
    if (rel.includes('/Tests/')) continue;              // fake/dublê de teste não é peça viva
    if (/^\s*abstract\s+class\s/m.test(txt)) continue;  // base abstrata não é implementação
    for (const [tipo, re] of Object.entries(IA_CONTRATOS)) if (re.test(txt)) out[tipo].push(rel);
  }
  return out;
}
/**
 * NÚCLEO PURO: lê provedores de config/ai.php. Cada bloco de provider tem exatamente
 * um `'driver' => '<nome>'`; o default GLOBAL é o primeiro `'default'` do arquivo,
 * buscado só no trecho ANTES de `'providers'` — senão casaria `models.text.default`.
 * O bloco `caching.embeddings` NÃO tem `driver` e por isso não conta: foi exatamente
 * o erro da contagem à mão ("16 provedores", eram 15).
 * @param {string} txt conteúdo de config/ai.php
 */
export function parseProvidersAi(txt) {
  const partes = String(txt).split("'providers'");
  const antes = partes[0] || '';
  // só o trecho DEPOIS de `'providers'`: um `'driver' =>` que apareça noutra chave
  // do config (cache, log, fila) não é provedor e inflaria a conta em silêncio.
  const depois = partes.length > 1 ? partes.slice(1).join("'providers'") : '';
  return {
    provs: [...depois.matchAll(/'driver'\s*=>\s*'([a-z0-9_]+)'/g)].map((x) => x[1]).sort(),
    defaultProv: (antes.match(/'default'\s*=>\s*'([a-z0-9_]+)'/) || [])[1] || null,
  };
}
/**
 * NÚCLEO PURO: a linha de agentes do painel. Três estados, e o terceiro é o que
 * importa — sem `git grep` o contrato devolve [] e escrever "0 agentes · ⚠️ a pasta
 * dá 22" seria alarme FALSO por ausência de instrumento. Zero-por-não-medi jamais
 * pode passar por zero-medido (§5 2026-07-17: `crontab` que não existe não prova
 * que não há cron). Exportado pra que os três estados sejam provados em fixture.
 * @param {{semInstrumento:boolean, agentes:number, agentesPorPasta:number}} ia
 */
export function linhaAgentes(ia) {
  if (ia.semInstrumento) {
    return '- **Agentes**: _não medido nesta geração_ — `git grep` falhou. Sem o contrato não há censo: a varredura por pasta acharia só quem segue a convenção.';
  }
  const fora = ia.foraDaConvencao || [];
  return `- **Agentes** (\`implements Agent\`, fora de \`Tests/\`): **${ia.agentes}**`
    + (fora.length === 0
      ? ' — todos em `Ai/Agents/`, convenção íntegra.'
      : ` — ⚠️ **${fora.length}** fora de \`Ai/Agents/\`: ${fora.join(', ')}.`);
}
/**
 * NÚCLEO PURO: a linha de tools. Fonte = REGISTRO; a pasta é contra-medida. O rótulo
 * diz "registradas", nunca "expostas": exposição é runtime (`MCP_TOOLS_EXPOSED`, default
 * false — no Hostinger o número exposto é ZERO), e afirmar runtime a partir de arquivo é
 * a classe presence-gate que esta seção declara evitar.
 */
export function linhaTools(ia) {
  const r = ia.registro || {};
  if (!r.ok) return '- **Tools MCP**: _não medido_ — não achei o array `$tools` do `OimpressoMcpServer`.';
  const quebra = Object.entries(r.porModulo).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
  return `- **Tools MCP registradas** no \`OimpressoMcpServer\`: **${r.total}**`
    + (quebra.length ? ` — ${quebra.map(([m, n]) => `${m} ${n}`).join(' · ')}` : '')
    + (ia.arquivosTool === r.total
      ? '. Bate com os arquivos `*Tool.php` em `Modules/*/Mcp/Tools/`.'
      : ` — ⚠️ existem **${ia.arquivosTool}** arquivos \`*Tool.php\`: tool escrita e não registrada não sobe.`)
    + ' _Registrada ≠ exposta_: a exposição é gated por `MCP_TOOLS_EXPOSED` (`config/mcp.php`), estado de runtime que a árvore não sabe.';
}
/**
 * NÚCLEO PURO: lê o REGISTRO de tools do `OimpressoMcpServer` — a lista que o servidor
 * de fato publica. Fonte deliberadamente diferente da PASTA: um `*Tool.php` que ninguém
 * registrou não sobe, e um registro pode apontar pra outro módulo. Duas formas convivem
 * no array: FQN (`\Modules\Brief\Mcp\Tools\X::class`) e relativa (`Tools\Y::class`, que
 * resolve no namespace do próprio servidor, Jana).
 *
 * Foi aqui que a 1ª versão desta seção errou: contou a pasta de UM módulo (39) pra
 * descrever o que o servidor registra em TRÊS (44) — o mesmo "oráculo errado" que a
 * seção existe pra matar, agora com selo de derivado. Refutado por revisão adversarial.
 * @param {string} txt conteúdo de OimpressoMcpServer.php
 * @param {string} [donoDoArquivo] módulo do servidor, pro qual a forma relativa resolve
 */
export function parseToolsRegistry(txt, donoDoArquivo = 'Jana') {
  const src = String(txt);
  const ini = src.indexOf('$tools = [');
  if (ini < 0) return { ok: false, total: 0, porModulo: {} };
  // até o primeiro fechamento de array no nível da propriedade (`    ];`)
  const resto = src.slice(ini);
  const fim = resto.search(/\n\s{0,4}\];/);
  const bloco = (fim > 0 ? resto.slice(0, fim) : resto)
    .replace(/\/\/[^\n]*/g, '')          // comentário de linha citaria ::class em prosa
    .replace(/\/\*[\s\S]*?\*\//g, '');
  const porModulo = {};
  let total = 0;
  for (const m of bloco.matchAll(/([\\\w]+)::class/g)) {
    const fqn = m[1];
    const mod = (fqn.match(/^\\?Modules\\(\w+)\\/) || [])[1] || donoDoArquivo;
    porModulo[mod] = (porModulo[mod] || 0) + 1;
    total++;
  }
  return { ok: true, total, porModulo };
}
/**
 * `{ok:false}` distingue "o instrumento falhou" de "rodou e não casou nada" — colapsar
 * os dois faz um repo genuinamente sem agentes ser reportado como "não medido", que é
 * o inverso exato do erro que o guard existe pra evitar.
 */
function gitGrepFiles(padrao) {
  try {
    const out = execSync(`git grep -lE "${padrao}" -- "Modules"`, {
      cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'],
    });
    return { ok: true, files: out.split('\n').map((s) => s.trim()).filter(Boolean) };
  } catch (e) {
    // git grep sai 1 quando não casa NADA (não é erro) e >1 quando falha de verdade
    if (e && e.status === 1) return { ok: true, files: [] };
    return { ok: false, files: [] };
  }
}
function gitLsFiles(pathspec) {
  try {
    const out = execSync(`git ls-files "${pathspec}"`, {
      cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'],
    });
    return out.split('\n').map((s) => s.trim()).filter(Boolean);
  } catch { return []; }
}
function measureIa() {
  // 1 chamada de git grep para os 3 contratos; a classificação fina é em JS (lendo
  // os ~35 candidatos), porque `\b` não é ERE POSIX e o git grep -E não o honra.
  const grep = gitGrepFiles('implements (Agent|MemoriaContrato|Reranker)');
  const porContrato = classificarIa(grep.files.map((rel) => ({ rel, txt: read(join(ROOT, rel)) })));
  const semInstrumento = !grep.ok;
  // agentes agrupados por módulo (Modules/<X>/...)
  const porModulo = {};
  for (const rel of porContrato.agente) {
    const m = rel.match(/^Modules\/([^/]+)\//);
    if (m) porModulo[m[1]] = (porModulo[m[1]] || 0) + 1;
  }
  // CONTRA-MEDIDA sobre o MESMO conjunto (não sobre o disco): quais agentes reais estão
  // fora da pasta canônica. Contar `.php` da pasta comparava maçã com laranja — o
  // contrato pula `abstract`, então uma classe-base ali dentro fabricava um alarme
  // dizendo "tem agente fora do lugar" justamente sobre um arquivo que está no lugar.
  const foraDaConvencao = porContrato.agente
    .filter((p) => !p.includes('/Ai/Agents/'))
    .map((p) => p.split('/').pop().replace('.php', ''))
    .sort();
  // tools MCP: REGISTRO do servidor (o que sobe), com a pasta como contra-medida
  const registro = parseToolsRegistry(read(join(ROOT, 'Modules', 'Jana', 'Mcp', 'OimpressoMcpServer.php')));
  const arquivosTool = gitLsFiles(':(glob)Modules/*/Mcp/Tools/*Tool.php').length;
  // provedores: cada bloco de provider tem exatamente um `'driver' => '<nome>'`;
  // o default global é o PRIMEIRO `'default'` do arquivo, antes do bloco `providers`.
  const cfgTxt = read(join(ROOT, 'config', 'ai.php'));
  const { provs, defaultProv } = parseProvidersAi(cfgTxt);
  return {
    semInstrumento,
    agentes: porContrato.agente.length,
    foraDaConvencao,
    registro,
    arquivosTool,
    semConfigAi: cfgTxt === '',
    porModulo,
    memoria: porContrato.memoria.map((p) => p.split('/').pop().replace('.php', '')).sort(),
    reranker: porContrato.reranker.map((p) => p.split('/').pop().replace('.php', '')).sort(),
    provs,
    defaultProv,
  };
}

// ── render ────────────────────────────────────────────────────────────────────
function render(data) {
  const { adr, proib, mods, sc, cnt, gates, ia } = data;

  const L = [];
  L.push('---');
  L.push('name: PAINEL-SISTEMA — índice gerado do estado do sistema oimpresso');
  L.push('description: MATRIZ gerada por scripts/governance/system-map.mjs. NÃO editar à mão (regenera). Índice que aponta pros donos canônicos + fatos deriváveis + frescor real.');
  L.push('type: reference');
  L.push('authority: generated');
  L.push('lifecycle: ativo');
  L.push('---');
  L.push('');
  L.push('# 🗺️ PAINEL-SISTEMA — estado do oimpresso');
  L.push('');
  L.push(`> ⚙️ **Gerado por máquina** (\`system-map.mjs\`) em **${NOW}**. NÃO edite à mão — a próxima geração sobrescreve.`);
  L.push('> Regenerar: `node scripts/governance/system-map.mjs`. Este é um **índice que aponta pros donos canônicos**, não uma cópia deles.');
  L.push('> Views humanas (mapa 🗺️ / guia 🧭 em claude.ai) derivam DESTES dados.');
  L.push('');

  // Módulos
  L.push('## Módulos & verticais');
  L.push('');
  L.push('> Status/narrativa vivem no BRIEFING de cada módulo (curado). Aqui: existência + **último toque real** (git). Data absoluta (determinística — sem churn diário); a leitura de "está velho?" é do olho: um BRIEFING de meses atrás é candidato a re-destilar.');
  L.push('');
  L.push('| Módulo | BRIEFING | Último toque |');
  L.push('|---|---|---|');
  for (const m of mods) {
    const link = m.brief ? `[BRIEFING](../${m.brief.replace('memory/', '')})` : '_sem BRIEFING_';
    L.push(`| ${m.modulo} | ${link} | ${m.atualizado || '—'} |`);
  }
  L.push('');

  // Camada de IA
  L.push('## Camada de IA');
  L.push('');
  L.push('> Contagem DERIVADA da árvore (contrato `implements`, não pasta). Isto conta **arquivo que implementa contrato** — não é nota, não é status e não prova que a peça roda. O que cada agente faz e se está ligado vive no BRIEFING do módulo e na config; aqui só existe o censo. Antes disto, estes números viviam à mão num diagrama e já tinham errado (`16 provedores` era 15).');
  L.push('');
  L.push(linhaAgentes(ia));
  const modsIa = Object.entries(ia.porModulo).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
  if (modsIa.length) L.push(`  - por módulo: ${modsIa.map(([m, n]) => `${m} ${n}`).join(' · ')}`);
  L.push(linhaTools(ia));
  if (ia.semConfigAi) {
    L.push('- **Provedores**: _não medido_ — `config/ai.php` ausente ou ilegível.');
  } else {
    L.push(`- **Provedores** declarados em \`config/ai.php\`: **${ia.provs.length}**`
      + (ia.defaultProv ? ` · default = \`${ia.defaultProv}\`` : '')
      + (ia.provs.length ? ` — ${ia.provs.join(', ')}` : '')
      + '. _Declarado ≠ com chave_: a credencial mora no ambiente.');
  }
  // "implementações", não "drivers": o contrato também é implementado por decorator
  // (RetrievalTelemetryDecorator) — chamar tudo de driver seria rótulo errado.
  if (ia.memoria.length) L.push(`- **Implementações de \`MemoriaContrato\`**: ${ia.memoria.join(' · ')}`);
  if (ia.reranker.length) L.push(`- **Rerankers** (\`implements Reranker\`): ${ia.reranker.join(' · ')}`);
  L.push('');
  L.push('> Não derivável e por isso NÃO listado aqui: quais pipelines de retrieval existem e qual está ligado — isso mora na config e no BRIEFING da Jana, e um número inventado aqui seria pior que a ausência.');
  L.push('');

  // SDD scorecard
  L.push('## Programa SDD (governança)');
  L.push('');
  if (sc) {
    L.push(`- Scorecard: **${sc.measured}/${sc.total}** métricas medidas${sc.floor != null ? ` · floor full-suite = **${sc.floor}**` : ''}.`);
    L.push('- Fonte viva: `governance/sdd-scorecard.json` (gerado por `sdd-scorecard.mjs`). Avaliação adversarial: `/sdd-avaliar`.');
  } else {
    L.push('- `governance/sdd-scorecard.json` ausente — rodar `node scripts/governance/sdd-scorecard.mjs`.');
  }
  L.push('- Roadmap dono: [`memory/requisitos/_Governanca/roadmap/_ROADMAP.md`](../requisitos/_Governanca/roadmap/_ROADMAP.md).');
  L.push('');

  // Auditorias & Gates
  L.push('## Auditorias & Gates');
  L.push('');
  L.push('> Fontes versionadas (offline, sem `gh api`): censo [`gates-registry.json`](../../scripts/governance/gates-registry.json) (o que **existe**) + [`required-checks-baseline.json`](../../governance/required-checks-baseline.json) (o que **bloqueia**, congelado). Anti-demoção invisível: `protection-drift.mjs` (GT-G4). As catracas mordem: `gate-selftest` (GT-G6). Censo cobrado por `memory-health` Check G/M.');
  L.push('');
  L.push(`### Bloqueiam merge — ${gates.required.length} required${gates.enforcement ? ` (enforcement: ${gates.enforcement})` : ''}`);
  if (gates.capturado) L.push(`> Congelados no baseline (captura ${gates.capturado}). Divergência do vivo é sinalizada pelo \`protection-drift\`, não reconciliada aqui.`);
  L.push('');
  for (const c of gates.required) L.push(`- ${c}`);
  L.push('');
  L.push(`### Censo — ${gates.total} workflows por classe`);
  L.push('');
  L.push('> Lista completa + propósito de cada um: [`gates-registry.json`](../../scripts/governance/gates-registry.json) (o dono). Aqui: contagem + exemplos.');
  L.push('');
  L.push('| Classe | Qtd | Exemplos |');
  L.push('|---|---|---|');
  const classLabel = { gate: 'gate (bloqueia/valida PR)', meta: 'meta (testa os gates)', automacao: 'automacao (cron/dispatch)', deploy: 'deploy (entrega)' };
  const order = ['gate', 'meta', 'automacao', 'deploy'];
  const classes = [...order.filter((c) => gates.byClass[c]), ...Object.keys(gates.byClass).filter((c) => !order.includes(c)).sort()];
  for (const cls of classes) {
    const files = gates.byClass[cls] || [];
    const ex = files.slice(0, 4).join(', ') + (files.length > 4 ? ', …' : '');
    L.push(`| ${classLabel[cls] || cls} | ${files.length} | ${ex} |`);
  }
  L.push('');

  // ADRs
  L.push('## Decisões (ADRs)');
  L.push('');
  L.push(`- **${adr.total}** ADRs no total. Índice gerado: [\`_INDEX-GENERATED.md\`](../decisions/_INDEX-GENERATED.md) · lifecycle: [\`_INDEX-LIFECYCLE.md\`](../decisions/_INDEX-LIFECYCLE.md).`);
  const st = Object.entries(adr.byStatus).sort((a, b) => b[1] - a[1]).map(([k, v]) => `${k}: ${v}`).join(' · ');
  if (st) L.push(`- Por status: ${st}.`);
  L.push(`- **${adr.superseded.length}** reversões de rota (ADR com \`supersedes:\`).`);
  L.push('');

  // Ideias mortas
  L.push('## Ideias avaliadas e ABANDONADAS (§5 — não re-propor)');
  L.push('');
  L.push(`> Dono canônico: [\`memory/proibicoes.md §5\`](../proibicoes.md). ${proib.descartadas.length} entradas.`);
  L.push('');
  for (const d of proib.descartadas) L.push(`- ~~${d}~~`);
  L.push('');

  // Tier 0 gaps
  if (proib.tier0gaps.length) {
    L.push('## Tier 0 gaps (esperam decisão/desbloqueio)');
    L.push('');
    for (const g of proib.tier0gaps) L.push(`- ⛔ ${g}`);
    L.push('');
  }

  // Rastro
  L.push('## Rastro');
  L.push('');
  L.push(`- **${cnt.handoffs}** handoffs · **${cnt.sessions}** session logs. Índice: [\`memory/08-handoff.md\`](../08-handoff.md).`);
  L.push('- Sessions recentes:');
  for (const s of cnt.recent) L.push(`  - \`${s}\``);
  L.push('');
  L.push('---');
  L.push(`_Gerado por \`scripts/governance/system-map.mjs\` · ${NOW} · deriva das fontes canônicas, não as substitui._`);
  L.push('');
  return L.join('\n');
}

// ── main ──────────────────────────────────────────────────────────────────────
// ── ONBOARDING-AGENTE-GERADO.md — artefato da rota de agentes. A porta global é
// README.md; este arquivo só oferece um prompt estável + ponteiros vivos. ──
const OUT_ONBOARDING = join(ROOT, 'memory', 'reference', 'ONBOARDING-AGENTE-GERADO.md');
function renderOnboardingAgent(data) {
  const { adr, mods } = data;
  const L = [];
  L.push('---');
  L.push('name: Onboarding de agente — prompt gerado');
  L.push('description: Artefato auxiliar da rota de agentes declarada no README.md. GERADO por system-map.mjs — prompt estável + ponteiros pras fontes vivas.');
  // `guide` (não `generated-prompt`): o enum de reference.schema.json só aceita
  // reference|feedback|protocol|guide|index — quem diz que é gerado é `authority`,
  // como o irmão PAINEL-SISTEMA já fazia. Ficava fora do enum desde que nasceu; só
  // apareceu quando a regeneração tocou o arquivo e acordou o gate diff-aware.
  L.push('type: guide');
  L.push('authority: generated');
  L.push('lifecycle: ativo');
  L.push('---');
  L.push('');
  L.push('# Prompt gerado de onboarding para agente');
  L.push('');
  L.push('<!-- documentation-entrypoint: tool:agent-onboarding -->');
  L.push('');
  L.push('> ⚙️ **Gerado por `system-map.mjs`.** NÃO editar à mão. Este arquivo não é outra porta global: a entrada única continua no [`README.md`](../../README.md), rota “Trabalhar com um agente de IA”.');
  L.push('');
  L.push('## Pra uma IA nova entender tudo — cole numa sessão nova');
  L.push('');
  L.push('```');
  L.push('Você vai trabalhar no oimpresso, meu ERP. Antes de qualquer coisa:');
  L.push('1. Rode a tool `brief-fetch` (estado consolidado do projeto). SE você NÃO tiver');
  L.push('   o servidor MCP conectado, PULE e leia em vez disso (fallback): o roadmap');
  L.push('   `memory/requisitos/_Governanca/roadmap/_ROADMAP.md` + o session log DATADO mais');
  L.push('   recente (arquivo `YYYY-MM-DD-*.md` em `memory/sessions/` — IGNORE README/_INDEX/');
  L.push('   _TEMPLATE, que um `ls -t` cru joga por cima). Nunca invente o retorno da tool.');
  L.push('2. As regras já carregaram via CLAUDE.md — respeite-as (multi-tenant, PT-BR,');
  L.push('   teste só no CT 100, aprovação humana antes de merge).');
  L.push('3. Leia `memory/reference/PAINEL-SISTEMA.md` — o índice GERADO do sistema');
  L.push('   inteiro (módulos + frescor, ADRs, ideias descartadas, o que está em aberto).');
  L.push('4. Pra o histórico do que já foi tentado e por que caiu, leia');
  L.push('   `memory/proibicoes.md` (seção "Ideias avaliadas e DESCARTADAS").');
  L.push('');
  L.push('Agora, ANTES de começar, me diga em 5 bullets o que você entendeu: o que é,');
  L.push('como roda, quem é o cliente, o que está em voo, e uma regra que nunca pode');
  L.push('quebrar. Se algum bullet estiver vago, releia a fonte.');
  L.push('```');
  L.push('');
  L.push('> O último passo força a IA a **provar** que entendeu, em vez de fingir.');
  L.push('');
  L.push('## Pra auditar / revisar o sistema (2 comandos bastam)');
  L.push('');
  L.push('- **`/sdd-avaliar`** — auditoria geral do processo (7 especialistas adversariais checam o estado REAL, nota 0-100 + riscos). Responde "o sistema está honesto?".');
  L.push('- **`/avaliar-modulo <X>`** — nota de um módulo em 9 dimensões + gaps. Responde "este módulo está bom?".');
  L.push('- _Mais fundo:_ `/audit-and-fix <tema>` · `capterra-senior` (vs mercado) · `design-arte` (UX) · `php artisan jana:health-check` (saúde diária).');
  L.push('');
  L.push('## Estado vivo (não apodrece — é derivado)');
  L.push('');
  L.push(`- **${mods.length} módulos** · **${adr.total} ADRs** — detalhe + frescor no [PAINEL-SISTEMA.md](PAINEL-SISTEMA.md) (gerado junto deste).`);
  L.push('- Estado consolidado agora: rode `brief-fetch`.');
  L.push('- Regras Tier 0 + o que já falhou: [proibicoes.md](../proibicoes.md).');
  L.push('- Como o sistema é construído: `CLAUDE.md` (carrega automático) + `memory/why-oimpresso.md` / `what-oimpresso.md` / `how-trabalhar.md`.');
  L.push('- Onde o CÓDIGO mora (pra mexer, não só entender): `Modules/<Vertical>` (features por vertical) · `app/Domain/Fsm` (máquina de estados de vendas/OS) · `resources/js/Pages/<Mod>/` (telas Inertia/React). Antes de criar/alterar, ABRA `Modules/Jana` · `Modules/Repair` e imite o padrão (ADR 0011). Pra criar módulo do zero, o passo-a-passo está em `memory/requisitos/Infra/RUNBOOK-criar-modulo.md`.');
  L.push('');
  return L.join('\n');
}

// ── REGRA MECÂNICA (Wagner 2026-07-12 "os caminhos indicados são errados, sem fonte,
// sem teste — isso deveria ser uma regra clara"): o gerador NUNCA emite path que não
// resolve. Varre os markdown-links de cada saída e existsSync cada um relativo ao dir
// do arquivo. Qualquer path morto = FALHA (exit 1), NÃO escreve doc com link quebrado. ──
// dirs de repo reconhecidos — um `code` inline que começa com um deles E tem `/` é
// tratado como PATH (relativo à RAIZ do repo) e verificado. Fecha o furo que deixou
// `Modules/Project` (inexistente) passar — antes só links markdown eram checados.
const REPO_DIRS = /^(Modules|app|resources|scripts|governance|database|tests|config|routes|bootstrap|\.github|\.claude|memory)\/\S/;
export function deadLinks(md, outPath) {
  const base = dirname(outPath);
  const dead = [];
  // 1) links markdown ](path) — relativos ao dir do doc
  const reLink = /\]\(([^)]+)\)/g; let m;
  while ((m = reLink.exec(md)) !== null) {
    const link = m[1].split('#')[0].trim();
    if (!link || /^(https?:|mailto:)/.test(link)) continue; // externos não se verificam no disco
    if (!existsSync(join(base, link))) dead.push(link);
  }
  // 2) paths de repo em `code` inline — relativos à RAIZ do repo.
  // Remove blocos cercados ``` … ``` ANTES de casar inline: os backticks internos
  // do bloco bagunçam o pareamento do regex e engoliriam paths inline reais depois
  // dele (Modules/Jana, app/Domain/Fsm ficavam SEM verificação — furo silencioso).
  const noFences = md.replace(/```[\s\S]*?```/g, '');
  const reCode = /`([^`]+)`/g;
  while ((m = reCode.exec(noFences)) !== null) {
    const t = m[1].trim();
    if (!REPO_DIRS.test(t)) continue;       // só o que parece path de repo
    if (/\s/.test(t)) continue;             // tem espaço = "path + rótulo" (ex `proibicoes.md §5`), não path puro
    if (/[<>*]/.test(t)) continue;          // templates/globs (Modules/<Vertical>, Pages/<Mod>/) não são paths reais
    const p = t.replace(/\/$/, '');         // tira barra final de dir
    if (!existsSync(join(ROOT, p))) dead.push(`(inline) ${t}`);
  }
  return dead;
}
export function assertLinksLive(pairs) {
  const problems = pairs.flatMap(([md, out]) => deadLinks(md, out).map((l) => `${out}: ${l}`));
  if (problems.length) {
    console.error('[system-map] PATH MORTO — o gerador se recusa a emitir link quebrado (regra):');
    problems.forEach((p) => console.error('  ✗ ' + p));
    process.exit(1);
  }
}

if (IS_DIRECT_RUN) {
  const data = {
    adr: measureAdrs(), proib: measureProibicoes(), mods: measureModules(),
    sc: measureScorecard(), cnt: measureCounts(), gates: measureGates(),
    ia: measureIa(),
  };
  const outPainel = render(data);
  const outOnboarding = renderOnboardingAgent(data);
  // REGRA: prova que TODO path emitido resolve, antes de qualquer coisa (fail-closed)
  assertLinksLive([[outPainel, OUT], [outOnboarding, OUT_ONBOARDING]]);
  // ignora a linha de data (volátil) do PAINEL na comparação de conteúdo
  const strip = (s) => s.replace(/em \*\*\d{4}-\d{2}-\d{2}\*\*/g, 'em **DATE**').replace(/· \d{4}-\d{2}-\d{2} ·/g, '· DATE ·');
  if (MODE_STDOUT) {
    process.stdout.write(outPainel + '\n\n' + outOnboarding);
  } else if (MODE_CHECK) {
    let stale = false;
    if (strip(read(OUT)) !== strip(outPainel)) { console.error('[system-map] PAINEL-SISTEMA.md desatualizado'); stale = true; }
    if (strip(read(OUT_ONBOARDING)) !== strip(outOnboarding)) { console.error('[system-map] ONBOARDING-AGENTE-GERADO.md desatualizado'); stale = true; }
    if (stale) { console.error('  → rode: node scripts/governance/system-map.mjs'); process.exit(1); }
    console.log('[system-map] PAINEL + ONBOARDING-AGENTE-GERADO em dia.');
  } else {
    writeFileSync(OUT, outPainel);
    writeFileSync(OUT_ONBOARDING, outOnboarding);
    console.log(`[system-map] escrito: ${OUT} + ${OUT_ONBOARDING}`);
  }
}
