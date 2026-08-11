export const meta = {
  name: 'reguas-do-sistema',
  description: 'Grade de réguas do IA OS vs acima-do-mercado: pesquisa por dimensão → refutação adversarial → verificação no repo vivo → grade com evidência + chips sugeridos',
  whenToUse: 'Skill reguas-do-sistema (cadência trimestral, pós-conclusão de leva de chips, ou Wagner pedir "grade de réguas"/"onde sou fraco"/"acima do mercado")',
  phases: [
    { title: 'Dossiê', detail: 'monta o retrato do sistema a partir do mapa vivo (ADR 0330-corrente) + fraquezas conhecidas' },
    { title: 'Pesquisar', detail: '1 pesquisador web por dimensão: líderes 2026 + a prática exata + fontes' },
    { title: 'Refutar', detail: 'cético derruba toda claim "estamos acima" (default: derrubar)' },
    { title: 'Integração', detail: 'o peer refutado cobre a PEÇA ou monta o TODO no mesmo contexto? (anti-falácia-de-composição)' },
    { title: 'Verificar', detail: 'toda fraqueza é caçada no REPO VIVO antes da nota — a lição 7/9' },
    { title: 'Grade', detail: 'notas com evidência + próximo degrau + chips sugeridos + rejeitados→§5' },
    { title: 'Delta-scan', detail: 'modo delta: ledger + git log por dimensão → só o que mudou re-mede (ADR proposta reguas-loop)' },
    { title: 'Persistir', detail: 'ledger memory/reguas/ gravado por CHECKPOINT (claims após Integração · retrato+fraquezas após a composição, antes da prosa); esta fase só RETENTA o que não confirmou' },
  ],
}
// RETOMÁVEL (2026-07-26): a rodada não é mais uma transação única de 88 agentes com a única
// escrita no fim. Duas alavancas, independentes:
//  1. PERSISTÊNCIA INCREMENTAL — o ledger recebe o que já fechou, quando fecha (ver bloco
//     "PERSISTÊNCIA INCREMENTAL" abaixo). Morreu no meio? o que fechou está no disco, e o
//     retrato declara `cobertura` (parcial nunca se passa por completo).
//  2. RODADA MENOR — `args.eixo: 'rodar-e-observar'` (ou `args.dimensoes: [...]`, que já
//     existia) roda o full em 3 transações de 4-7 dimensões em vez de 1 de 12.

// ── Config (args sobrescrevem) ────────────────────────────────────────────────
// args: { base?: 'D:/caminho/worktree-fresco-do-main', dimensoes?: [{key,escopo}] }
// ROBUSTEZ (2026-07-10): a fronteira do tool Workflow serializa `args` pra STRING (visto 2×
// — `args.base` chegava undefined → BASE caía no placeholder e os agentes tinham que se
// auto-curar lendo origin/main na mão). Então parse defensivo: aceita objeto OU string JSON.
const A = typeof args === 'string' ? (() => { try { return JSON.parse(args) } catch { return {} } })() : (args || {})
const BASE = (A && A.base) || 'AJUSTE: passe args.base = worktree FRESCO do origin/main'
// MODO (ADR proposta reguas-loop-maquina-evolucao — Órgão 2): 'full' = comportamento original;
// 'delta' = rodada incremental barata dirigida pelo ledger memory/reguas/ (alvo ≤2,5M tokens).
const MODO = (A && A.modo) === 'delta' ? 'delta' : 'full'
const LEDGER_DIR = (A && A.ledger) || 'memory/reguas'
// ── ESTRATÉGIA DE MODELO (Wagner 2026-07-19: "estratégia de funcionar com opus 4.8") ──
// Os agentes de JULGAMENTO (pesquisa/refutação/verificação/integração/prosa/grade) INHERIT o
// modelo da sessão — quando a sessão é Opus 4.8 (ou o Zelador roda nele), o raciocínio pesado
// roda em Opus, que é onde a qualidade importa. Os agentes MECÂNICOS (delta-scan = git log +
// aritmética de data + ler JSON; persistir = escrever JSON validado) são FIXADOS num tier mais
// barato — são estruturados/determinísticos, Opus ali é desperdício. Assim o delta fica barato
// MESMO sob sessão Opus 4.8. Override: args.modelo_mecanico. A grade-final caiu de 'max'→'high'
// porque a composição agora é DETERMINÍSTICA (regra 16): o agente só escreve prosa, não decide nota.
const MODELO_MECANICO = (A && A.modelo_mecanico) || 'sonnet'
const DIMS_DEFAULT = [
  { key: 'spec-governanca', eixo: 'construir-e-governar', escopo: 'spec-driven development + governança de agentes (Spec Kit, Kiro, Codex, Cursor, Tessl; gates/required; ratchets)' },
  { key: 'design-to-code', eixo: 'construir-e-governar', escopo: 'design→code fidelity (Figma Code Connect/Dev Mode, v0, Builder.io; VRT: Chromatic/Applitools/Percy; tokens DTCG/Style Dictionary)' },
  { key: 'memoria-conhecimento', eixo: 'construir-e-governar', escopo: 'memória de agente + sobrevivência de conhecimento (Letta, mem0, Zep, LangMem; docs-as-code freshness: Swimm, Dosu; ADR tooling)' },
  // Adicionada 2026-07-21 (pedido [W]: "a régua do sistema deve conter essa nova estrutura"): o catálogo-por-módulo + parecer-de-código-ancorado. DISTINTO de memoria-conhecimento (freshness doc↔código): aqui a régua é a ESTRUTURA (descritor+superfície derivada+opinião-com-rubrica por contexto).
  { key: 'catalogo-modulo-opiniao-codigo', eixo: 'construir-e-governar', escopo: 'catálogo de conhecimento POR MÓDULO (descritor schema + superfície de arquivos DERIVADA + índice/resumo que aponta) + PARECER de código ancorado a RUBRICA externa (concordo/não por função, anti-sicofância, citação extrativa — nunca opinião livre). Referência de mercado = IDP/software-catalog + scorecards (Backstage catalog-info+Soundcheck, Cortex/OpsLevel/Port scorecard-as-code) + code-health opinionado contra rubrica validada (CodeScene Code Red, fitness functions). Régua = cada CONTEXTO tem (1) descritor de fronteira, (2) superfície de arquivos derivada-não-escrita, (3) resumo que aponta (não recopia), (4) opinião por função COMPILADA em critérios+evidência (não vibe). Estado (2026-07-21): SCOPE.md (fronteira, scope-guard enforçado) + SUPERFICIE.md (derivada, module-surface.mjs, Classe A+B) + BRIEFING (resumo) + funcao-scorecard (8 critérios ancorados ADR 0093/proibicoes/§5; bite-test 3/3 famílias de defeito achadas + T1 96,9% + 0 over-flag no controle). Fonte: proposals/2026-07-21-taxonomia-arquivos-modulo.md + memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md. Medir o Δ conforme espalha por módulo.' },
  { key: 'orquestracao-adversarial', eixo: 'construir-e-governar', escopo: 'multi-agente + verificação adversarial (Anthropic orchestrator-worker, Devin/Cognition, Jules, Amp, Agent HQ)' },
  { key: 'evals-outcome', eixo: 'construir-e-governar', escopo: 'evals e medição de outcome de agentes (Braintrust, LangSmith, DORA/DX for AI; goal-based evals; scorecards de processo)' },
  { key: 'erp-ia-produto', eixo: 'construir-e-governar', escopo: 'IA embarcada em ERP — o PRODUTO (SAP Joule, Dynamics Copilot, Odoo AI; BR: Bling/Tiny/Omie/Conta Azul)' },
  // ── Eixo RODAR-E-OBSERVAR (a IA que o sistema produz, viva em prod — não o loop de construir/governar). ──
  // Adicionadas 2026-07-10: as 9 réguas v1→v3 só mediam CONSTRUIR-E-GOVERNAR; este eixo era ponto cego. Ver ADR 0333 (emenda à 0330).
  { key: 'observabilidade-agente', eixo: 'rodar-e-observar', escopo: 'traces/custo/latência/alucinação do agente e da IA em produção (Langfuse, LangSmith, Braintrust, OpenTelemetry GenAI semantic conventions; spans + custo por run). Régua = painel vivo + heartbeat que prova o FLUXO (não só a via), não log solto. Estado (2026-07): Langfuse LIVE desde 2026-07-02 + emissão OTel GenAI (LaravelAiSdkDriver::emitirOtelGenAi); heartbeat langfuse_trace_uptime_24h no HealthCheckCommand shipado 2026-07-17 (#4425, lê a fonte real da API /api/public/traces) + fix da prova multi-tenant do OTel biz=1 (#4427) + advisory desligado-prod (#4444). Baseline da dimensão na grade v2 (2026-07-17) = 6,5/10 — medir o Δ pós-chips' },
  { key: 'qualidade-drift-ia-producao', eixo: 'rodar-e-observar', escopo: 'qualidade + drift da IA-PRODUTO (a Jana) em prod — recall/hallucination gold-set + canary de drift (RAGAS, DeepEval, continuous-eval). DISTINTO de evals-outcome (que é DORA/outcome do agente-DEV): aqui a régua é a resposta da Jana ao cliente. Projeto tem jana-ragas-gate JÁ + #3 P0 drift-sentinel pendente' },
  { key: 'seguranca-do-agente', eixo: 'rodar-e-observar', escopo: 'defesa a prompt-injection + fronteira instrução-vs-dado + modelo de permissão de tools/hooks (OWASP LLM Top 10, Anthropic agent-safety, Google SAIF). Régua = superfície de tool/hook auditada + injection tratada, não confiança implícita' },
  { key: 'custo-eficiencia', eixo: 'rodar-e-observar', escopo: 'token/crédito por tarefa como MÉTRICA medida — hoje é só valor cultural do Wagner, sem número (Cursor, Cognition/Devin cost-per-task). Régua = custo por PR/feature observável, não "economize crédito" verbal' },
  // ── Eixo SERVIR-O-NEGÓCIO (a inteligência de negócio do PRODUTO — o ponto cego que o adversário 2026-07-10 expôs). ──
  // A grade media CONSTRUIR-E-GOVERNAR e RODAR-E-OBSERVAR, mas nunca "o sistema serve o negócio (A+B) ou governa a si mesmo (C)?".
  { key: 'inteligencia-de-negocio', eixo: 'servir-o-negocio', escopo: 'inteligência de negócio embarcada + cliente-como-sinal: a IA responde o DONO do negócio com dado real do tenant (SAP Joule, Odoo AI, Dynamics Copilot) e o backlog nasce de sinal de cliente/uso, não de régua/prazo (product-led: Linear, Productboard, PLG). Régua = (1) equilíbrio entre construir-produto (A+B) e governar-o-processo (C) — fonte interna scripts/governance/negocio-vs-governanca-ratio.mjs; (2) órgão-sensor do sinal do cliente vivo e conectado (loop client_signal→cycle_goal), não apodrecido. DISTINTO de erp-ia-produto (features do ERP) e de qualidade-drift-ia-producao (recall da Jana): aqui a régua é se a ENERGIA do sistema serve o negócio ou a própria governança. Ponto cego confirmado pelo adversário-inteligencia-negocio 2026-07-10.' },
]

