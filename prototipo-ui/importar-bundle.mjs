#!/usr/bin/env node
// importar-bundle.mjs — IMPORT atômico do ZIP Cowork (Fase −1 como máquina, não receita).
//
// Por que existe (Q2/Q3, auditoria 2026-06-30): a Fase −1 era receita PowerShell na mão —
// `Remove-Item $staging` ANTES de extrair. Se a extração falha no meio (o `?v=hash` aborta
// o extrator nativo em silêncio), você fica SEM o velho E sem o novo completo. E não havia
// teste. Esta máquina fecha os dois furos:
//   1) extrai pra um TEMP (entry-by-entry Windows-safe, sanitiza `[<>:"|?*]`)
//   2) VERIFICA extraídos == entries do zip (conta independente, não circular)
//   3) só então TROCA atômica pro dir fixo (o velho só morre quando o novo está provado)
//   4) chama detectar-telas no fim (manifesto)
//
// Destino é 1 lugar FIXO, sobrescrito (RUNBOOK §−1) — `~/Downloads/_cowork-handoff-staging`.
//
// Uso:
//   node prototipo-ui/importar-bundle.mjs "<zip>" [--dir <staging-fixo>] [--no-detect]
//   node prototipo-ui/importar-bundle.mjs --selftest
//
// Exit: 0 = importado e verificado | 1 = falha de integridade (staging antigo PRESERVADO) | 2 = uso
// Windows-only (bundles Cowork + ?v=hash): a extração delega ao PowerShell/.NET.

import { execFileSync } from 'node:child_process';
import { existsSync, rmSync, renameSync, mkdtempSync, writeFileSync } from 'node:fs';
import { join, resolve, dirname, basename } from 'node:path';
import { tmpdir, homedir } from 'node:os';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const DESTINO_PADRAO = join(homedir(), 'Downloads', '_cowork-handoff-staging');

// ── decisão de integridade (lógica PURA, testável sem extrair nada) ───────────
export function decidirSwap({ entries, extraidos }) {
  if (typeof entries !== 'number' || typeof extraidos !== 'number') return { ok: false, motivo: 'contagem inválida' };
  if (entries <= 0) return { ok: false, motivo: 'zip sem entradas de arquivo' };
  if (extraidos !== entries) return { ok: false, motivo: `extraídos(${extraidos}) ≠ entries(${entries}) — extração incompleta` };
  return { ok: true, motivo: `${extraidos}/${entries} arquivos íntegros` };
}

// ── extração Windows-safe pra um TEMP, devolve {entries, extraidos} ───────────
function extrairParaTemp(zip, tempDir) {
  const ps = `
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$z=[System.IO.Compression.ZipFile]::OpenRead([string]$env:OI_ZIP)
$inv=[IO.Path]::GetInvalidFileNameChars(); $ok=0; $ent=0
foreach($e in $z.Entries){ if($e.FullName.EndsWith('/')){continue}; $ent++
  $rel=($e.FullName -split '/' | ForEach-Object{ $s=$_; foreach($c in $inv){ if($c -ne '/'){ $s=$s.Replace($c,'_') } }; $s }) -join '\\'
  $d=Join-Path $env:OI_TMP $rel; $dir=Split-Path $d -Parent
  if(-not (Test-Path $dir)){ New-Item -ItemType Directory $dir -Force | Out-Null }
  [IO.Compression.ZipFileExtensions]::ExtractToFile($e,$d,$true); $ok++ }
$z.Dispose()
Write-Output ("ENTRIES=" + $ent); Write-Output ("EXTRACTED=" + $ok)`;
  const ps1 = join(tmpdir(), `cowork-extract-${process.pid}.ps1`);
  // UTF-8 com BOM pra PowerShell 5.1 ler acento do path corretamente
  writeFileSync(ps1, '﻿' + ps, 'utf8');
  let out = '';
  try {
    out = execFileSync('powershell', ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', ps1], {
      encoding: 'utf8', env: { ...process.env, OI_ZIP: zip, OI_TMP: tempDir },
    });
  } finally { try { rmSync(ps1, { force: true }); } catch {} }
  const entries = Number((out.match(/ENTRIES=(\d+)/) || [])[1]);
  const extraidos = Number((out.match(/EXTRACTED=(\d+)/) || [])[1]);
  return { entries, extraidos };
}

function importar(zip, destino, { detect = true } = {}) {
  if (!existsSync(zip)) { console.error(`✗ zip não existe: ${zip}`); return 1; }
  const temp = mkdtempSync(join(tmpdir(), 'cowork-import-'));
  console.log(`→ extraindo pra TEMP (verifica antes de tocar o destino): ${temp}`);
  let res;
  try { res = extrairParaTemp(zip, temp); }
  catch (e) { rmSync(temp, { recursive: true, force: true }); console.error(`✗ extração falhou: ${e.message}`); return 1; }

  const veredito = decidirSwap(res);
  console.log(`  integridade: ${veredito.motivo}`);
  if (!veredito.ok) {
    rmSync(temp, { recursive: true, force: true });
    console.error(`✗ ABORTADO — staging antigo PRESERVADO (${destino}). Nada foi trocado.`);
    return 1;
  }
  // TROCA atômica: o velho só morre agora que o novo está provado íntegro
  if (existsSync(destino)) rmSync(destino, { recursive: true, force: true });
  renameSync(temp, destino);
  console.log(`✓ TROCADO → ${destino}  (${veredito.motivo})`);

  if (detect) {
    console.log('\n→ detectar-telas (manifesto):');
    try {
      const proj = existsSync(join(destino, 'project')) ? join(destino, 'project') : destino;
      const out = execFileSync('node', [join(HERE, 'detectar-telas.mjs'), '--staging', proj], { encoding: 'utf8' });
      console.log(out.split('\n').slice(-4).join('\n'));
    } catch (e) { console.log((e.stdout || '').split('\n').slice(-6).join('\n')); }
  }
  return 0;
}

function selftest() {
  let f = 0; const t = (l, c) => { if (!c) f++; console.log(`  [${c ? 'PASS' : 'FAIL'}] ${l}`); };
  t('match exato → swap ok', decidirSwap({ entries: 1218, extraidos: 1218 }).ok === true);
  t('extração incompleta → NÃO troca', decidirSwap({ entries: 1218, extraidos: 1200 }).ok === false);
  t('zip vazio → NÃO troca', decidirSwap({ entries: 0, extraidos: 0 }).ok === false);
  t('contagem inválida → NÃO troca', decidirSwap({ entries: 'x', extraidos: 1 }).ok === false);
  console.log(f ? `\nSELFTEST FALHOU (${f})` : '\nSELFTEST OK — só troca quando extraídos==entries (delete-before-verify fechado).');
  process.exit(f ? 1 : 0);
}

const argv = process.argv.slice(2);
const has = (x) => argv.includes(x);
const val = (x) => { const i = argv.indexOf(x); return i >= 0 && argv[i + 1] ? argv[i + 1] : null; };
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  if (has('--selftest')) selftest();
  else {
    const zip = argv.find((a) => !a.startsWith('--') && argv[argv.indexOf(a) - 1] !== '--dir');
    if (!zip) { console.error('uso: node prototipo-ui/importar-bundle.mjs "<zip>" [--dir <staging>] [--no-detect]'); process.exit(2); }
    process.exit(importar(resolve(zip), resolve(val('--dir') || DESTINO_PADRAO), { detect: !has('--no-detect') }));
  }
}
