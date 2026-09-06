export const meta = {
  name: 'refutador-gt-g5',
  description: 'Refutação adversarial GT-G5 de um PR-de-lote IA: 1 refutador (instância NOVA, sessão fresca, tier máximo) por rodada com o prompt canônico; reprovado → devolve os refutados e PARA (o conserto é do gerador/humano); aprovado → entry no ledger + ledger-check --enforce',
  whenToUse: 'PR que toca >10 arquivos em memory/requisitos/** gerado por IA (PROTOCOLO-REFUTADOR-BACKFILL §1). Caminho executável do §2 do protocolo. Re-invocar com args.resume=true depois de consertar o lote.',
  phases: [
    { title: 'Escopo', detail: 'lista o lote (git diff base...HEAD -- memory/requisitos) + shas + data; em resume, colhe as rodadas já registradas' },
    { title: 'Refutar', detail: '1 agente refutador NOVO por rodada, proibido de abrir evidências anteriores, escreve memory/sessions/<data>-refutacao-gt-g5-lote-<pr>-r<N>.md' },
    { title: 'Registrar', detail: 'só se aprovado: entry no ledger (append) + node scripts/governance/ledger-check.mjs --enforce' },
  ],
}
// ─────────────────────────────────────────────────────────────────────────────
// refutador-gt-g5.js — caminho EXECUTÁVEL do §2 do PROTOCOLO-REFUTADOR-BACKFILL
// (memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md, §7 aponta pra cá).
//
// O QUE FAZ, por invocação (= UMA rodada):
//   1. Escopo   — agente mecânico lista o lote (`git diff --name-only <base>...HEAD --
//                 memory/requisitos`), mede shas/raso/data. Em `args.resume`, lê a CAUDA
//                 das evidências já gravadas (`...-r<N>.md`) — o JSON final de cada uma
//                 é parseado AQUI (parseResultadoDeMarkdown), não pelo agente.
//   2. Refutar  — UM agente refutador, SEMPRE instância nova (é a `sessao_fresca` do §2.2:
//                 contexto próprio, zero herança), modelo = o máximo disponível (fable —
//                 o ledger-check aceita igualdade gerador/refutador só no tier máximo),
//                 PROIBIDO de abrir `memory/sessions/*refutacao*`. Recebe o prompt
//                 adversarial canônico (itens 1–6 abaixo — a estrutura que funcionou nas 9
//                 rodadas do PR #6897 em 2026-09-06) e escreve a evidência
//                 `memory/sessions/<data>-refutacao-gt-g5-lote-<pr>-r<N>.md`.
//   3. Veredito — calculado AQUI (error_rate < 2 · pii_hits = 0 · controles positivos
//                 7/7), nunca pelo adjetivo do agente. Se o agente discordar, log + vence
//                 a conta. Reprovado → devolve `refutados[]` ao chamador e PARA. O
//                 workflow NÃO edita o lote: consertar é do gerador/humano (§2.6 — o lote
//                 volta INTEIRO). Só repete quando invocado de novo com `args.resume`.
//   4. Registrar — só se aprovado: monta a entry no formato da entry do PR #6897
//                 (gerador/refutador com modelo real · sessao_fresca:true · trajetoria com
//                 TODAS as rodadas), um agente mecânico faz o append em
//                 governance/sdd-verification-ledger.json e roda
//                 `node scripts/governance/ledger-check.mjs --pr <pr> --base <base> --head HEAD --enforce`;
//                 a saída volta colada no resultado. A entry gravada é comparada
//                 campo-a-campo com a montada (entryIgual) — transcrição não vale.
//
// CUSTO (medido 2026-09-06, PR #6897, 9 rodadas manuais): cada rodada do refutador custou
// ~330–400k tokens (fable, lote de 16–27 arquivos, 62–141 itens). O loop só compensa para
// lote > 10 arquivos em memory/requisitos/** — abaixo disso o ledger-check nem dispara
// (threshold do §1) e este workflow sai no Escopo sem gastar refutador (a menos de
// `args.forcar`).
//
// args = {
//   pr: 6897,                 // obrigatório — número do PR do lote
//   base: 'origin/main',      // default
//   tipo: 'anchors'|'prosa',  // default 'anchors' (prosa exige amostra ≥30% com seed declarada)
//   maxRodadas: 5,            // teto de rodadas ACUMULADO entre resumes (r<N> > teto → PARA, decisão humana)
//   resume: false,            // true = continuar: lê rodadas já gravadas e roda a próxima
//   gerador: 'claude-opus-5 (Anthropic) — sessão que autorou o lote',  // obrigatório na aprovação
//   modeloRefutador: 'fable', // default — tier máximo
//   loteId: 'SA-A5-...',      // default gt-g5-lote-<pr>
//   esforco: 'high',          // effort do refutador
//   forcar: false,            // roda mesmo com ≤10 arquivos (o gate não dispara; custo puro)
//   marcadorAbsorcao: 'n/a',  // campo livre da entry (ver entry 6897)
//   selftest: false,          // true = só roda os testes das partes puras (zero agentes)
// }
//
// SELFTEST hermético: `node scripts/governance/refutador-gt-g5-workflow.test.mjs` embrulha
// ESTE arquivo (não uma cópia — lápide §5 2026-08-14: selftest que exercita a cópia é
// carimbo) com um dublê de agent() e roda `args.selftest` + 3 cenários de controle de fluxo.
// Também dá pra rodar via Workflow({ scriptPath, args: { selftest: true } }).
//
// PROIBIDO neste sandbox: Date.now / Math.random / fs — a data vem do agente de Escopo
// (`date +%F`) e a amostra de prosa é determinística (seed = 8 chars do HEAD sha).
// ─────────────────────────────────────────────────────────────────────────────

// ── args (parse defensivo: a fronteira do tool já serializou args pra STRING 2× — ver reguas-do-sistema.js) ──
const A = (() => {
  const raw = typeof args === 'undefined' ? {} : args
  if (typeof raw === 'string') { try { return JSON.parse(raw) } catch { return {} } }
  return raw || {}
})()