// ── EIXOS — rótulo declarado (o dado já existia, só em COMENTÁRIO acima e em prosa na SKILL) ──
// Serve a duas coisas, ambas do chip "incremental e retomável":
//  (a) `args.eixo` roda o full em 3 transações menores (1 por eixo, ~4-7 dimensões cada) sem o
//      caller digitar as keys à mão — key errada degrada a pesquisa em SILÊNCIO (incidente
//      2026-07-17, o mesmo que motivou a resolução por string logo abaixo);
//  (b) o retrato declara `cobertura.eixos_medidos` / `eixos_nao_medidos`, pra rodada de 1 eixo
//      NUNCA se passar por retrato do sistema inteiro.
// NÃO é régua nova nem agrupamento novo: é o MESMO DIMS_DEFAULT com o rótulo que já estava
// escrito ao lado dele. `args.dimensoes` continua funcionando idêntico (era, e segue sendo, o
// caminho suficiente pra rodada parcial — o eixo só remove o footgun de digitar 4-7 keys).
const EIXOS = [...new Set(DIMS_DEFAULT.map((d) => d.eixo))]
const EIXO_DE = Object.fromEntries(DIMS_DEFAULT.map((d) => [d.key, d.eixo]))
let SELECAO = 'completa' // 'completa' | 'eixo' | 'dimensoes' — o delta usa pra forçar re-medida

// Resolve args.eixo / args.dimensoes pra rodada PARCIAL (re-medir 1 eixo ou 1 dimensão). Aceita:
//   ["observabilidade-agente"]            → string: resolve o escopo do DIMS_DEFAULT pela key
//   [{ key, escopo }]                     → objeto: usa direto (escopo custom)
// Sem esta resolução uma STRING vira {key:undefined, escopo:undefined} → o prompt do pesquisador
// fica "SUA DIMENSÃO: undefined — undefined" e o agente se auto-cura na dimensão transversal do
// dossiê → a grade mede a dimensão ERRADA em silêncio. É a MESMA classe da truncagem silenciosa
// corrigida na Fase 4. Incidente 2026-07-17: dimensoes:["observabilidade-agente"] rodou a grade de
// governança executável por 1,77M tokens. String sem match no default: escopo = a própria key +
// log() (nunca undefined mudo). Regra do tool Workflow: "No silent caps — log() what was dropped".
const DIMS = (() => {
  // args.eixo: 'rodar-e-observar' | ['construir-e-governar','servir-o-negocio'] — alias pras keys
  const eixosPedidos = (A && A.eixo) ? (Array.isArray(A.eixo) ? A.eixo : [A.eixo]).map((e) => String(e).trim().toLowerCase()) : []
  if (eixosPedidos.length) {
    const semEixo = eixosPedidos.filter((e) => !EIXOS.includes(e))
    const dims = DIMS_DEFAULT.filter((d) => eixosPedidos.includes(d.eixo))
    if (semEixo.length) log(`⚠️ eixo sem match (ignorado): ${semEixo.join(', ')} — eixos válidos: ${EIXOS.join(' · ')}`)
    if (dims.length) {
      SELECAO = 'eixo'
      log(`rodada por EIXO [${eixosPedidos.filter((e) => EIXOS.includes(e)).join(', ')}]: ${dims.length} dimensões — ${dims.map((d) => d.key).join(', ')}`)
      return dims
    }
    log('⚠️ nenhum eixo válido em args.eixo — caindo pro conjunto COMPLETO (o retrato vai declarar cobertura completa; se você queria 1 eixo, aborte e corrija)')
  }
  const req = A && A.dimensoes
  if (!Array.isArray(req) || req.length === 0) return DIMS_DEFAULT
  SELECAO = 'dimensoes'
  const semMatch = []
  const resolved = req.map((d) => {
    if (d && typeof d === 'object' && d.key) return d
    const key = String(d)
    const found = DIMS_DEFAULT.find((x) => x.key === key)
    if (found) return found
    semMatch.push(key)
    return { key, escopo: key }
  })
  if (semMatch.length) log(`⚠️ dimensoes sem match no DIMS_DEFAULT (escopo = a própria key, pesquisa mais fraca): ${semMatch.join(', ')} — keys válidas: ${DIMS_DEFAULT.map((x) => x.key).join(', ')}`)
  else log(`rodada parcial: ${resolved.map((x) => x.key).join(', ')}`)
  return resolved
})()

const RESEARCH_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['dimensao', 'lideres', 'oimpresso_acima', 'oimpresso_atras', 'roubar'],
  properties: {
    dimensao: { type: 'string' },
    lideres: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['quem', 'porque'], properties: {
      quem: { type: 'string' }, porque: { type: 'string' }, fonte: { type: 'string' } } } },
    oimpresso_acima: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['ideia', 'porque_acima'], properties: {
      ideia: { type: 'string' }, porque_acima: { type: 'string' } } } },
    oimpresso_atras: { type: 'array', items: { type: 'string' } },
    roubar: { type: 'array', items: { type: 'object', additionalProperties: false, required: ['o_que', 'de_quem', 'impacto'], properties: {
      o_que: { type: 'string' }, de_quem: { type: 'string' }, impacto: { type: 'string', enum: ['alto', 'medio', 'baixo'] } } } },
  },
}
const VERDICT = { type: 'object', additionalProperties: false, required: ['veredito', 'razao'], properties: {
  veredito: { type: 'string', enum: ['ACIMA_CONFIRMADO', 'EMPATADO', 'REFUTADO'] }, razao: { type: 'string' }, quem_ja_faz: { type: 'string' } } }
const EXISTE = { type: 'object', additionalProperties: false, required: ['veredito', 'evidencia', 'nota_sugerida'], properties: {
  veredito: { type: 'string', enum: ['JA_EXISTE_TOTAL', 'PARCIAL', 'NAO_EXISTE'] },
  evidencia: { type: 'string', description: 'arquivo:linha / workflow / required-vs-advisory — ou prova de ausência' },
  nota_sugerida: { type: 'number' }, onde_indexar: { type: 'string' } } }
// Teste de integração: o peer refutado monta o TODO integrado, ou só a peça isolada? (anti-falácia-de-composição — Wagner 2026-07-10, proibições §5)
// EMENDA 2026-07-19 (proibições §5): braço discriminativo real. O campo `incremento` é OBRIGATÓRIO — força
// nomear o que a integração acrescenta ALÉM da soma das peças; "nenhum além da identidade" é resposta válida e
// dispara REFUTADO_TB. Motivo: 81/81 DIFERENCIAL_SISTEMA em 8 runs (ledger 2026-07-18) = carimbo, não medição.
const INTEG = { type: 'object', additionalProperties: false, required: ['veredito', 'incremento', 'razao'], properties: {
  veredito: { type: 'string', enum: ['DIFERENCIAL_SISTEMA', 'REFUTADO_TB'] },
  incremento: { type: 'string', description: 'o que a INTEGRAÇÃO acrescenta ALÉM da soma das peças (a "cola") — 1 frase concreta; "nenhum além da identidade/contexto" é resposta válida e força REFUTADO_TB' },
  razao: { type: 'string' }, quem_monta_o_todo: { type: 'string' } } }

// ── Refuter compartilhado + negative control anti-Goodhart (chip orq-anti-goodhart) ──
// promptRefuterFull: a MESMA string pro claim real E pro artefato plantado — garante que o
// canário exercita o refuter IDÊNTICO (contexto-zero); se este texto mudar, muda pros dois.
const promptRefuterFull = (c) =>
  `REFUTADOR (contexto zero — você não herda a pesquisa). Claim: oimpresso está ACIMA do mercado em "${c.ideia}" (${c.porque_acima}; dimensão ${c.dimensao}). Busque na web (2-4 buscas) quem JÁ faz igual/melhor em produção. Achou → REFUTADO (diga quem). Parecido → EMPATADO. Só ACIMA_CONFIRMADO sem par publicado. Default cético.`
