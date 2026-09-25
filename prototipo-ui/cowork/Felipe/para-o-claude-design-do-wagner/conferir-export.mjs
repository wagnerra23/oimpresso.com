#!/usr/bin/env node
// conferir-export.mjs — porteiro de pacote para protótipo que consome um design system.
// Zero dependências: `node conferir-export.mjs [pasta]`. Sai 0 se passou, 1 se reprovou.
//
// Onze testes. Cada um existe porque um export real quebrou por ele; o porquê de cada um
// está em `pre-export.md`, na seção de mesmo número. Nenhum valor de projeto é cravado
// aqui: o nome global do DS sai do cabeçalho `@ds-bundle` do próprio bundle, e os tamanhos
// esperados saem de `_export-baseline.json`, gravado por `--baseline`.
//
// `--baseline` reescreve `_export-baseline.json`. Rodar SÓ logo depois de regenerar o
// espelho do DS a partir da fonte viva — nunca para calar um alarme.

import { readdirSync, readFileSync, statSync, existsSync, writeFileSync } from 'node:fs';
import { join, extname, dirname, resolve, relative, basename } from 'node:path';
import { createHash } from 'node:crypto';

// ─────────────────────────────────────────────────────────────────── CONFIGURAÇÃO
// A única parte que cada projeto ajusta. Tudo abaixo disto é genérico.
const CFG = {
  // Pasta onde vive a cópia local do design system. Padrão do Cowork: '_ds'.
  dirEspelho: '_ds',
  // Nomes de pastas/espelhos que FORAM APAGADOS e não podem mais ser citados em código.
  // Comece vazio. Acrescente um nome toda vez que apagar um espelho antigo — é o que
  // impede um <link> apontar para pasta inexistente (a página abre sem tokens e ninguém vê).
  caminhosMortos: [],
  // Pastas ignoradas na varredura.
  ignorar: ['node_modules', '.git', 'screenshots', 'uploads'],
  // Piso de tamanho para arquivo de fonte (bytes). Abaixo disso, suspeita de subset truncado.
  pisoFonte: 20000,
};
// ────────────────────────────────────────────────────────────────────────────────

const ROOT = resolve(process.argv[2] && !process.argv[2].startsWith('--') ? process.argv[2] : '.');
const ESCREVER_BASELINE = process.argv.includes('--baseline');
const CODIGO = new Set(['.html', '.jsx', '.js', '.mjs', '.css']);
const IGNORAR = new Set(CFG.ignorar);

const R = [];
const ok = (n, t, d = '') => R.push({ n, t, s: 'PASSA', d });
const falha = (n, t, d) => R.push({ n, t, s: 'FALHA', d });
const info = (n, t, d) => R.push({ n, t, s: 'INFO', d });

function listar(dir, out = []) {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    if (e.name.startsWith('.')) continue;
    const p = join(dir, e.name);
    if (e.isDirectory()) { if (!IGNORAR.has(e.name)) listar(p, out); } else out.push(p);
  }
  return out;
}
const todos = listar(ROOT);
const rel = (p) => relative(ROOT, p).split('\\').join('/');
const tx = (p) => readFileSync(p, 'utf8');
const bt = (p) => statSync(p).size;
const EU = /^conferir-export\.(mjs|cowork\.js)$/;   // o porteiro não se varre
const arquivosCodigo = todos.filter((p) => CODIGO.has(extname(p).toLowerCase())
  && !rel(p).startsWith(CFG.dirEspelho + '/') && !EU.test(basename(p)));

