<#
.SYNOPSIS
  Portão de 1 comando pra handoff Cowork: extrai o ZIP (Windows-safe) e diz se MUDOU vs o aceito.

.DESCRIPTION
  Junta a Fase -1 (extrair) + o gate determinístico (handoff-changed.mjs) num passo só.
  - Extrai pra 1 destino FIXO fora do repo (sobrescreve sempre — as âncoras dependem do path estável).
  - Tolera nomes com cache-bust `?v=hash` (o extrator nativo do Windows aborta neles em silêncio).
  - Roda o gate: exit 0 = idêntico ao baseline (NÃO processe, custo zero) · 1 = mudou (lista o delta).

.PARAMETER Zip     Caminho do .zip de handoff (baixado do Claude Design / claude.ai/design).
.PARAMETER Accept  Depois de revisar, ACEITA o snapshot atual como novo baseline (--update).

.EXAMPLE
  pwsh prototipo-ui/check-handoff.ps1 -Zip "C:\Users\wagne\Downloads\handoff.zip"
.EXAMPLE
  pwsh prototipo-ui/check-handoff.ps1 -Zip "...\handoff.zip" -Accept
#>
param(
  [Parameter(Mandatory = $true)][string]$Zip,
  [switch]$Accept
)
$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot -Parent
$staging = Join-Path $env:USERPROFILE 'Downloads\_cowork-handoff-staging'  # FIXO, fora do repo

if (-not (Test-Path $Zip)) { Write-Error "ZIP nao encontrado: $Zip"; exit 2 }

Add-Type -AssemblyName System.IO.Compression.FileSystem
if ([System.IO.Directory]::Exists($staging)) { [System.IO.Directory]::Delete($staging, $true) }
[System.IO.Directory]::CreateDirectory($staging) | Out-Null

$z = [System.IO.Compression.ZipFile]::OpenRead($Zip); $inv = [IO.Path]::GetInvalidFileNameChars(); $ok = 0; $entries = 0
foreach ($e in $z.Entries) {
  $entries++; if ($e.FullName.EndsWith('/')) { continue }
  $rel = ($e.FullName -split '/' | ForEach-Object { $s = $_; foreach ($c in $inv) { if ($c -ne '/') { $s = $s.Replace($c, '_') } }; $s }) -join '\'
  $d = Join-Path $staging $rel; $dir = Split-Path $d -Parent
  if (-not (Test-Path $dir)) { New-Item -ItemType Directory $dir -Force | Out-Null }
  [IO.Compression.ZipFileExtensions]::ExtractToFile($e, $d, $true); $ok++
}
$z.Dispose()
if ($ok -ne ($entries - ($entries - $ok))) {}  # no-op guard
Write-Host "extraidos=$ok / entries=$entries -> $staging`n"

# Ancora o root na MARCA real do DS (determinístico, imune a quantos dirs de topo o ZIP tenha).
# Ordem: _ds_manifest.json → colors_and_type.css → pasta components/ → staging.
$marker = Get-ChildItem $staging -Recurse -File -Filter '_ds_manifest.json' | Select-Object -First 1
if (-not $marker) { $marker = Get-ChildItem $staging -Recurse -File -Filter 'colors_and_type.css' | Select-Object -First 1 }
if ($marker) { $scan = Split-Path $marker.FullName -Parent }
else {
  $comp = Get-ChildItem $staging -Recurse -Directory -Filter 'components' | Select-Object -First 1
  $scan = if ($comp) { Split-Path $comp.FullName -Parent } else { $staging }
}
Write-Host "root do DS = $scan`n"

$gate = Join-Path $PSScriptRoot 'handoff-changed.mjs'
if ($Accept) { node $gate --staging $scan --update } else { node $gate --staging $scan }
exit $LASTEXITCODE
