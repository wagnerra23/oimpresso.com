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
//   5) SINCRONIZA o bundle (…/project/) → prototipo-ui/cowork/ (SSOT no repo, BUILD-ONLY, git=rede)
//
// Dois destinos FIXOS, sobrescritos sempre (RUNBOOK §−1):
//   • staging FORA do repo — `~/Downloads/_cowork-handoff-staging` (verificação + detectar-telas)
//   • SSOT NO repo — `prototipo-ui/cowork/`: só a CAMADA DE DESIGN pousa (BUILD-ONLY: jsx/tsx/ts/
//     js/mjs/css/html/json/php) — `.md` (charters/memory/ADRs) é CANON e NÃO entra aqui. Isso É LEI:
//     `cowork-ssot-guard.mjs` R1 (design-memory-gate.yml) reprova `.md` em cowork/, e a ADR-proposta
//     2026-06-23 §77 manda "o filtro de landing exclui .md". O sync usa `/PURGE` (tira órfão de
//     rename — o SSOT é ESPELHO do último handoff, não união). git é a rede (diff/deleção visível).
//     Decisão Opção A (Wagner 2026-07-01) supersede o espelho per-tela. Desligar: `--no-sync-cowork`.
//
// Uso:
//   node prototipo-ui/importar-bundle.mjs "<zip>" [--dir <staging-fixo>] [--no-detect] [--no-sync-cowork]
//   node prototipo-ui/importar-bundle.mjs --selftest
//
// Exit: 0 = importado e verificado | 1 = falha de integridade (staging antigo PRESERVADO) | 2 = uso
// Windows-only (bundles Cowork + ?v=hash): a extração delega ao PowerShell/.NET.

import { execFileSync } from 'node:child_process';
import { existsSync, rmSync, renameSync, mkdtempSync, writeFileSync, readdirSync } from 'node:fs';
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

// ── cowork/ é BUILD-ONLY, ZERO .md (cowork-ssot-guard.mjs R1 + ADR-proposta 2026-06-23 §77).
//    Só estas extensões pousam no SSOT; .md (charters/memory/ADRs) é canon e fica no lugar dele.
const BUILD_PATS = ['*.jsx', '*.tsx', '*.ts', '*.js', '*.mjs', '*.css', '*.html', '*.json', '*.php'];

// ── acha a RAIZ real do bundle — o zip abre em `<slug>/project/`, NÃO em `project/` no topo;
//    assumir `destino/project` aninha o slug inteiro (bug 2026-07-01). Marca FORTE = host+app juntos;
//    ordena os candidatos (determinístico) e avisa em ambiguidade em vez de first-wins cego (adv A3).
function acharBundleRoot(destino) {
  const forte = (d) => existsSync(join(d, 'oimpresso.com.html')) && existsSync(join(d, 'app.jsx'));
  const fraco = (d) => existsSync(join(d, 'oimpresso.com.html')) || existsSync(join(d, 'app.jsx'));
  let subs = [];
  try { subs = readdirSync(destino, { withFileTypes: true }).filter((e) => e.isDirectory()).map((e) => e.name).sort(); } catch {}
  const cands = [join(destino, 'project'), ...subs.map((s) => join(destino, s, 'project')), ...subs.map((s) => join(destino, s)), destino];
  const fortes = cands.filter((c) => existsSync(c) && forte(c));
  if (fortes.length === 1) return fortes[0];
  if (fortes.length > 1) { console.error(`⚠ raiz ambígua (${fortes.length} dirs com host+app): ${fortes.join(' | ')} — usando o 1º ordenado`); return fortes[0]; }
  const fracos = cands.filter((c) => existsSync(c) && fraco(c));
  if (fracos.length) { console.error(`⚠ nenhuma raiz FORTE (host+app juntos); caindo pra marca fraca: ${fracos[0]}`); return fracos[0]; }
  console.error(`⚠ nenhum host-marker achado em ${destino} — usando destino cru (pode aninhar)`); return destino;
}