const MODELO_ID = { fable: 'claude-fable-5-1', mythos: 'claude-mythos-5-1', opus: 'claude-opus-5', sonnet: 'claude-sonnet-5', haiku: 'claude-haiku-4-5-20251001' }
const THRESHOLD_LOTE = 10   // §1 do protocolo — o mesmo do ledger-check (--threshold default)
const ACEITE_ERROR_RATE = 2 // §2.6 — aceite < 2%
const PII_PADROES = 7       // CPF pontuado · CPF cru · CNPJ · telefone BR · telefone cru · e-mail · valor em reais

// ── PARTES PURAS (testadas pelo selftest — são as MESMAS funções que o fluxo usa) ──

function normalizarArgs(a) {
  const erros = []
  const pr = Number.parseInt(String(a.pr ?? ''), 10)
  if (!Number.isInteger(pr) || pr < 1) erros.push('args.pr obrigatório (inteiro ≥ 1 — número do PR do lote)')
  const tipo = a.tipo || 'anchors'
  if (tipo !== 'anchors' && tipo !== 'prosa') erros.push(`args.tipo="${tipo}" inválido (anchors|prosa)`)
  const maxRodadas = Number.parseInt(String(a.maxRodadas ?? 5), 10)
  if (!Number.isInteger(maxRodadas) || maxRodadas < 1) erros.push('args.maxRodadas inválido (inteiro ≥ 1)')
  const modeloRefutador = String(a.modeloRefutador || 'fable').toLowerCase()
  if (!MODELO_ID[modeloRefutador]) erros.push(`args.modeloRefutador="${modeloRefutador}" desconhecido (${Object.keys(MODELO_ID).join('|')})`)
  return {
    ok: erros.length === 0, erros,
    pr, base: a.base || 'origin/main', tipo, maxRodadas,
    resume: a.resume === true || a.resume === 'true',
    forcar: a.forcar === true,
    gerador: a.gerador ? String(a.gerador) : null,
    modeloRefutador,
    loteId: a.loteId ? String(a.loteId) : `gt-g5-lote-${pr}`,
    esforco: a.esforco || 'high',
    marcadorAbsorcao: a.marcadorAbsorcao ? String(a.marcadorAbsorcao) : 'n/a — lote não afrouxa baseline nenhum',
    selftest: a.selftest === true,
  }
}

// error_rate = erros/itens*100, 2 casas (1/74 → 1.35 · 19/141 → 13.48). Sem itens não há taxa.
function calcErrorRate(itens, erros) {
  if (!Number.isInteger(itens) || itens < 1 || !Number.isInteger(erros) || erros < 0 || erros > itens) return null
  return Math.round((erros / itens) * 10000) / 100
}

const ehLote = (nArquivos, threshold = THRESHOLD_LOTE) => nArquivos > threshold

const pathEvidencia = (data, pr, n) => `memory/sessions/${data}-refutacao-gt-g5-lote-${pr}-r${n}.md`

// O veredito é da MÁQUINA: taxa < 2, zero PII, e a sonda de PII provada (controle positivo
// em TODOS os padrões — sonda que não casa nada não mediu nada, §5 2026-08-01).
function vereditoDe(r) {
  const taxa = calcErrorRate(r.itens_verificados, r.erros_confirmados)
  if (taxa === null) return { veredito: 'reprovado', taxa: null, motivo: 'itens/erros inválidos (sem denominador não há taxa)' }
  if (r.pii_controles_positivos_ok !== PII_PADROES) return { veredito: 'reprovado', taxa, motivo: `scan PII não provado: controles positivos ${r.pii_controles_positivos_ok}/${PII_PADROES}` }
  if (r.pii_hits !== 0) return { veredito: 'reprovado', taxa, motivo: `pii_hits=${r.pii_hits} (repo público — obrigatório 0)` }
  if (taxa >= ACEITE_ERROR_RATE) return { veredito: 'reprovado', taxa, motivo: `error_rate ${taxa}% ≥ ${ACEITE_ERROR_RATE}%` }
  return { veredito: 'aprovado', taxa, motivo: `error_rate ${taxa}% < ${ACEITE_ERROR_RATE}% · pii 0 · controles ${PII_PADROES}/${PII_PADROES}` }
}

// Valida a forma do JSON que o refutador devolve. Não confia no veredito nem na taxa dele.
function parseRefutacao(obj) {
  const erros = []
  if (!obj || typeof obj !== 'object') return { ok: false, erros: ['refutador devolveu nada (agente nulo/abortado)'] }
  const intNaoNeg = (k) => { if (!Number.isInteger(obj[k]) || obj[k] < 0) erros.push(`${k}=${obj[k]} (exige inteiro ≥ 0)`) }
  intNaoNeg('itens_verificados'); intNaoNeg('erros_confirmados'); intNaoNeg('pii_hits'); intNaoNeg('pii_controles_positivos_ok')
  if (Number.isInteger(obj.itens_verificados) && obj.itens_verificados < 1) erros.push('itens_verificados=0 — refutação sem denominador não é refutação')
  if (Number.isInteger(obj.erros_confirmados) && Number.isInteger(obj.itens_verificados) && obj.erros_confirmados > obj.itens_verificados) erros.push('erros_confirmados > itens_verificados (impossível)')
  if (!Array.isArray(obj.refutados)) erros.push('refutados ausente (array, pode ser vazio)')
  else {
    obj.refutados.forEach((r, i) => {
      for (const k of ['arquivo', 'item', 'evidencia']) if (!r || typeof r[k] !== 'string' || !r[k].trim()) erros.push(`refutados[${i}].${k} ausente`)
    })
    if (Number.isInteger(obj.erros_confirmados) && obj.refutados.length !== obj.erros_confirmados) erros.push(`refutados.length=${obj.refutados.length} ≠ erros_confirmados=${obj.erros_confirmados} — cada erro confirmado precisa de evidência`)
  }
  if (typeof obj.evidencia !== 'string' || !/^memory\/sessions\/\d{4}-\d{2}-\d{2}-refutacao-gt-g5-lote-\d+-r\d+\.md$/.test(obj.evidencia)) erros.push(`evidencia="${obj.evidencia}" fora do padrão memory/sessions/<data>-refutacao-gt-g5-lote-<pr>-r<N>.md`)
  if (obj.arquivo_existia === true) erros.push('a evidência desta rodada JÁ existia antes do refutador escrever — rodada colidiria com registro anterior; use args.resume')
  if (obj.abriu_evidencia_anterior === true) erros.push('refutador declarou ter aberto evidência de rodada anterior — sessão contaminada, rodada inválida (§6 anti-gaming)')
  const taxa = calcErrorRate(obj.itens_verificados, obj.erros_confirmados)
  const avisos = []
  if (taxa !== null && typeof obj.error_rate_pct === 'number' && Math.abs(obj.error_rate_pct - taxa) > 0.05) avisos.push(`agente declarou error_rate_pct=${obj.error_rate_pct}; a conta dá ${taxa} — vence a conta`)
  const v = erros.length ? null : vereditoDe(obj)
  if (v && obj.veredito && obj.veredito !== v.veredito) avisos.push(`agente declarou veredito="${obj.veredito}"; a máquina dá "${v.veredito}" (${v.motivo}) — vence a máquina`)
  return { ok: erros.length === 0, erros, avisos, taxa, veredito: v ? v.veredito : null, motivo: v ? v.motivo : null }
}

