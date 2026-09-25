#!/usr/bin/env node
// conferir-ds.mjs — porteiro do export do DESIGN SYSTEM (não do protótipo).
//
// Onde este arquivo mora: na RAIZ DO PROJETO DO DS, ao lado de `_ds_manifest.json` e
// `_ds_bundle.js`. Ele foi escrito no projeto do protótipo porque é de lá que eu escrevo,
// mas o lugar dele é o DS — copiar os dois (este e `pre-export-ds.md`) para lá.
//
// Quando rodar: DEPOIS de puxar as atualizações do DS do Wagner e ANTES de exportar; e de
// novo no Code, sobre o pacote descompactado. `node conferir-ds.mjs [pasta]`.
//
// A pergunta que ele responde é uma só: **o que o manifest promete existe no disco, e o
// bundle publica exatamente isso?** Todo teste sai do `_ds_manifest.json` ou do cabeçalho
// `@ds-bundle` — nenhum número cravado aqui.
//
// `--baseline` escreve `_export-baseline-ds.json` na raiz: o recibo do último pull. Rodar
// logo DEPOIS de puxar do Wagner e conferir que está tudo certo, nunca antes.

import { readdirSync, readFileSync, statSync, existsSync, writeFileSync } from 'node:fs';
import { join, extname, dirname, resolve, relative, basename } from 'node:path';
import { createHash } from 'node:crypto';

const ROOT = resolve(process.argv[2] && !process.argv[2].startsWith('--') ? process.argv[2] : '.');
const ESCREVER_BASELINE = process.argv.includes('--baseline');
const IGNORAR_DIR = new Set(['node_modules', '.git', 'uploads', 'screenshots', 'arquivo', 'memory', 'preview']);

const R = [];
const ok = (n, t, d = '') => R.push({ n, t, s: 'PASSA', d });
const falha = (n, t, d) => R.push({ n, t, s: 'FALHA', d });
const info = (n, t, d) => R.push({ n, t, s: 'INFO', d });

function listar(dir, out = []) {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    if (e.name.startsWith('.')) continue;
    const p = join(dir, e.name);
    if (e.isDirectory()) { if (!IGNORAR_DIR.has(e.name)) listar(p, out); } else out.push(p);
  }
  return out;
}
const todos = listar(ROOT);
const rel = (p) => relative(ROOT, p).split('\\').join('/');
const tx = (p) => readFileSync(p, 'utf8');
const bt = (p) => statSync(p).size;
const aqui = (r) => join(ROOT, r);

// ───────────────────────────────── 1. manifest e bundle na raiz, sem segunda cópia
const pMan = aqui('_ds_manifest.json'), pBun = aqui('_ds_bundle.js');
let man = null;
if (!existsSync(pMan) || !existsSync(pBun)) {
  falha(1, 'manifest e bundle na raiz', `${existsSync(pMan) ? '' : '_ds_manifest.json '}${existsSync(pBun) ? '' : '_ds_bundle.js '}ausente — sem isso o pacote não é um DS`);
} else {
  const copias = todos.filter((p) => /(_ds_bundle\.js|_ds_manifest\.json)$/.test(p) && dirname(p) !== ROOT).map(rel);
  copias.length ? falha(1, 'manifest e bundle na raiz', `cópia em subpasta: ${copias.join(', ')} — duas cópias sempre divergem`)
                : ok(1, 'manifest e bundle na raiz', 'um de cada');
  try { man = JSON.parse(tx(pMan)); } catch { falha(1, 'manifest legível', '_ds_manifest.json não é JSON válido'); }
}