// Artefatos PLANTADOS: claims de superioridade ABSURDAMENTE falsas que o refuter TEM que
// derrubar (REFUTADO). Se ele APROVA (ACIMA_CONFIRMADO), carimbou → Goodhart (One Token to
// Fool). É o análogo do gate-selftest aplicado ao LAYER DE AGENTE: nada provava que o refuter
// refuta de verdade e não só carimba. O gate-selftest prova que as CATRACAS mordem; este canário
// prova que o REFUTER discrimina. id/dimensao `__canary__` → NUNCA entram no placar/ledger/grade.
// Espelha "REFUTADO_TB=0 em 81": prova controlada de que o braço negativo do adversário PODE
// disparar. Corpus/selftest determinístico: scripts/governance/refuter-canary-check.mjs (catraca
// `refuter-canary` do gate-selftest). MANTER o corpus em sincronia com o `--list` daquele script.
const CANARIOS_REFUTER = [
  { id: '__canary__git-versionamento', dimensao: '__canary__', ideia: 'usar git para versionar o código-fonte', porque_acima: 'nenhum ERP concorrente no mundo usa controle de versão — só o oimpresso' },
  { id: '__canary__json-config', dimensao: '__canary__', ideia: 'guardar configuração em arquivos JSON', porque_acima: 'é uma técnica inédita que nenhum outro software adota' },
]
const rodarCanarios = async () => {
  const vs = (await parallel(CANARIOS_REFUTER.map((c) => () => agent(
    promptRefuterFull(c),
    { label: `canary:${c.id}`.slice(0, 48), phase: 'Refutar', schema: VERDICT, agentType: 'general-purpose', effort: 'medium' },
  ).then((v) => (v ? { id: c.id, veredito: v.veredito } : null))))).filter(Boolean)
  const goodhart = vs.filter((v) => v.veredito === 'ACIMA_CONFIRMADO')
  const derrubados = vs.filter((v) => v.veredito === 'REFUTADO')
  if (goodhart.length) log(`🔴 ANTI-GOODHART: refuter APROVOU ${goodhart.length}/${CANARIOS_REFUTER.length} artefato(s) PLANTADO(s) absurdo(s) (${goodhart.map((g) => g.id).join(', ')}) — está CARIMBANDO, não avaliando (One Token to Fool). Vereditos desta rodada são suspeitos.`)
  else log(`✓ anti-Goodhart: refuter derrubou ${derrubados.length}/${vs.length} plantado(s) medido(s) — o braço negativo dispara (cf. REFUTADO_TB=0/81)`)
  return { total: CANARIOS_REFUTER.length, medidos: vs.length, derrubados: derrubados.length, goodhart: goodhart.length, goodhart_ids: goodhart.map((g) => g.id), goodhart_ok: goodhart.length === 0 }
}

// ── Cap de agentes das fases adversariais (Refutar · Integração · Verificar) ──
// TRUNCAGEM SILENCIOSA, ROUND 2 (achados #21/#24 do passe adversarial 2026-07-18):
// a Fase Verificar fazia `fraquezas.slice(0, 24)` SEM log — na rodada completa de
// 2026-07-18 cortou 52 de 76 fraquezas e, pela ordem do DIMS_DEFAULT, as 24 verificadas
// foram 100% do eixo CONSTRUIR-E-GOVERNAR; RODAR-E-OBSERVAR e SERVIR-O-NEGÓCIO (os
// eixos que as ADRs 0333/0334 existem pra cobrir) ficaram sem verificação-por-fraqueza.
// MESMA classe corrigida na Fase Grade em #4477 (2026-07-17). Varredura contada
// (lápide 2026-07-15): 3 caps de dados silenciosos no arquivo — Refutar, Integração e
// Verificar — TODOS migrados pra este helper; os demais `slice(` são cosméticos de
// label/quote (48/300 chars) ou o `fit()` da Fase Grade, que já loga. `head_limit`: 0.
// Estratégia: round-robin por rank de dimensão — cada dimensão mantém suas top-N na
// ordem em que a pesquisa as listou (N o mais igual possível entre dimensões); nenhuma
// zera enquanto cap ≥ nº de dimensões. Proporcional-ao-tamanho foi descartado de
// propósito: premiaria a dimensão cujo pesquisador listou MAIS itens (viés de
// verbosidade), e o defeito é justamente eixo inteiro sem cobertura. Cortou → log()
// de quantas e quais dimensões perderam ("No silent caps — log() what was dropped").
// Rodada parcial (args.dimensoes com 1-2 dims) fica abaixo do cap → passa intacta.
// Custo avaliado (por que NÃO subir o cap pra 76): Verificar roda effort high; 76
// agentes ≈ 3× o custo da fase. Estratificar mantém 24 e devolve a cobertura por eixo.
// Função PURA (logFn injetado, sem globals) entre marcadores CAP-ESTRAT-INI/FIM pro
// harness dry extrair e testar o código REAL do arquivo, não uma cópia.
const CAP_AGENTES_POR_FASE = 24
/* CAP-ESTRAT-INI */
const capEstratificado = (nome, items, cap, logFn) => {
  if (items.length <= cap) return items
  const ordem = []
  const porDim = new Map()
  for (const it of items) {
    const d = it.dimensao || '(sem-dimensao)'
    if (!porDim.has(d)) { porDim.set(d, []); ordem.push(d) }
    porDim.get(d).push(it)
  }
  const mantidosPorDim = new Map(ordem.map((d) => [d, 0]))
  const mantidos = []
  for (let rank = 0; mantidos.length < cap; rank++) {
    let pegou = false
    for (const d of ordem) {
      if (mantidos.length >= cap) break
      if (rank < porDim.get(d).length) { mantidos.push(porDim.get(d)[rank]); mantidosPorDim.set(d, rank + 1); pegou = true }
    }
    if (!pegou) break
  }
  const perdas = ordem.filter((d) => mantidosPorDim.get(d) < porDim.get(d).length)
    .map((d) => `${d} ${mantidosPorDim.get(d)}/${porDim.get(d).length}`)
  const zeradas = ordem.filter((d) => mantidosPorDim.get(d) === 0)
  logFn(`⚠️ CORTE ${nome}: ${items.length} → ${mantidos.length} (cap ${cap}) — ${items.length - mantidos.length} descartadas; dimensões que perderam (mantidas/total): ${perdas.join(' · ')}${zeradas.length ? ' — ZERADAS (nenhuma verificada): ' + zeradas.join(', ') : ''}`)
  return mantidos
}
/* CAP-ESTRAT-FIM */

// ── PERSISTÊNCIA INCREMENTAL — checkpoints (2026-07-26) ──────────────────────
// DEFEITO MEDIDO, não hipótese: `Persistir` era a ÚLTIMA e ÚNICA escrita da transação. As duas
// rodadas de 2026-07-25/26 morreram no meio — a 1ª interrompida em Refutar/Integração, a 2ª no
// teto de uso (27 de 88 agentes com "session limit", matando a fase Verificar inteira + a grade
// + o persistir-full) — e nas DUAS o trabalho CARO (12 pesquisas web + 24 refutações + 15
// integrações) sobreviveu só no journal do run: `notas` null, `grade` null, `persistencia` null,
// `retratos.json` intocado desde 2026-07-18. Agora o ledger recebe o que FECHOU, quando fecha:
//   CP-claims  → logo após Integração        (claims.json — upsert por id)
//   CP-retrato → após a composição determinística e ANTES da prosa cara (fraquezas.json + retrato)
// A fase Persistir final vira RETENTATIVA: só gasta agente se algum checkpoint não confirmou.
// Idempotência: claims/fraquezas são upsert-POR-ID (re-aplicar é seguro) e o retrato leva
// `cobertura.assinatura` (determinística, derivada das contagens da rodada) pra retentativa/resume
// não inserir dois retratos da mesma rodada. Append-only preservado: retrato novo entra NO TOPO,
// retrato antigo NUNCA é editado.
// Regra 16 INTACTA: os números seguem fechando em JS — o agente de persistência só TRANSCREVE.
// Os checkpoints não chamam o marcador global phase() (passam phase:'Persistir' no opts do agent),
// pra não intercalar cabeçalho de fase no meio das fases caras.
const PERSIST_RESULT = { type: 'object', additionalProperties: false, required: ['ok', 'resumo'], properties: {
  ok: { type: 'boolean', description: 'true SÓ se os arquivos foram gravados E validados com JSON.parse' },
  resumo: { type: 'string' }, arquivos: { type: 'array', items: { type: 'string' } }, erro: { type: 'string' } } }
const persistir = (label, prompt) => agent(prompt, { label, phase: 'Persistir', schema: PERSIST_RESULT, effort: 'low', model: MODELO_MECANICO, agentType: 'general-purpose' })
const okPersist = (r) => !!(r && r.ok === true)
const RETENTAR = (p) => `RETENTATIVA — o checkpoint desta MESMA rodada não confirmou gravação. ⚠️ ANTES DE ESCREVER, cheque idempotência: claims/fraquezas são upsert POR ID (re-aplicar é seguro); o RETRATO não — se o TOPO de retratos.json já for desta rodada (mesma \`cobertura.assinatura\`), NÃO insira outro, só confirme ok:true no resumo.\n\n${p}`

/* COBERTURA-INI */
// Cobertura HONESTA do retrato — restrição dura do chip: retrato parcial NÃO pode se passar por
// retrato completo. `etapas` = [{nome, esperado, obtido}] com o `esperado` JÁ pós-cap (o corte do
// capEstratificado é decisão de custo declarada, não morte de rodada). Função PURA (zero globals)
// entre marcadores, pro selftest extrair e testar o código REAL do arquivo — mesma convenção do
// CAP-ESTRAT acima.
const montarCobertura = ({ modo, etapas = [], notas = {}, dimsAlvo = [], eixoDe = {}, eixosTodos = [], extra = {} }) => {
  const faltas = etapas.filter((e) => e.obtido < e.esperado)
  const semNota = dimsAlvo.filter((k) => notas[k] === null || notas[k] === undefined)
  const eixosMedidos = [...new Set(dimsAlvo.map((k) => eixoDe[k] || '(sem-eixo)'))]
  return {
    completo: faltas.length === 0 && dimsAlvo.length > 0,
    motivo_parcial: faltas.length ? faltas.map((e) => `${e.nome} ${e.obtido}/${e.esperado} (perdidas ${e.esperado - e.obtido})`).join(' · ') : null,
    etapas: Object.fromEntries(etapas.map((e) => [e.nome, `${e.obtido}/${e.esperado}`])),
    dimensoes_alvo: dimsAlvo,
    dimensoes_sem_nota: semNota,
    eixos_medidos: eixosMedidos,
    eixos_nao_medidos: eixosTodos.filter((e) => !eixosMedidos.includes(e)),
    cap_agentes_por_fase: CAP_AGENTES_POR_FASE,
    assinatura: `${modo}|${dimsAlvo.length}d|${etapas.map((e) => `${e.nome}:${e.obtido}/${e.esperado}`).join(',')}`,
    ...extra,
  }
}
/* COBERTURA-FIM */

