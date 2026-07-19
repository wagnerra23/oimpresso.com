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
    { title: 'Persistir', detail: 'grava retrato/claims/fraquezas no ledger memory/reguas/ — o estado do looping' },
  ],
}

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
  { key: 'spec-governanca', escopo: 'spec-driven development + governança de agentes (Spec Kit, Kiro, Codex, Cursor, Tessl; gates/required; ratchets)' },
  { key: 'design-to-code', escopo: 'design→code fidelity (Figma Code Connect/Dev Mode, v0, Builder.io; VRT: Chromatic/Applitools/Percy; tokens DTCG/Style Dictionary)' },
  { key: 'memoria-conhecimento', escopo: 'memória de agente + sobrevivência de conhecimento (Letta, mem0, Zep, LangMem; docs-as-code freshness: Swimm, Dosu; ADR tooling)' },
  { key: 'orquestracao-adversarial', escopo: 'multi-agente + verificação adversarial (Anthropic orchestrator-worker, Devin/Cognition, Jules, Amp, Agent HQ)' },
  { key: 'evals-outcome', escopo: 'evals e medição de outcome de agentes (Braintrust, LangSmith, DORA/DX for AI; goal-based evals; scorecards de processo)' },
  { key: 'erp-ia-produto', escopo: 'IA embarcada em ERP — o PRODUTO (SAP Joule, Dynamics Copilot, Odoo AI; BR: Bling/Tiny/Omie/Conta Azul)' },
  // ── Eixo RODAR-E-OBSERVAR (a IA que o sistema produz, viva em prod — não o loop de construir/governar). ──
  // Adicionadas 2026-07-10: as 9 réguas v1→v3 só mediam CONSTRUIR-E-GOVERNAR; este eixo era ponto cego. Ver ADR 0333 (emenda à 0330).
  { key: 'observabilidade-agente', escopo: 'traces/custo/latência/alucinação do agente e da IA em produção (Langfuse, LangSmith, Braintrust, OpenTelemetry GenAI semantic conventions; spans + custo por run). Régua = painel vivo + heartbeat que prova o FLUXO (não só a via), não log solto. Estado (2026-07): Langfuse LIVE desde 2026-07-02 + emissão OTel GenAI (LaravelAiSdkDriver::emitirOtelGenAi); heartbeat langfuse_trace_uptime_24h no HealthCheckCommand shipado 2026-07-17 (#4425, lê a fonte real da API /api/public/traces) + fix da prova multi-tenant do OTel biz=1 (#4427) + advisory desligado-prod (#4444). Baseline da dimensão na grade v2 (2026-07-17) = 6,5/10 — medir o Δ pós-chips' },
  { key: 'qualidade-drift-ia-producao', escopo: 'qualidade + drift da IA-PRODUTO (a Jana) em prod — recall/hallucination gold-set + canary de drift (RAGAS, DeepEval, continuous-eval). DISTINTO de evals-outcome (que é DORA/outcome do agente-DEV): aqui a régua é a resposta da Jana ao cliente. Projeto tem jana-ragas-gate JÁ + #3 P0 drift-sentinel pendente' },
  { key: 'seguranca-do-agente', escopo: 'defesa a prompt-injection + fronteira instrução-vs-dado + modelo de permissão de tools/hooks (OWASP LLM Top 10, Anthropic agent-safety, Google SAIF). Régua = superfície de tool/hook auditada + injection tratada, não confiança implícita' },
  { key: 'custo-eficiencia', escopo: 'token/crédito por tarefa como MÉTRICA medida — hoje é só valor cultural do Wagner, sem número (Cursor, Cognition/Devin cost-per-task). Régua = custo por PR/feature observável, não "economize crédito" verbal' },
  // ── Eixo SERVIR-O-NEGÓCIO (a inteligência de negócio do PRODUTO — o ponto cego que o adversário 2026-07-10 expôs). ──
  // A grade media CONSTRUIR-E-GOVERNAR e RODAR-E-OBSERVAR, mas nunca "o sistema serve o negócio (A+B) ou governa a si mesmo (C)?".
  { key: 'inteligencia-de-negocio', escopo: 'inteligência de negócio embarcada + cliente-como-sinal: a IA responde o DONO do negócio com dado real do tenant (SAP Joule, Odoo AI, Dynamics Copilot) e o backlog nasce de sinal de cliente/uso, não de régua/prazo (product-led: Linear, Productboard, PLG). Régua = (1) equilíbrio entre construir-produto (A+B) e governar-o-processo (C) — fonte interna scripts/governance/negocio-vs-governanca-ratio.mjs; (2) órgão-sensor do sinal do cliente vivo e conectado (loop client_signal→cycle_goal), não apodrecido. DISTINTO de erp-ia-produto (features do ERP) e de qualidade-drift-ia-producao (recall da Jana): aqui a régua é se a ENERGIA do sistema serve o negócio ou a própria governança. Ponto cego confirmado pelo adversário-inteligencia-negocio 2026-07-10.' },
]