// ───────────────────────────────────────────────── 1. espelho único, nenhum aninhado
const dirDs = join(ROOT, CFG.dirEspelho);
let espelho = null;
if (!existsSync(dirDs)) {
  falha(1, `espelho único em ${CFG.dirEspelho}/`, `a pasta ${CFG.dirEspelho}/ não existe no pacote`);
} else {
  const pastas = readdirSync(dirDs, { withFileTypes: true }).filter((e) => e.isDirectory()).map((e) => e.name);
  if (pastas.length === 1) { espelho = join(dirDs, pastas[0]); ok(1, `espelho único em ${CFG.dirEspelho}/`, pastas[0]); }
  else falha(1, `espelho único em ${CFG.dirEspelho}/`, `${pastas.length} pastas: ${pastas.join(', ')} — duas cópias do mesmo DS sempre divergem`);
  const aninhados = todos.filter((p) => ('/' + rel(p)).includes(`/${CFG.dirEspelho}/`) && !rel(p).startsWith(CFG.dirEspelho + '/'));
  if (aninhados.length) falha(1, `nenhum ${CFG.dirEspelho}/ aninhado`, `${aninhados.length} arquivo(s), ex: ${rel(aninhados[0])}`);
}

// ──────────────────────────── 2 e 3. o bundle e o nome global que ele publica
let NS = null;
const bundle = espelho && join(espelho, '_ds_bundle.js');
if (!bundle || !existsSync(bundle)) {
  falha(2, 'bundle do DS presente', 'sem _ds_bundle.js no espelho');
} else {
  const src = tx(bundle);
  const cab = src.match(/@ds-bundle:\s*(\{[\s\S]*?\})\s*\*\//);
  try {
    const meta = JSON.parse(cab[1]);
    NS = meta.namespace;
    ok(2, 'namespace lido do cabeçalho @ds-bundle', `${NS} · ${(meta.components || []).length} componentes`);
  } catch {
    falha(2, 'namespace lido do cabeçalho @ds-bundle', 'cabeçalho ausente ou ilegível — sem ele o nome do DS é cravado em prosa, que foi o erro de origem');
  }
  const alias = [...src.matchAll(/^\s*window\.([A-Za-z0-9_$]+)\s*=\s*window\.([A-Za-z0-9_$]+)\s*;/gm)];
  if (alias.length) falha(3, 'bundle sem alias acrescentado', alias.map((a) => `${a[1]} = ${a[2]}`).join(' · ') + ' — o gerador do DS não produz isso; toda regeneração apaga em silêncio');
  else if (!/\}\)\(\);\s*$/.test(src)) falha(3, 'bundle sem alias acrescentado', `não termina em })(); — cauda ${JSON.stringify(src.slice(-50))}`);
  else ok(3, 'bundle sem alias acrescentado', 'termina em })();');
}

// ──────────────────────────────── 4. todo código lê só o nome publicado
if (NS) {
  const raiz = NS.replace(/_[0-9a-f]{4,}$/i, '');           // tronco do nome, sem o sufixo de id
  const re = new RegExp(`window\\.(${raiz}[A-Za-z0-9_$]*|[A-Za-z0-9_$]*DesignSystem[A-Za-z0-9_$]*)`, 'g');
  const reTag = new RegExp(`component-from-global-scope=["']([A-Za-z0-9_$]+)\\.`, 'g');
  const lidos = new Map();
  for (const p of arquivosCodigo) {
    const t = tx(p);
    for (const m of [...t.matchAll(re), ...t.matchAll(reTag)]) {
      if (m[1] === NS || !/DesignSystem|Design_|DS_/i.test(m[1])) continue;
      if (!lidos.has(m[1])) lidos.set(m[1], new Set());
      lidos.get(m[1]).add(rel(p));
    }
  }
  if (!lidos.size) ok(4, `código lê só ${NS}`, `${arquivosCodigo.length} arquivos varridos`);
  else falha(4, `código lê só ${NS}`, [...lidos].map(([n, s]) => `${n} em ${s.size}: ${[...s].slice(0, 4).join(', ')}`).join(' | ') + ' — some sem erro no console');
}