// Cada evidência termina num bloco ```json {"itens_verificados":..} — é o que o resume lê.
// Parseia o ÚLTIMO bloco json com itens_verificados; null se não achar (arquivo sem recibo).
function parseResultadoDeMarkdown(md) {
  if (typeof md !== 'string') return null
  const blocos = [...md.matchAll(/```json\s*([\s\S]*?)```/g)].map((m) => m[1].trim())
  for (let i = blocos.length - 1; i >= 0; i--) {
    try {
      const o = JSON.parse(blocos[i])
      if (o && Number.isInteger(o.itens_verificados)) return o
    } catch { /* bloco que não é o recibo */ }
  }
  return null
}

const rodadaDePath = (p) => { const m = String(p).match(/-r(\d+)\.md$/); return m ? Number.parseInt(m[1], 10) : null }
const proximaRodada = (anteriores) => anteriores.reduce((mx, r) => Math.max(mx, r.rodada), 0) + 1

// pt-BR: vírgula decimal. "3 rodadas: 13,48% (r1, 19/141) → 2,84% (r2, 4/141) → 1,35% (r3, 1/74)"
const pct = (n) => (n === null || n === undefined ? '—' : String(n.toFixed(2)).replace('.', ',') + '%')
function montarTrajetoria(rodadas) {
  const rs = [...rodadas].sort((a, b) => a.rodada - b.rodada)
  const passos = rs.map((r) => `${pct(calcErrorRate(r.itens_verificados, r.erros_confirmados))} (r${r.rodada}, ${r.erros_confirmados}/${r.itens_verificados})`)
  return `${rs.length} rodada${rs.length === 1 ? '' : 's'}: ${passos.join(' → ')}`
}

// Entry no formato da do PR #6897 (mesmas chaves, mesma ordem). `veredito` sempre aprovado
// aqui — reprovado NÃO chega a esta função (o workflow para antes; o registro do reprovado
// fica na evidência .md, e a entry reprovada é do humano se quiser, §6 "o ledger registra
// REPROVADOS também" — este workflow não grava entry que o ledger-check vai rejeitar).
function montarEntry({ pr, loteId, data, tipo, gerador, modeloRefutador, rodadas, marcadorAbsorcao, amostraPct }) {
  const final = [...rodadas].sort((a, b) => a.rodada - b.rodada).at(-1)
  const anteriores = rodadas.filter((r) => r.rodada !== final.rodada).sort((a, b) => a.rodada - b.rodada)
  const evidencia = anteriores.length
    ? `${final.evidencia} (rodada aprovada); anteriores: ${anteriores.map((r) => r.evidencia).join(', ')}`
    : `${final.evidencia} (rodada aprovada)`
  return {
    pr,
    lote_id: loteId,
    data,
    tipo,
    gerador,
    refutador: `${MODELO_ID[modeloRefutador]} (Anthropic) — subagente do workflow .claude/workflows/refutador-gt-g5.js em contexto próprio, zero contexto do gerador e das rodadas anteriores (instruído a não abrir as evidências prévias); uma instância NOVA por rodada, r1–r${final.rodada}`,
    sessao_fresca: true,
    amostra_pct: amostraPct,
    itens_verificados: final.itens_verificados,
    erros_confirmados: final.erros_confirmados,
    error_rate_pct: calcErrorRate(final.itens_verificados, final.erros_confirmados),
    pii_scan: true,
    pii_hits: 0,
    veredito: 'aprovado',
    evidencia,
    trajetoria: montarTrajetoria(rodadas),
    marcador_absorcao: marcadorAbsorcao,
  }
}

// Deep-equal simples (JSON canônico) — prova que o escrivão gravou a entry MONTADA, não uma transcrição.
function entryIgual(a, b) {
  const canon = (o) => JSON.stringify(o, Object.keys(o || {}).sort())
  return !!a && !!b && canon(a) === canon(b)
}