// Resolve args.dimensoes pra rodada PARCIAL (re-medir 1 dimensão após um chip). Aceita:
//   ["observabilidade-agente"]            → string: resolve o escopo do DIMS_DEFAULT pela key
//   [{ key, escopo }]                     → objeto: usa direto (escopo custom)
// Sem esta resolução uma STRING vira {key:undefined, escopo:undefined} → o prompt do pesquisador
// fica "SUA DIMENSÃO: undefined — undefined" e o agente se auto-cura na dimensão transversal do
// dossiê → a grade mede a dimensão ERRADA em silêncio. É a MESMA classe da truncagem silenciosa
// corrigida na Fase 4. Incidente 2026-07-17: dimensoes:["observabilidade-agente"] rodou a grade de
// governança executável por 1,77M tokens. String sem match no default: escopo = a própria key +
// log() (nunca undefined mudo). Regra do tool Workflow: "No silent caps — log() what was dropped".
const DIMS = (() => {
  const req = A && A.dimensoes
  if (!Array.isArray(req) || req.length === 0) return DIMS_DEFAULT
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
const INTEG = { type: 'object', additionalProperties: false, required: ['veredito', 'razao'], properties: {
  veredito: { type: 'string', enum: ['DIFERENCIAL_SISTEMA', 'REFUTADO_TB'] }, razao: { type: 'string' }, quem_monta_o_todo: { type: 'string' } } }

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

// ── MODO DELTA (Órgão 2 da máquina — ADR proposta reguas-loop-maquina-evolucao) ──
// Rodada INCREMENTAL dirigida pelo ledger memory/reguas/ (alvo ≤2,5M tokens vs ~11,4M full):
// re-verifica SÓ dimensões com Δ material de commits nos paths mapeados (config.paths_por_dimensao)
// e re-refuta SÓ claims com TTL vencido. NÃO pesquisa mercado (regra 5 da skill: lado-mercado
// reusado), NÃO roda Integração (claim nova só nasce no full — e o braço negativo nunca disparou:
// 0 REFUTADO_TB em 81 vereditos, ledger 2026-07-18). Composição DETERMINÍSTICA (regra 16 do
// adversário 2026-07-18 mecanizada): nota = média 1-decimal das fraquezas re-verificadas; sem Δ
// herda com flag. Disclosure do placar sai do ledger (regra 17 mecanizada).
if (MODO === 'delta') {
  phase('Delta-scan')
  const SCAN = { type: 'object', required: ['dims_delta', 'claims_vencidas', 'fraquezas'], properties: {
    erro: { type: 'string', description: 'preencher SÓ se o ledger estiver ausente/ilegível' },
    ultimo_retrato: { type: 'object', properties: { data: { type: 'string' }, notas: { type: 'object', additionalProperties: { type: 'number' } }, integ_hist: { type: 'object' } } },
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
  const minC = scan.delta_min_commits || 3
  const forcadas = Array.isArray(A && A.dimensoes) && A.dimensoes.length ? DIMS.map((d) => d.key) : []
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

  phase('Grade')
  // Regra 16 mecanizada: números fechados AQUI (JS), prosa depois — o agente não altera nota.
  const media1 = (xs) => Math.round((xs.reduce((a, b) => a + b, 0) / xs.length) * 10) / 10
  const notasAntigas = (scan.ultimo_retrato && scan.ultimo_retrato.notas) || {}
  const notasNovas = {}
  const proveniencia = {}
  for (const k of Object.keys(notasAntigas)) {
    const rows = verificadas.filter((v) => v.dimensao === k && typeof v.check.nota_sugerida === 'number')
    if (rows.length) { notasNovas[k] = media1(rows.map((r) => r.check.nota_sugerida)); proveniencia[k] = `re-medida (${rows.length} fraquezas, média determinística)` }
    else { notasNovas[k] = notasAntigas[k]; proveniencia[k] = ativas.includes(k) ? 'herdada (dim ativa mas 0 fraquezas com nota)' : 'herdada (sem Δ material)' }
  }
  const integHist = (scan.ultimo_retrato && scan.ultimo_retrato.integ_hist) || {}
  const prosa = await agent(
    `PROSA da rodada DELTA da grade de réguas (PT-BR, ≤450 palavras, datada de hoje). Os NÚMEROS estão FECHADOS pela composição determinística (regra 16 — PROIBIDO alterar, fundir ou re-atribuir nota): ${JSON.stringify({ notasNovas, notasAntigas, proveniencia, dims_ativas: ativas, dims_delta: scan.dims_delta })}.\nFraquezas re-medidas (evidência nova): ${JSON.stringify(verificadas.map((v) => ({ id: v.id, dimensao: v.dimensao, titulo: v.titulo, de: v.nota, para: v.check.nota_sugerida, veredito: v.check.veredito, evidencia: (v.check.evidencia || '').slice(0, 200) })))}.\nClaims re-vereditadas: ${JSON.stringify(reRefutadas.map((r) => ({ id: r.id, de: r.refutador, para: r.verdict.veredito, peer: r.verdict.quem_ja_faz || '' })))}.\nEscreva: (1) o que mudou e por quê (Δ por dimensão re-medida, com a evidência); (2) o que segue herdado; (3) DISCLOSURE OBRIGATÓRIO do placar (regra 17): REFUTADO_TB acumulado ${JSON.stringify(integHist)} — nunca disparou; o valor está nas razões, não no binário; (4) próximo degrau mais barato. NADA de nota nova inventada.`,
    { label: 'prosa-delta', phase: 'Grade', effort: 'medium' },
  )

  phase('Persistir')
  const persist = await agent(
    `PERSISTIR a rodada delta no ledger (${BASE}/${LEDGER_DIR}/). Passos EXATOS:\n` +
    `1. retratos.json: insira NO TOPO do array um retrato novo {data: hoje (ISO), modo: "delta", regra_nota: "media-deterministica-v1", notas: ${JSON.stringify(notasNovas)}, proveniencia_notas: ${JSON.stringify(proveniencia)}, integ_hist: (copie do retrato anterior — delta não roda Integração), links: []}. NUNCA edite retratos antigos (append-only).\n` +
    `2. fraquezas.json: pra cada re-medida em ${JSON.stringify(verificadas.map((v) => ({ id: v.id, nota: v.check.nota_sugerida, veredito: v.check.veredito, evidencia: (v.check.evidencia || '').slice(0, 250), onde_indexar: v.check.onde_indexar || null })))}: atualize nota/veredito/evidencia/data(hoje); se onde_indexar veio preenchido, sete existia_invisivel:true e grave onde_indexar. ⚠️ PRESERVE o valor de \`indexado\` que JÁ existe na entrada — NUNCA rebaixe indexado:true→false nem crie indexado:false onde não havia campo (um mecanismo já indexado no mapa/índice não volta pra fila; senão o próximo delta re-descobre eternamente — bug pego no teste 2026-07-19).\n` +
    `3. claims.json: pra cada re-vereditada em ${JSON.stringify(reRefutadas.map((r) => ({ id: r.id, veredito: r.verdict.veredito, peer: r.verdict.quem_ja_faz || r.verdict.razao || '' })))}: atualize refutador/peer/data_veredito(hoje); ttl_dias = 30 se o veredito novo for ACIMA_CONFIRMADO, senão 90.\n` +
    `4. Valide os 3 JSONs com node (JSON.parse) antes de terminar. Retorne resumo: o que gravou, contagens, e a fila de indexação pendente (rode node ${BASE}/scripts/governance/reguas-indexar.mjs se existir).`,
    { label: 'persistir', phase: 'Persistir', effort: 'low', model: MODELO_MECANICO, agentType: 'general-purpose' },
  )
  return {
    modo: 'delta',
    dims_ativas: ativas,
    fraquezas_re_medidas: verificadas.length,
    claims_re_vereditadas: reRefutadas.length,
    notas: notasNovas,
    proveniencia,
    prosa,
    persistencia: persist,
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
const pesquisas = (await parallel(DIMS.map((d) => () => agent(
  `${COMUM}\n\nSUA DIMENSÃO: ${d.key} — ${d.escopo}`,
  { label: `p:${d.key}`, phase: 'Pesquisar', schema: RESEARCH_SCHEMA, agentType: 'general-purpose', effort: 'high' },
)))).filter(Boolean)
log(`${pesquisas.length}/${DIMS.length} dimensões pesquisadas`)

// ── Fase 2 — Refutar toda claim "acima" (default: derrubar) ──────────────────
phase('Refutar')
const claims = pesquisas.flatMap((p) => p.oimpresso_acima.map((c) => ({ ...c, dimensao: p.dimensao })))
const refutados = (await parallel(capEstratificado('Refutar', claims, CAP_AGENTES_POR_FASE, log).map((c) => () => agent(
  `REFUTADOR (contexto zero — você não herda a pesquisa). Claim: oimpresso está ACIMA do mercado em "${c.ideia}" (${c.porque_acima}; dimensão ${c.dimensao}). Busque na web (2-4 buscas) quem JÁ faz igual/melhor em produção. Achou → REFUTADO (diga quem). Parecido → EMPATADO. Só ACIMA_CONFIRMADO sem par publicado. Default cético.`,
  { label: `r:${c.ideia}`.slice(0, 48), phase: 'Refutar', schema: VERDICT, agentType: 'general-purpose', effort: 'medium' },
).then((v) => (v ? { ...c, verdict: v } : null))))).filter(Boolean)
log(`refutação: ${refutados.filter((r) => r.verdict.veredito === 'ACIMA_CONFIRMADO').length} acima · ${refutados.filter((r) => r.verdict.veredito === 'REFUTADO').length} derrubadas`)

// ── Fase 2.5 — Teste de INTEGRAÇÃO (o TODO tem peer, ou só as partes?) ────────
// O refutador julga slice-a-slice por construção; sem este passo a soma de peças-com-peer
// fabrica um "0 acima" falso (falácia de composição). Wagner 2026-07-10 — proibições §5.
phase('Integração')
const derrubadas = refutados.filter((r) => r.verdict.veredito !== 'ACIMA_CONFIRMADO')
const integrados = (await parallel(capEstratificado('Integração', derrubadas, CAP_AGENTES_POR_FASE, log).map((r) => () => agent(
  `TESTE DE INTEGRAÇÃO (o refutador já achou peer pra ESTA peça isolada — não repita a busca da peça). Claim: "${r.ideia}" (dimensão ${r.dimensao}); refutador deu ${r.verdict.veredito} citando "${(r.verdict.quem_ja_faz || r.verdict.razao || '').slice(0, 300)}". PERGUNTA ÚNICA: algum produto/prática publicado monta o TODO INTEGRADO no MESMO contexto do oimpresso — a pilha inteira DENTRO de um ERP vertical multi-tenant BR em produção, aplicada A SI MESMA (governança recursiva: o agente-codador cita o próprio §5 pra se auto-barrar) + o loop medir→corrigir→travar que de fato fecha? Busque 2-3× o CONJUNTO, não a peça. Se um peer monta o todo no mesmo contexto → REFUTADO_TB (a integração também tem par; diga quem). Se os peers só cobrem PEÇAS e ninguém monta o conjunto → DIFERENCIAL_SISTEMA (o diferencial é de instanciação/integração/recursão, NÃO da categoria — proibido re-inflar a peça isolada como "acima"). Default: exija o peer do TODO.`,
  { label: `i:${r.ideia}`.slice(0, 48), phase: 'Integração', schema: INTEG, agentType: 'general-purpose', effort: 'medium' },
).then((v) => (v ? { ...r, integ: v } : null))))).filter(Boolean)
log(`integração: ${integrados.filter((i) => i.integ.veredito === 'DIFERENCIAL_SISTEMA').length} diferenciais de sistema · ${integrados.filter((i) => i.integ.veredito === 'REFUTADO_TB').length} o todo também tem par`)

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
const fit = (nome, obj, cap) => {
  const s = JSON.stringify(obj)
  if (s.length <= cap) return s
  log(`⚠️ TRUNCADO ${nome}: ${s.length} → ${cap} chars (-${(((s.length - cap) / s.length) * 100).toFixed(0)}%) — a grade NÃO viu tudo`)
  return s.slice(0, cap)
}
const pesquisasStr = fit('pesquisas', pesquisas, CAPS.pesquisas)
const refutadosStr = fit('refutados', refutados.map((r) => ({ ideia: r.ideia, v: r.verdict })), CAPS.refutados)
const integradosStr = fit('integrados', integrados.map((i) => ({ ideia: i.ideia, integ: i.integ })), CAPS.integrados)
const verificadasStr = fit('verificadas', verificadas, CAPS.verificadas)
log(`Grade vai ler: ${pesquisas.length} pesquisas · ${refutados.length} refutações · ${integrados.length} integrações · ${verificadas.length} verificações (corpus ${(pesquisasStr.length + refutadosStr.length + integradosStr.length + verificadasStr.length) / 1000 | 0}k chars)`)
// Regra 16 MECANIZADA (composição fiel ao journal — adversário 2026-07-18, 2 strikes da classe
// composição≠journal: 07-10 e 07-18): os NÚMEROS fecham AQUI, em JS, antes do compositor.
// Nota da dimensão = média aritmética (1 decimal) das nota_sugerida dos verificadores DELA;
// dimensão sem fraqueza verificada com nota = null (declarada "sem nota nesta rodada" — nunca
// inventada nem herdada em silêncio). O compositor escreve prosa em volta e é PROIBIDO de alterar.
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
const grade = await agent(
  `Escreva a GRADE DE RÉGUAS para Wagner (PT-BR, direto, sem ego inflado nem falsa modéstia). Datada de HOJE.\n\n` +
  `⚠️ NÚMEROS JÁ FECHADOS PELA COMPOSIÇÃO DETERMINÍSTICA (regra 16 — adversário 2026-07-18). NOTA POR DIMENSÃO = ${JSON.stringify(notasPorDim)} (média das fraquezas verificadas; null = sem fraqueza-com-nota nesta rodada — declare "sem nota" honestamente, NÃO invente). PLACAR = ${JSON.stringify(placarJS)}. Você é PROIBIDO de alterar, arredondar, fundir ou re-atribuir qualquer número: use EXATAMENTE estes. Seu trabalho é a PROSA (evidência, diferenciais, degraus, leitura fria) em volta dos números fechados.\n\n` +
  `PESQUISAS: ${pesquisasStr}\nREFUTAÇÃO slice-a-slice: ${refutadosStr}\nTESTE DE INTEGRAÇÃO (DIFERENCIAL_SISTEMA = à-frente-por-integração, o peer só cobre a peça; REFUTADO_TB = o todo também tem par): ${integradosStr}\nVERIFICAÇÃO NO REPO (nota só com evidência): ${verificadasStr}\n\nCOBERTURA OBRIGATÓRIA: as ${pesquisas.length} dimensões acima cobrem TRÊS eixos — (1) CONSTRUIR-E-GOVERNAR, (2) RODAR-E-OBSERVAR (observabilidade-agente · qualidade-drift-ia-producao · seguranca-do-agente · custo-eficiencia — eixo add pela ADR 0333 porque era ponto cego), (3) SERVIR-O-NEGÓCIO (inteligencia-de-negocio — ponto cego da ADR 0334). A grade DEVE emitir nota pras dimensões dos TRÊS eixos; se um eixo sair sem fraqueza/nota, o ponto cego que as 0333/0334 fecharam volta pela síntese. Declare no cabeçalho as ${pesquisas.length} dimensões — não só as que renderam mais texto.\n\nDUAS REGRAS ANTES DE ESCREVER: (a) NÃO reporte "0 acima" a partir de refutação de slices — o placar de superioridade tem DUAS colunas distintas: "acima-de-categoria" (ACIMA_CONFIRMADO) E "à-frente-por-integração" (DIFERENCIAL_SISTEMA — o diferencial real quando ninguém monta o TODO no mesmo contexto; NÃO re-inflar a peça isolada). (b) CREDITE O QUE JÁ SHIPOU: antes de listar um gap/roubar como aberto, cheque em ${BASE} (git log recente + arquivos novos) se já foi fechado desde o último retrato; se sim, marque FEITO e não re-liste.\n\nEstrutura: 1) placar honesto (acima-de-categoria · à-frente-por-integração · empatadas · refutadas); 2) DIFERENCIAIS REAIS (os DIFERENCIAL_SISTEMA, na altitude do sistema, com o limite honesto de cada um); 3) GRADE das fraquezas — técnica · régua (quem+prática+fonte) · critério objetivo · nota COM evidência · próximo degrau; 4) JÁ FEITO desde o último retrato; 5) onde a régua é você (empates a defender); 6) O QUE ROUBAR top-8 (impacto÷esforço, onde plugar) — só o que NÃO shipou; 7) CHIPS SUGERIDOS (1 por fraqueza real, ressalva do adversário embutida); 8) REJEITADOS → proibições §5; 9) leitura fria (3 frases). Regra: nenhuma nota sem evidência citada.`,
  { label: 'grade-final', phase: 'Grade', effort: 'high' }, // era 'max' — composição determinística (regra 16) deixou o agente só com a prosa
)

// ── Fase Persistir (Órgão 1) — grava o retrato no ledger memory/reguas/ ────────
// Fecha a pendência da regra 12 da skill ("notas precisam de artefato versionado").
// O agente só TRANSCREVE os números já fechados (regra 16) + as claims/fraquezas — não decide nota.
phase('Persistir')
const persistencia = await agent(
  `PERSISTIR o retrato FULL no ledger ${BASE}/${LEDGER_DIR}/ (crie os arquivos se não existirem — schema no README.md de lá). NÚMEROS FECHADOS (transcreva, não recalcule):\n` +
  `- notas por dimensão: ${JSON.stringify(notasPorDim)}\n- placar: ${JSON.stringify(placarJS)}\n` +
  `- fraquezas (id derive de dimensao+slug do título): ${fit('rows', rowsPorDim, 120_000)}\n` +
  `- claims (refutador + integração): ${fit('claims', refutados.map((r) => ({ titulo: r.ideia, dimensao: r.dimensao, refutador: r.verdict.veredito, peer: r.verdict.quem_ja_faz || '' })), 80_000)}\n` +
  `Passos: (1) retratos.json — insira NO TOPO {data: hoje ISO, modo:"full", regra_nota:"media-deterministica-v1", notas, placar, integ_hist:{...copie do retrato anterior se existir, senão {vereditos_acumulados: ${placarJS.diferencial_sistema + placarJS.refutado_tb}, refutado_tb_acumulado: ${placarJS.refutado_tb}}}, links:[]}; NUNCA edite retrato antigo (append-only). (2) fraquezas.json — upsert por id (nota/veredito/evidencia/degrau/data hoje; existia_invisivel+onde_indexar quando a verificação indicou). ⚠️ PRESERVE o \`indexado\` existente — NUNCA rebaixe indexado:true→false (mecanismo já indexado não volta pra fila). (3) claims.json — upsert por id (data_veredito hoje; ttl 30 se ACIMA_CONFIRMADO senão 90; preserve correcao_obrigatoria existente). (4) valide os 3 com JSON.parse. Retorne resumo + rode node ${BASE}/scripts/governance/reguas-indexar.mjs pra listar a fila de indexação.`,
  { label: 'persistir-full', phase: 'Persistir', effort: 'low', model: MODELO_MECANICO, agentType: 'general-purpose' },
)

return {
  modo: 'full',
  dimensoes: pesquisas.length,
  notas: notasPorDim,
  placar: placarJS,
  acima_confirmadas: placarJS.acima_confirmadas,
  diferenciais_de_sistema: placarJS.diferencial_sistema,
  refutadas: placarJS.refutadas,
  fraquezas_com_algo_existente: verificadas.filter((v) => v.check.veredito !== 'NAO_EXISTE').length,
  grade,
  persistencia,
}