// `fit` vive AQUI (e não mais só na fase Grade) porque a persistência incremental também precisa
// dele — e um `const` declarado depois estaria em TDZ pro checkpoint que roda antes. Corta com
// LOG (nunca em silêncio): "No silent caps — log() what was dropped" (regra do tool Workflow).
const fit = (nome, obj, cap) => {
  const s = JSON.stringify(obj)
  if (s.length <= cap) return s
  log(`⚠️ TRUNCADO ${nome}: ${s.length} → ${cap} chars (-${(((s.length - cap) / s.length) * 100).toFixed(0)}%) — o consumidor NÃO viu tudo`)
  return s.slice(0, cap)
}

// Prompts de persistência COMPARTILHADOS entre full e delta (uma fonte só pras regras duras do
// ledger: append-only no retrato · upsert por id · PRESERVAR `indexado` · validar com JSON.parse).
const promptClaims = (rows, comId) =>
  `PERSISTIR (checkpoint incremental) as CLAIMS desta rodada em ${BASE}/${LEDGER_DIR}/claims.json (crie o arquivo se não existir — schema no README.md de lá). Dados JÁ FECHADOS (transcreva, não reinterprete): ${rows}\n` +
  `Passos EXATOS: (1) upsert POR ID — ${comId ? 'o id vem nos dados; NUNCA crie entrada nova pra id existente' : 'derive o id de dimensao+slug do título e REUSE o id já existente quando a claim já estiver no arquivo (não duplique a mesma claim com id novo)'}; grave refutador, peer, integracao (veredito do teste de integração) e data_veredito = hoje (ISO); ttl_dias = 30 se o veredito do refutador for ACIMA_CONFIRMADO, senão 90. (2) PRESERVE os campos existentes que não vieram nos dados — em especial \`correcao_obrigatoria\` e \`journal\` (a correção viaja COM a claim). (3) valide o arquivo com node (JSON.parse) e só então retorne ok:true. NÃO toque em retratos.json nem em fraquezas.json — outro checkpoint cuida deles.`

const promptRetrato = ({ modoRetrato, notas, proveniencia, cobertura, placar, integHistInstrucao, fraquezasRows, extraRetrato }) =>
  `PERSISTIR (checkpoint incremental) FRAQUEZAS + RETRATO no ledger ${BASE}/${LEDGER_DIR}/ (crie os arquivos se não existirem — schema no README.md de lá). NÚMEROS JÁ FECHADOS EM JS (transcreva LITERAL; PROIBIDO recalcular, arredondar, fundir ou re-atribuir):\n` +
  `- notas por dimensão: ${JSON.stringify(notas)}\n- proveniência das notas: ${JSON.stringify(proveniencia)}\n` +
  (placar ? `- placar: ${JSON.stringify(placar)}\n` : '') +
  `- COBERTURA REAL desta rodada (transcreva LITERAL — é o campo que impede um retrato PARCIAL de se passar por completo): ${JSON.stringify(cobertura)}\n` +
  `- fraquezas re-medidas: ${fraquezasRows}\n` +
  `Passos EXATOS:\n` +
  `(1) retratos.json — insira NO TOPO do array {data: hoje (ISO), modo: "${modoRetrato}", regra_nota: "media-deterministica-v1", notas, proveniencia_notas, ${placar ? 'placar, ' : ''}cobertura, integ_hist, links: []${extraRetrato ? ', ' + extraRetrato : ''}}. NUNCA edite retrato antigo (append-only). ⚠️ Se o TOPO já for um retrato desta MESMA rodada (mesma \`cobertura.assinatura\`), NÃO insira outro — apenas retorne ok:true dizendo isso no resumo.\n` +
  `    integ_hist: ${integHistInstrucao}\n` +
  `(2) fraquezas.json — upsert POR ID (atualize nota/veredito/evidencia/degrau + data = hoje; sete existia_invisivel:true + onde_indexar quando a verificação indicou). ⚠️ PRESERVE o valor de \`indexado\` que JÁ existe na entrada — NUNCA rebaixe indexado:true→false nem crie indexado:false onde não havia campo (mecanismo já indexado não volta pra fila; senão a próxima rodada re-descobre eternamente — bug pego no teste 2026-07-19).\n` +
  `(3) valide os 2 arquivos com node (JSON.parse) e só então retorne ok:true.\n` +
  `(4) rode node ${BASE}/scripts/governance/reguas-indexar.mjs (se existir) e cite no resumo a fila de indexação pendente — é o chip mais barato da rodada.`