// ── SELFTEST (partes puras; zero agentes) ────────────────────────────────────
function selftest() {
  const falhas = []
  const ok = (cond, msg) => { if (!cond) falhas.push(msg) }

  // calcErrorRate — os números reais das 9 rodadas do #6897
  ok(calcErrorRate(74, 1) === 1.35, 'calcErrorRate 1/74 = 1.35')
  ok(calcErrorRate(141, 19) === 13.48, 'calcErrorRate 19/141 = 13.48')
  ok(calcErrorRate(141, 4) === 2.84, 'calcErrorRate 4/141 = 2.84')
  ok(calcErrorRate(62, 2) === 3.23, 'calcErrorRate 2/62 = 3.23')
  ok(calcErrorRate(0, 0) === null, 'calcErrorRate 0/0 = null (sem denominador)')
  ok(calcErrorRate(10, 11) === null, 'calcErrorRate erros > itens = null')
  ok(calcErrorRate(10.5, 1) === null, 'calcErrorRate itens fracionário = null')

  // ehLote — o threshold do §1 (mesmo do ledger-check)
  ok(ehLote(11) && !ehLote(10) && !ehLote(0), 'ehLote: >10 é lote; 10 não')

  // pathEvidencia
  ok(pathEvidencia('2026-09-06', 6897, 3) === 'memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897-r3.md', 'pathEvidencia formato -r<N>')

  // vereditoDe — a máquina decide
  const base = { itens_verificados: 74, erros_confirmados: 1, pii_hits: 0, pii_controles_positivos_ok: 7 }
  ok(vereditoDe(base).veredito === 'aprovado', 'veredito: 1/74 · pii 0 · 7/7 → aprovado')
  ok(vereditoDe({ ...base, erros_confirmados: 2 }).veredito === 'reprovado', 'veredito: 2/74 = 2.70% → reprovado')
  ok(vereditoDe({ ...base, itens_verificados: 100, erros_confirmados: 2 }).veredito === 'reprovado', 'veredito: exatamente 2.00% → reprovado (aceite é < 2)')
  ok(vereditoDe({ ...base, itens_verificados: 101, erros_confirmados: 2 }).veredito === 'aprovado', 'veredito: 1.98% → aprovado')
  ok(vereditoDe({ ...base, pii_hits: 1 }).veredito === 'reprovado', 'veredito: pii_hits=1 reprova mesmo com taxa 0')
  ok(vereditoDe({ ...base, erros_confirmados: 0, pii_controles_positivos_ok: 6 }).veredito === 'reprovado', 'veredito: controle positivo 6/7 = sonda não provada → reprovado')

  // parseRefutacao — forma + máquina vence o adjetivo
  const bom = { ...base, error_rate_pct: 1.35, veredito: 'aprovado', evidencia: 'memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897-r9.md',
    refutados: [{ arquivo: 'memory/requisitos/Compras/compras-gap.md', item: 'Drawer / Sheet — trilho FSM', evidencia: 'Drawer.tsx l.12-19/197-209 já tem fsm-track' }] }
  const p1 = parseRefutacao(bom)
  ok(p1.ok && p1.veredito === 'aprovado' && p1.taxa === 1.35 && p1.avisos.length === 0, 'parseRefutacao: recibo r9 real passa limpo')
  const p2 = parseRefutacao({ ...bom, veredito: 'reprovado', error_rate_pct: 9 })
  ok(p2.ok && p2.veredito === 'aprovado' && p2.avisos.length === 2, 'parseRefutacao: adjetivo/taxa do agente discordam → avisos, vence a máquina')
  ok(!parseRefutacao({ ...bom, refutados: [] }).ok, 'parseRefutacao: erros_confirmados=1 sem refutados[] → inválido (erro sem evidência)')
  ok(!parseRefutacao({ ...bom, refutados: [{ arquivo: 'x', item: 'y' }] }).ok, 'parseRefutacao: refutado sem evidencia → inválido')
  ok(!parseRefutacao({ ...bom, evidencia: 'memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897.md' }).ok, 'parseRefutacao: evidencia sem -r<N> → inválido')
  ok(!parseRefutacao({ ...bom, arquivo_existia: true }).ok, 'parseRefutacao: evidência já existia → inválido (pede resume)')
  ok(!parseRefutacao({ ...bom, abriu_evidencia_anterior: true }).ok, 'parseRefutacao: abriu rodada anterior → sessão contaminada')
  ok(!parseRefutacao({ ...bom, itens_verificados: 0, erros_confirmados: 0 }).ok, 'parseRefutacao: 0 itens → inválido')
  ok(!parseRefutacao(null).ok, 'parseRefutacao: agente nulo → inválido')

  // parseResultadoDeMarkdown — a cauda real da r9
  const md = '## Veredito\n\n74 itens · 1 erro\n\n```json\n{"itens_verificados": 74, "erros_confirmados": 1, "error_rate_pct": 1.35, "pii_hits": 0, "veredito": "aprovado"}\n```\n'
  const r = parseResultadoDeMarkdown(md)
  ok(r && r.itens_verificados === 74 && r.erros_confirmados === 1, 'parseResultadoDeMarkdown: lê o bloco json final')
  ok(parseResultadoDeMarkdown('```json\n{"x":1}\n```\n```json\n{"itens_verificados": 5, "erros_confirmados": 0}\n```\n```json\n{"foo":"bar"}\n```') .itens_verificados === 5, 'parseResultadoDeMarkdown: pula blocos que não são o recibo')
  ok(parseResultadoDeMarkdown('sem bloco nenhum') === null && parseResultadoDeMarkdown(null) === null, 'parseResultadoDeMarkdown: sem recibo → null')

  // rodadaDePath / proximaRodada
  ok(rodadaDePath('memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897-r7.md') === 7, 'rodadaDePath -r7')
  ok(rodadaDePath('memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897.md') === null, 'rodadaDePath sem sufixo = null')
  ok(proximaRodada([]) === 1 && proximaRodada([{ rodada: 1 }, { rodada: 3 }]) === 4, 'proximaRodada: max+1, vazio → 1')

  // montarTrajetoria — pt-BR, ordenada por rodada
  const rodadas = [
    { rodada: 2, itens_verificados: 141, erros_confirmados: 4, evidencia: 'memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897-r2.md' },
    { rodada: 1, itens_verificados: 141, erros_confirmados: 19, evidencia: 'memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897-r1.md' },
    { rodada: 3, itens_verificados: 74, erros_confirmados: 1, evidencia: 'memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897-r3.md' },
  ]
  ok(montarTrajetoria(rodadas) === '3 rodadas: 13,48% (r1, 19/141) → 2,84% (r2, 4/141) → 1,35% (r3, 1/74)', 'montarTrajetoria: ordem + vírgula decimal')
  ok(montarTrajetoria([rodadas[2]]) === '1 rodada: 1,35% (r3, 1/74)', 'montarTrajetoria: singular')

  // montarEntry — formato da entry do PR #6897, chaves na mesma ordem
  const e = montarEntry({ pr: 6897, loteId: 'gt-g5-lote-6897', data: '2026-09-06', tipo: 'anchors', gerador: 'claude-fable-5-1 (Anthropic) — sessão que autorou o lote', modeloRefutador: 'fable', rodadas, marcadorAbsorcao: 'n/a', amostraPct: 100 })
  ok(Object.keys(e).join(',') === 'pr,lote_id,data,tipo,gerador,refutador,sessao_fresca,amostra_pct,itens_verificados,erros_confirmados,error_rate_pct,pii_scan,pii_hits,veredito,evidencia,trajetoria,marcador_absorcao', 'montarEntry: chaves na ordem da entry 6897')
  ok(e.pr === 6897 && e.itens_verificados === 74 && e.erros_confirmados === 1 && e.error_rate_pct === 1.35 && e.veredito === 'aprovado' && e.sessao_fresca === true && e.pii_scan === true && e.pii_hits === 0 && e.amostra_pct === 100, 'montarEntry: números da rodada FINAL')
  ok(/^claude-fable-5-1 \(Anthropic\)/.test(e.refutador) && /r1–r3$/.test(e.refutador), 'montarEntry: refutador com modelo real + faixa de rodadas')
  ok(e.evidencia.startsWith('memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897-r3.md (rodada aprovada); anteriores: ') && e.evidencia.includes('-r1.md, memory') && e.evidencia.includes('-r2.md'), 'montarEntry: evidencia = final + anteriores')
  ok(e.trajetoria.startsWith('3 rodadas: 13,48%'), 'montarEntry: trajetoria com todas as rodadas')
  ok(!/R\$\s?\d/.test(JSON.stringify(e)), 'montarEntry: sem valor em reais')
  // regras do ledger-check que a entry montada PRECISA satisfazer (espelho de validateEntry — a
  // prova real é o ledger-check rodado pelo escrivão; isto só barra o óbvio antes de gastar agente)
  ok(/fable|opus|sonnet|haiku|mythos/.test(e.refutador) && /fable|opus|sonnet|haiku|mythos/.test(e.gerador), 'montarEntry: gerador/refutador com modelo reconhecível')
  ok(entryIgual(e, JSON.parse(JSON.stringify(e))) && !entryIgual(e, { ...e, erros_confirmados: 2 }), 'entryIgual: igual ↔ diferente')

  // normalizarArgs
  ok(!normalizarArgs({}).ok, 'normalizarArgs: sem pr → inválido')
  ok(!normalizarArgs({ pr: 1, tipo: 'juiz' }).ok, 'normalizarArgs: tipo juiz não é refutação de lote')
  const n = normalizarArgs({ pr: '6897', resume: 'true' })
  ok(n.ok && n.pr === 6897 && n.tipo === 'anchors' && n.maxRodadas === 5 && n.resume === true && n.modeloRefutador === 'fable' && n.loteId === 'gt-g5-lote-6897' && n.base === 'origin/main', 'normalizarArgs: defaults')

  return { ok: falhas.length === 0, total: 44, falhas }
}

