#!/usr/bin/env node
// refutacao-recibo.mjs — extrai o RECIBO de uma evidência de refutação GT-G5.
//
// O QUE FAZ: lê `memory/sessions/<data>-refutacao-gt-g5-lote-<pr>-r<N>.md`, acha o ÚLTIMO
// bloco ```json que tenha `itens_verificados`, remove o array `refutados` (pode ter centenas
// de linhas — a r1 do #7224 tinha ~700) e imprime o bloco compacto. É o que o resume do
// workflow `.claude/workflows/refutador-gt-g5.js` parseia pra montar a trajetória.
//
// POR QUE EXISTE (2026-09-13): o Escopo do workflow pedia `tail -n 40` da evidência; num lote
// reprovado com muitos refutados o `itens_verificados` fica fora da cauda, o parse dá null,
// a rodada anterior some da trajetória e a próxima nasce numerada de novo como r1.
//
// USO: node scripts/governance/refutacao-recibo.mjs <arquivo.md>   → stdout: ```json {...} ```
//      exit 0 com recibo · exit 1 sem bloco json com itens_verificados (imprime tail -n 40).
import { readFileSync } from 'node:fs'

const arq = process.argv[2]
if (!arq) { console.error('uso: refutacao-recibo.mjs <evidencia.md>'); process.exit(2) }
const md = readFileSync(arq, 'utf8')
const blocos = [...md.matchAll(/```json\s*([\s\S]*?)```/g)].map((m) => m[1].trim())
for (let i = blocos.length - 1; i >= 0; i--) {
  try {
    const o = JSON.parse(blocos[i])
    if (o && Number.isInteger(o.itens_verificados)) {
      const n = Array.isArray(o.refutados) ? o.refutados.length : 0
      delete o.refutados
      o.refutados_count = n
      console.log('```json\n' + JSON.stringify(o) + '\n```')
      process.exit(0)
    }
  } catch { /* não é o recibo */ }
}
console.log(md.split('\n').slice(-40).join('\n'))
process.exit(1)