// ── MODO DELTA (Órgão 2 da máquina — ADR proposta reguas-loop-maquina-evolucao) ──
// Rodada INCREMENTAL dirigida pelo ledger memory/reguas/ (alvo ≤2,5M tokens vs ~11,4M full):
// re-verifica SÓ dimensões com Δ material de commits nos paths mapeados (config.paths_por_dimensao)
// e re-refuta SÓ claims com TTL vencido. NÃO pesquisa mercado (regra 5 da skill: lado-mercado
// reusado), NÃO roda Integração (claim nova só nasce no full). Contexto histórico: até 2026-07-19 o
// braço negativo NUNCA disparou (0 REFUTADO_TB em 81 vereditos, ledger 2026-07-18) — por isso a pergunta
// de Integração foi REFORMULADA nessa data pra ganhar braço discriminativo (emenda §5 2026-07-19; só o
// full pós-emenda dirá se passou a discriminar). Composição DETERMINÍSTICA (regra 16 do
// adversário 2026-07-18 mecanizada): nota = média 1-decimal das fraquezas re-verificadas; sem Δ
// herda com flag. Disclosure do placar sai do ledger (regra 17 mecanizada).
if (MODO === 'delta') {
  phase('Delta-scan')
  // `ultimo_retrato` é REQUIRED desde 2026-08-08 (era opcional; ver o guard de âncora abaixo):
  // ele é a ÂNCORA da composição determinística, não um enfeite. Sem `notas` a grade sai {}.
  const SCAN = { type: 'object', required: ['dims_delta', 'claims_vencidas', 'fraquezas', 'ultimo_retrato'], properties: {
    erro: { type: 'string', description: 'preencher SÓ se o ledger estiver ausente/ilegível' },
    ultimo_retrato: { type: 'object', required: ['data', 'notas'], description: 'ÂNCORA da composição — retratos[0]. `notas` NÃO pode vir vazio: a nota de cada dimensão é calculada iterando as chaves dele.', properties: { data: { type: 'string' }, notas: { type: 'object', additionalProperties: { type: 'number' } }, integ_hist: { type: 'object' } } },
    dims_delta: { type: 'object', additionalProperties: { type: 'object', required: ['commits'], properties: { commits: { type: 'number' }, resumo: { type: 'string' } } } },
    claims_vencidas: { type: 'array', items: { type: 'object', required: ['id', 'titulo', 'dimensao'], properties: { id: { type: 'string' }, titulo: { type: 'string' }, dimensao: { type: 'string' }, refutador: { type: 'string' }, peer: { type: 'string' }, correcao_obrigatoria: { type: 'string' } } } },
    fraquezas: { type: 'array', items: { type: 'object', required: ['id', 'dimensao', 'titulo'], properties: { id: { type: 'string' }, dimensao: { type: 'string' }, titulo: { type: 'string' }, veredito: { type: 'string' }, nota: { type: ['number', 'null'] }, evidencia: { type: 'string' }, degrau: { type: 'string' } } } },
    delta_min_commits: { type: 'number' },
  } }
  const scan = await agent(
    `SCANNER do modo delta da grade de réguas. Tarefas EXATAS (sem interpretar além):\n` +
    `1. Leia ${BASE}/${LEDGER_DIR}/config.json, retratos.json, claims.json, fraquezas.json. Algum ausente/ilegível → retorne só {erro:"..."}.\n` +
    `2. ultimo_retrato = retratos[0] (data + notas + integ_hist).\n` +
    `3. Pra CADA dimensão de config.paths_por_dimensao rode: git -C ${BASE} log --oneline --since="<data do ultimo retrato>" -- <paths da dimensão> | conte as linhas (comando rodado, não estimativa; liste os paths literalmente no comando). dims_delta[key] = {commits: N, resumo: "1 linha do tema dos commits, se N>0"}.\n` +
    `4. claims_vencidas = claims onde data_veredito + ttl_dias <= hoje (compare datas ISO; inclua correcao_obrigatoria quando houver).\n` +
    `5. fraquezas = o array INTEIRO de fraquezas.json (campos id/dimensao/titulo/veredito/nota/evidencia/degrau — condense evidencia a ≤200 chars).\n` +
    `6. delta_min_commits = config.delta_min_commits.\nRetorne SÓ o JSON.`,
    { label: 'delta-scan', phase: 'Delta-scan', schema: SCAN, effort: 'low', model: MODELO_MECANICO, agentType: 'general-purpose' },
  )
  if (!scan || scan.erro) {
    log(`⚠️ delta abortado: ${(scan && scan.erro) || 'scan falhou'} — rode o modo full pra semear o ledger`)
    return { modo: 'delta', erro: (scan && scan.erro) || 'scan falhou', acao: 'rodar full' }
  }
  // ── GUARD DE ÂNCORA (defeito MEDIDO 2026-08-08, run wf_32c91912-fca) ────────────────────
  // A composição determinística itera `Object.keys(notasAntigas)`, e `notasAntigas` vem de
  // `scan.ultimo_retrato.notas`. O campo era OPCIONAL no schema: o scanner o omitiu, a validação
  // passou, e o laço rodou ZERO vezes — `notas:{}`, `eixos_medidos:[]`. Só que isso aconteceu
  // DEPOIS de 39 agentes (24 verificações + 15 refutações, 10,9M tokens): a rodada gastou tudo e
  // gravou um retrato que não mede nada, em silêncio, no topo de um ledger append-only.
  // Duas travas, porque uma não bastava: o `required` do schema (acima) força o campo a VIR, e
  // este guard cobre o caso que o schema não pega — vir PRESENTE e VAZIO (`notas:{}`).
  // Aborta ANTES da fase Verificar: instrumento sem âncora não mede, e o que não mede não grava
  // (§5 2026-07-29 — "instrumento AFIRMAR verde quando não conseguiu MEDIR").
  const notasAncora = (scan.ultimo_retrato && scan.ultimo_retrato.notas) || {}
  if (!Object.keys(notasAncora).length) {
    log(`⚠️ delta ABORTADO antes de gastar agentes: o scan não trouxe \`ultimo_retrato.notas\` — sem essa âncora a composição sairia {} em SILÊNCIO. Chaves recebidas: [${Object.keys(scan).join(', ')}]. Rode o full, ou conserte o scanner.`)
    return { modo: 'delta', erro: 'âncora ausente: ultimo_retrato.notas vazio ou ausente', acao: 'rodar full', scan_retornou: Object.keys(scan) }
  }
  const minC = scan.delta_min_commits || 3
  // Seleção explícita (args.dimensoes OU args.eixo) força re-medida mesmo sem Δ de commits.
  const forcadas = SELECAO !== 'completa' ? DIMS.map((d) => d.key) : []
  const ativas = Object.entries(scan.dims_delta || {})
    .filter(([k, v]) => (v && v.commits >= minC) || forcadas.includes(k)).map(([k]) => k)
  log(`delta desde ${scan.ultimo_retrato && scan.ultimo_retrato.data}: ativas [${ativas.join(', ') || 'nenhuma'}] (≥${minC} commits ou forçadas) · claims vencidas ${scan.claims_vencidas.length}`)
  if (!ativas.length && !scan.claims_vencidas.length) {
    log('nada a re-medir — retrato segue válido (heartbeat barato do looping)')
    return { modo: 'delta', nada_a_medir: true, ultimo_retrato: scan.ultimo_retrato && scan.ultimo_retrato.data }
  }

  phase('Verificar')
  const alvo = (scan.fraquezas || []).filter((f) => ativas.includes(f.dimensao))
  const verificadas = alvo.length ? (await parallel(capEstratificado('Verificar', alvo, CAP_AGENTES_POR_FASE, log).map((f) => () => agent(
    `RE-VERIFICAÇÃO delta. Fraqueza CONHECIDA do ledger: "${f.titulo}" (dimensão ${f.dimensao}; nota anterior ${f.nota == null ? 's/nota' : f.nota}; veredito anterior ${f.veredito}; evidência anterior: ${(f.evidencia || '').slice(0, 250)}). A dimensão teve commits novos desde o último retrato — re-meça no repo VIVO (paths ABSOLUTOS a partir de ${BASE}): fechou? avançou? regrediu? Dê o veredito e a nota 0-10 SÓ com evidência NOVA (file:line ou PR — recibo, não memória) e diga onde indexar se existia-mas-invisível.`,
    { label: `v:${f.titulo}`.slice(0, 48), phase: 'Verificar', schema: EXISTE, effort: 'high' },
  ).then((v) => (v ? { ...f, check: v } : null))))).filter(Boolean) : []
  log(`delta-verificação: ${verificadas.length}/${alvo.length} fraquezas re-medidas`)

  phase('Refutar')
  const reRefutadas = scan.claims_vencidas.length ? (await parallel(capEstratificado('Refutar', scan.claims_vencidas, CAP_AGENTES_POR_FASE, log).map((c) => () => agent(
    `REFUTADOR (contexto zero). Claim com TTL VENCIDO pra re-veredito: oimpresso estaria "${c.refutador === 'ACIMA_CONFIRMADO' ? 'ACIMA do mercado' : 'na barra do mercado'}" em "${c.titulo}" (dimensão ${c.dimensao}; veredito anterior ${c.refutador}${c.peer ? '; peer anterior: ' + c.peer : ''}).${c.correcao_obrigatoria ? ' CORREÇÃO OBRIGATÓRIA que viaja com a claim (não re-alegar o que ela mata): ' + c.correcao_obrigatoria : ''} Busque na web (2-4 buscas) o estado ATUAL: quem faz igual/melhor em produção HOJE. Achou → REFUTADO (diga quem). Parecido → EMPATADO. Só ACIMA_CONFIRMADO sem par publicado. Default cético.`,
    { label: `r:${c.titulo}`.slice(0, 48), phase: 'Refutar', schema: VERDICT, agentType: 'general-purpose', effort: 'medium' },
  ).then((v) => (v ? { ...c, verdict: v } : null))))).filter(Boolean) : []
  if (scan.claims_vencidas.length) log(`delta-refutação: ${reRefutadas.length} claims re-vereditadas · ${reRefutadas.filter((r) => r.verdict.veredito === 'ACIMA_CONFIRMADO').length} seguem acima`)

  // CHECKPOINT 1 — claims já fecharam: grava AGORA (a prosa e o retrato ainda podem morrer).
  const cpClaims = reRefutadas.length
    ? await persistir('cp-claims-delta', promptClaims(JSON.stringify(reRefutadas.map((r) => ({
        id: r.id, titulo: r.titulo, dimensao: r.dimensao, refutador: r.verdict.veredito, peer: r.verdict.quem_ja_faz || r.verdict.razao || '',
      }))), true))
    : null
  if (reRefutadas.length) log(`checkpoint claims: ${okPersist(cpClaims) ? 'gravado' : '⚠️ NÃO confirmado (retentativa no fim)'}`)

  phase('Grade')
  // Regra 16 mecanizada: números fechados AQUI (JS), prosa depois — o agente não altera nota.
  const media1 = (xs) => Math.round((xs.reduce((a, b) => a + b, 0) / xs.length) * 10) / 10
  const notasAntigas = notasAncora // já provado não-vazio pelo guard de âncora acima
  const notasNovas = {}
  const proveniencia = {}
  for (const k of Object.keys(notasAntigas)) {
    const rows = verificadas.filter((v) => v.dimensao === k && typeof v.check.nota_sugerida === 'number')
    if (rows.length) { notasNovas[k] = media1(rows.map((r) => r.check.nota_sugerida)); proveniencia[k] = `re-medida (${rows.length} fraquezas, média determinística)` }
    else { notasNovas[k] = notasAntigas[k]; proveniencia[k] = ativas.includes(k) ? 'herdada (dim ativa mas 0 fraquezas com nota)' : 'herdada (sem Δ material)' }
  }
  const integHist = (scan.ultimo_retrato && scan.ultimo_retrato.integ_hist) || {}

  // CHECKPOINT 2 — notas fechadas (JS). Grava fraquezas + retrato ANTES da prosa: se a prosa
  // morrer (ou o teto de uso bater), o ledger já tem o retrato desta rodada, com a cobertura real.
  const dimsAlvoDelta = Object.keys(notasNovas)
  const reMedidas = dimsAlvoDelta.filter((k) => String(proveniencia[k] || '').startsWith('re-medida'))
  const coberturaDelta = montarCobertura({
    modo: 'delta',
    etapas: [
      { nome: 'verificar', esperado: Math.min(alvo.length, CAP_AGENTES_POR_FASE), obtido: verificadas.length },
      { nome: 'refutar', esperado: Math.min(scan.claims_vencidas.length, CAP_AGENTES_POR_FASE), obtido: reRefutadas.length },
    ],
    notas: notasNovas, dimsAlvo: dimsAlvoDelta, eixoDe: EIXO_DE, eixosTodos: EIXOS,
    extra: {
      dimensoes_re_medidas: reMedidas,
      dimensoes_herdadas: dimsAlvoDelta.filter((k) => !reMedidas.includes(k)),
      dims_ativas: ativas,
      integracao_nao_rodada: true, // delta não roda Integração — integ_hist é HERDADO, não medido
    },
  })
  const cpRetrato = await persistir('cp-retrato-delta', promptRetrato({
    modoRetrato: coberturaDelta.completo ? 'delta' : 'delta-parcial',
    notas: notasNovas, proveniencia, cobertura: coberturaDelta, placar: null,
    integHistInstrucao: 'copie EXATAMENTE do retrato anterior — o delta NÃO roda a fase Integração, então este acumulado é HERDADO, nunca recalculado.',
    fraquezasRows: JSON.stringify(verificadas.map((v) => ({ id: v.id, nota: v.check.nota_sugerida, veredito: v.check.veredito, evidencia: (v.check.evidencia || '').slice(0, 250), onde_indexar: v.check.onde_indexar || null }))),
  }))
  log(`checkpoint retrato+fraquezas (delta${coberturaDelta.completo ? '' : '-PARCIAL: ' + coberturaDelta.motivo_parcial}): ${okPersist(cpRetrato) ? 'gravado' : '⚠️ NÃO confirmado (retentativa no fim)'}`)

  const prosa = await agent(
    `PROSA da rodada DELTA da grade de réguas (PT-BR, ≤450 palavras, datada de hoje). Os NÚMEROS estão FECHADOS pela composição determinística (regra 16 — PROIBIDO alterar, fundir ou re-atribuir nota): ${JSON.stringify({ notasNovas, notasAntigas, proveniencia, dims_ativas: ativas, dims_delta: scan.dims_delta })}.\nFraquezas re-medidas (evidência nova): ${JSON.stringify(verificadas.map((v) => ({ id: v.id, dimensao: v.dimensao, titulo: v.titulo, de: v.nota, para: v.check.nota_sugerida, veredito: v.check.veredito, evidencia: (v.check.evidencia || '').slice(0, 200) })))}.\nClaims re-vereditadas: ${JSON.stringify(reRefutadas.map((r) => ({ id: r.id, de: r.refutador, para: r.verdict.veredito, peer: r.verdict.quem_ja_faz || '' })))}.\nEscreva: (1) o que mudou e por quê (Δ por dimensão re-medida, com a evidência); (2) o que segue herdado; (3) DISCLOSURE OBRIGATÓRIO do placar (regra 17): REFUTADO_TB acumulado ${JSON.stringify(integHist)}. Até 2026-07-19 o braço negativo nunca disparou (0/81) e o valor esteve nas RAZÕES, não no binário; a pergunta de Integração foi reformulada nessa data (emenda §5) pra ganhar braço discriminativo — mas delta NÃO roda Integração, então este número é HERDADO do último full; NÃO afirme que "agora discrimina" antes do placar de um full pós-emenda; (4) próximo degrau mais barato. NADA de nota nova inventada.`,
    { label: 'prosa-delta', phase: 'Grade', effort: 'medium' },
  )

  // Fase Persistir = RETENTATIVA. Se os dois checkpoints confirmaram, não gasta agente nenhum.
  phase('Persistir')
  const pendentesDelta = []
  if (reRefutadas.length && !okPersist(cpClaims)) pendentesDelta.push('claims')
  if (!okPersist(cpRetrato)) pendentesDelta.push('retrato+fraquezas')
  const retentativaDelta = {}
  if (!pendentesDelta.length) log('ledger já gravado pelos checkpoints (claims + fraquezas + retrato) — nada a re-persistir (0 agentes)')
  else log(`⚠️ checkpoint(s) sem confirmação: ${pendentesDelta.join(' · ')} — retentativa`)
  if (pendentesDelta.includes('retrato+fraquezas')) {
    retentativaDelta.retrato = await persistir('re-retrato-delta', RETENTAR(promptRetrato({
      modoRetrato: coberturaDelta.completo ? 'delta' : 'delta-parcial',
      notas: notasNovas, proveniencia, cobertura: coberturaDelta, placar: null,
      integHistInstrucao: 'copie EXATAMENTE do retrato anterior — o delta NÃO roda a fase Integração, então este acumulado é HERDADO, nunca recalculado.',
      fraquezasRows: JSON.stringify(verificadas.map((v) => ({ id: v.id, nota: v.check.nota_sugerida, veredito: v.check.veredito, evidencia: (v.check.evidencia || '').slice(0, 250), onde_indexar: v.check.onde_indexar || null }))),
    })))
  }
  if (pendentesDelta.includes('claims')) {
    retentativaDelta.claims = await persistir('re-claims-delta', RETENTAR(promptClaims(JSON.stringify(reRefutadas.map((r) => ({
      id: r.id, titulo: r.titulo, dimensao: r.dimensao, refutador: r.verdict.veredito, peer: r.verdict.quem_ja_faz || r.verdict.razao || '',
    }))), true)))
  }
  return {
    modo: 'delta',
    dims_ativas: ativas,
    fraquezas_re_medidas: verificadas.length,
    claims_re_vereditadas: reRefutadas.length,
    notas: notasNovas,
    proveniencia,
    cobertura: coberturaDelta,
    prosa,
    persistencia: { claims: cpClaims, retrato: cpRetrato, pendentes: pendentesDelta, retentativa: retentativaDelta },
  }
}