// ── PROMPTS ──────────────────────────────────────────────────────────────────
const promptEscopo = (n, arquivosEvid) => `Você é um agente MECÂNICO do workflow refutador-gt-g5 (só mede, não julga). Working dir = raiz do repo oimpresso. Rode na ordem e devolva o JSON no schema.

1. \`git fetch origin main --quiet\`; \`git rev-parse --is-shallow-repository\`; \`git rev-parse HEAD\`; \`git rev-parse ${n.base}\`; \`git merge-base ${n.base} HEAD\`.
2. \`git diff --name-status ${n.base}...HEAD -- memory/requisitos\` → lista COMPLETA (sem head, sem paginação) de {status, path}. Também \`git diff --name-only ${n.base}...HEAD\` fora de memory/requisitos → só a contagem + até 30 paths.
3. \`date +%F\` → data de hoje (YYYY-MM-DD).
4. ${arquivosEvid ? `RESUME: liste \`memory/sessions/*-refutacao-gt-g5-lote-${n.pr}-r*.md\` (git ls-files + ls). Para CADA arquivo devolva {arquivo, rodada (o N do sufixo -rN), cauda: ÚLTIMAS 40 LINHAS literais (\`tail -n 40\`)}. NÃO interprete o conteúdo, NÃO resuma — a cauda crua é o que o workflow parseia.` : `Confira se JÁ existe alguma evidência \`memory/sessions/*-refutacao-gt-g5-lote-${n.pr}-r*.md\` (ls). Se existir, devolva a lista em evidencias_existentes com rodada e cauda (tail -n 40) — o workflow vai PARAR e pedir resume.`}
5. Não edite nada. Não commite. Não abra o conteúdo dos arquivos do lote.`

const ESCOPO_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['data', 'head_sha', 'base_sha', 'merge_base', 'raso', 'arquivos', 'fora_requisitos_count', 'evidencias_existentes'],
  properties: {
    data: { type: 'string' }, head_sha: { type: 'string' }, base_sha: { type: 'string' }, merge_base: { type: 'string' }, raso: { type: 'boolean' },
    arquivos: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['status', 'path'], properties: { status: { type: 'string' }, path: { type: 'string' } } } },
    fora_requisitos_count: { type: 'integer' },
    fora_requisitos_paths: { type: 'array', items: { type: 'string' } },
    evidencias_existentes: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['arquivo', 'rodada', 'cauda'], properties: { arquivo: { type: 'string' }, rodada: { type: 'integer' }, cauda: { type: 'string' } } } },
  },
}

// O prompt adversarial canônico — estrutura dos itens 1–6 reproduzida das 9 rodadas do PR
// #6897 (2026-09-06). O que cada rodada DERRUBOU é o que o refutador precisa checar:
//   r1 Ação × veredito da prosa (19 "Decidir." sobre decisão fechada) · r2 âncora de fundação
//   ancorada no consumidor errado + frontmatter não lido pelo leitor real · r4 banner de
//   invalidade do próprio arquivo reaberto · r5 truncagem que perde item enumerado / Tier 0
//   / _pendente_ · r6 linha citada errada + "vivo não expõe X" com X exposto · r7 "vivo tem
//   2 tabs" (eram 5) · r9 "resta trilho FSM" (já existia, Drawer.tsx l.12-19/197-209).
const promptRefutador = (n, esc, rodada, evidencia) => `Você é o REFUTADOR GT-G5 da rodada r${rodada} do lote do PR #${n.pr} (branch atual, HEAD ${esc.head_sha.slice(0, 10)}, base ${n.base} = ${esc.base_sha.slice(0, 10)}). Working dir = raiz do repo oimpresso. Protocolo: memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md §2–§4 (leia o §3 checklist e o §6 anti-gaming; NÃO leia o §7).

