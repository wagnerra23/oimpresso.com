#!/usr/bin/env node
// conferir-export.mjs — porteiro do pacote. Roda antes de exportar (aqui) e depois de
// descompactar (no Code). Zero dependências: `node conferir-export.mjs [pasta]`.
//
// Cada teste abaixo existe porque um export já quebrou por ele. O histórico de cada um
// está em pre-export.md, na seção de mesmo número. Nenhum valor é cravado no código:
// o namespace e a contagem de componentes saem do cabeçalho `@ds-bundle` do próprio
// bundle, e os tamanhos saem de `_ds/_export-baseline.json`.
//
// Saída: uma linha por teste. Exit 1 se algum FALHA. Os testes marcados INFO nunca
// reprovam — são medições que o exportador, não o projeto, decide.
//
// `--baseline` reescreve `_ds/_export-baseline.json` a partir do espelho atual.
// Use SÓ depois de regenerar o espelho da fonte viva do DS.

import { readdirSync, readFileSync, statSync, existsSync, writeFileSync } from 'node:fs';
import { join, extname, dirname, resolve, relative, basename } from 'node:path';
import { createHash } from 'node:crypto';

const ROOT = resolve(process.argv[2] && !process.argv[2].startsWith('--') ? process.argv[2] : '.');
const ESCREVER_BASELINE = process.argv.includes('--baseline');

const CODIGO = new Set(['.html', '.jsx', '.js', '.mjs', '.css']);
const IGNORAR_DIR = new Set(['node_modules', '.git', 'screenshots', 'uploads', '.thumbnail']);

const resultados = [];
const ok = (n, t, d = '') => resultados.push({ n, t, s: 'PASSA', d });
const falha = (n, t, d) => resultados.push({ n, t, s: 'FALHA', d });
const info = (n, t, d) => resultados.push({ n, t, s: 'INFO', d });

function listar(dir, out = []) {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    if (e.name.startsWith('.') && e.name !== '.thumbnail') continue;
    const p = join(dir, e.name);
    if (e.isDirectory()) { if (!IGNORAR_DIR.has(e.name)) listar(p, out); }
    else out.push(p);
  }
  return out;
}

const todos = listar(ROOT);
const rel = (p) => relative(ROOT, p).split('\\').join('/');
const texto = (p) => readFileSync(p, 'utf8');
const bytes = (p) => statSync(p).size;

// Código do protótipo = tudo que roda, menos o espelho do DS (que é cópia da fonte viva).
// O próprio porteiro cita os nomes das pastas apagadas (teste 7) e o namespace (teste 4):
// varrer a si mesmo faria o teste reprovar sempre.
const EU = /^conferir-export\.(mjs|cowork\.js)$/;
const arquivosCodigo = todos.filter((p) => CODIGO.has(extname(p).toLowerCase()) && !rel(p).startsWith('_ds/') && !EU.test(basename(p)));
const arquivosMd = todos.filter((p) => extname(p).toLowerCase() === '.md');