// ── Fase 0 — Dossiê (do mapa VIVO, nunca de memória) ─────────────────────────
phase('Dossiê')
const dossie = await agent(
  `Monte o DOSSIÊ compacto (≤500 palavras) do IA OS do oimpresso pra alimentar pesquisadores de mercado. FONTE OBRIGATÓRIA (leia, não lembre): ${BASE}/memory/decisions/ — o "mapa dos níveis" mais RECENTE (glob por *mapa-dos-niveis* e pegue o de maior número; hoje 0330) + a doutrina *doutrina-documentacao* + ${BASE}/memory/proibicoes.md §5 (o que já foi rejeitado — pros pesquisadores não re-proporem). Estruture: mecanismos por camada (com nome de arquivo/gate e required|advisory) + FRAQUEZAS CONFESSAS (do mapa + do §5) — sem esconder nada. Se o mapa citar "mecanismos que existiam mas eram inacháveis", INCLUA (é a lista anti-falso-negativo).`,
  { label: 'dossie', phase: 'Dossiê', effort: 'high' },
)
log('dossiê montado do mapa vivo')

// ── Fase 1 — Pesquisar (web, 1 por dimensão) ─────────────────────────────────
phase('Pesquisar')
const COMUM = `Você pesquisa o ESTADO DA ARTE da sua dimensão (WebSearch/WebFetch — 5-8 buscas reais, fontes dos últimos 12 meses) e compara com o IA OS do oimpresso (dossiê abaixo). Duro nos 2 sentidos: só declare "oimpresso acima" se o mercado comprovadamente NÃO pratica (cite o que os líderes fazem); liste sem dó onde está atrás. NÃO re-proponha o que o dossiê marca como rejeitado (§5).\n\nDOSSIÊ:\n${dossie}`
// ⚠️ CARIMBO DA KEY CANÔNICA (bug medido 2026-07-26, passe adversarial — impacto MÁXIMO):
// `RESEARCH_SCHEMA.dimensao` é string LIVRE (sem enum), e a composição de notas filtra por
// igualdade exata (`v.dimensao === d.key`). Na rodada 07-26, 9 dos 12 pesquisadores devolveram
// `"<key> — <escopo>"` (ecoando o prompt) e 1 devolveu ACENTUADO (`custo-eficiência` × a key
// `custo-eficiencia`); só 3 devolveram a key nua. Resultado: **as 3 únicas dimensões que
// "fecharam nota" foram exatamente as 3 nuas** — as outras 9 saíram `null` com 24/24
// verificadores tendo devolvido nota numérica. Pior: a prosa da grade vestiu o acidente de
// princípio ("compor seria agregação incomensurável que o §5 proíbe"), que é a classe LC-08
// aplicada ao próprio medidor. O orquestrador SEMPRE soube a key (`d.key`) — não há motivo pra
// confiar na string que o agente devolve. Aqui ela é sobrescrita na origem, então todo consumidor
// a jusante (claims, fraquezas, composição, ledger) herda a key canônica.
const pesquisas = (await parallel(DIMS.map((d) => () => agent(
  `${COMUM}\n\nSUA DIMENSÃO: ${d.key} — ${d.escopo}`,
  { label: `p:${d.key}`, phase: 'Pesquisar', schema: RESEARCH_SCHEMA, agentType: 'general-purpose', effort: 'high' },
).then((r) => (r ? { ...r, dimensao: d.key, dimensao_reportada: r.dimensao } : null))))).filter(Boolean)
const divergiu = pesquisas.filter((p) => p.dimensao_reportada !== p.dimensao)
log(`${pesquisas.length}/${DIMS.length} dimensões pesquisadas`)
if (divergiu.length) log(`ℹ️ key canônica carimbada em ${divergiu.length}/${pesquisas.length} (o agente devolveu outra string; sem o carimbo a nota da dimensão sairia null): ${divergiu.map((p) => p.dimensao).join(', ')}`)

// ── Fase 2 — Refutar toda claim "acima" (default: derrubar) ──────────────────
// ⚠️ VERIFICAÇÃO SAME-MODEL (fraqueza 5,0 · orquestracao-adversarial): este refutador roda
// Opus×Opus by-design → agreement-bias (o modelo tende a concordar consigo). O ORÁCULO
// cross-model (cross-vendor, régua Amp Oracle) é um passe SEPARADO, de PROCESSO — não cabe
// aqui dentro porque agent() é Claude-only. Depois que a fase Persistir grava o ledger, rode:
//   node scripts/governance/reguas-cross-model.mjs            (cross-vendor OpenAI, se OPENAI_API_KEY)
//   node scripts/governance/reguas-cross-model.mjs --verdicts <f.json> --modelo-cross <label>
// Ele re-ataca BLIND as claims que o Opus MANTEVE e diffa contra o ledger (controle negativo).
// DIVERGE_DERRUBA = um 2º modelo derruba o que o Opus manteve → vai pro humano (nunca auto-aplica).
// NÃO é gate de CI (não avermelha PR). Ver memory/reguas/cross-model/ + README §Cross-model.
phase('Refutar')
const claims = pesquisas.flatMap((p) => p.oimpresso_acima.map((c) => ({ ...c, dimensao: p.dimensao })))
const refutados = (await parallel(capEstratificado('Refutar', claims, CAP_AGENTES_POR_FASE, log).map((c) => () => agent(
  promptRefuterFull(c),
  { label: `r:${c.ideia}`.slice(0, 48), phase: 'Refutar', schema: VERDICT, agentType: 'general-purpose', effort: 'medium' },
).then((v) => (v ? { ...c, verdict: v } : null))))).filter(Boolean)
log(`refutação: ${refutados.filter((r) => r.verdict.veredito === 'ACIMA_CONFIRMADO').length} acima · ${refutados.filter((r) => r.verdict.veredito === 'REFUTADO').length} derrubadas`)

// Negative control anti-Goodhart: injeta os plantados no MESMO refuter (fora do cap e do
// placar/ledger). Só a fase FULL refuta claims frescas — o canário nunca entra no ledger,
// logo nunca vira "vencida", logo o modo delta não precisa (nem deve) re-refutá-lo.
const canarioRefuter = await rodarCanarios()