// ───────────────────────────── 2 e 3. cabeçalho, namespace e o que o bundle publica
let NS = null, cab = null, src = null;
if (man && existsSync(pBun)) {
  src = tx(pBun);
  const m = src.match(/@ds-bundle:\s*(\{[\s\S]*?\})\s*\*\//);
  try { cab = JSON.parse(m[1]); } catch { cab = null; }
  if (!cab) falha(2, 'cabeçalho @ds-bundle legível', 'ausente ou ilegível — é a única fonte do nome publicado; sem ele, quem consome crava o nome e quebra no próximo gerador');
  else if (cab.namespace !== man.namespace) falha(2, 'cabeçalho @ds-bundle legível', `namespace divergente: bundle ${cab.namespace} × manifest ${man.namespace}`);
  else { NS = cab.namespace; ok(2, 'cabeçalho @ds-bundle legível', `${NS} · ${cab.components.length} componentes`); }

  // 3. o que o cabeçalho promete × o que o bundle atribui × o que o manifest lista
  const atrib = [...src.matchAll(/__ds_ns\.([A-Za-z0-9_$]+)\s*=/g)].map((x) => x[1]).filter((n) => !n.startsWith('__'));
  const nCab = new Set((cab?.components || []).map((c) => c.name));
  const nMan = new Set((man.components || []).map((c) => c.name));
  const soBundle = atrib.filter((n) => !nCab.has(n));
  const soCab = [...nCab].filter((n) => !atrib.includes(n));
  const difMan = [...nCab].filter((n) => !nMan.has(n)).concat([...nMan].filter((n) => !nCab.has(n)));
  const probs = [];
  if (soBundle.length) probs.push(`publicados fora do cabeçalho: ${soBundle.join(', ')}`);
  if (soCab.length) probs.push(`no cabeçalho e não publicados: ${soCab.join(', ')}`);
  if (difMan.length) probs.push(`manifest × cabeçalho: ${difMan.join(', ')}`);
  probs.length ? falha(3, 'bundle publica exatamente o catálogo', probs.join(' · '))
               : ok(3, 'bundle publica exatamente o catálogo', `${atrib.length} componentes, iguais nos três lugares`);

  // 4. sem alias/shim acrescentado
  const al = [...src.matchAll(/^\s*window\.([A-Za-z0-9_$]+)\s*=\s*window\.([A-Za-z0-9_$]+)\s*;/gm)];
  if (al.length) falha(4, 'bundle sem alias acrescentado', al.map((a) => `${a[1]} = ${a[2]}`).join(' · ') + ' — alias que o gerador não produz é apagado na próxima regeneração, em silêncio');
  else if (!/\}\)\(\);\s*$/.test(src)) falha(4, 'bundle sem alias acrescentado', 'não termina em })();');
  else ok(4, 'bundle sem alias acrescentado', 'termina em })();');
}

// ───────────────────────────── 5. todo componente do manifest existe no disco
if (man) {
  const semFonte = [], semTipo = [];
  for (const c of man.components || []) {
    const f = aqui(c.sourcePath);
    if (!existsSync(f)) { semFonte.push(`${c.name} → ${c.sourcePath}`); continue; }
    const dts = f.replace(/\.jsx?$/, '.d.ts');
    if (!existsSync(dts)) semTipo.push(c.name);
  }
  semFonte.length ? falha(5, 'sourcePath de todo componente existe', `${semFonte.length}: ${semFonte.slice(0, 6).join(' · ')}`)
                  : ok(5, 'sourcePath de todo componente existe', `${(man.components || []).length} componentes`);
  info(5.1, 'contrato de props (.d.ts) ao lado', semTipo.length ? `${semTipo.length} sem .d.ts: ${semTipo.slice(0, 8).join(', ')}` : 'todos têm');
}

// ───────────────────────────── 6. CSS global declarado existe e não está vazio
if (man) {
  const probs = [];
  for (const c of man.globalCssPaths || []) {
    const f = aqui(c);
    if (!existsSync(f)) probs.push(`${c}: ausente`);
    else if (bt(f) < 200) probs.push(`${c}: ${bt(f)} B — vazio ou stub`);
  }
  probs.length ? falha(6, 'CSS global do manifest existe', probs.join(' · '))
               : ok(6, 'CSS global do manifest existe', (man.globalCssPaths || []).join(', '));
  // cockpit_domains.css não está em globalCssPaths mas é consumido pelo shell do protótipo
  const cd = aqui('cockpit_domains.css');
  info(6.1, 'cockpit_domains.css presente', !existsSync(cd) ? 'ausente — o shell do protótipo o carrega; conferir se saiu de propósito'
    : bt(cd) < 200 ? `${bt(cd)} B — suspeito de stub` : `${bt(cd)} B`);
}

