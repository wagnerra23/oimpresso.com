#!/usr/bin/env node
/**
 * Selftest do workflow `.claude/workflows/refutador-gt-g5.js` — hermético (zero agentes, zero
 * rede, zero git, zero LLM).
 *
 * COMO TESTA O CÓDIGO REAL: o arquivo do workflow é executado pelo tool Workflow dentro de uma
 * função async com os globals (`args`, `agent`, `parallel`, `phase`, `log`) injetados — por isso
 * tem `return`/`await` de topo e não é importável direto. Aqui o MESMO fonte é embrulhado nessa
 * função (o idiom do `reguas-workflow.test.mjs`) e rodado com um DUBLÊ de `agent()`. Não é
 * fixture copiada: é o arquivo vivo — lápide §5 2026-08-14 (selftest que exercita a CÓPIA, não o
 * chokepoint, fica verde enquanto o pipeline regride).
 *
 * O que trava:
 *  [1] `args.selftest` → as 44 asserções das PARTES PURAS (parse do JSON do refutador, montagem
 *      da entry no formato da entry do PR #6897, cálculo de error_rate, veredito da máquina,
 *      parse da cauda das evidências no resume, trajetória).
 *  [2] REPROVADO PARA: refutador devolve taxa ≥ 2% → o workflow devolve os refutados e NÃO chama
 *      o escrivão (o conserto é do gerador/humano; o workflow não edita o lote).
 *  [3] APROVADO REGISTRA: entry montada com `trajetoria` de TODAS as rodadas (resume lê as caudas
 *      das evidências anteriores) + escrivão chamado com a entry byte-a-byte + ledger-check colado.
 *  [4] Guardas: lote ≤ 10 arquivos sai sem gastar refutador; rodada anterior sem `resume` para;
 *      teto de rodadas para; adjetivo do agente NÃO vence a conta.
 *
 * Advisory por desenho (ADR 0314/0275: required = só Tier-0).
 */
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const RAIZ = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const ALVO = path.join(RAIZ, '.claude/workflows/refutador-gt-g5.js')
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'refutador-wf-'))

const carregar = async () => {
  const src = fs.readFileSync(ALVO, 'utf8').replace(/^export const meta/m, 'const meta')
  const f = path.join(TMP, `wf-${process.pid}-${Math.random().toString(36).slice(2)}.mjs`)
  fs.writeFileSync(f, 'export default async function (args, agent, parallel, pipeline, phase, log) {\n' + src + '\n}')
  return (await import(`file://${f}`)).default
}

let falhas = 0
const ok = (cond, msg) => { console.log(`${cond ? '  ok  ' : '  FALHOU  '}${msg}`); if (!cond) falhas++ }

const EV = (n) => `memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897-r${n}.md`
const cauda = (itens, erros) => `## Veredito\n\n\`\`\`json\n{"itens_verificados": ${itens}, "erros_confirmados": ${erros}, "error_rate_pct": 0, "pii_hits": 0, "veredito": "x"}\n\`\`\`\n`
const escopo = ({ nArq = 16, evid = [] } = {}) => ({
  data: '2026-09-06', head_sha: 'bd57cd8334aaaaaaaaaa', base_sha: '26ac293f46bbbbbbbbbb', merge_base: '26ac293f46bbbbbbbbbb', raso: false,
  arquivos: Array.from({ length: nArq }, (_, i) => ({ status: 'M', path: `memory/requisitos/Mod/arq-${i}.md` })),
  fora_requisitos_count: 0, evidencias_existentes: evid,
})
const refutacao = (rodada, itens, erros, extra = {}) => ({
  itens_verificados: itens, erros_confirmados: erros, error_rate_pct: Math.round((erros / itens) * 10000) / 100, pii_hits: 0, pii_controles_positivos_ok: 7,
  veredito: erros / itens < 0.02 ? 'aprovado' : 'reprovado',
  refutados: Array.from({ length: erros }, (_, i) => ({ arquivo: `memory/requisitos/Mod/arq-${i}.md`, item: `linha ${i}`, evidencia: `Drawer.tsx l.${i}` })),
  evidencia: EV(rodada), arquivo_existia: false, abriu_evidencia_anterior: false, observacoes: [], ...extra,
})

// Dublê por PREFIXO de label — o contrato de rotulagem do workflow.
const rodar = async (args, { esc, ref, escrivao } = {}) => {
  const chamadas = []; const logs = []
  const agent = async (prompt, opts = {}) => {
    const l = opts.label || ''
    chamadas.push({ label: l, prompt, model: opts.model, effort: opts.effort })
    if (l.startsWith('escopo:')) return esc
    if (l.startsWith('refutador:')) return typeof ref === 'function' ? ref(l) : ref
    if (l.startsWith('escrivao:')) return typeof escrivao === 'function' ? escrivao(prompt) : escrivao
    return null
  }
  const fn = await carregar()
  const out = await fn(args, agent, (fns) => Promise.all(fns.map((f) => f())), null, () => {}, (m) => logs.push(String(m)))
  return { out, chamadas, logs, labels: chamadas.map((c) => c.label), prompt: (p) => (chamadas.find((c) => c.label.startsWith(p)) || {}).prompt || '' }
}