// ── Fase 2.5 — Teste de INTEGRAÇÃO (o TODO tem peer, ou só as partes?) ────────
// O refutador julga slice-a-slice por construção; sem este passo a soma de peças-com-peer
// fabrica um "0 acima" falso (falácia de composição). Wagner 2026-07-10 — proibições §5.
phase('Integração')
const derrubadas = refutados.filter((r) => r.verdict.veredito !== 'ACIMA_CONFIRMADO')
const integrados = (await parallel(capEstratificado('Integração', derrubadas, CAP_AGENTES_POR_FASE, log).map((r) => () => agent(
  `TESTE DE INTEGRAÇÃO — BRAÇO DISCRIMINATIVO (emenda §5 2026-07-19: o veredito deu 81/81 DIFERENCIAL_SISTEMA em 8 runs = virou CARIMBO; ele PRECISA poder dar negativo). O refutador já achou peer pra ESTA peça isolada — não repita a busca da peça. Claim: "${r.ideia}" (dimensão ${r.dimensao}); refutador deu ${r.verdict.veredito} citando "${(r.verdict.quem_ja_faz || r.verdict.razao || '').slice(0, 300)}".\n` +
  `PASSO 1 — NOMEIE O INCREMENTO (campo \`incremento\`, OBRIGATÓRIO): em 1 frase concreta, o que a INTEGRAÇÃO acrescenta ALÉM da soma das peças que o refutador já pareou — a "cola", uma capacidade que um concorrente teria que CONSTRUIR e hoje não tem. ⚠️ Se você NÃO consegue nomear incremento algum além de "é dentro do oimpresso / é o nosso produto / é o nosso contexto" (isso é IDENTIDADE, não capacidade), então incremento="nenhum além da identidade" e o veredito É **REFUTADO_TB** (razão: sem incremento defensável).\n` +
  `PASSO 2 — CACE O PEER DO TODO (2-3 buscas no CONJUNTO, não na peça): REFUTADO_TB também dispara se UM ÚNICO produto/prática publicado monta um TODO EQUIVALENTE nos eixos que SUSTENTAM o incremento (ERP vertical + multi-tenant + auto-governança recursiva + loop medir→corrigir→travar que fecha) — MESMO em outro vertical/mercado. Diga quem em \`quem_monta_o_todo\`. [O antigo bar "no MESMO contexto do oimpresso" foi REMOVIDO: era tautológico — ninguém É o oimpresso, então dava DIFERENCIAL_SISTEMA sempre.]\n` +
  `GUARD ANTI-FALÁCIA-DE-COMPOSIÇÃO (§5 2026-07-10, INVIOLÁVEL): peers DIFERENTES cobrindo cada um UM eixo NÃO dispara REFUTADO_TB — somar slices-com-peer é exatamente o erro que esta fase existe pra barrar. REFUTADO_TB exige UM peer montando o todo equivalente, jamais a soma de vários.\n` +
  `DIFERENCIAL_SISTEMA (só quando os DOIS valem): (a) incremento nomeado e defensável E (b) nenhum peer ÚNICO monta o equivalente. É diferencial de instanciação/integração/recursão — proibido re-inflar a peça isolada como "acima". Default entre os dois: exija ou o incremento defensável (→DS) ou o peer-único-do-todo (→RTB); identidade sem incremento cai em RTB.`,
  { label: `i:${r.ideia}`.slice(0, 48), phase: 'Integração', schema: INTEG, agentType: 'general-purpose', effort: 'medium' },
).then((v) => (v ? { ...r, integ: v } : null))))).filter(Boolean)
log(`integração: ${integrados.filter((i) => i.integ.veredito === 'DIFERENCIAL_SISTEMA').length} diferenciais de sistema · ${integrados.filter((i) => i.integ.veredito === 'REFUTADO_TB').length} o todo também tem par`)

// ── CHECKPOINT 1 — as claims FECHARAM (refutação + integração): grava no ledger AGORA ─────────
// É exatamente este trabalho que as duas rodadas mortas de 2026-07-25/26 perderam por estar
// tudo no fim da transação (24 refutações + 15 integrações vivas só no journal do run).
const cpClaims = refutados.length
  ? await persistir('cp-claims', promptClaims(fit('claims', refutados.map((r) => {
      const i = integrados.find((x) => x.ideia === r.ideia && x.dimensao === r.dimensao)
      return {
        titulo: r.ideia, dimensao: r.dimensao,
        refutador: r.verdict.veredito, peer: r.verdict.quem_ja_faz || '',
        integracao: i ? i.integ.veredito : null, incremento: i ? i.integ.incremento : null,
      }
    }), 80_000), false))
  : null
log(`checkpoint claims (${refutados.length}): ${okPersist(cpClaims) ? 'gravado no ledger' : '⚠️ NÃO confirmado — retentativa na fase Persistir'}`)

// ── Fase 3 — Verificar FRAQUEZAS no repo vivo (a lição 7/9) ──────────────────
phase('Verificar')
const fraquezas = pesquisas.flatMap((p) => p.oimpresso_atras.map((f) => ({ fraqueza: f, dimensao: p.dimensao })))
const verificadas = (await parallel(capEstratificado('Verificar', fraquezas, CAP_AGENTES_POR_FASE, log).map((f) => () => agent(
  `A pesquisa marcou o oimpresso como FRACO em: "${f.fraqueza}" (dimensão ${f.dimensao}). ANTES de aceitar: cace no repo VIVO (paths ABSOLUTOS a partir de ${BASE}) mecanismos que JÁ cobrem isso total/parcialmente — .github/workflows (nomes dos checks!), scripts/governance, .claude/{skills,hooks}, prototipo-ui/*.mjs, gates-registry/required-checks-baseline. Precedente: numa rodada anterior 7 de 9 "fraquezas" JÁ existiam, invisíveis por desorganização. Dê a nota 0-10 SÓ com evidência (file:line ou prova de ausência) e diga onde indexar o achado (mapa 0330-corrente) se existia-mas-invisível.`,
  { label: `v:${f.fraqueza}`.slice(0, 48), phase: 'Verificar', schema: EXISTE, effort: 'high' },
).then((v) => (v ? { ...f, check: v } : null))))).filter(Boolean)
// Counter por veredito (achado #24) — denominador honesto pra grade: distingue
// "já existe inteiro" de "parcial" de "buraco real". `total` = fraquezas VERIFICADAS
// (pós-cap), não as ${fraquezas.length} levantadas; o corte já foi logado por capEstratificado.
const vTot = verificadas.filter((v) => v.check.veredito === 'JA_EXISTE_TOTAL').length
const vPar = verificadas.filter((v) => v.check.veredito === 'PARCIAL').length
const vNao = verificadas.filter((v) => v.check.veredito === 'NAO_EXISTE').length
log(`verificação: ${verificadas.length}/${fraquezas.length} fraquezas verificadas (resto cortado pelo cap, ver log acima) · JA_EXISTE_TOTAL=${vTot} · PARCIAL=${vPar} · NAO_EXISTE=${vNao} (buracos reais)`)

// Regra 16 MECANIZADA (composição fiel ao journal — adversário 2026-07-18, 2 strikes da classe
// composição≠journal: 07-10 e 07-18): os NÚMEROS fecham AQUI, em JS, antes do compositor.
// Nota da dimensão = média aritmética (1 decimal) das nota_sugerida dos verificadores DELA;
// dimensão sem fraqueza verificada com nota = null (declarada "sem nota nesta rodada" — nunca
// inventada nem herdada em silêncio). O compositor escreve prosa em volta e é PROIBIDO de alterar.
// SUBIU pra ANTES da fase Grade (2026-07-26): a composição é JS instantâneo e o retrato só depende
// dela — assim o CHECKPOINT 2 grava o retrato ANTES da prosa cara, que é onde a rodada morre.
const media1 = (xs) => Math.round((xs.reduce((a, b) => a + b, 0) / xs.length) * 10) / 10
const notasPorDim = {}
const rowsPorDim = {}
for (const d of DIMS) {
  const rows = verificadas.filter((v) => v.dimensao === d.key)
  const comNota = rows.filter((r) => typeof r.check.nota_sugerida === 'number')
  notasPorDim[d.key] = comNota.length ? media1(comNota.map((r) => r.check.nota_sugerida)) : null
  rowsPorDim[d.key] = rows.map((r) => ({ titulo: (r.fraqueza || '').slice(0, 120), veredito: r.check.veredito, nota: r.check.nota_sugerida, evidencia: (r.check.evidencia || '').slice(0, 200) }))
}
const placarJS = {
  claims: refutados.length,
  acima_confirmadas: refutados.filter((r) => r.verdict.veredito === 'ACIMA_CONFIRMADO').length,
  empatadas: refutados.filter((r) => r.verdict.veredito === 'EMPATADO').length,
  refutadas: refutados.filter((r) => r.verdict.veredito === 'REFUTADO').length,
  diferencial_sistema: integrados.filter((i) => i.integ.veredito === 'DIFERENCIAL_SISTEMA').length,
  refutado_tb: integrados.filter((i) => i.integ.veredito === 'REFUTADO_TB').length,
}
log(`notas determinísticas (média por dimensão): ${JSON.stringify(notasPorDim)} · placar JS: ${JSON.stringify(placarJS)}`)

// ── CHECKPOINT 2 — fraquezas + RETRATO, ANTES da prosa cara ──────────────────────────────────
// A cobertura é declarada no próprio retrato: rodada que perdeu agentes (ou que rodou só 1 eixo)
// entra como `full-parcial` com o motivo — retrato parcial NUNCA se passa por completo.
const dimsAlvo = DIMS.map((d) => d.key)
const cobertura = montarCobertura({
  modo: 'full',
  etapas: [
    { nome: 'pesquisar', esperado: DIMS.length, obtido: pesquisas.length },
    { nome: 'refutar', esperado: Math.min(claims.length, CAP_AGENTES_POR_FASE), obtido: refutados.length },
    { nome: 'integracao', esperado: Math.min(derrubadas.length, CAP_AGENTES_POR_FASE), obtido: integrados.length },
    { nome: 'verificar', esperado: Math.min(fraquezas.length, CAP_AGENTES_POR_FASE), obtido: verificadas.length },
  ],
  notas: notasPorDim, dimsAlvo, eixoDe: EIXO_DE, eixosTodos: EIXOS,
  extra: {
    selecao: SELECAO, // 'completa' | 'eixo' | 'dimensoes' — rodada por eixo NÃO é retrato do sistema
    fraquezas_levantadas: fraquezas.length,
    claims_levantadas: claims.length,
    canario_refuter: canarioRefuter,
  },
})
if (!cobertura.completo) log(`⚠️ rodada PARCIAL — o retrato vai declarar isso: ${cobertura.motivo_parcial}`)
if (SELECAO !== 'completa') log(`⚠️ seleção ${SELECAO} (${dimsAlvo.length} de ${DIMS_DEFAULT.length} dimensões) — retrato declara eixos_nao_medidos: [${cobertura.eixos_nao_medidos.join(', ') || 'nenhum'}]`)
const cpRetrato = await persistir('cp-retrato', promptRetrato({
  modoRetrato: cobertura.completo && SELECAO === 'completa' ? 'full' : 'full-parcial',
  notas: notasPorDim,
  proveniencia: Object.fromEntries(dimsAlvo.map((k) => [k, notasPorDim[k] == null
    ? 'sem nota nesta rodada (0 fraquezas verificadas com nota) — NÃO herde nem invente'
    : `medida por fraqueza (${rowsPorDim[k].filter((r) => typeof r.nota === 'number').length} verificações, média determinística 1 decimal)`])),
  cobertura, placar: placarJS,
  integHistInstrucao: `some os DESTA rodada ao acumulado do retrato anterior — vereditos_acumulados += ${placarJS.diferencial_sistema + placarJS.refutado_tb}, refutado_tb_acumulado += ${placarJS.refutado_tb}, runs += 1. Se não houver retrato anterior, use exatamente esses valores com runs: 1. (É o disclosure da regra 17 — o placar tem que poder crescer.)`,
  fraquezasRows: fit('rows', rowsPorDim, 120_000),
}))
log(`checkpoint retrato+fraquezas (${cobertura.completo ? 'completo' : 'PARCIAL'}): ${okPersist(cpRetrato) ? 'gravado no ledger' : '⚠️ NÃO confirmado — retentativa na fase Persistir'}`)