// ── sync do bundle → SSOT no repo (prototipo-ui/cowork/), overlay + drop dos dupes ?v=hash ──
function sincronizarCowork(destino) {
  const proj = acharBundleRoot(destino);
  const cowork = join(HERE, 'cowork');
  // BUILD-ONLY + PURGE: só extensões de build pousam; /PURGE tira órfão de rename (adv C1 — "retirar
  // o diff"); sweep de .md garante R1 (o filtro de dupe `?v=hash` some — os patterns já não os pegam).
  const ps = `
$ErrorActionPreference='Continue'
$src=[string]$env:OI_SRC; $dst=[string]$env:OI_DST
if(-not (Test-Path $dst)){ New-Item -ItemType Directory $dst -Force | Out-Null }
$pats = '${BUILD_PATS.join("','")}'.Split(',') | ForEach-Object { $_.Trim("'") }
# /XD: dirs de arquivo/scratch NÃO são design-source (mesmo SKIP_DIRS do _lib-charter + os que o Wagner mandou apagar)
$noise = @('_arquivo','_BACKUP-NAO-USAR','scraps','screenshots','uploads','assets','benchmark','_ds')
robocopy $src $dst @pats /S /PURGE /XD @noise /NFL /NDL /NJH /NJS /R:1 /W:1 | Out-Null
$rc=$LASTEXITCODE
# apaga do DEST os dirs de ruído já presentes (de runs anteriores; /XD só evita copiar, não purga)
foreach($nd in $noise){ Get-ChildItem $dst -Recurse -Directory -Filter $nd -ErrorAction SilentlyContinue | ForEach-Object { Remove-Item $_.FullName -Recurse -Force -ErrorAction SilentlyContinue } }
# BUILD-ONLY estrito (allowlist): varre TUDO que não é build-ext nem .gitignore — mata .md,
# .proposto, dupes ?v=hash (ext quebrada) e qualquer canonical-shadow. /PURGE só pega build-ext órfão.
$keep=@('.jsx','.tsx','.ts','.js','.mjs','.css','.html','.json','.php')
$junk=@(Get-ChildItem $dst -Recurse -File | Where-Object { $_.Name -ne '.gitignore' -and ($keep -notcontains $_.Extension.ToLower()) })
foreach($f in $junk){ Remove-Item $f.FullName -Force -ErrorAction SilentlyContinue }
Write-Output ("JUNK_SWEPT=" + $junk.Count)
# resíduo de PROCESSO (espelha scripts/bundle-lint.mjs RESIDUO): audit/tribunal/adversário/GAPS/FORCE
# — são .html/.md de esteira, NÃO design-source. bundle-lint (advisory) os flagra; o sync tira na origem.
$resPat='_arquivo|benchmark|uploads|\.thumbnail$|GAPS_v\d|FORCE_|Advers.rio|Tribunal|Avaliac'
$res=@(Get-ChildItem $dst -Recurse -File | Where-Object { $_.FullName -match $resPat })
foreach($f in $res){ Remove-Item $f.FullName -Force -ErrorAction SilentlyContinue }
Write-Output ("RESIDUE_SWEPT=" + $res.Count)
if($rc -ge 8){ exit 1 } else { exit 0 }`;
  const ps1 = join(tmpdir(), `cowork-sync-${process.pid}.ps1`);
  writeFileSync(ps1, '﻿' + ps, 'utf8'); // BOM: PowerShell 5.1 lê acento do path
  try {
    const out = execFileSync('powershell', ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', ps1], {
      encoding: 'utf8', env: { ...process.env, OI_SRC: proj, OI_DST: cowork },
    });
    const swept = Number((out.match(/JUNK_SWEPT=(\d+)/) || [])[1]) || 0;
    const resid = Number((out.match(/RESIDUE_SWEPT=(\d+)/) || [])[1]) || 0;
    console.log(`✓ SINCRONIZADO → ${cowork}  (build-only + purge; ${swept} não-build + ${resid} resíduo-processo varrido(s) — R1 + bundle-lint limpos)`);
  } catch (e) {
    console.error(`✗ sync cowork falhou (robocopy ≥8): ${e.message}`);
    return 1;
  } finally { try { rmSync(ps1, { force: true }); } catch {} }
  // rede de segurança: mostra o que o git vai rastrear (nada é perdido sem aparecer aqui)
  try {
    const REPO = dirname(HERE);
    const tocados = execFileSync('git', ['-C', REPO, 'status', '--short', '--', 'prototipo-ui/cowork'], { encoding: 'utf8' })
      .split('\n').filter(Boolean).length;
    console.log(`  git: ${tocados} arquivo(s) tocado(s) em prototipo-ui/cowork/ (rode 'git diff --stat' pra ver)`);
  } catch {}
  return 0;
}

function importar(zip, destino, { detect = true, syncCowork = true } = {}) {
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

  if (syncCowork) {
    console.log('\n→ sincronizando SSOT no repo (prototipo-ui/cowork/):');
    const rc = sincronizarCowork(destino);
    if (rc !== 0) return rc;
  }

  if (detect) {
    console.log('\n→ detectar-telas (manifesto):');
    try {
      const proj = acharBundleRoot(destino);
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
    if (!zip) { console.error('uso: node prototipo-ui/importar-bundle.mjs "<zip>" [--dir <staging>] [--no-detect] [--no-sync-cowork]'); process.exit(2); }
    process.exit(importar(resolve(zip), resolve(val('--dir') || DESTINO_PADRAO), { detect: !has('--no-detect'), syncCowork: !has('--no-sync-cowork') }));
  }
}