console.log('\n[1] args.selftest — as partes puras (mesmas funções que o fluxo usa)')
{
  const r = await rodar({ selftest: true })
  ok(r.out && r.out.selftest && r.out.selftest.ok === true, `selftest interno verde (${r.out?.selftest?.total} asserções)`)
  if (r.out?.selftest?.falhas?.length) for (const f of r.out.selftest.falhas) console.log(`      ✗ ${f}`)
  ok(r.chamadas.length === 0, 'selftest não gasta agente nenhum')
}

console.log('\n[2] REPROVADO para: devolve refutados, NÃO chama escrivão, NÃO edita o lote')
{
  const r = await rodar({ pr: 6897, gerador: 'claude-opus-5 (Anthropic) — gerador' }, { esc: escopo(), ref: refutacao(1, 141, 19) })
  ok(r.out.veredito === 'reprovado' && r.out.rodada === 1, 'veredito reprovado na r1')
  ok(r.out.error_rate_pct === 13.48, 'error_rate calculado pela máquina (13.48)')
  ok(Array.isArray(r.out.refutados) && r.out.refutados.length === 19 && r.out.refutados[0].evidencia, '19 refutados com evidência devolvidos ao chamador')
  ok(!r.labels.some((l) => l.startsWith('escrivao:')), 'escrivão NÃO chamado (ledger intocado)')
  ok(/resume=true/.test(r.out.proximo_passo), 'próximo passo = consertar e reinvocar com resume')
  ok(r.labels.filter((l) => l.startsWith('refutador:')).length === 1, 'exatamente 1 refutador por invocação')
  const p = r.prompt('refutador:')
  ok(/PROIBIDO abrir qualquer `memory\/sessions\/\*refutacao\*`/.test(p), 'prompt: proibido abrir evidências anteriores')
  ok(/1\. ÂNCORA EXISTE/.test(p) && /2\. ÂNCORA NÃO REVOGADA/.test(p) && /3\. AÇÃO × VEREDITO DA PROSA/.test(p) && /4\. CÉLULA ÍNTEGRA/.test(p) && /5\. MÁQUINA DERIVADA/.test(p) && /6\. SCAN PII COM CONTROLE POSITIVO/.test(p), 'prompt: itens 1–6 do adversarial canônico')
  ok(p.includes(EV(1)), 'prompt: path exato da evidência -r1')
  ok(p.includes('memory/requisitos/Mod/arq-15.md'), 'prompt: lista completa do lote (16/16)')
  const ref = r.chamadas.find((c) => c.label.startsWith('refutador:'))
  ok(ref.model === 'fable', 'refutador no tier máximo (fable) por default')
  ok(r.chamadas.find((c) => c.label.startsWith('escopo:')).model === 'sonnet', 'escopo é mecânico (sonnet)')
}

console.log('\n[3] APROVADO registra: resume lê as caudas, entry com trajetória de TODAS as rodadas, escrivão + ledger-check')
{
  const evid = [{ arquivo: EV(1), rodada: 1, cauda: cauda(141, 19) }, { arquivo: EV(2), rodada: 2, cauda: cauda(141, 4) }]
  let entryRecebida = null
  const escrivao = (prompt) => {
    const m = prompt.match(/```json\n([\s\S]*?)\n```/)
    entryRecebida = JSON.parse(m[1])
    return { gravou: true, ultima_entry_json: JSON.stringify(entryRecebida), ledger_check_saida: '✓ ledger-check: PR-de-lote #6897 (16 arquivos) com entry valida no ledger — refutacao adversarial registrada.', ledger_check_rc: 0, diff_stat: '1 file changed' }
  }
  const r = await rodar({ pr: 6897, resume: true, gerador: 'claude-opus-5 (Anthropic) — sessão que autorou o lote' }, { esc: escopo({ evid }), ref: refutacao(3, 74, 1), escrivao })
  ok(r.out.ok === true && r.out.veredito === 'aprovado' && r.out.rodada === 3, 'resume: próxima rodada = r3, aprovada')
  ok(r.prompt('refutador:').includes(EV(3)), 'refutador da r3 recebe o path -r3')
  ok(r.out.entry.trajetoria === '3 rodadas: 13,48% (r1, 19/141) → 2,84% (r2, 4/141) → 1,35% (r3, 1/74)', 'trajetória com as 3 rodadas (2 lidas das caudas + a atual)')
  ok(r.out.entry.pr === 6897 && r.out.entry.error_rate_pct === 1.35 && r.out.entry.sessao_fresca === true && r.out.entry.veredito === 'aprovado' && r.out.entry.tipo === 'anchors' && r.out.entry.amostra_pct === 100, 'entry no formato 6897 (números da rodada final)')
  ok(/^claude-fable-5-1 \(Anthropic\)/.test(r.out.entry.refutador) && /r1–r3$/.test(r.out.entry.refutador), 'refutador com modelo REAL + faixa r1–r3')
  ok(r.out.entry.gerador.startsWith('claude-opus-5'), 'gerador = o declarado em args')
  ok(entryRecebida && JSON.stringify(entryRecebida) === JSON.stringify(r.out.entry), 'escrivão recebe a entry MONTADA byte-a-byte')
  ok(r.out.entry_gravada_identica === true, 'entry gravada conferida contra a montada (entryIgual)')
  ok(r.out.ledger_check.rc === 0 && /refutacao adversarial registrada/.test(r.out.ledger_check.saida), 'saída do ledger-check --enforce colada no resultado')
  ok(/ledger-check\.mjs --pr 6897 --base origin\/main --head HEAD --enforce/.test(r.prompt('escrivao:')), 'escrivão manda rodar o ledger-check com --enforce')
  ok(!/R\$\s?\d/.test(JSON.stringify(r.out)), 'sem valor em reais no resultado')
}

