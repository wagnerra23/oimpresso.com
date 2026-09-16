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
//   5) SINCRONIZA o bundle (…/project/) → prototipo-ui/cowork/Wagner/ (SSOT no repo, git=rede)
//
// Dois destinos FIXOS, sobrescritos sempre (RUNBOOK §−1):
//   • staging FORA do repo — `~/Downloads/_cowork-handoff-staging` (verificação + detectar-telas)
//   • SSOT NO repo — `prototipo-ui/cowork/Wagner/`: o pacote pousa com a ÁRVORE DO COWORK,
//     `.md` incluído (ver ESPELHO_EXTS).
//
//     ── o que mudou em 2026-09-13, e por quê (decisão [W]) ──────────────────────────────────
//     Até aqui o landing era BUILD-ONLY e o `.md` era varrido como junk. O efeito, medido no
//     pacote de 2026-09-11: **816 arquivos no `project/` → 400 pousavam, 416 descartados, 337
//     deles `.md`**. Descartar 41% do pacote não é filtrar ruído — é receber outra coisa. O
//     `cowork-inbox/` (canal por onde o Cowork manda ordem de serviço) chegava pela metade.
//     [W] 2026-09-13, textual: *"deve ser igual ao cowork, não poderia mudar assim facilita
//     muito mais. na importação ou leitura lá"*. Espelho que muda a forma não é espelho.
//     O `cowork-ssot-guard.mjs` **R3** foi emendado junto (emenda à ADR 0397 D3): `.md` passa a
//     ser aceito em qualquer lugar sob `cowork/<dono>/` — a forma interna é a do Cowork.
//     Isto CONSERTA o defeito nº 2 que este arquivo reportava: o sweep apagava os `.md` de
//     `cowork/Wagner/handoffs/` que a própria R3 permitia (ver classificarParaSync).
//     ⚠ A R4 (zero duplicata de bytes) NÃO foi afrouxada, e ela morde o pacote: medido em
//     2026-09-13, `cowork-inbox/sidebar/playbook/` e `entrega-sidebar-code/playbook/` trazem
//     **10 pares byte-idênticos**. Duplicata na FONTE não vira duplicata no espelho — quem
//     desduplica é o lado Cowork; aqui o import falha e diz qual par.
//     O sync usa `/PURGE` (tira órfão de
//     rename — o SSOT é ESPELHO do último handoff, não união). git é a rede (diff/deleção visível).
//     ⚠️ O `/PURGE` só é legítimo porque ESTA rota recebe a ÁRVORE COMPLETA da conta: aqui a
//     ausência de um arquivo É informação da origem. Em lote PARCIAL/delta ela é silêncio, e
//     podar apagaria o que a conta nunca disse que saiu — por isso o `aplicar-payload` não apaga
//     naquele modo. As duas frases não se contradizem: o que muda é o ESCOPO (ADR 0406 D2).
//     Decisão Opção A (Wagner 2026-07-01) supersede o espelho per-tela. Desligar: `--no-sync-cowork`.
//
// Uso:
//   node scripts/design/importar-bundle.mjs "<zip>" [--dir <staging-fixo>] [--no-detect] [--no-sync-cowork]
//   node scripts/design/importar-bundle.mjs --selftest
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

// ── o que o ESPELHO aceita (cowork-ssot-guard.mjs R1/R2/R3/R4).
//    O nome era `BUILD_EXTS` até 2026-09-13: com `.md` na lista, um const chamado "BUILD"
//    descreveria o oposto do que faz, e comentário/nome que mente é a classe LC-10 do ledger.
//    `.md` entra porque `cowork/<dono>/` é espelho da conta, não recorte dela (ver cabeçalho).
//
//    FONTE ÚNICA das três listas que o sync usa. Antes elas viviam DUPLICADAS: os patterns aqui
//    e `$keep` hardcoded dentro do heredoc PowerShell — duas verdades pra uma regra, que é como
//    drift nasce. Agora o heredoc INTERPOLA daqui (§"não duplicar a regra em JS e em PS").
export const ESPELHO_EXTS = ['.jsx', '.tsx', '.ts', '.js', '.mjs', '.css', '.html', '.json', '.php', '.md'];
const ESPELHO_PATS = ESPELHO_EXTS.map((e) => `*${e}`);