SEU MANDATO: PROVE QUE ESTE LOTE ESTÁ ERRADO. Default = refutado quando a evidência não fecha. Você é uma instância nova, sem contexto do gerador nem das rodadas anteriores — e tem que continuar assim:
- PROIBIDO abrir qualquer \`memory/sessions/*refutacao*\` (inclusive desta rodada de outros PRs) e qualquer \`memory/handoffs/\` de hoje. Se abrir, declare \`abriu_evidencia_anterior: true\` — a rodada é descartada.
- PROIBIDO ler o corpo do PR / commit message como evidência: o texto do PR é a CLAIM, não a prova.
- Tudo se mede contra \`${n.base}\` (\`git ls-tree ${n.base} -- <path>\`, \`git show ${n.base}:<path>\`, \`git grep <pat> ${n.base} -- <dir>\`), nunca contra o diff. Em Git Bash, \`<ref>:<path>\` com path começando por ponto mente — use \`git ls-tree\` pra existência (§5 2026-08-23). Repo raso = ${esc.raso} (se true, datas de git log não valem como recibo — use a API do GitHub).
- Claim negativa ("não existe", "vivo não expõe") exige varredura CONTADA no repo inteiro com \`rg --hidden -g '!.git/**'\` ou \`git grep\` (diga "N de N") — hit que volta como comentário não é evidência de ausência.
- Sonda que devolve vazio só vale com exit code lido E controle positivo (um padrão que você SABE que casa, mesmas flags).

O LOTE (${esc.arquivos.length} arquivos em memory/requisitos, medido por \`git diff --name-status ${n.base}...HEAD -- memory/requisitos\`; re-meça você mesmo):
${esc.arquivos.map((a) => `  ${a.status}\t${a.path}`).join('\n')}
Tipo: ${n.tipo}. ${n.tipo === 'anchors'
  ? 'Amostra = 100% dos itens (todo path, toda chave de frontmatter, toda linha de tabela derivada, todo `Implementado em`/US-id).'
  : `Amostra ≥ 30% dos arquivos, seleção DETERMINÍSTICA com seed declarada na evidência: seed = "${esc.head_sha.slice(0, 8)}"; ordene os arquivos por sha1(seed + path) e pegue os primeiros ceil(30%). Dentro de cada arquivo escolhido, 100% das afirmações.`}

O QUE VERIFICAR — para CADA item: CONFIRMADO ou REFUTADO + evidência (path + linha/commit + porquê). Conte os itens por grupo e declare o número:
1. ÂNCORA EXISTE EM ${n.base}: todo path citado pelo lote (map.json \`prototipo.arquivo\`/\`vivo.arquivo\`, frontmatter \`tela_viva\`/\`prototipo\`, \`**Implementado em:**\`, US-ids no SPEC, links relativos) — \`git ls-tree ${n.base} -- <path>\` devolve blob; controle negativo com um path inexistente. Path sob \`resources/js/Pages/**\` vs \`Components/**\`: âncora de fundação apontada pra consumidor de tela é REFUTADA.
2. ÂNCORA NÃO REVOGADA E LIDA PELO LEITOR REAL: o charter dono da tela não marca o protótipo como REVOGADA/MIS-ANCHOR (\`node prototipo-ui/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork\` resolve \`âncora ✓\`); o frontmatter é lido pelo consumidor de verdade (\`fmVal\` em prototipo-ui/gerar-contrato.mjs, \`gerar-map.mjs\`, o schema do gate) — regenerar o derivado a partir da fonte e comparar chave a chave.
3. AÇÃO × VEREDITO DA PROSA, E AFIRMAÇÃO SOBRE CÓDIGO: a célula/linha derivada é REFUTADA se INVERTE, INVENTA, OMITE, TRUNCA item relevante (item enumerado como gap real, restrição Tier 0 / regra mestre, \`_pendente_\` que condiciona a existência do gap), REABRE um veredito (inclusive banner de invalidade do próprio arquivo, decisão [W] registrada, "NÃO fazer", "já é canon", "— · —"), CITA LINHA ERRADA (a linha citada tem que conter o que se afirma — abra e confira), ou AFIRMA ALGO SOBRE O CÓDIGO QUE O CÓDIGO EM ${n.base} CONTRADIZ (abra o componente/controller e conte — "vivo tem 2 tabs" com 5 declaradas é erro; comentário de cabeçalho não é o código). "Decidir." só vale nos termos da prosa. Em conflito, a prosa vence — mas a prosa também pode estar stale contra canon mais novo (ADR/charter em ${n.base}): aí o canon vence e é erro do lote carimbar a data de hoje.
4. CÉLULA ÍNTEGRA: pipe não escapado cortando célula, code-span truncado, conteúdo do MOCKUP rotulado como "vivo", reticência que engole o veredito; \`acao\` do map.json == célula da tabela (regenere com o gerador do repo e faça diff chave a chave).
5. MÁQUINA DERIVADA: rode os \`--check\` que o lote afirma satisfazer (ex.: \`node scripts/governance/requisitos-status.mjs <Mod> --check\`, \`plans-index.mjs --check\`, \`design-code-map-check.mjs --check --strict\`, \`doc-id-index.mjs --check-collisions\`) e cole o rc literal de cada um; arquivo que o PR diz ter regenerado mas não está no diff é achado.
6. SCAN PII COM CONTROLE POSITIVO: sobre as linhas \`+\` de \`git diff ${n.base}...HEAD -- memory/requisitos\`, ${PII_PADROES} padrões — CPF pontuado, CPF cru (11 dígitos isolados), CNPJ, telefone BR, telefone cru (10–11 dígitos), e-mail, valor em reais (o símbolo seguido de dígito). Cada padrão rodado TAMBÉM contra uma linha sintética que casa (controle positivo) — devolva quantos dos ${PII_PADROES} controles casaram. Nomes de cliente do CRM contam como hit; persona interna/mocks já presentes em ${n.base} não. NUNCA reproduza na evidência o literal do padrão de reais nem um valor em reais (hook block-brl-values-in-memory barra o arquivo) — descreva.

Deixe a árvore LIMPA depois de qualquer sondagem que escreva (\`git status --short\` vazio exceto a evidência). Não edite o lote. Não commite. Não escreva no ledger.

EVIDÊNCIA — escreva EXATAMENTE em \`${evidencia}\` (antes de escrever: se o arquivo JÁ EXISTIR, não sobrescreva — devolva \`arquivo_existia: true\`). Frontmatter YAML no schema scripts/memory-schemas/session.schema.json: \`date: "${esc.data}"\`, \`topic:\` (≤250 chars), \`authors: ["C"]\`, \`prs: [${n.pr}]\`, \`outcomes:\` (3 bullets). Corpo em PT-BR: cabeçalho com base/HEAD/raso/sessão fresca; §3 checklist marcado; escopo medido; tabela por grupo (Itens · Confirmados · Refutados · como mediu); seção REFUTADOS com 1 subseção por erro (arquivo · item · afirmação · o que ${n.base} diz · linha/commit · porquê é erro do lote); observações não contadas; scan PII (tabela padrão × hits × controle); comandos reproduzíveis; e TERMINE com um bloco \`\`\`json contendo {"itens_verificados","erros_confirmados","error_rate_pct","pii_hits","veredito"}. Sem valor em reais em lugar nenhum.

DEVOLVA o JSON no schema (é dado, não mensagem): itens_verificados (soma dos grupos), erros_confirmados (= refutados.length), error_rate_pct, pii_hits, pii_controles_positivos_ok (0–${PII_PADROES}), veredito (aprovado se error_rate < 2 e pii_hits = 0; o workflow recalcula), refutados[{arquivo, item, evidencia}], evidencia (o path acima), itens_por_grupo, arquivo_existia, abriu_evidencia_anterior, observacoes.`