// ─────────────────────────────────────────────────────────────── 1. espelho único
const dirDs = join(ROOT, '_ds');
let espelho = null;
if (!existsSync(dirDs)) {
  falha(1, 'espelho único em _ds/', 'a pasta _ds/ não existe no pacote');
} else {
  const pastas = readdirSync(dirDs, { withFileTypes: true }).filter((e) => e.isDirectory()).map((e) => e.name);
  if (pastas.length === 1) { espelho = join(dirDs, pastas[0]); ok(1, 'espelho único em _ds/', pastas[0]); }
  else falha(1, 'espelho único em _ds/', `${pastas.length} pastas: ${pastas.join(', ')} — duas cópias do DS sempre divergem`);
  // cópias enfiadas dentro dos pacotes de handoff
  const aninhados = todos.filter((p) => /\/_ds\//.test('/' + rel(p)) && !rel(p).startsWith('_ds/'));
  if (aninhados.length) falha(1, 'nenhum _ds/ aninhado', `${aninhados.length} arquivo(s), ex: ${rel(aninhados[0])}`);
}

// ─────────────────────────────────────────────────── 2. o bundle e o nome que ele publica
let NS = null, nComp = 0;
const bundle = espelho && join(espelho, '_ds_bundle.js');
if (!bundle || !existsSync(bundle)) {
  falha(2, 'bundle do DS presente', 'sem _ds_bundle.js no espelho');
} else {
  const src = texto(bundle);
  const cab = src.match(/@ds-bundle:\s*(\{[\s\S]*?\})\s*\*\//);
  try {
    const meta = JSON.parse(cab[1]);
    NS = meta.namespace;
    nComp = (meta.components || []).length;
    ok(2, 'namespace lido do cabeçalho @ds-bundle', `${NS} · ${nComp} componentes`);
  } catch {
    falha(2, 'namespace lido do cabeçalho @ds-bundle', 'cabeçalho @ds-bundle ausente ou ilegível — não dá para derivar o nome, e cravá-lo aqui foi o erro de origem');
  }
  // 3. alias acrescentado no fim (o defeito que apagava em toda regeneração)
  const cauda = src.slice(-400);
  const aliases = [...src.matchAll(/^\s*window\.([A-Za-z0-9_$]+)\s*=\s*window\.([A-Za-z0-9_$]+)\s*;/gm)];
  if (aliases.length) falha(3, 'bundle sem alias acrescentado', aliases.map((a) => `${a[1]} = ${a[2]}`).join(' · ') + ' — o DS não gera isso; regeneração apaga em silêncio');
  else if (!/\}\)\(\);\s*$/.test(src)) falha(3, 'bundle sem alias acrescentado', `não termina em })(); — cauda: ${JSON.stringify(cauda.slice(-60))}`);
  else ok(3, 'bundle sem alias acrescentado', 'termina em })();');
}

