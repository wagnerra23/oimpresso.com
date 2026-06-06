#!/usr/bin/env node
// design-identity-grade.mjs — GRADE de identidade visual DETERMINÍSTICO (ADR 0254).
//
// Por quê (SCREEN-GRADE-METODO §7 T1): um LLM-judge deu 91-depois-71 no MESMO PR
// (σ=14 ≫ 3) = rubrica subjetiva = alucinação. A cura prescrita: endurecer cada
// critério em BINÁRIO/MEDÍVEL. Aqui cada dimensão = % de conformidade com o token,
// contada por regex. Mesmo código → mesma nota (σ=0). Número não alucina.
//
// Uso:
//   node scripts/design-identity-grade.mjs            # mostra o grade (resources/js)
//   node scripts/design-identity-grade.mjs --baseline # congela o estado atual
//   node scripts/design-identity-grade.mjs --check     # ratchet: falha se a nota CAIR
//
// Refs: ADR 0254 · ADR 0209 (ratchet gêmeo) · SCREEN-GRADE-METODO §7

import { readFileSync, readdirSync, statSync, writeFileSync, existsSync } from 'node:fs'
import { join, extname, resolve } from 'node:path'

const ROOT = 'resources/js'
const BASELINE = resolve(process.cwd(), 'config/design-identity-baseline.json')
const MODE = process.argv.includes('--baseline') ? 'baseline'
  : process.argv.includes('--check') ? 'check' : 'show'

// ── CONFIG EXPLÍCITO (auditável — é o "o quê auditar" especificado) ──────────────
// Famílias de cor que DEVEM ser token (neutros → muted/border/foreground; azul/roxo
// cru → primary). Contam como violação de identidade.
const COR_DRIFT = 'slate|gray|zinc|neutral|stone|blue|indigo|violet|purple|fuchsia|cyan|teal|pink|lime|orange'
// Famílias permitidas (convenção de STATUS semântico — soft pills success/warn/danger/info).
// NÃO contam como violação (calibração v1, Wagner 2026-06-06).
const COR_STATUS_OK = 'emerald|green|amber|yellow|rose|red|sky'

function walk(d) {
  const o = []
  for (const n of readdirSync(d)) {
    const p = join(d, n); const s = statSync(p)
    if (s.isDirectory()) o.push(...walk(p))
    else if (['.tsx', '.jsx'].includes(extname(p)) && !/\.(test|spec)\./.test(p)) o.push(p)
  }
  return o
}
// remove comentários de linha/bloco (emoji em comentário não é violação de ícone)
const stripComments = (s) => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/[^\n]*/g, '')

const files = walk(ROOT)
const SRC = files.map((f) => readFileSync(f, 'utf8')).join('\n')
const SRC_NC = files.map((f) => stripComments(readFileSync(f, 'utf8'))).join('\n')

const count = (re, str = SRC) => (str.match(re) || []).length
const conf = (tok, cru) => (tok + cru === 0 ? 100 : Math.round((tok / (tok + cru)) * 100))

// 1. TIPOGRAFIA — token text-(xs..) vs cru text-[Npx]
// Onda 3: credita o primitivo <Text> (tipografia 100% token-by-design)
const textTok = count(/\btext-(xs|sm|base|lg|xl|2xl|3xl|4xl|5xl)\b/g) + count(/<Text\b/g)
const textRaw = count(/\btext-\[[0-9.]+px\]/g)
const TIPO = conf(textTok, textRaw)

// 2. COR — token semântico vs cor de DRIFT (status semântico é allowlisted)
const corTok = count(/\b(bg|text|border|ring|fill|stroke)-(primary|secondary|muted|accent|destructive|foreground|background|card|popover|border|ring|input)\b/g)
const corDrift = count(new RegExp(`\\b(bg|text|border|ring|fill|stroke)-(${COR_DRIFT})-(\\d{2,3})\\b`, 'g')) + count(/(?:bg|text|border)-\[#[0-9a-fA-F]{3,6}\]/g)
const corStatus = count(new RegExp(`\\b(bg|text|border)-(${COR_STATUS_OK})-(\\d{2,3})\\b`, 'g'))
const COR = conf(corTok + corStatus, corDrift) // status conta como conforme

// 3. ESPAÇO — token vs sub-token (gap-0.5 / [px])
const espTok = count(/\b(gap|p|px|py|m|mx|my|space-[xy])-(0|1|2|3|4|5|6|8|10|12|16|20|24)\b/g)
const espRaw = count(/\b(gap|p|px|py|m|mx|my)-(\[[^\]]+\]|0\.5|1\.5|2\.5|3\.5)\b/g)
const ESP = conf(espTok, espRaw)

// 4. FORMA — radius token vs cru
const FORMA = conf(count(/\brounded(-(sm|md|lg|xl|2xl|3xl|full|none))?\b/g), count(/\brounded-\[[^\]]+\]/g))

// ── Ondas 1-2 (refino justo): controles shared de @/Components/ui JÁ carregam
//    focus-ring + transition embutidos (button.tsx = `transition-all focus-visible:ring`).
//    Creditar a ADOÇÃO deles mede a REALIDADE — o grade cru punia o padrão BOM
//    (componente centralizado) e premiava sprinkle de classe. Goodhart ao contrário.
const sharedCtl = count(/<(Button|Input|Textarea|Checkbox|Switch|Select|SelectTrigger|Toggle|ToggleGroup|Tabs|TabsTrigger|Slider|RadioGroup|DropdownMenuTrigger)\b/g)
const rawCtl = count(/<(button|input|textarea|select)\b/g)
const interativos = sharedCtl + rawCtl