//    Dirs de arquivo/scratch que NÃO são design-source (mesmo SKIP_DIRS do _lib-charter + os que
//    o Wagner mandou apagar). robocopy os exclui via /XD e o sweep seguinte os apaga do destino.
export const NOISE_DIRS = ['_arquivo', '_BACKUP-NAO-USAR', 'scraps', 'screenshots', 'uploads', 'assets', 'benchmark', '_ds'];

//    Resíduo de PROCESSO (espelha scripts/bundle-lint.mjs RESIDUO): audit/tribunal/adversário/
//    GAPS/FORCE — são .html de esteira, NÃO design-source.
//
//    ⚠⚠ ESTE É O PADRÃO EFETIVO, e ele NÃO é o que o fonte antigo parecia dizer. O heredoc é um
//    TEMPLATE LITERAL de JS, então as barras do literal `\.thumbnail$|GAPS_v\d` COLAPSARAM antes
//    de a PS ver qualquer coisa (LC-26 · §5 2026-08-19): o que chegava em `$resPat` era
//    `.thumbnail$|GAPS_vd`. Medido em 2026-09-13 reproduzindo a linha exata de origin/main:
//      $resPat='_arquivo|benchmark|uploads|.thumbnail$|GAPS_vd|FORCE_|Advers.rio|Tribunal|Avaliac'
//      GAPS_v2.html -> false   ·   GAPS_vd.html -> true   ·   FORCE_a.html -> true
//    Consequência: `GAPS_v2.html` NUNCA foi varrido como resíduo; `GAPS_vd` (letra d literal) é
//    que seria, e ninguém nomeia arquivo assim. Está REPORTADO, não consertado — consertar muda o
//    que o sync APAGA, e é outro intent (o PR que introduz o conserto já nasce com este teste).
//    Por isso a constante é backslash-FREE: ela transporta o padrão efetivo, e a ausência de barra
//    é a própria evidência do colapso. Se um dia o `\d` voltar, o caso GAPS_v2 deste teste vira
//    vermelho — ele é o tripwire do conserto, não o endosso do defeito.
export const RESIDUO_PATTERN = '_arquivo|benchmark|uploads|.thumbnail$|GAPS_vd|FORCE_|Advers.rio|Tribunal|Avaliac';

//    O único arquivo não-build que SOBREVIVE ao sweep. Não entra em ESPELHO_EXTS de propósito: ele
//    não vem do bundle (robocopy nunca o copia, o filespec não o pega) — ele mora no repo e o
//    sweep o POUPA. Medido em 2026-09-13: `[IO.Path]::GetExtension('.gitignore')` devolve
//    `.gitignore`, não `''` — por isso a guarda é pelo NOME, e ela é load-bearing.
const PRESERVADOS = ['.gitignore'];