// ───────────────────────── 5. espelho igual à linha de base da última regeneração
const pBase = join(ROOT, '_export-baseline.json');   // na RAIZ: _ds/ é cópia do DS e nota nossa lá dentro se perde
if (espelho && ESCREVER_BASELINE) {
  const arquivos = {};
  for (const p of listar(espelho)) {
    const r = relative(espelho, p).split('\\').join('/');
    if (/^(_ds_bundle\.js|_ds_manifest\.json)$/.test(r) || /\.(css|woff2)$/i.test(r)) arquivos[r] = bt(p);
  }
  writeFileSync(pBase, JSON.stringify({ gerado: new Date().toISOString().slice(0, 10), espelho: basename(espelho), namespace: NS, arquivos }, null, 2) + '\n');
  info(5, 'linha de base reescrita', `${Object.keys(arquivos).length} arquivos`);
} else if (espelho && !existsSync(pBase)) {
  falha(5, 'espelho bate com a linha de base', '_export-baseline.json não existe na raiz — rode com --baseline logo após regenerar o espelho');
} else if (espelho) {
  const b = JSON.parse(tx(pBase));
  const difs = [];
  for (const [a, n] of Object.entries(b.arquivos || {})) {
    const f = join(espelho, a);
    if (!existsSync(f)) difs.push(`${a}: sumiu`);
    else if (bt(f) !== n) difs.push(`${a}: ${bt(f)} B ≠ ${n} B`);
  }
  if (b.namespace && NS && b.namespace !== NS) difs.push(`namespace: ${NS} ≠ ${b.namespace}`);
  difs.length ? falha(5, 'espelho bate com a linha de base', difs.slice(0, 8).join(' · ') + ' — ou editaram o espelho, ou regeneraram sem atualizar a base')
              : ok(5, 'espelho bate com a linha de base', `${Object.keys(b.arquivos).length} arquivos`);
}

// ─────────────── 6. fontes: peso repetido é um arquivo copiado sobre os outros
if (espelho) {
  const fontes = listar(espelho).filter((p) => /\.woff2?$/i.test(p));
  if (!fontes.length) info(6, 'fontes com pesos distintos', 'nenhuma fonte no espelho');
  else {
    // agrupa por família = nome do arquivo sem o peso final (ex.: "ibm-plex-sans-500" → "ibm-plex-sans")
    const porFam = new Map();
    for (const p of fontes) {
      const n = basename(p).replace(/\.woff2?$/i, '');
      const fam = n.replace(/[-_]?\d{3}$/, '');
      porFam.set(fam, [...(porFam.get(fam) || []), { n, b: bt(p) }]);
    }
    const probs = [];
    for (const [fam, lista] of porFam) {
      if (lista.length < 2) continue;
      const porTam = new Map();
      for (const it of lista) porTam.set(it.b, [...(porTam.get(it.b) || []), it.n]);
      for (const [b, ns] of porTam) if (ns.length > 1) probs.push(`${ns.join(' = ')} (${b} B) — mesmo arquivo em pesos diferentes`);
      const leves = lista.filter((i) => i.b < CFG.pisoFonte);
      if (leves.length) probs.push(`${leves.map((i) => i.n).join(', ')} abaixo de ${CFG.pisoFonte} B`);
    }
    probs.length ? falha(6, 'fontes com pesos distintos', probs.join(' · ') + ' — a prévia mostra o peso errado e nada acusa')
                 : ok(6, 'fontes com pesos distintos', `${fontes.length} arquivos, nenhum tamanho repetido`);
  }
}

// ─────────────────────────────── 7. caminho para espelho que não existe mais
if (CFG.caminhosMortos.length) {
  const achados = [];
  for (const p of arquivosCodigo) { const t = tx(p); for (const m of CFG.caminhosMortos) if (t.includes(m)) achados.push(`${rel(p)} → ${m}`); }
  achados.length ? falha(7, 'sem caminho para espelho apagado', achados.join(' · '))
                 : ok(7, 'sem caminho para espelho apagado', 'nenhum em código');
} else {
  info(7, 'sem caminho para espelho apagado', 'CFG.caminhosMortos está vazio — preencha ao apagar um espelho antigo');
}