const REFUTACAO_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['itens_verificados', 'erros_confirmados', 'error_rate_pct', 'pii_hits', 'pii_controles_positivos_ok', 'veredito', 'refutados', 'evidencia', 'arquivo_existia', 'abriu_evidencia_anterior'],
  properties: {
    itens_verificados: { type: 'integer' }, erros_confirmados: { type: 'integer' }, error_rate_pct: { type: 'number' },
    pii_hits: { type: 'integer' }, pii_controles_positivos_ok: { type: 'integer' },
    veredito: { type: 'string', enum: ['aprovado', 'reprovado'] },
    refutados: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['arquivo', 'item', 'evidencia'], properties: { arquivo: { type: 'string' }, item: { type: 'string' }, evidencia: { type: 'string' } } } },
    evidencia: { type: 'string' },
    itens_por_grupo: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['grupo', 'itens', 'refutados'], properties: { grupo: { type: 'string' }, itens: { type: 'integer' }, refutados: { type: 'integer' } } } },
    arquivo_existia: { type: 'boolean' }, abriu_evidencia_anterior: { type: 'boolean' },
    observacoes: { type: 'array', items: { type: 'string' } },
  },
}

const promptEscrivao = (n, entry) => `Você é um agente MECÂNICO (escrivão) do workflow refutador-gt-g5. Working dir = raiz do repo oimpresso. NÃO julgue, NÃO reescreva, NÃO "melhore" texto nenhum.

1. Salve o JSON abaixo, BYTE A BYTE, num arquivo temporário fora do repo (heredoc com aspas simples no delimitador) — não digite de novo, copie:
\`\`\`json
${JSON.stringify(entry, null, 2)}
\`\`\`
2. Faça o APPEND no ledger com node (append-only — nunca edite entry existente, nunca reordene):
   node -e "const fs=require('fs');const L='governance/sdd-verification-ledger.json';const d=JSON.parse(fs.readFileSync(L,'utf8'));const e=JSON.parse(fs.readFileSync(process.argv[1],'utf8'));d.entries.push(e);fs.writeFileSync(L,JSON.stringify(d,null,2)+'\\n');console.log('entries='+d.entries.length)" <caminho-do-temporário>
3. Prove a gravação: node -e "const d=JSON.parse(require('fs').readFileSync('governance/sdd-verification-ledger.json','utf8'));console.log(JSON.stringify(d.entries.at(-1)))" → devolva ESSA saída literal em ultima_entry_json.
4. Rode: node scripts/governance/ledger-check.mjs --pr ${n.pr} --base ${n.base} --head HEAD --enforce ; echo rc=$? → devolva a saída literal (stdout+stderr) em ledger_check_saida e o rc em ledger_check_rc.
5. \`git diff --stat -- governance/sdd-verification-ledger.json\` → devolva em diff_stat. Não commite.`

const ESCRIVAO_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['gravou', 'ultima_entry_json', 'ledger_check_saida', 'ledger_check_rc', 'diff_stat'],
  properties: { gravou: { type: 'boolean' }, ultima_entry_json: { type: 'string' }, ledger_check_saida: { type: 'string' }, ledger_check_rc: { type: 'integer' }, diff_stat: { type: 'string' } },
}