// ── DECISÃO de sync (lógica PURA, testável sem robocopy nem PowerShell) ───────
//    A EXECUÇÃO (robocopy /PURGE + Remove-Item) segue no PowerShell; o que sai de lá é só o
//    JULGAMENTO de cada caminho. Existe porque a parte que DESTRÓI era intestável por construção:
//    a regra vivia dentro de uma string heredoc, e `git grep -lF robocopy` devolvia 1 arquivo — o
//    próprio script (medido 2026-09-13). Não há runner Windows no CI (`runs-on:.*windows` → 0),
//    então nem havia onde rodá-la. Mesmo movimento de `decidirSwap` e `acharBundleRoot`: tirar a
//    decisão do caminho imperativo é o que as tornou testáveis.
//
//    Ordem de precedência = ordem de EXECUÇÃO da PS (quem morre primeiro nunca chega no sweep
//    seguinte): dir de ruído → junk (não-build) → resíduo de processo → sobrevive.
//
//    @param {string} caminhoRelativo — path relativo à raiz do SSOT (`prototipo-ui/cowork/Wagner`),
//      com `/` ou `\`. A PS casa o resíduo contra o FullName ABSOLUTO; aqui é o relativo, que é a
//      intenção da regra (o resíduo é do caminho do arquivo DENTRO do SSOT). Divergência conhecida
//      e reportada, não consertada aqui: com o SSOT sob um diretório cujo nome casa o padrão
//      (ex. um worktree chamado `benchmark-x`), a PS varreria o SSOT inteiro.
//    @returns {{acao:'copia'|'junk'|'residuo'|'ruido-dir'|'invalido', motivo:string}}
export function classificarParaSync(caminhoRelativo) {
  if (typeof caminhoRelativo !== 'string' || caminhoRelativo.trim() === '') {
    return { acao: 'invalido', motivo: 'não é caminho de arquivo (a PS itera arquivos reais, nunca vê isto)' };
  }
  const segs = caminhoRelativo.split(/[\\/]+/).filter(Boolean);
  if (segs.length === 0) return { acao: 'invalido', motivo: 'caminho sem segmentos' };
  const nome = segs[segs.length - 1];
  const dirs = segs.slice(0, -1);

  // 1) /XD do robocopy + o loop que apaga os dirs de ruído do destino. Case-insensitive porque é
  //    o que robocopy e o FS do Windows fazem — não é escolha, é paridade.
  const ruido = dirs.find((d) => NOISE_DIRS.some((n) => n.toLowerCase() === d.toLowerCase()));
  if (ruido) return { acao: 'ruido-dir', motivo: `dir de ruído no caminho: ${ruido}` };

  // 2) sweep BUILD-ONLY (allowlist). `ext` replica [IO.Path]::GetExtension: do ÚLTIMO ponto do
  //    NOME, inclusive, mesmo quando o ponto é o 1º char; '' quando não há ponto.
  const i = nome.lastIndexOf('.');
  const ext = i >= 0 ? nome.slice(i).toLowerCase() : '';
  if (!PRESERVADOS.includes(nome) && !ESPELHO_EXTS.includes(ext)) {
    return { acao: 'junk', motivo: ext ? `extensão fora do build: ${ext}` : 'arquivo sem extensão' };
  }

  // 3) sweep de resíduo de processo. Roda DEPOIS do junk e NÃO poupa o .gitignore — a PS também
  //    não poupa (a guarda de nome é só do sweep anterior).
  if (new RegExp(RESIDUO_PATTERN, 'i').test(caminhoRelativo)) {
    return { acao: 'residuo', motivo: 'resíduo de processo (bundle-lint RESIDUO)' };
  }

  return PRESERVADOS.includes(nome)
    ? { acao: 'copia', motivo: `${nome} — preservado pelo nome (não vem do bundle; o sweep o poupa)` }
    : { acao: 'copia', motivo: `design-source (${ext})` };
}

// ── serializa uma lista JS como array literal PowerShell ('a','b') ────────────
const psLista = (xs) => xs.map((x) => `'${x}'`).join(',');

// ── acha a RAIZ real do bundle — o zip abre em `<slug>/project/`, NÃO em `project/` no topo;
//    assumir `destino/project` aninha o slug inteiro (bug 2026-07-01). Marca FORTE = host+app juntos;
//    ordena os candidatos (determinístico) e avisa em ambiguidade em vez de first-wins cego (adv A3).
export function acharBundleRoot(destino) {
  const forte = (d) => existsSync(join(d, 'oimpresso.com.html')) && existsSync(join(d, 'app.jsx'));
  const fraco = (d) => existsSync(join(d, 'oimpresso.com.html')) || existsSync(join(d, 'app.jsx'));
  let subs = [];
  try { subs = readdirSync(destino, { withFileTypes: true }).filter((e) => e.isDirectory()).map((e) => e.name).sort(); } catch {}
  // `new Set`: com o bundle abrindo em `project/` no topo, `join(destino,'project')` e o `project`
  // vindo de `subs` sao o MESMO path — sem dedupe, `fortes` vinha com 2 entradas IGUAIS e o
  // aviso de ambiguidade disparava no caso mais simples que existe (achado pelo bite-test,
  // 2026-09-13). Set preserva a ordem de insercao, entao a precedencia dos candidatos nao muda.
  const cands = [...new Set([join(destino, 'project'), ...subs.map((s) => join(destino, s, 'project')), ...subs.map((s) => join(destino, s)), destino])];
  const fortes = cands.filter((c) => existsSync(c) && forte(c));
  if (fortes.length === 1) return fortes[0];
  if (fortes.length > 1) { console.error(`⚠ raiz ambígua (${fortes.length} dirs com host+app): ${fortes.join(' | ')} — usando o 1º ordenado`); return fortes[0]; }
  const fracos = cands.filter((c) => existsSync(c) && fraco(c));
  if (fracos.length) { console.error(`⚠ nenhuma raiz FORTE (host+app juntos); caindo pra marca fraca: ${fracos[0]}`); return fracos[0]; }
  console.error(`⚠ nenhum host-marker achado em ${destino} — usando destino cru (pode aninhar)`); return destino;
}

