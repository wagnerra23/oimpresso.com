#!/usr/bin/env node
// reguas-indexar.mjs — Órgão 4 da máquina de réguas em looping (ADR proposta reguas-loop-maquina-evolucao).
// Consome memory/reguas/fraquezas.json e lista os achados "existia-mas-invisível" AINDA NÃO indexados
// no mapa/BRIEFINGs — o sangramento nº 1 do ciclo (7+15 gaps falsos re-descobertos em 2026-07-18/19
// porque o mecanismo existia FORA do mapa). REPORT-ONLY / advisory por design (lápide 0336: gate novo
// só com mordida provada; este script não bloqueia nada).
//
// Uso:
//   node scripts/governance/reguas-indexar.mjs             → fila de indexação agrupada por alvo
//   node scripts/governance/reguas-indexar.mjs --marcar id1,id2  → marca como indexado (rodar no PR que indexou)
//   node scripts/governance/reguas-indexar.mjs --selftest  → fixture boa/ruim (a catraca morde e libera certo)
//   node scripts/governance/reguas-indexar.mjs --json      → saída machine-readable
import { readFileSync, writeFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const FRAQUEZAS = join(ROOT, 'memory', 'reguas', 'fraquezas.json')

/* CORE-INI (função pura — testável sem fs) */
export const filaDeIndexacao = (fraquezas) =>
  fraquezas.filter((f) => f.existia_invisivel === true && f.indexado !== true && f.onde_indexar)
export const agruparPorAlvo = (fila) => {
  const porAlvo = new Map()
  for (const f of fila) {
    // alvo = primeiro segmento do onde_indexar ("mapa: ..." / "BRIEFING Compras + ...")
    const alvo = String(f.onde_indexar).split(':')[0].trim()
    if (!porAlvo.has(alvo)) porAlvo.set(alvo, [])
    porAlvo.get(alvo).push(f)
  }
  return porAlvo
}
/* CORE-FIM */

const selftest = () => {
  const boa = [ // fixture RUIM-pro-gate: pendência REAL → o report TEM que listar
    { id: 'x1', existia_invisivel: true, indexado: false, onde_indexar: 'mapa: mecanismo Y', dimensao: 'd', titulo: 't' },
  ]
  const limpa = [ // fixture BOA: tudo indexado / não-invisível → fila vazia
    { id: 'x2', existia_invisivel: true, indexado: true, onde_indexar: 'mapa: Z' },
    { id: 'x3', existia_invisivel: false, onde_indexar: 'mapa: W' },
    { id: 'x4', existia_invisivel: true, indexado: false }, // sem onde_indexar → não entra (nada a indexar)
  ]
  const f1 = filaDeIndexacao(boa)
  const f2 = filaDeIndexacao(limpa)
  const ok1 = f1.length === 1 && f1[0].id === 'x1'
  const ok2 = f2.length === 0
  const g = agruparPorAlvo(f1)
  const ok3 = g.size === 1 && g.has('mapa')
  if (ok1 && ok2 && ok3) { console.log('selftest ✓ — detecta pendência real, ignora indexado/sem-alvo, agrupa por alvo'); process.exit(0) }
  console.error(`selftest ✗ — detecta=${ok1} ignora=${ok2} agrupa=${ok3}`)
  process.exit(1)
}

const main = () => {
  const argv = process.argv.slice(2)
  if (argv.includes('--selftest')) return selftest()
  const fraquezas = JSON.parse(readFileSync(FRAQUEZAS, 'utf8'))

  const marcarIdx = argv.indexOf('--marcar')
  if (marcarIdx !== -1) {
    const ids = String(argv[marcarIdx + 1] || '').split(',').map((s) => s.trim()).filter(Boolean)
    if (!ids.length) { console.error('uso: --marcar id1,id2'); process.exit(1) }
    let n = 0
    for (const f of fraquezas) if (ids.includes(f.id)) { f.indexado = true; n++ }
    writeFileSync(FRAQUEZAS, JSON.stringify(fraquezas, null, 2) + '\n')
    console.log(`marcados como indexados: ${n}/${ids.length} (${ids.join(', ')})`)
    if (n !== ids.length) { console.error('⚠️ ids não encontrados: ' + ids.filter((i) => !fraquezas.some((f) => f.id === i)).join(', ')); process.exit(1) }
    return
  }

  const fila = filaDeIndexacao(fraquezas)
  if (argv.includes('--json')) { console.log(JSON.stringify({ pendentes: fila.length, fila }, null, 2)); return }
  if (!fila.length) { console.log('fila de indexação vazia ✓ — nenhum existia-mas-invisível pendente'); return }
  console.log(`FILA DE INDEXAÇÃO — ${fila.length} achado(s) "existia-mas-invisível" pendente(s) (o chip mais barato da rodada):\n`)
  for (const [alvo, itens] of agruparPorAlvo(fila)) {
    console.log(`▸ ${alvo} (${itens.length}):`)
    for (const f of itens) console.log(`  - [${f.id}] ${f.titulo} → ${f.onde_indexar} (${f.dimensao}, ${f.data || 's/data'})`)
  }
  console.log('\nAo indexar num PR: node scripts/governance/reguas-indexar.mjs --marcar <ids>')
}

main()
