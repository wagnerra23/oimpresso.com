#!/usr/bin/env node
// recortar-regiao.mjs — W2 do processo região-a-região: recorta o screenshot da tela pela
// ÂNCORA de cada região, pra Wagner aprovar a PARTE, não a tela inteira.
//
// Por que (Wagner 2026-06-30): hoje TODO gate visual é da tela toda (1 screenshot pro Wagner).
// Aí ele aprova/reprova a tela inteira e não sabe QUAL parte errou. W2 troca por aceite POR
// REGIÃO: 1 PNG recortado pelo boundingBox de cada `data-contract="<id>"`.
//
// DIVISÃO (testável vs flaky), igual ao importar-bundle:
//  - PLANO DE RECORTE = lógica PURA (que regiões, que bbox, ausente) → --selftest no CI (Linux).
//  - CROP do PNG = rasterização (PowerShell System.Drawing, Windows) → roda local.
//  - COLETA do bbox = o AGENTE faz via browser MCP (getBoundingClientRect de [data-contract]),
//    e alimenta este script com o --bboxes json. CLI Node não dirige browser; por isso a divisão.
//
// REGRA DURA (furo do adversário): região cuja âncora NÃO está no DOM/bbox-map → status AUSENTE
// explícito. NUNCA recorta a tela inteira em silêncio (esse silêncio é o "meio-feito").
//
// Uso:
//   node prototipo-ui/recortar-regiao.mjs --contract <c.json> --bboxes <b.json> --png <full.png> --out <dir>
//      bboxes = { "<regiao_id>": { "x":N,"y":N,"w":N,"h":N }, ... }  (o agente coleta do browser)
//   node prototipo-ui/recortar-regiao.mjs --selftest
//
// Exit: 0 = todas as regiões recortadas | 1 = alguma AUSENTE (ancore antes) | 2 = uso

import { readFileSync, existsSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, resolve, dirname, basename } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

// ── PLANO DE RECORTE (lógica PURA, testável) ─────────────────────────────────
// secoes = [{id}], bboxMap = {id:{x,y,w,h}} → {recortes:[{id,bbox,png}], ausentes:[id]}
export function planoDeRecorte(secoes, bboxMap, outDir = '.') {
  const recortes = [], ausentes = [];
  for (const s of secoes || []) {
    const bb = bboxMap && bboxMap[s.id];
    if (bb && [bb.x, bb.y, bb.w, bb.h].every((n) => typeof n === 'number') && bb.w > 0 && bb.h > 0) {
      recortes.push({ id: s.id, bbox: { x: Math.round(bb.x), y: Math.round(bb.y), w: Math.round(bb.w), h: Math.round(bb.h) }, png: join(outDir, `${s.id}.png`) });
    } else {
      ausentes.push(s.id); // âncora não está no DOM → NUNCA cai pra tela-inteira
    }
  }
  return { recortes, ausentes };
}

// ── CROP do PNG via PowerShell/.NET System.Drawing (Windows) ─────────────────
function cropPng(srcPng, bbox, outPng) {
  const ps = `
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.Drawing
$src=[System.Drawing.Image]::FromFile([string]$env:OI_SRC)
$x=[int]$env:OI_X; $y=[int]$env:OI_Y; $w=[int]$env:OI_W; $h=[int]$env:OI_H
# clamp ao tamanho real da imagem (bbox de viewport pode exceder)
if($x -lt 0){$x=0}; if($y -lt 0){$y=0}
if($x+$w -gt $src.Width){$w=$src.Width-$x}; if($y+$h -gt $src.Height){$h=$src.Height-$y}
$rect=New-Object System.Drawing.Rectangle $x,$y,$w,$h
$bmp=New-Object System.Drawing.Bitmap $w,$h
$g=[System.Drawing.Graphics]::FromImage($bmp)
$g.DrawImage($src,(New-Object System.Drawing.Rectangle 0,0,$w,$h),$rect,[System.Drawing.GraphicsUnit]::Pixel)
$bmp.Save([string]$env:OI_OUT,[System.Drawing.Imaging.ImageFormat]::Png)
$g.Dispose(); $bmp.Dispose(); $src.Dispose()
Write-Output ("CROPPED " + $w + "x" + $h)`;
  const ps1 = join(tmpdir(), `crop-${process.pid}-${basename(outPng)}.ps1`);
  writeFileSync(ps1, '﻿' + ps, 'utf8');
  try {
    return execFileSync('powershell', ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', ps1],
      { encoding: 'utf8', env: { ...process.env, OI_SRC: srcPng, OI_OUT: outPng, OI_X: bbox.x, OI_Y: bbox.y, OI_W: bbox.w, OI_H: bbox.h } }).trim();
  } finally { try { rmSync(ps1, { force: true }); } catch {} }
}