console.log('\n[4] guardas')
{
  const a = await rodar({ pr: 6897 }, { esc: escopo({ nArq: 10 }) })
  ok(a.out.lote === false && !a.labels.some((l) => l.startsWith('refutador:')), 'lote ≤ 10 arquivos: sai no Escopo sem gastar refutador')
  const b = await rodar({ pr: 6897, forcar: true }, { esc: escopo({ nArq: 3 }), ref: refutacao(1, 20, 5) })
  ok(b.labels.some((l) => l.startsWith('refutador:')) && b.out.veredito === 'reprovado', 'args.forcar roda mesmo abaixo do threshold')
  const c = await rodar({ pr: 6897 }, { esc: escopo({ evid: [{ arquivo: EV(1), rodada: 1, cauda: cauda(141, 19) }] }) })
  ok(c.out.ok === false && !c.labels.some((l) => l.startsWith('refutador:')) && /use resume/.test(c.out.erros[0]), 'rodada anterior sem args.resume: PARA antes do refutador (não colide -r1)')
  const d = await rodar({ pr: 6897, resume: true, maxRodadas: 2 }, { esc: escopo({ evid: [{ arquivo: EV(1), rodada: 1, cauda: cauda(141, 19) }, { arquivo: EV(2), rodada: 2, cauda: cauda(141, 4) }] }) })
  ok(d.out.veredito === 'teto' && d.out.rodada === 3 && !d.labels.some((l) => l.startsWith('refutador:')), 'teto de rodadas (r3 > maxRodadas=2): PARA, decisão humana')
  const e = await rodar({ pr: 6897, gerador: 'opus' }, { esc: escopo(), ref: refutacao(1, 74, 1, { veredito: 'reprovado', error_rate_pct: 40 }) })
  ok(e.out.veredito === 'aprovado' && e.logs.some((l) => /vence a máquina/.test(l)), 'adjetivo do agente NÃO vence a conta (1/74 aprova mesmo com o agente dizendo reprovado)')
  const f = await rodar({ pr: 6897, gerador: 'opus' }, { esc: escopo(), ref: refutacao(1, 74, 0, { pii_hits: 1 }) })
  ok(f.out.veredito === 'reprovado' && !f.labels.some((l) => l.startsWith('escrivao:')), 'pii_hits=1 reprova com taxa 0 (repo público)')
  const g = await rodar({ pr: 6897, gerador: 'opus' }, { esc: escopo(), ref: refutacao(1, 74, 1, { arquivo_existia: true }) })
  ok(g.out.ok === false && Array.isArray(g.out.invalida), 'evidência que já existia → rodada inválida, não conta')
  const h = await rodar({ pr: 6897 }, { esc: escopo(), ref: refutacao(1, 74, 1) })
  ok(h.out.ok === false && h.out.veredito === 'aprovado' && !h.labels.some((l) => l.startsWith('escrivao:')), 'aprovado sem args.gerador: não grava entry (campo não é terra de ninguém)')
  const i = await rodar({ pr: 6897, gerador: 'opus' }, { esc: escopo(), ref: null })
  ok(i.out.ok === false && !i.labels.some((l) => l.startsWith('escrivao:')), 'refutador nulo (abortado) → rodada inválida, sem escrivão')
  const j = await rodar({}, {})
  ok(j.out.ok === false && j.chamadas.length === 0, 'sem args.pr → nada roda')
}

fs.rmSync(TMP, { recursive: true, force: true })
console.log(falhas ? `\n${falhas} FALHA(S)` : '\nOK — selftest refutador-gt-g5 verde')
process.exit(falhas ? 1 : 0)