// ─────────────────────── 8. toda referência local de HTML existe no pacote
{
  const quebradas = [];
  for (const p of todos.filter((f) => extname(f) === '.html')) {
    for (const m of tx(p).matchAll(/(?:src|href)=["']([^"'#]+)["']/g)) {
      const u = m[1];
      if (/^(https?:|data:|mailto:|\/\/)/.test(u)) continue;
      if (!existsSync(resolve(dirname(p), u.split('?')[0]))) quebradas.push(`${rel(p)} → ${u}`);
    }
  }
  quebradas.length ? falha(8, 'referências locais resolvem', `${quebradas.length}: ${quebradas.slice(0, 6).join(' · ')}`)
                   : ok(8, 'referências locais resolvem', 'todas');
}

// ──── 11. página que monta tela do DS tem de carregar o bundle (senão abre EM BRANCO)
if (NS) {
  const orfas = [];
  for (const p of todos.filter((f) => extname(f) === '.html' && !rel(f).startsWith(CFG.dirEspelho + '/'))) {
    const carregados = [...tx(p).matchAll(/<script[^>]+src=["']([^"']+)["']/g)].map((x) => x[1]);
    if (carregados.some((u) => /_ds_bundle\.js/.test(u))) continue;
    const cons = [];
    for (const u of carregados) {
      if (/^(https?:|data:|\/\/)/.test(u) || !/\.jsx?$/.test(u)) continue;
      const alvo = resolve(dirname(p), u.split('?')[0]);
      if (existsSync(alvo) && tx(alvo).includes(NS)) cons.push(u);
    }
    if (cons.length) orfas.push(`${rel(p)} carrega ${cons.length} arquivo(s) que leem ${NS} (${cons.slice(0, 3).join(', ')}) e nenhum _ds_bundle.js`);
  }
  orfas.length ? falha(11, 'página que usa o DS carrega o bundle', orfas.join(' | ') + ' — a tela abre em branco e o console não acusa')
               : ok(11, 'página que usa o DS carrega o bundle', 'nenhuma página órfã');
}

// ──────────────────────────── 9 e 10. informativos: duplicatas e quebra de linha
{
  const porHash = new Map();
  for (const p of todos) {
    if (bt(p) < 2000 || rel(p).startsWith(CFG.dirEspelho + '/')) continue;
    const h = createHash('sha1').update(readFileSync(p)).digest('hex');
    porHash.set(h, [...(porHash.get(h) || []), rel(p)]);
  }
  const dups = [...porHash.values()].filter((v) => v.length > 1);
  info(9, 'arquivos idênticos em dois endereços', dups.length ? `${dups.length} par(es): ${dups.slice(0, 5).map((v) => v.join(' = ')).join(' · ')} — decida qual é o dono` : 'nenhum');

  const crlf = [];
  for (const p of todos) {
    const e = extname(p).toLowerCase();
    if (!CODIGO.has(e) && e !== '.md' && e !== '.json') continue;
    const b = readFileSync(p);
    for (let i = 0; i < b.length - 1; i++) if (b[i] === 13 && b[i + 1] === 10) { crlf.push(rel(p)); break; }
  }
  info(10, 'quebra de linha LF', crlf.length ? `${crlf.length} em CRLF (medido por byte)` : 'todos em LF');
}

const larg = Math.max(...R.map((r) => r.t.length));
console.log(`\nconferir-export · ${rel(ROOT) || '.'} · ${todos.length} arquivos\n`);
for (const r of R.sort((a, b) => a.n - b.n)) {
  console.log(`${r.s === 'PASSA' ? '  ok  ' : r.s === 'FALHA' ? ' FALHA' : ' info '} ${String(r.n).padStart(2)}. ${r.t.padEnd(larg)}  ${r.d}`);
}
const nf = R.filter((r) => r.s === 'FALHA').length;
console.log(nf ? `\n${nf} teste(s) reprovado(s) — ver pre-export.md, seção de mesmo número.\n` : '\nPacote liberado.\n');
process.exit(nf ? 1 : 0);