function selftest() {
  let f = 0; const t = (l, c) => { if (!c) f++; console.log(`  [${c ? 'PASS' : 'FAIL'}] ${l}`); };
  const secoes = [{ id: 'header' }, { id: 'tabela' }, { id: 'drawer' }];
  const bbox = { header: { x: 0, y: 0, w: 1280, h: 64 }, tabela: { x: 0, y: 64, w: 1280, h: 400 } }; // drawer ausente
  const p = planoDeRecorte(secoes, bbox, '/out');
  t('2 regiões com bbox → 2 recortes', p.recortes.length === 2);
  t('região sem bbox (drawer) → AUSENTE, não tela-inteira', p.ausentes.length === 1 && p.ausentes[0] === 'drawer');
  t('recorte carrega o bbox arredondado + path por id', p.recortes[0].id === 'header' && p.recortes[0].bbox.h === 64 && p.recortes[0].png.endsWith('header.png'));
  t('bbox inválido (w=0) → AUSENTE (não recorta lixo)', planoDeRecorte([{ id: 'x' }], { x: { x: 0, y: 0, w: 0, h: 10 } }).ausentes[0] === 'x');
  t('determinismo: mesmo input → mesmo plano', JSON.stringify(planoDeRecorte(secoes, bbox, '/out')) === JSON.stringify(p));
  console.log(f ? `\nSELFTEST FALHOU (${f})` : '\nSELFTEST OK — plano por região, ausente explícito, nunca tela-inteira em silêncio.');
  process.exit(f ? 1 : 0);
}

const argv = process.argv.slice(2);
const val = (k) => { const i = argv.indexOf(k); return i >= 0 && argv[i + 1] ? argv[i + 1] : null; };
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  if (argv.includes('--selftest')) selftest();
  else {
    const cf = val('--contract'), bf = val('--bboxes'), png = val('--png'), out = val('--out') || '.';
    if (!cf || !bf || !png) { console.error('uso: recortar-regiao.mjs --contract <c.json> --bboxes <b.json> --png <full.png> --out <dir> | --selftest'); process.exit(2); }
    for (const [n, p] of [['contract', cf], ['bboxes', bf], ['png', png]]) if (!existsSync(p)) { console.error(`✗ ${n} não existe: ${p}`); process.exit(1); }
    const secoes = JSON.parse(readFileSync(cf, 'utf8')).secoes || [];
    const bboxMap = JSON.parse(readFileSync(bf, 'utf8'));
    if (!existsSync(out)) mkdirSync(out, { recursive: true });
    const plano = planoDeRecorte(secoes, bboxMap, out);
    const indice = {};
    for (const r of plano.recortes) {
      try { const res = cropPng(resolve(png), r.bbox, resolve(r.png)); console.log(`✓ ${r.id} → ${r.png} (${res})`); indice[r.id] = { png: r.png, status: 'recortado' }; }
      catch (e) { console.error(`✗ crop falhou em ${r.id}: ${e.message}`); indice[r.id] = { status: 'erro' }; }
    }
    for (const a of plano.ausentes) { console.error(`⚠️ AUSENTE: região "${a}" não tem âncora data-contract no DOM (ancore antes — W0/W1). NÃO recortei a tela inteira.`); indice[a] = { status: 'ausente' }; }
    writeFileSync(join(out, '_indice.json'), JSON.stringify(indice, null, 2));
    console.log(`\nÍndice: ${join(out, '_indice.json')} · ${plano.recortes.length} recortado(s) · ${plano.ausentes.length} ausente(s)`);
    process.exit(plano.ausentes.length ? 1 : 0);
  }
}