// ───────────────────────────── 7. fontes: arquivo existe, peso não repetido
if (man) {
  const probs = [], porFam = new Map();
  for (const f of man.fonts || []) {
    for (const arq of f.files || []) {
      const p = aqui(arq);
      if (!existsSync(p)) { probs.push(`${arq}: ausente`); continue; }
      const k = f.family;
      porFam.set(k, [...(porFam.get(k) || []), { arq, peso: f.weight, b: bt(p) }]);
    }
  }
  for (const [fam, lista] of porFam) {
    const porTam = new Map();
    for (const it of lista) porTam.set(it.b, [...(porTam.get(it.b) || []), it.peso]);
    for (const [b, pesos] of porTam) if (pesos.length > 1) probs.push(`${fam}: pesos ${pesos.join('/')} com ${b} B idênticos — é um peso copiado sobre os outros`);
  }
  probs.length ? falha(7, 'fontes declaradas existem, pesos distintos', probs.join(' · '))
               : ok(7, 'fontes declaradas existem, pesos distintos', [...porFam].map(([f, l]) => `${f}: ${l.map((i) => i.peso + ':' + (i.b / 1000).toFixed(1) + 'k').join(' ')}`).join(' | '));
}

// ───────────────────────────── 8. templates: entrada existe e suas referências resolvem
if (man) {
  const semEntrada = [], quebradas = [];
  for (const t of man.templates || []) {
    const e = aqui(t.entryPath);
    if (!existsSync(e)) { semEntrada.push(`${t.name} → ${t.entryPath}`); continue; }
    const txt = tx(e);
    for (const m of txt.matchAll(/(?:src|href)=["']([^"'#]+)["']/g)) {
      const u = m[1];
      if (/^(https?:|data:|mailto:|\/\/)/.test(u)) continue;
      if (!existsSync(resolve(dirname(e), u.split('?')[0]))) quebradas.push(`${rel(e)} → ${u}`);
    }
  }
  const probs = [];
  if (semEntrada.length) probs.push(`entrada ausente: ${semEntrada.join(' · ')}`);
  if (quebradas.length) probs.push(`${quebradas.length} referência(s) quebrada(s): ${quebradas.slice(0, 6).join(' · ')}`);
  probs.length ? falha(8, 'templates abrem e suas referências resolvem', probs.join(' | '))
               : ok(8, 'templates abrem e suas referências resolvem', `${(man.templates || []).length} templates`);
}

// ───────────────────────────── 9. o DS não conhece o espelho do protótipo
{
  const sujos = [];
  for (const p of todos) {
    if (!/\.(html|jsx|js|mjs|css|json)$/i.test(p)) continue;
    if (basename(p) === basename(new URL(import.meta.url).pathname)) continue;
    const t = tx(p);
    if (/_ds\/wagner-|_ds\/office-impresso|\/projects\/[0-9a-f]{8}-/.test(t)) sujos.push(rel(p));
  }
  sujos.length ? falha(9, 'DS sem caminho do espelho do protótipo', `${sujos.length}: ${sujos.slice(0, 6).join(', ')} — o DS é a fonte; caminho de consumidor dentro dele inverte a direção`)
               : ok(9, 'DS sem caminho do espelho do protótipo', 'nenhum');
}

