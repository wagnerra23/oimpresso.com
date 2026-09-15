#!/usr/bin/env node
// refutacao-recibo.test.mjs — bite-test do extrator de recibo de evidência GT-G5.
//
// Exercita o CLI de fora (subprocesso), não uma cópia da lógica (lápide §5 2026-08-14).
// Fixture 1 = o caso que o `tail -n 40` perdia: bloco json com 120 refutados (o recibo
// fica a centenas de linhas do fim). Fixture 2 = evidência sem bloco json → rc 1 + tail.
import { execFileSync, spawnSync } from 'node:child_process'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const SCRIPT = new URL('./refutacao-recibo.mjs', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1')
let falhas = 0
const ok = (cond, msg) => { console.log(`  ${cond ? 'ok ' : 'FAIL'} ${msg}`); if (!cond) falhas++ }

const dir = mkdtempSync(join(tmpdir(), 'recibo-'))
const refutados = Array.from({ length: 120 }, (_, i) => ({ arquivo: `memory/requisitos/X/f${i}.md`, linha: i + 1, categoria: 'B', item: 'a -> b', evidencia: 'path novo não existe' }))
const recibo = { pr: 7224, tipo: 'anchors', amostra_pct: 100, itens_verificados: 1387, erros_confirmados: 189, error_rate_pct: 13.63, pii_scan: true, pii_hits: 0, refutados, veredito: 'reprovado', evidencia: 'memory/sessions/2026-09-11-refutacao-gt-g5-lote-7224-r1.md', sessao_fresca: true }
const longa = join(dir, 'longa.md')
writeFileSync(longa, '# Evidência\n\n```json\n{"nota":"bloco anterior, não é o recibo"}\n```\n\n```json\n' + JSON.stringify(recibo, null, 1) + '\n```\n')

const out = execFileSync(process.execPath, [SCRIPT, longa], { encoding: 'utf8' })
const linhasTotais = String(JSON.stringify(recibo, null, 1)).split('\n').length
ok(linhasTotais > 40, `fixture é o caso real: bloco json com ${linhasTotais} linhas (> 40 do tail antigo)`)
const m = out.match(/```json\s*([\s\S]*?)```/)
ok(!!m, 'stdout traz um bloco ```json')
const o = m ? JSON.parse(m[1]) : {}
ok(o.itens_verificados === 1387 && o.erros_confirmados === 189, 'recibo preserva itens_verificados/erros_confirmados (o que o resume parseia)')
ok(!('refutados' in o) && o.refutados_count === 120, 'array refutados removido, contagem preservada')
ok(o.veredito === 'reprovado' && o.sessao_fresca === true, 'campos escalares preservados')

// CONTROLE NEGATIVO: sem bloco json com itens_verificados → rc 1 e cai no tail
const semRecibo = join(dir, 'sem.md')
writeFileSync(semRecibo, Array.from({ length: 60 }, (_, i) => `linha ${i + 1}`).join('\n') + '\n')
const r = spawnSync(process.execPath, [SCRIPT, semRecibo], { encoding: 'utf8' })
ok(r.status === 1, `sem recibo → exit 1 (veio ${r.status})`)
ok(r.stdout.includes('linha 60') && !r.stdout.includes('linha 20'), 'sem recibo → imprime tail -n 40 (fallback honesto)')

if (falhas) { console.error(`\n${falhas} falha(s)`); process.exit(1) }
console.log('\nOK — refutacao-recibo verde')