// ── FLUXO ────────────────────────────────────────────────────────────────────
async function fluxo() {
  const n = normalizarArgs(A)
  if (n.selftest) {
    const r = selftest()
    log(r.ok ? `selftest: ${r.total} asserções verdes (partes puras, zero agentes)` : `selftest: ${r.falhas.length} FALHA(S) — ${r.falhas.join(' · ')}`)
    return { selftest: r }
  }
  if (!n.ok) { log(`args inválidos: ${n.erros.join(' · ')}`); return { ok: false, erros: n.erros } }

  phase('Escopo')
  const esc = await agent(promptEscopo(n, n.resume), { label: `escopo:pr${n.pr}`, phase: 'Escopo', schema: ESCOPO_SCHEMA, model: 'sonnet', effort: 'low', agentType: 'general-purpose' })
  if (!esc) { log('agente de escopo não devolveu nada — abortando sem gastar refutador'); return { ok: false, erros: ['escopo nulo'] } }
  log(`lote PR #${n.pr}: ${esc.arquivos.length} arquivo(s) em memory/requisitos · HEAD ${esc.head_sha.slice(0, 10)} · ${n.base} ${esc.base_sha.slice(0, 10)} · raso=${esc.raso} · data ${esc.data}`)
  if (esc.fora_requisitos_count) log(`fora de memory/requisitos: ${esc.fora_requisitos_count} arquivo(s) (o gate só conta memory/requisitos)`)
  if (!ehLote(esc.arquivos.length) && !n.forcar) {
    log(`não é PR-de-lote (${esc.arquivos.length} ≤ ${THRESHOLD_LOTE}) — o ledger-check nem dispara; o loop (~330–400k tokens/rodada) não compensa. args.forcar=true pra rodar mesmo assim.`)
    return { ok: true, lote: false, arquivos: esc.arquivos.length, threshold: THRESHOLD_LOTE }
  }

  // Rodadas anteriores: derivadas das evidências gravadas (parse AQUI da cauda crua), nunca de memória.
  const anteriores = []
  const semRecibo = []
  for (const ev of esc.evidencias_existentes || []) {
    const rod = ev.rodada || rodadaDePath(ev.arquivo)
    const res = parseResultadoDeMarkdown(ev.cauda)
    if (!rod || !res) { semRecibo.push(ev.arquivo); continue }
    anteriores.push({ rodada: rod, itens_verificados: res.itens_verificados, erros_confirmados: res.erros_confirmados, evidencia: ev.arquivo })
  }
  if (semRecibo.length) log(`⚠️ evidência(s) sem bloco json final (não entram na trajetória): ${semRecibo.join(', ')}`)
  if (anteriores.length && !n.resume) {
    log(`já existem ${anteriores.length} rodada(s) gravada(s) pro PR #${n.pr} (${anteriores.map((r) => 'r' + r.rodada).join(', ')}) — re-invoque com args.resume=true pra rodar a próxima sem colidir`)
    return { ok: false, erros: ['rodadas anteriores existem; use resume'], anteriores }
  }
  const rodada = proximaRodada(anteriores)
  if (rodada > n.maxRodadas) {
    log(`teto de rodadas atingido (r${rodada} > maxRodadas=${n.maxRodadas}) — PARA. ${anteriores.length} rodada(s) reprovada(s): ${montarTrajetoria(anteriores)}. Decisão humana: subir o teto, reescrever o lote, ou desistir.`)
    return { ok: false, veredito: 'teto', rodada, maxRodadas: n.maxRodadas, anteriores, trajetoria: montarTrajetoria(anteriores) }
  }
  const evidencia = pathEvidencia(esc.data, n.pr, rodada)

  phase('Refutar')
  log(`rodada r${rodada}/${n.maxRodadas} — refutador ${MODELO_ID[n.modeloRefutador]} (instância nova, sessão fresca) → ${evidencia}`)
  const bruto = await agent(promptRefutador(n, esc, rodada, evidencia), { label: `refutador:r${rodada}`, phase: 'Refutar', schema: REFUTACAO_SCHEMA, model: n.modeloRefutador, effort: n.esforco, agentType: 'general-purpose' })
  const p = parseRefutacao(bruto)
  for (const a of p.avisos || []) log(`⚠️ ${a}`)
  if (!p.ok) {
    log(`rodada r${rodada} INVÁLIDA (não conta): ${p.erros.join(' · ')}`)
    return { ok: false, rodada, invalida: p.erros, bruto }
  }
  const atual = { rodada, itens_verificados: bruto.itens_verificados, erros_confirmados: bruto.erros_confirmados, evidencia: bruto.evidencia }
  const rodadas = [...anteriores, atual]
  log(`r${rodada}: ${bruto.itens_verificados} itens · ${bruto.erros_confirmados} erro(s) · ${pct(p.taxa)} · pii ${bruto.pii_hits} (controles ${bruto.pii_controles_positivos_ok}/${PII_PADROES}) → ${p.veredito.toUpperCase()} (${p.motivo})`)

  if (p.veredito !== 'aprovado') {
    log(`REPROVADO. ${bruto.refutados.length} refutado(s) devolvido(s) ao chamador. O conserto é do gerador/humano (§2.6: o lote inteiro volta) — este workflow NÃO edita o lote. Depois de consertar, re-invoque com args.resume=true (próxima = r${rodada + 1}${rodada + 1 > n.maxRodadas ? ' — acima do teto, ajuste maxRodadas' : ''}).`)
    return {
      ok: true, veredito: 'reprovado', rodada, motivo: p.motivo, error_rate_pct: p.taxa,
      itens_verificados: bruto.itens_verificados, erros_confirmados: bruto.erros_confirmados, pii_hits: bruto.pii_hits,
      refutados: bruto.refutados, evidencia: bruto.evidencia, trajetoria: montarTrajetoria(rodadas), observacoes: bruto.observacoes || [],
      proximo_passo: `consertar os ${bruto.refutados.length} item(ns) no lote (gerador/humano) e reinvocar com resume=true`,
    }
  }

  // APROVADO → entry + ledger-check. Sem gerador declarado não há entry honesta (o campo não é terra de ninguém — §4.1).
  if (!n.gerador) {
    log('APROVADO, mas args.gerador não informado — a entry precisa do modelo REAL do gerador (§4). Re-invoque com resume=true e args.gerador; a rodada aprovada já está gravada na evidência e entra na trajetória.')
    return { ok: false, veredito: 'aprovado', rodada, evidencia: bruto.evidencia, trajetoria: montarTrajetoria(rodadas), erros: ['args.gerador ausente'] }
  }
  phase('Registrar')
  const entry = montarEntry({ pr: n.pr, loteId: n.loteId, data: esc.data, tipo: n.tipo, gerador: n.gerador, modeloRefutador: n.modeloRefutador, rodadas, marcadorAbsorcao: n.marcadorAbsorcao, amostraPct: n.tipo === 'anchors' ? 100 : 30 })
  const esc2 = await agent(promptEscrivao(n, entry), { label: `escrivao:pr${n.pr}`, phase: 'Registrar', schema: ESCRIVAO_SCHEMA, model: 'sonnet', effort: 'low', agentType: 'general-purpose' })
  if (!esc2 || !esc2.gravou) { log('escrivão não confirmou a gravação — entry NÃO registrada; grave à mão a entry abaixo e rode o ledger-check'); return { ok: false, veredito: 'aprovado', rodada, entry, escrivao: esc2 } }
  let gravada = null
  try { gravada = JSON.parse(esc2.ultima_entry_json) } catch { /* fica null */ }
  const integra = entryIgual(entry, gravada)
  if (!integra) log('⚠️ a última entry do ledger NÃO é idêntica à montada — transcrição/edição no caminho; confira o diff antes de commitar')
  log(`ledger-check --enforce rc=${esc2.ledger_check_rc}: ${esc2.ledger_check_saida.split('\n')[0]}`)
  return {
    ok: integra && esc2.ledger_check_rc === 0, veredito: 'aprovado', rodada, entry, entry_gravada_identica: integra,
    ledger_check: { rc: esc2.ledger_check_rc, saida: esc2.ledger_check_saida }, diff_stat: esc2.diff_stat,
    trajetoria: entry.trajetoria, evidencia: bruto.evidencia, observacoes: bruto.observacoes || [],
    proximo_passo: 'commitar evidência + ledger no MESMO PR do lote (§2.7); ledger-hash-chain --build sela depois (operador)',
  }
}

return await fluxo()