// ── sync do bundle → SSOT no repo (prototipo-ui/cowork/Wagner/), overlay + drop dos dupes ?v=hash ──
function sincronizarCowork(destino) {
  const proj = acharBundleRoot(destino);
  const cowork = resolve(HERE, '../../prototipo-ui/cowork/Wagner');
  // BUILD-ONLY + PURGE: só extensões de build pousam; /PURGE tira órfão de rename (adv C1 — "retirar
  // o diff"); sweep de .md garante R1 (o filtro de dupe `?v=hash` some — os patterns já não os pegam).
  const ps = `
$ErrorActionPreference='Continue'
$src=[string]$env:OI_SRC; $dst=[string]$env:OI_DST
if(-not (Test-Path $dst)){ New-Item -ItemType Directory $dst -Force | Out-Null }
$pats = '${ESPELHO_PATS.join("','")}'.Split(',') | ForEach-Object { $_.Trim("'") }
# /XD: dirs de ruído — lista vem de NOISE_DIRS (fonte única no JS; ver classificarParaSync)
$noise = @(${psLista(NOISE_DIRS)})
robocopy $src $dst @pats /S /PURGE /XD @noise /NFL /NDL /NJH /NJS /R:1 /W:1 | Out-Null
$rc=$LASTEXITCODE
# apaga do DEST os dirs de ruído já presentes (de runs anteriores; /XD só evita copiar, não purga)
foreach($nd in $noise){ Get-ChildItem $dst -Recurse -Directory -Filter $nd -ErrorAction SilentlyContinue | ForEach-Object { Remove-Item $_.FullName -Recurse -Force -ErrorAction SilentlyContinue } }
# BUILD-ONLY estrito (allowlist): varre TUDO que não é build-ext nem .gitignore — mata .md,
# .proposto, dupes ?v=hash (ext quebrada) e qualquer canonical-shadow. /PURGE só pega build-ext órfão.
# $keep vem de ESPELHO_EXTS e $pats (robocopy) do MESMO array — não podem mais drifar entre si.
$keep=@(${psLista(ESPELHO_EXTS)})
$junk=@(Get-ChildItem $dst -Recurse -File | Where-Object { $_.Name -ne '${PRESERVADOS[0]}' -and ($keep -notcontains $_.Extension.ToLower()) })
foreach($f in $junk){ Remove-Item $f.FullName -Force -ErrorAction SilentlyContinue }
Write-Output ("JUNK_SWEPT=" + $junk.Count)
# resíduo de PROCESSO — padrão vem de RESIDUO_PATTERN (fonte única no JS). Os MESMOS bytes que
# esta linha já mandava: o literal antigo tinha \. e \d, mas o heredoc é template literal e eles
# colapsavam antes de chegar aqui (medido 2026-09-13; ver o comentário de RESIDUO_PATTERN).
$resPat='${RESIDUO_PATTERN}'
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
    const REPO = resolve(HERE, '../..');
    const tocados = execFileSync('git', ['-C', REPO, 'status', '--short', '--', 'prototipo-ui/cowork/Wagner'], { encoding: 'utf8' })
      .split('\n').filter(Boolean).length;
    console.log(`  git: ${tocados} arquivo(s) tocado(s) em prototipo-ui/cowork/Wagner/ (rode 'git diff --stat' pra ver)`);
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
    console.log('\n→ sincronizando SSOT no repo (prototipo-ui/cowork/Wagner/):');
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
    if (!zip) { console.error('uso: node scripts/design/importar-bundle.mjs "<zip>" [--dir <staging>] [--no-detect] [--no-sync-cowork]'); process.exit(2); }
    process.exit(importar(resolve(zip), resolve(val('--dir') || DESTINO_PADRAO), { detect: !has('--no-detect'), syncCowork: !has('--no-sync-cowork') }));
  }
}