// ─────────────────────────────── 4. as páginas leem o nome que o bundle publica
if (NS) {
  const lidos = new Map(); // nome global → arquivos
  const re = /window\.([A-Za-z0-9_$]*(?:DesignSystem|PontoWR2)[A-Za-z0-9_$]*)/g;
  const reTag = /component-from-global-scope=["']([A-Za-z0-9_$]*(?:DesignSystem|PontoWR2)[A-Za-z0-9_$]*)\./g;
  for (const p of arquivosCodigo) {
    const t = texto(p);
    for (const m of [...t.matchAll(re), ...t.matchAll(reTag)]) {
      if (m[1] === NS) continue;
      if (!lidos.has(m[1])) lidos.set(m[1], new Set());
      lidos.get(m[1]).add(rel(p));
    }
  }
  if (lidos.size === 0) ok(4, `código lê só ${NS}`, `${arquivosCodigo.length} arquivos de código varridos`);
  else {
    const det = [...lidos].map(([n, s]) => `${n} em ${s.size} arquivo(s): ${[...s].slice(0, 4).join(', ')}${s.size > 4 ? '…' : ''}`);
    falha(4, `código lê só ${NS}`, det.join(' | ') + ' — some sem erro no console');
  }
}

// ─────────────────────────────────── 5. espelho igual à linha de base da regeneração
// A nota de tamanhos mora na RAIZ, não em _ds/: _ds/ é cópia do design system, e nota do
// projeto dentro dela se perde na próxima regeneração.
const pBase = join(ROOT, '_export-baseline.json');
if (espelho && ESCREVER_BASELINE) {
  const alvos = ['_ds_bundle.js', '_ds_manifest.json', 'colors_and_type.css', 'styles.css', 'cockpit_domains.css',
    'support.js', '_adherence.oxlintrc.json',
    'assets/fonts/ibm-plex-sans-400.woff2', 'assets/fonts/ibm-plex-sans-500.woff2',
    'assets/fonts/ibm-plex-sans-600.woff2', 'assets/fonts/ibm-plex-sans-700.woff2',
    'assets/fonts/ibm-plex-mono-400.woff2', 'assets/fonts/ibm-plex-mono-500.woff2',
    'assets/fonts/ibm-plex-mono-600.woff2'];
  const arquivos = {};
  for (const a of alvos) { const f = join(espelho, a); if (existsSync(f)) arquivos[a] = bytes(f); }
  writeFileSync(pBase, JSON.stringify({ gerado: new Date().toISOString().slice(0, 10), espelho: basename(espelho), namespace: NS, arquivos }, null, 2) + '\n');
  info(5, 'linha de base reescrita', rel(pBase));
} else if (!espelho) {
  // já reprovou no 1
} else if (!existsSync(pBase)) {
  falha(5, 'espelho bate com a linha de base', '_export-baseline.json não existe na raiz — rode com --baseline logo após regenerar o espelho');
} else {
  const b = JSON.parse(texto(pBase));
  const difs = [];
  for (const [a, n] of Object.entries(b.arquivos || {})) {
    const f = join(espelho, a);
    if (!existsSync(f)) { difs.push(`${a}: sumiu`); continue; }
    const real = bytes(f);
    if (real !== n) difs.push(`${a}: ${real} B ≠ ${n} B`);
  }
  if (b.namespace && NS && b.namespace !== NS) difs.push(`namespace: ${NS} ≠ ${b.namespace}`);
  if (difs.length) falha(5, 'espelho bate com a linha de base', difs.join(' · ') + ' — ou alguém editou o espelho, ou ele foi regenerado sem atualizar a base');
  else ok(5, 'espelho bate com a linha de base', `${Object.keys(b.arquivos).length} arquivos`);
}

// ────────────────────────── 6. fontes: peso duplicado é cópia disfarçada da 400
if (espelho) {
  const dirF = join(espelho, 'assets/fonts');
  if (!existsSync(dirF)) falha(6, 'fontes com pesos distintos', 'assets/fonts/ não existe no espelho');
  else {
    const sans = readdirSync(dirF).filter((n) => /ibm-plex-sans-\d+\.woff2$/.test(n));
    const porTamanho = new Map();
    for (const n of sans) { const s = bytes(join(dirF, n)); porTamanho.set(s, [...(porTamanho.get(s) || []), n]); }
    const iguais = [...porTamanho.values()].filter((v) => v.length > 1);
    const leves = sans.filter((n) => bytes(join(dirF, n)) < 55000);
    if (iguais.length) falha(6, 'fontes com pesos distintos', iguais.map((v) => v.join(' = ')).join(' · ') + ' — arquivos idênticos são a 400 copiada; a prévia mostra o peso errado');
    else if (leves.length) falha(6, 'fontes com pesos distintos', `${leves.join(', ')} abaixo de 55 KB — suspeito de subset truncado`);
    else ok(6, 'fontes com pesos distintos', sans.map((n) => `${n.match(/\d+/)[0]}:${(bytes(join(dirF, n)) / 1000).toFixed(1)}k`).join(' '));
  }
}

// ─────────────────────────────────── 7. caminhos para pastas que não existem mais
{
  const mortos = ['019dd02f', 'd7f88676', 'office-impresso-atual'];
  const achados = [];
  for (const p of arquivosCodigo) {
    const t = texto(p);
    for (const m of mortos) if (t.includes(m)) achados.push(`${rel(p)} → ${m}`);
  }
  if (achados.length) falha(7, 'sem caminho para espelho apagado', achados.join(' · '));
  else ok(7, 'sem caminho para espelho apagado', 'nenhum em código (os .md podem citar como histórico)');
}

// ── 11. página que monta tela do DS tem de carregar o bundle (senão abre EM BRANCO)
if (NS) {
  const faltando = [];
  for (const p of todos.filter((f) => extname(f) === '.html' && !rel(f).startsWith('_ds/'))) {
    const t = texto(p);
    const carregados = [...t.matchAll(/<script[^>]+src=["']([^"']+)["']/g)].map((x) => x[1]);
    if (carregados.some((u) => /_ds_bundle\.js/.test(u))) continue;
    // Quais .jsx locais esta página carrega, e algum deles lê o namespace?
    const consumidores = [];
    for (const u of carregados) {
      if (/^(https?:|data:|\/\/)/.test(u)) continue;
      if (!/\.jsx?$/.test(u)) continue;
      const alvo = resolve(dirname(p), u.split('?')[0]);
      if (!existsSync(alvo)) continue;
      if (texto(alvo).includes(NS)) consumidores.push(u);
    }
    if (consumidores.length) faltando.push(`${rel(p)} carrega ${consumidores.length} arquivo(s) que leem ${NS} (${consumidores.slice(0, 3).join(', ')}) e nenhum _ds_bundle.js`);
  }
  faltando.length ? falha(11, 'página que usa o DS carrega o bundle', faltando.join(' | ') + ' — a tela abre em branco e o console não acusa')
                  : ok(11, 'página que usa o DS carrega o bundle', 'nenhuma página órfã');
}

// ─────────────────────────────── 8. toda referência local de HTML existe no pacote
{
  const quebradas = [];
  for (const p of todos.filter((f) => extname(f) === '.html')) {
    const t = texto(p);
    for (const m of t.matchAll(/(?:src|href)=["']([^"'#]+)["']/g)) {
      const u = m[1];
      if (/^(https?:|data:|mailto:|\/\/)/.test(u)) continue;
      const alvo = resolve(dirname(p), u.split('?')[0]);
      if (!existsSync(alvo)) quebradas.push(`${rel(p)} → ${u}`);
    }
  }
  if (quebradas.length) falha(8, 'referências locais resolvem', `${quebradas.length}: ` + quebradas.slice(0, 6).join(' · ') + (quebradas.length > 6 ? ' …' : ''));
  else ok(8, 'referências locais resolvem', 'todas');
}

// ──────────────────────────────────────── 9. arquivo repetido em dois endereços
{
  const porHash = new Map();
  for (const p of todos) {
    if (bytes(p) < 2000) continue;
    if (rel(p).startsWith('_ds/')) continue;
    const h = createHash('sha1').update(readFileSync(p)).digest('hex');
    porHash.set(h, [...(porHash.get(h) || []), rel(p)]);
  }
  const dups = [...porHash.values()].filter((v) => v.length > 1);
  if (dups.length) info(9, 'arquivos idênticos em dois endereços', `${dups.length} par(es): ` + dups.slice(0, 5).map((v) => v.join(' = ')).join(' · ') + ' — cópia sempre diverge; decida qual é o dono');
  else ok(9, 'arquivos idênticos em dois endereços', 'nenhum');
}

// ────────────────────────────── 10. CRLF — medido por byte, informativo por natureza
{
  const crlf = [];
  for (const p of todos) {
    const e = extname(p).toLowerCase();
    if (!CODIGO.has(e) && e !== '.md' && e !== '.json') continue;
    const b = readFileSync(p);
    for (let i = 0; i < b.length - 1; i++) if (b[i] === 13 && b[i + 1] === 10) { crlf.push(rel(p)); break; }
  }
  info(10, 'quebra de linha LF', crlf.length === 0 ? 'todos em LF'
    : `${crlf.length} arquivo(s) em CRLF — se o projeto está em LF, quem converteu foi o empacotador, não o projeto`);
}

// ─────────────────────────────────────────────────────────────────────── saída
const larg = Math.max(...resultados.map((r) => r.t.length));
console.log(`\nconferir-export · ${rel(ROOT) || '.'} · ${todos.length} arquivos\n`);
for (const r of resultados.sort((a, b) => a.n - b.n)) {
  const marca = r.s === 'PASSA' ? '  ok  ' : r.s === 'FALHA' ? ' FALHA' : ' info ';
  console.log(`${marca} ${String(r.n).padStart(2)}. ${r.t.padEnd(larg)}  ${r.d}`);
}
const nFalhas = resultados.filter((r) => r.s === 'FALHA').length;
console.log(nFalhas ? `\n${nFalhas} teste(s) reprovado(s) — ver pre-export.md, seção de mesmo número.\n` : '\nPacote liberado.\n');
process.exit(nFalhas ? 1 : 0);