// 5. MOVIMENTO — % de interativos com transição (shared embutido + raw com transition)
const transRaw = Math.min(count(/\btransition(-[a-z]+)?\b/g), rawCtl)
const MOV = interativos === 0 ? 100 : Math.min(100, Math.round(((sharedCtl + transRaw) / interativos) * 100))

// 6. FOCO (assinatura a11y) — % de interativos com focus-ring (shared embutido + raw com ring)
const ringRaw = Math.min(count(/focus(-visible)?:ring/g), rawCtl)
const FOCO = interativos === 0 ? 100 : Math.min(100, Math.round(((sharedCtl + ringRaw) / interativos) * 100))

// 7. ÍCONE — emoji pictográfico cru NO JSX (sem comentário). Gate já força lucide.
const emoji = count(/[\u{1F300}-\u{1FAFF}]/gu, SRC_NC)
// densidade (emoji por arquivo ×100) — não zera o projeto por causa de 1 tela poluída
const ICONE = Math.max(0, 100 - Math.round((emoji / files.length) * 100))

// 8. LAYOUT — primitivos vs flex/grid cru
const flexCru = count(/className=["'{`][^"'}`]*\bflex(-col)?\b/g)
const gridCru = count(/className=["'{`][^"'}`]*\bgrid-cols-/g)
const primit = count(/<(Stack|Inline|Grid|Box|Container)\b/g)
const LAYOUT = conf(primit, flexCru + gridCru)

const DIMS = [
  ['tipografia', TIPO, 3, `${textRaw} text-[px] crus vs ${textTok} token`],
  ['cor', COR, 2, `${corDrift} cor-drift vs ${corTok + corStatus} ok (status allowlisted)`],
  ['espaco', ESP, 2, `${espRaw} sub-token vs ${espTok} token`],
  ['forma', FORMA, 1, `${count(/\brounded-\[[^\]]+\]/g)} radius cru`],
  ['movimento', MOV, 2, `${sharedCtl} shared + ${transRaw} raw-transition / ${interativos} interativos`],
  ['foco', FOCO, 2, `${sharedCtl} shared + ${ringRaw} raw-ring / ${interativos} interativos`],
  ['icone', ICONE, 1, `${emoji} emoji pictográfico no JSX`],
  ['layout', LAYOUT, 2, `${primit} primitivos vs ${flexCru + gridCru} flex/grid cru`],
]
const somaPeso = DIMS.reduce((a, [, , w]) => a + w, 0)
const nota = Math.round(DIMS.reduce((a, [, s, w]) => a + s * w, 0) / somaPeso)
const nivel = nota >= 95 ? 'Champion' : nota >= 85 ? 'Leader' : nota >= 70 ? 'Advanced' : nota >= 50 ? 'Developing' : 'Beginner'
const dimsObj = Object.fromEntries(DIMS.map(([n, s]) => [n, s]))

function printTable() {
  console.log(`\n=== GRADE DE IDENTIDADE VISUAL · determinístico (${ROOT}) ===\n`)
  for (const [n, s, w, ev] of DIMS) console.log(`  ${n.padEnd(12)} ${String(s).padStart(3)}  ${w}×  ${ev}`)
  console.log(`\n  NOTA: ${nota}/100 · ${nivel} · σ=0 (reprodutível)\n`)
}

// Onda 4: acionável — top-5 arquivos ofensores por dimensão (vira roadmap de migração)
function printOffenders() {
  const sig = [
    ['layout', (f) => (f.match(/className=["'{`][^"'}`]*\b(flex(-col)?|grid-cols-)/g) || []).length],
    ['tipografia', (f) => (f.match(/text-\[[0-9.]+px\]/g) || []).length],
  ]
  for (const [dim, fn] of sig) {
    const top = files.map((p) => [fn(readFileSync(p, 'utf8')), p]).filter(([n]) => n > 0).sort((a, b) => b[0] - a[0]).slice(0, 5)
    console.log(`  top-5 ofensores · ${dim}:`)
    for (const [n, p] of top) console.log(`    ${String(n).padStart(3)}  ${p.replace(/^.*resources[\/\\]js[\/\\]/, '')}`)
    console.log('')
  }
}

if (MODE === 'baseline') {
  printTable()
  writeFileSync(BASELINE, JSON.stringify({ nota, nivel, dims: dimsObj, _note: 'ratchet — só sobe. ADR 0254. Regenerar: --baseline' }, null, 2) + '\n')
  console.log(`✅ Baseline gravado em ${BASELINE}`)
} else if (MODE === 'check') {
  printTable()
  if (!existsSync(BASELINE)) { console.error('❌ Sem baseline. Rode --baseline.'); process.exit(1) }
  const base = JSON.parse(readFileSync(BASELINE, 'utf8'))
  const regrediu = []
  if (nota < base.nota) regrediu.push(`NOTA ${base.nota}→${nota}`)
  for (const [n, s] of Object.entries(dimsObj)) if (s < (base.dims[n] ?? 0)) regrediu.push(`${n} ${base.dims[n]}→${s}`)
  if (regrediu.length) { console.error(`❌ Identidade REGREDIU vs baseline:\n  - ${regrediu.join('\n  - ')}`); process.exit(1) }
  console.log(`✅ Sem regressão (nota ${nota} ≥ baseline ${base.nota})`)
} else {
  printTable()
  printOffenders()
}
