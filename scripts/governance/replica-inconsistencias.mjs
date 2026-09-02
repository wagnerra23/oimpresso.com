#!/usr/bin/env node
// replica-inconsistencias.mjs — LISTA DE INCONSISTÊNCIAS pós-aplicação do protótipo (ADR 0388).
//
// POR QUE EXISTE. Decisão [W] 2026-09-02, textual: "quero que isso sirva para todo o protótipo,
// isso não é erro isolado. Eu acho que poderia ter uma lista de inconsistências para o Code
// resolver depois de aplicar. Senão não sei o que acontece, simplesmente não funciona e nem sei
// o que fazer." A pilha de gates de conformidade do DS (ui:lint R1/R3/R4, ds-guard "paleta",
// stylelint/fontramp/css-size/conformance) foi desenhada pra forçar tokenização — e por isso
// reprova uma cópia FIEL do protótipo antes de ela existir. Medido na Forja em 2026-09-02:
// 6 gates contra o bundle CSS, 3 contra o JSX (505 glifos, 19 oklch inline, 0 PageHeader canon).
//
// O QUE FAZ. É um REPORTER, nunca um gate: roda os mesmos detectores que os donos rodam e
// escreve a lista em dois lugares — `memory/requisitos/<Mod>/INCONSISTENCIAS-replica.md`
// (leitura humana, é a lista que [W] pediu) e `governance/replica-inconsistencias/<mod>.json`
// (estado por item, com `status` que um humano muda para `resolvida`/`aceita`). Exit code é
// SEMPRE 0 quando mediu; 2 só quando NÃO conseguiu medir (é a regra da lápide §5 2026-07-29:
// "não medi" não vira "verde").
//
// QUEM É DONO DE CADA REGRA (este script NÃO abre régua paralela — cita o dono):
//   R1 cor crua        → app/Console/Commands/UiLintCommand.php (R1)   — espelhado aqui porque
//                        o worktree do agente raramente tem PHP; no CI o dono é quem vale
//   R3 glifo/emoji     → UiLintCommand.php (R3)                        — idem
//   R4 PT-01 canon     → UiLintCommand.php (R4)                        — idem
//   PALETA             → prototipo-ui/ds-guard.mjs (delegado, --report)
//   FONTRAMP           → scripts/conformance-gate.mjs (.fontramp-baseline.json) — contagem espelhada
//   IMPORTANT          → stylelint declaration-no-important (config/stylelint-baseline.json)
//   HEX-CSS            → stylelint color-no-hex — contagem espelhada
//   FLEX-CRU           → scripts/layout-primitives-guard.mjs — contagem espelhada (mesma regra)
//   SINTAXE            → build do Vite/@tailwindcss (parênteses/chaves desbalanceados) — pegou o CI em 2026-09-02
//
// USO
//   node scripts/governance/replica-inconsistencias.mjs --modulo Forja
//   node scripts/governance/replica-inconsistencias.mjs --modulo Forja --prototipo prototipo-ui/cowork/forja-*.jsx
//        ^ mede TAMBÉM o que vai entrar (o JSX do protótipo) e marca como "entrada prevista"
//   node scripts/governance/replica-inconsistencias.mjs --files a.tsx b.css --modulo X
//   node scripts/governance/replica-inconsistencias.mjs --selftest
//
// O que ele NÃO faz: não decide o que é bug e o que é decisão (isso é a régua de três
// categorias da ADR 0385 e mora no `<tela>-visual-comparison.md`); não apaga item — item some
// só quando a medição seguinte não o encontra mais, ou quando um humano marca `aceita`.
import { readFileSync, writeFileSync, existsSync, mkdirSync, readdirSync, statSync, rmSync } from 'node:fs';
import { join, relative, basename, dirname, resolve } from 'node:path';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const argv = process.argv.slice(2);
const arg = (k) => { const i = argv.indexOf(k); return i === -1 ? null : argv[i + 1]; };
const rel = (p) => relative(ROOT, p).replace(/\\/g, '/');