// ── Fase 4 — Grade final ──────────────────────────────────────────────────────
phase('Grade')
// ⚠️ TRUNCAGEM SILENCIOSA (corrigido 2026-07-17) — os `.slice()` cegos que ficavam aqui
// descartavam ~80% da evidência SEM avisar, e a grade saía com cara de completa.
// Medido na run wf_5ae5c554-67f: pesquisas 175.975→38.000 (-78%) · verificadas
// 114.969→18.000 (-84%, só 5 de 24 fraquezas visíveis) · refutados -86% · integrados -83%.
// Sintoma: 11 dimensões pesquisadas, grade final emitiu nota pra 3 — os eixos RODAR-E-OBSERVAR
// (ADR 0333) e SERVIR-O-NEGÓCIO (ADR 0334) sumiram, reproduzindo o ponto cego que essas
// ADRs existem pra fechar. Regra do tool Workflow: "No silent caps — log() what was dropped".
// Agora: limite folgado (cabe o corpus inteiro) + log do que cortar, se cortar.
const CAPS = { pesquisas: 400_000, refutados: 160_000, integrados: 160_000, verificadas: 260_000 }
// (o helper `fit` subiu pro bloco de persistência — o checkpoint que roda antes desta fase usa ele)
const pesquisasStr = fit('pesquisas', pesquisas, CAPS.pesquisas)
const refutadosStr = fit('refutados', refutados.map((r) => ({ ideia: r.ideia, v: r.verdict })), CAPS.refutados)
const integradosStr = fit('integrados', integrados.map((i) => ({ ideia: i.ideia, integ: i.integ })), CAPS.integrados)
const verificadasStr = fit('verificadas', verificadas, CAPS.verificadas)
log(`Grade vai ler: ${pesquisas.length} pesquisas · ${refutados.length} refutações · ${integrados.length} integrações · ${verificadas.length} verificações (corpus ${(pesquisasStr.length + refutadosStr.length + integradosStr.length + verificadasStr.length) / 1000 | 0}k chars)`)
const grade = await agent(
  `Escreva a GRADE DE RÉGUAS para Wagner (PT-BR, direto, sem ego inflado nem falsa modéstia). Datada de HOJE.\n\n` +
  `⚠️ NÚMEROS JÁ FECHADOS PELA COMPOSIÇÃO DETERMINÍSTICA (regra 16 — adversário 2026-07-18). NOTA POR DIMENSÃO = ${JSON.stringify(notasPorDim)} (média das fraquezas verificadas; null = sem fraqueza-com-nota nesta rodada — declare "sem nota" honestamente, NÃO invente). PLACAR = ${JSON.stringify(placarJS)}. Você é PROIBIDO de alterar, arredondar, fundir ou re-atribuir qualquer número: use EXATAMENTE estes. Seu trabalho é a PROSA (evidência, diferenciais, degraus, leitura fria) em volta dos números fechados.\n\n` +
  `ANTI-GOODHART (negative control do refuter · regra 17 — DISCLOSAR no placar): ${JSON.stringify(canarioRefuter)}. São artefatos PLANTADOS (claims absurdamente falsas) que o refuter TINHA que derrubar; goodhart>0 = o refuter CARIMBOU e os vereditos da rodada ficam suspeitos. É o análogo do gate-selftest aplicado ao layer de agente e a prova controlada de que o braço negativo dispara (cf. REFUTADO_TB).\n\n` +
  `PESQUISAS: ${pesquisasStr}\nREFUTAÇÃO slice-a-slice: ${refutadosStr}\nTESTE DE INTEGRAÇÃO (DIFERENCIAL_SISTEMA = à-frente-por-integração, o peer só cobre a peça; REFUTADO_TB = o todo também tem par): ${integradosStr}\nVERIFICAÇÃO NO REPO (nota só com evidência): ${verificadasStr}\n\nCOBERTURA OBRIGATÓRIA: as ${pesquisas.length} dimensões acima cobrem TRÊS eixos — (1) CONSTRUIR-E-GOVERNAR, (2) RODAR-E-OBSERVAR (observabilidade-agente · qualidade-drift-ia-producao · seguranca-do-agente · custo-eficiencia — eixo add pela ADR 0333 porque era ponto cego), (3) SERVIR-O-NEGÓCIO (inteligencia-de-negocio — ponto cego da ADR 0334). A grade DEVE emitir nota pras dimensões dos TRÊS eixos; se um eixo sair sem fraqueza/nota, o ponto cego que as 0333/0334 fecharam volta pela síntese. Declare no cabeçalho as ${pesquisas.length} dimensões — não só as que renderam mais texto.\n\nDUAS REGRAS ANTES DE ESCREVER: (a) NÃO reporte "0 acima" a partir de refutação de slices — o placar de superioridade tem DUAS colunas distintas: "acima-de-categoria" (ACIMA_CONFIRMADO) E "à-frente-por-integração" (DIFERENCIAL_SISTEMA — o diferencial real quando ninguém monta o TODO no mesmo contexto; NÃO re-inflar a peça isolada). (b) CREDITE O QUE JÁ SHIPOU: antes de listar um gap/roubar como aberto, cheque em ${BASE} (git log recente + arquivos novos) se já foi fechado desde o último retrato; se sim, marque FEITO e não re-liste.\n\nEstrutura: 1) placar honesto (acima-de-categoria · à-frente-por-integração · empatadas · refutadas); 2) DIFERENCIAIS REAIS (os DIFERENCIAL_SISTEMA, na altitude do sistema, com o limite honesto de cada um); 3) GRADE das fraquezas — técnica · régua (quem+prática+fonte) · critério objetivo · nota COM evidência · próximo degrau; 4) JÁ FEITO desde o último retrato; 5) onde a régua é você (empates a defender); 6) O QUE ROUBAR top-8 (impacto÷esforço, onde plugar) — só o que NÃO shipou; 7) CHIPS SUGERIDOS (1 por fraqueza real, ressalva do adversário embutida); 8) REJEITADOS → proibições §5; 9) leitura fria (3 frases). Regra: nenhuma nota sem evidência citada.`,
  { label: 'grade-final', phase: 'Grade', effort: 'high' }, // era 'max' — composição determinística (regra 16) deixou o agente só com a prosa
)

// ── Fase Persistir (Órgão 1) — agora é RETENTATIVA dos checkpoints ────────────
// O ledger JÁ foi gravado no meio da rodada (CP-claims após Integração · CP-retrato após a
// composição). Esta fase só gasta agente se algum checkpoint não confirmou — se os dois
// confirmaram, custa ZERO. Fecha a pendência da regra 12 da skill ("notas precisam de artefato
// versionado") mesmo quando a rodada morre depois da Verificar (os 2 casos de 2026-07-25/26).
phase('Persistir')
const pendentes = []
if (refutados.length && !okPersist(cpClaims)) pendentes.push('claims')
if (!okPersist(cpRetrato)) pendentes.push('retrato+fraquezas')
const retentativa = {}
if (!pendentes.length) log('ledger já gravado pelos checkpoints (claims + fraquezas + retrato) — nada a re-persistir (0 agentes)')
else log(`⚠️ checkpoint(s) sem confirmação: ${pendentes.join(' · ')} — retentativa`)
if (pendentes.includes('retrato+fraquezas')) {
  retentativa.retrato = await persistir('re-retrato', RETENTAR(promptRetrato({
    modoRetrato: cobertura.completo && SELECAO === 'completa' ? 'full' : 'full-parcial',
    notas: notasPorDim,
    proveniencia: Object.fromEntries(dimsAlvo.map((k) => [k, notasPorDim[k] == null
      ? 'sem nota nesta rodada (0 fraquezas verificadas com nota) — NÃO herde nem invente'
      : `medida por fraqueza (${rowsPorDim[k].filter((r) => typeof r.nota === 'number').length} verificações, média determinística 1 decimal)`])),
    cobertura, placar: placarJS,
    integHistInstrucao: `some os DESTA rodada ao acumulado do retrato anterior — vereditos_acumulados += ${placarJS.diferencial_sistema + placarJS.refutado_tb}, refutado_tb_acumulado += ${placarJS.refutado_tb}, runs += 1. Se não houver retrato anterior, use exatamente esses valores com runs: 1.`,
    fraquezasRows: fit('rows', rowsPorDim, 120_000),
  })))
}
if (pendentes.includes('claims')) {
  retentativa.claims = await persistir('re-claims', RETENTAR(promptClaims(fit('claims', refutados.map((r) => {
    const i = integrados.find((x) => x.ideia === r.ideia && x.dimensao === r.dimensao)
    return { titulo: r.ideia, dimensao: r.dimensao, refutador: r.verdict.veredito, peer: r.verdict.quem_ja_faz || '', integracao: i ? i.integ.veredito : null, incremento: i ? i.integ.incremento : null }
  }), 80_000), false)))
}
const persistencia = { claims: cpClaims, retrato: cpRetrato, pendentes, retentativa }

return {
  modo: cobertura.completo && SELECAO === 'completa' ? 'full' : 'full-parcial',
  dimensoes: pesquisas.length,
  cobertura, // declara o que MEDIU e o que não mediu — o retorno também não se passa por completo
  notas: notasPorDim,
  placar: placarJS,
  acima_confirmadas: placarJS.acima_confirmadas,
  diferenciais_de_sistema: placarJS.diferencial_sistema,
  refutadas: placarJS.refutadas,
  canario_refuter: canarioRefuter, // negative control anti-Goodhart (chip orq-anti-goodhart)
  fraquezas_com_algo_existente: verificadas.filter((v) => v.check.veredito !== 'NAO_EXISTE').length,
  grade,
  persistencia,
}