// ───────────────────────────── 10. recibo do último pull do DS do Wagner
const pBase = aqui('_export-baseline-ds.json');
if (ESCREVER_BASELINE) {
  if (!man) { falha(10, 'recibo do pull', 'sem manifest, não há o que registrar'); }
  else {
    const arquivos = {};
    const alvos = ['_ds_bundle.js', '_ds_manifest.json', ...(man.globalCssPaths || []), 'cockpit_domains.css',
      ...(man.fonts || []).flatMap((f) => f.files || [])];
    for (const a of [...new Set(alvos)]) { const f = aqui(a); if (existsSync(f)) arquivos[a] = bt(f); }
    writeFileSync(pBase, JSON.stringify({
      gerado: new Date().toISOString().slice(0, 10), namespace: man.namespace,
      componentes: (man.components || []).length, arquivos,
    }, null, 2) + '\n');
    info(10, 'recibo do pull reescrito', `${Object.keys(arquivos).length} arquivos · ${(man.components || []).length} componentes`);
  }
} else if (!existsSync(pBase)) {
  info(10, 'recibo do pull', '_export-baseline-ds.json não existe — rode com --baseline logo depois de puxar do Wagner, para o próximo export ter com o que comparar');
} else if (man) {
  const b = JSON.parse(tx(pBase));
  const difs = [];
  for (const [a, n] of Object.entries(b.arquivos || {})) {
    const f = aqui(a);
    if (!existsSync(f)) { difs.push(`${a}: sumiu`); continue; }
    if (bt(f) !== n) difs.push(`${a}: ${bt(f)} B ≠ ${n} B`);
  }
  if (b.namespace !== man.namespace) difs.push(`namespace: ${man.namespace} ≠ ${b.namespace}`);
  if (b.componentes !== (man.components || []).length) difs.push(`componentes: ${(man.components || []).length} ≠ ${b.componentes}`);
  // Aqui diferença NÃO é defeito: é o pull. O teste só exige que ela seja consciente.
  info(10, 'mudou desde o último pull registrado', difs.length
    ? `${difs.length}: ${difs.slice(0, 8).join(' · ')} — se isto é o pull do Wagner, rode --baseline; se não é, alguém editou o DS à mão`
    : `nada mudou desde ${b.gerado} — se você acabou de puxar do Wagner, o pull não chegou`);
}

// ───────────────────────────── 11 e 12. duplicatas e quebra de linha (informativos)
{
  const porHash = new Map();
  for (const p of todos) { if (bt(p) < 2000) continue;
    const h = createHash('sha1').update(readFileSync(p)).digest('hex');
    porHash.set(h, [...(porHash.get(h) || []), rel(p)]); }
  const dups = [...porHash.values()].filter((v) => v.length > 1);
  info(11, 'arquivos idênticos em dois endereços', dups.length ? `${dups.length} par(es): ${dups.slice(0, 5).map((v) => v.join(' = ')).join(' · ')}` : 'nenhum');

  const crlf = [];
  for (const p of todos) { if (!/\.(html|jsx|js|mjs|css|json|md|d\.ts)$/i.test(p)) continue;
    const b = readFileSync(p);
    for (let i = 0; i < b.length - 1; i++) if (b[i] === 13 && b[i + 1] === 10) { crlf.push(rel(p)); break; } }
  info(12, 'quebra de linha LF', crlf.length ? `${crlf.length} em CRLF — medido por byte` : 'todos em LF');
}

const larg = Math.max(...R.map((r) => r.t.length));
console.log(`\nconferir-ds · ${rel(ROOT) || '.'} · ${todos.length} arquivos\n`);
for (const r of R.sort((a, b) => a.n - b.n)) {
  const marca = r.s === 'PASSA' ? '  ok  ' : r.s === 'FALHA' ? ' FALHA' : ' info ';
  console.log(`${marca} ${String(r.n).padStart(4)}. ${r.t.padEnd(larg)}  ${r.d}`);
}
const nf = R.filter((r) => r.s === 'FALHA').length;
console.log(nf ? `\n${nf} teste(s) reprovado(s) — ver pre-export-ds.md, seção de mesmo número.\n` : '\nDS liberado para export.\n');
process.exit(nf ? 1 : 0);