// ── detectores (cada um cita o dono no campo `dono`) ─────────────────────────────────────
const GLIFOS = /[←-⇿☀-➿⬀-⯿\u{1F300}-\u{1FAFF}•■-◿]/gu; // setas, símbolos, dingbats, emoji, geométricos
const DET = [
  { id: 'R1', nome: 'cor crua em Page/CSS', dono: 'UiLintCommand.php R1 · conformance-gate', ext: /\.(tsx|jsx|css)$/,
    conta: (t) => (t.match(/#[0-9a-fA-F]{3,8}\b/g) || []).filter((h) => !/^#(fff|ffffff|000|000000)$/i.test(h)).length + (t.match(/oklch\([0-9.]+ /g) || []).length,
    exemplo: (t) => (t.match(/oklch\([^)]*\)|#[0-9a-fA-F]{3,8}\b/g) || []).slice(0, 2).join(' · ') },
  { id: 'R3', nome: 'glifo/emoji em UI de produto', dono: 'UiLintCommand.php R3', ext: /\.(tsx|jsx)$/,
    conta: (t) => (t.match(GLIFOS) || []).length,
    exemplo: (t) => [...new Set(t.match(GLIFOS) || [])].slice(0, 6).join(' ') },
  { id: 'R4', nome: 'Index sem PageHeader/DataTable canon (PT-01)', dono: 'UiLintCommand.php R4', ext: /Index\.(tsx|jsx)$/,
    conta: (t) => (/<PageHeader\b/.test(t) && /<DataTable\b/.test(t)) ? 0 : 1,
    exemplo: (t) => `PageHeader=${/<PageHeader\b/.test(t) ? 'sim' : 'não'} · DataTable=${/<DataTable\b/.test(t) ? 'sim' : 'não'}` },
  { id: 'FONTRAMP', nome: 'font-size em px fora do ramp --fs-1..9', dono: 'conformance-gate (.fontramp-baseline.json)', ext: /\.css$/,
    conta: (t) => (t.match(/font-size:\s*[0-9.]+px/g) || []).length,
    exemplo: (t) => [...new Set(t.match(/font-size:\s*[0-9.]+px/g) || [])].slice(0, 3).join(' · ') },
  { id: 'IMPORTANT', nome: '!important', dono: 'stylelint declaration-no-important', ext: /\.css$/,
    conta: (t) => (t.match(/!important/g) || []).length, exemplo: () => '' },
  { id: 'HEX-CSS', nome: 'hex literal em CSS', dono: 'stylelint color-no-hex', ext: /\.css$/,
    conta: (t) => (t.match(/#[0-9a-fA-F]{3,8}\b/g) || []).length,
    exemplo: (t) => [...new Set(t.match(/#[0-9a-fA-F]{3,8}\b/g) || [])].slice(0, 4).join(' ') },
  { id: 'FLEX-CRU', nome: 'flex/grid cru em className (sem Stack/Inline/Grid)', dono: 'layout-primitives-guard.mjs', ext: /\.(tsx|jsx)$/,
    conta: (t) => (t.match(/className=["'`][^"'`]*\b(flex|grid)\b(?!-)/g) || []).length, exemplo: () => '' },
  { id: 'SINTAXE', nome: 'parênteses/chaves desbalanceados (o navegador tolera, o Tailwind v4 do Vite NÃO — "Missing opening (")', dono: 'build do Vite (@tailwindcss/vite)', ext: /\.css$/,
    conta: (t) => { const c = t.replace(/\/\*[\s\S]*?\*\//g, ''); const n = (ch) => (c.match(new RegExp('\' + ch, 'g')) || []).length; return (n('(') !== n(')') ? 1 : 0) + (n('{') !== n('}') ? 1 : 0); },
    exemplo: (t) => { const c = t.replace(/\/\*[\s\S]*?\*\//g, ''); const bad = c.split('
').map((l, i) => [i + 1, (l.match(/\(/g) || []).length - (l.match(/\)/g) || []).length]).filter(([, d]) => d !== 0).slice(0, 3); return bad.map(([ln, d]) => `linha ${ln} (${d > 0 ? '+' : ''}${d})`).join(' · '); } },
  { id: 'PALETA', nome: 'família de tokens de cor com prefixo próprio (>=4)', dono: 'prototipo-ui/ds-guard.mjs', ext: /\.css$/,
    conta: (t, f) => {
      try {
        const out = execSync(`node "${join(ROOT, 'prototipo-ui', 'ds-guard.mjs')}" --report "${f}"`, { encoding: 'utf8', cwd: ROOT });
        const m = out.match(/paleta (.+)/); return m ? (m[1].match(/\(\d+\)/g) || []).length : 0;
      } catch { return -1; }
    },
    exemplo: (t, f) => {
      try { const out = execSync(`node "${join(ROOT, 'prototipo-ui', 'ds-guard.mjs')}" --report "${f}"`, { encoding: 'utf8', cwd: ROOT }); const m = out.match(/paleta (.+)/); return m ? m[1].trim() : ''; }
      catch { return 'ds-guard não rodou'; }
    } },
];

function walk(dir, out = []) {
  if (!existsSync(dir)) return out;
  for (const n of readdirSync(dir)) {
    const p = join(dir, n);
    if (statSync(p).isDirectory()) { if (!/node_modules|\.git/.test(n)) walk(p, out); }
    else if (/\.(tsx|jsx|css)$/.test(n)) out.push(p);
  }
  return out;
}

function alvosDoModulo(mod) {
  const files = [
    ...walk(join(ROOT, 'Modules', mod, 'Resources', 'js', 'Pages')),
    ...walk(join(ROOT, 'resources', 'js', 'Pages', mod)),
  ];
  const bundle = join(ROOT, 'resources', 'css', `cowork-${mod.toLowerCase()}-bundle.css`);
  if (existsSync(bundle)) files.push(bundle);
  return files;
}

export function medir(files, { origem = 'aplicado' } = {}) {
  const itens = [];
  for (const f of files) {
    let t; try { t = readFileSync(f, 'utf8'); } catch { itens.push({ id: `ILEGIVEL:${rel(f)}`, regra: 'ILEGIVEL', dono: '-', arquivo: rel(f), contagem: -1, exemplo: 'não medido', origem }); continue; }
    for (const d of DET) {
      if (!d.ext.test(f)) continue;
      const n = d.conta(t, f);
      if (n === 0) continue;
      itens.push({ id: `${d.id}:${rel(f)}`, regra: d.id, nome: d.nome, dono: d.dono, arquivo: rel(f), contagem: n, exemplo: n < 0 ? 'NÃO MEDIDO' : d.exemplo(t, f), origem });
    }
  }
  return itens;
}

function mesclar(novos, anterior) {
  const prev = new Map((anterior?.itens || []).map((i) => [i.id, i]));
  return novos.map((i) => {
    const p = prev.get(i.id);
    return p ? { ...i, status: p.status || 'aberta', nota: p.nota || '', aberta_em: p.aberta_em || hoje() } : { ...i, status: 'aberta', nota: '', aberta_em: hoje() };
  });
}
const hoje = () => new Date().toISOString().slice(0, 10);

// RECEITAS — como cada classe se resolve (pedido [W] 2026-09-02: "pode indicar soluções, eu quero resolver").
// ONDE: `fonte` = no protótipo (Cowork), desce de novo pelo espelho — é o único lugar onde o conserto
// NÃO regride no próximo --export-from (ADR 0374). `code` = no repo, sem mudar o layout. `missão` = some
// sozinho quando a onda troca/apaga a tela. `decisão` = [W] marca `aceita` se for design, não dívida.
const RECEITAS = {
  R1: { onde: 'fonte + code', auto: 'parcial',
    como: 'valor com token equivalente no DS → var() (ex.: oklch(0.55 0.15 295)=var(--accent); 0.58 0.21 25=var(--neg); 0.63 0.16 68=var(--warn)); cor DINÂMICA por hue (prio/fase/papel) → classe com custom property (`style={{"--h":hue}}` + `.fj-x{color:oklch(0.6 0.18 var(--h))}`), que tira o literal do JSX sem mudar 1 pixel. O que não tem token: pedir ao Cowork o token na fonte (FORJA-137), não inventar aqui.' },
  R3: { onde: 'code', auto: 'sim',
    como: 'codemod glifo→lucide com tamanho igual ao do texto: ✦ Sparkles · ⚠ AlertTriangle · ★/☆ Star · → ArrowRight · ↗ ArrowUpRight · ✓ Check · ✗ X · ⚿ KeyRound · ● Circle(fill). Um componente <Glifo> concentra o mapa; o texto ao redor não muda.' },
  R4: { onde: 'missão + decisão', auto: 'não',
    como: 'as telas /project-mgmt/* saem na onda de revogação (item some); nas telas réplica o header É o do protótipo — R4 exige PageHeader+DataTable canon que o protótipo não usa: [W] marca `aceita` OU a regra R4 passa a reconhecer o header do bundle (`.fj-page > header`) como canon. Não reescrever o header pra agradar R4.' },
  FONTRAMP: { onde: 'fonte', auto: 'sim (na fonte)',
    como: 'snap ao ramp --fs-1..9 (10.5/11.5/12.5/13.5/15/18/22/28/38) NO PROTÓTIPO, pelo [CC]; fazer aqui muda o pixel (11→11.5) e a sonda D4 acusa. Enquanto a fonte não snapa: `aceita` com nota, contagem fica visível.' },
  IMPORTANT: { onde: 'fonte', auto: 'sim (na fonte)', como: 'subir a especificidade do seletor em vez de !important; 2 ocorrências, pedir ao [CC].' },
  'HEX-CSS': { onde: 'fonte', auto: 'sim (na fonte)', como: '#fff → var(--accent-fg) / var(--surface) conforme o papel; 6 ocorrências, pedir ao [CC].' },
  'FLEX-CRU': { onde: 'missão', auto: 'não precisa',
    como: 'as telas antigas usam Tailwind `flex`/`grid` cru; a réplica troca por classes do bundle (`.fj-row`, `.fj-toolbar`…) e o item some. Nas 8 telas /project-mgmt/* some pela revogação. NÃO refatorar pra Stack/Inline antes da onda — seria pagar 2×.' },
  SINTAXE: { onde: 'fonte', auto: 'sim (na fonte)',
    como: 'o navegador tolera `)` sobrando, o parser do Tailwind v4 no build do Vite derruba o build inteiro (medido 2026-09-02, forja-page.css:778). Consertar no protótipo; enquanto não desce, desvio de 1 byte DECLARADO no cabeçalho do bundle.' },
  PALETA: { onde: 'fonte (DS)', auto: 'sim',
    como: 'promover --dev/--dev-soft/--dev-line a token do DS (`--origin-DEV*`) no SSOT `resources/css/tokens/semantic.tokens.json` + `npm run tokens:build`; o bundle passa a consumir var() e o ds-guard para de ver família própria. É token novo = decisão [W] (FORJA-137).' },
};

function render(mod, itens, comando) {
  const abertas = itens.filter((i) => i.status === 'aberta');
  const linhas = itens.map((i) => `| ${i.status === 'aberta' ? '🔴' : i.status === 'aceita' ? '🟡' : '✅'} ${i.status} | \`${i.regra}\` | \`${i.arquivo}\` | ${i.contagem < 0 ? 'NÃO MEDIDO' : i.contagem} | ${i.exemplo ? i.exemplo.replace(/\|/g, '\\|') : ''} | ${i.dono} | ${i.origem} |`);
  return `# ${mod} — inconsistências pós-réplica (ADR 0388)

> **O que é.** A lista que a máquina gera DEPOIS de aplicar o protótipo: cada linha é uma regra de
> conformidade do DS que a cópia fiel viola, com o dono da regra e a contagem. Nada aqui bloqueia a
> aplicação — é o que o Code resolve depois, ou o que [W] aceita como decisão (\`status: aceita\`).
> **Gerado por máquina** — não edite contagem; mude só \`status\`/\`nota\` no JSON em
> \`governance/replica-inconsistencias/${mod.toLowerCase()}.json\` e regenere.
>
> Gerado em ${hoje()} · comando: \`${comando}\` · **${abertas.length} aberta(s)** de ${itens.length}.
> \`origem = aplicado\` mede o que está no repo; \`origem = prototipo\` mede o que VAI entrar quando a onda copiar o JSX.

| status | regra | arquivo | contagem | exemplo | dono da regra | origem |
|---|---|---|---:|---|---|---|
${linhas.join('\n') || '| — | — | — | 0 | nada encontrado | — | — |'}

## Soluções por regra (a receita que [W] pediu)

| regra | onde se resolve | automatizável | como |
|---|---|---|---|
${Object.entries(RECEITAS).map(([r, x]) => '| `' + r + '` | ' + x.onde + ' | ' + x.auto + ' | ' + x.como + ' |').join('\n')}

## Como fechar um item

1. **Resolver** (o Code): tokeniza / troca glifo por lucide / adota PageHeader canon **sem mudar o layout** — a próxima medição não encontra o item e ele sai.
2. **Aceitar** (só [W]): a inconsistência é decisão de design, não dívida — \`status: aceita\` + \`nota\` no JSON. Fica visível, não alarma.
3. Nunca: apagar a linha à mão, ou relaxar a regra no dono pra o item sumir.
`;
}

function selftest() {
  const dir = join(tmpdir(), 'replica-inc-selftest-' + Date.now());
  mkdirSync(join(dir, 'Pages'), { recursive: true });
  const tsx = join(dir, 'Pages', 'Index.tsx');
  writeFileSync(tsx, `export default function Index(){ return <div className="flex gap-2" style={{color:"oklch(0.55 0.15 295)"}}>✦ olá ⚠</div> }`);
  const css = join(dir, 'x.css');
  writeFileSync(css, `.a{font-size:12px;color:#abc!important}.b{font-size:13px;background:oklch(0.5 0.1 20))}`);
  const bom = join(dir, 'Pages', 'Bom.tsx');
  writeFileSync(bom, `import {PageHeader} from 'x'; export default function Bom(){ return <PageHeader/> }`);
  const it = medir([tsx, css, bom]);
  const get = (id, f) => it.find((i) => i.regra === id && i.arquivo === rel(f))?.contagem;
  const checks = [
    ['R3 conta 2 glifos', get('R3', tsx) === 2],
    ['R1 conta 1 oklch no tsx', get('R1', tsx) === 1],
    ['R4 acusa Index sem PageHeader', get('R4', tsx) === 1],
    ['FLEX-CRU conta 1', get('FLEX-CRU', tsx) === 1],
    ['FONTRAMP conta 2 no css', get('FONTRAMP', css) === 2],
    ['IMPORTANT conta 1', get('IMPORTANT', css) === 1],
    ['HEX-CSS conta 1', get('HEX-CSS', css) === 1],
    ['SINTAXE acusa o ) sobrando', get('SINTAXE', css) === 1],
    ['arquivo limpo não gera item', !it.some((i) => i.arquivo === rel(bom))],
  ];
  rmSync(dir, { recursive: true, force: true });
  let fail = 0;
  for (const [n, ok] of checks) { console.log((ok ? '  ✓ ' : '  ✗ ') + n); if (!ok) fail++; }
  console.log(fail ? `✗ selftest: ${fail} falha(s)` : `✓ replica-inconsistencias selftest OK — ${checks.length} asserts (mede R1/R3/R4/FLEX/FONTRAMP/IMPORTANT/HEX; controle negativo: arquivo limpo)`);
  process.exit(fail ? 1 : 0);
}

function main() {
  if (argv.includes('--selftest')) return selftest();
  const mod = arg('--modulo');
  if (!mod) { console.error('uso: --modulo <Mod> [--prototipo <arquivos...>] [--files <arquivos...>] | --selftest'); process.exit(2); }
  let files = [];
  const fi = argv.indexOf('--files');
  if (fi !== -1) files = argv.slice(fi + 1).filter((a) => !a.startsWith('--')).map((f) => resolve(ROOT, f));
  else files = alvosDoModulo(mod);
  const pi = argv.indexOf('--prototipo');
  const protos = pi !== -1 ? argv.slice(pi + 1).filter((a) => !a.startsWith('--')).map((f) => resolve(ROOT, f)) : [];
  if (!files.length && !protos.length) { console.error(`✗ NÃO MEDI — nenhum arquivo .tsx/.jsx/.css encontrado para o módulo ${mod}`); process.exit(2); }
  const jsonPath = join(ROOT, 'governance', 'replica-inconsistencias', `${mod.toLowerCase()}.json`);
  const mdDir = join(ROOT, 'memory', 'requisitos', mod);
  if (!existsSync(mdDir)) { console.error(`✗ NÃO MEDI — memory/requisitos/${mod}/ não existe (o módulo não tem casa canônica)`); process.exit(2); }
  const anterior = existsSync(jsonPath) ? JSON.parse(readFileSync(jsonPath, 'utf8')) : null;
  const itens = mesclar([...medir(files, { origem: 'aplicado' }), ...medir(protos, { origem: 'prototipo' })], anterior);
  const comando = `node scripts/governance/replica-inconsistencias.mjs --modulo ${mod}${protos.length ? ' --prototipo …' : ''}`;
  mkdirSync(dirname(jsonPath), { recursive: true });
  writeFileSync(jsonPath, JSON.stringify({ modulo: mod, gerado_em: new Date().toISOString(), comando, arquivos_medidos: files.length + protos.length, itens }, null, 2) + '\n');
  writeFileSync(join(mdDir, 'INCONSISTENCIAS-replica.md'), render(mod, itens, comando));
  const abertas = itens.filter((i) => i.status === 'aberta').length;
  const naoMedidos = itens.filter((i) => i.contagem < 0).length;
  console.log(`📋 ${mod}: ${itens.length} item(ns) · ${abertas} aberta(s) · ${naoMedidos} não medido(s) · ${files.length} arquivo(s) aplicados + ${protos.length} do protótipo`);
  console.log(`   → ${rel(join(mdDir, 'INCONSISTENCIAS-replica.md'))}\n   → ${rel(jsonPath)}`);
  process.exit(naoMedidos ? 2 : 0);
}
main();
