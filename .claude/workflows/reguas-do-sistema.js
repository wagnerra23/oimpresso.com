export const meta = {
  name: 'reguas-do-sistema',
  description: 'Grade de réguas do IA OS vs acima-do-mercado: pesquisa por dimensão → refutação adversarial → verificação no repo vivo → grade com evidência + chips sugeridos',
  whenToUse: 'Skill reguas-do-sistema (cadência trimestral, pós-conclusão de leva de chips, ou Wagner pedir "grade de réguas"/"onde sou fraco"/"acima do mercado")',
  phases: [
    { title: 'Dossiê', detail: 'monta o retrato do sistema a partir do mapa vivo (ADR 0330-corrente) + fraquezas conhecidas' },
    { title: 'Pesquisar', detail: '1 pesquisador web por dimensão: líderes 2026 + a prática exata + fontes' },
    { title: 'Refutar', detail: 'cético derruba toda claim "estamos acima" (default: derrubar)' },
    { title: 'Verificar', detail: 'toda fraqueza é caçada no REPO VIVO antes da nota — a lição 7/9' },
    { title: 'Grade', detail: 'notas com evidência + próximo degrau + chips sugeridos + rejeitados→§5' },
  ],
}

// ── Config (args sobrescrevem) ────────────────────────────────────────────────
// args: { base?: 'D:/caminho/worktree-fresco-do-main', dimensoes?: [{key,escopo}] }
const BASE = (args && args.base) || 'AJUSTE: passe args.base = worktree FRESCO do origin/main'
const DIMS = (args && args.dimensoes) || [
  { key: 'spec-governanca', escopo: 'spec-driven development + governança de agentes (Spec Kit, Kiro, Codex, Cursor, Tessl; gates/required; ratchets)' },
  { key: 'design-to-code', escopo: 'design→code fidelity (Figma Code Connect/Dev Mode, v0, Builder.io; VRT: Chromatic/Applitools/Percy; tokens DTCG/Style Dictionary)' },
  { key: 'memoria-conhecimento', escopo: 'memória de agente + sobrevivência de conhecimento (Letta, mem0, Zep, LangMem; docs-as-code freshness: Swimm, Dosu; ADR tooling)' },
  { key: 'orquestracao-adversarial', escopo: 'multi-agente + verificação adversarial (Anthropic orchestrator-worker, Devin/Cognition, Jules, Amp, Agent HQ)' },
  { key: 'evals-outcome', escopo: 'evals e medição de outcome de agentes (Braintrust, LangSmith, DORA/DX for AI; goal-based evals; scorecards de processo)' },
  { key: 'erp-ia-produto', escopo: 'IA embarcada em ERP — o PRODUTO (SAP Joule, Dynamics Copilot, Odoo AI; BR: Bling/Tiny/Omie/Conta Azul)' },
]

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
const refutados = (await parallel(claims.slice(0, 24).map((c) => () => agent(
  `REFUTADOR (contexto zero — você não herda a pesquisa). Claim: oimpresso está ACIMA do mercado em "${c.ideia}" (${c.porque_acima}; dimensão ${c.dimensao}). Busque na web (2-4 buscas) quem JÁ faz igual/melhor em produção. Achou → REFUTADO (diga quem). Parecido → EMPATADO. Só ACIMA_CONFIRMADO sem par publicado. Default cético.`,
  { label: `r:${c.ideia}`.slice(0, 48), phase: 'Refutar', schema: VERDICT, agentType: 'general-purpose', effort: 'medium' },
).then((v) => ({ ...c, verdict: v }))))).filter(Boolean)
log(`refutação: ${refutados.filter((r) => r.verdict.veredito === 'ACIMA_CONFIRMADO').length} acima · ${refutados.filter((r) => r.verdict.veredito === 'REFUTADO').length} derrubadas`)

// ── Fase 3 — Verificar FRAQUEZAS no repo vivo (a lição 7/9) ──────────────────
phase('Verificar')
const fraquezas = pesquisas.flatMap((p) => p.oimpresso_atras.map((f) => ({ fraqueza: f, dimensao: p.dimensao })))
const verificadas = (await parallel(fraquezas.slice(0, 24).map((f) => () => agent(
  `A pesquisa marcou o oimpresso como FRACO em: "${f.fraqueza}" (dimensão ${f.dimensao}). ANTES de aceitar: cace no repo VIVO (paths ABSOLUTOS a partir de ${BASE}) mecanismos que JÁ cobrem isso total/parcialmente — .github/workflows (nomes dos checks!), scripts/governance, .claude/{skills,hooks}, prototipo-ui/*.mjs, gates-registry/required-checks-baseline. Precedente: numa rodada anterior 7 de 9 "fraquezas" JÁ existiam, invisíveis por desorganização. Dê a nota 0-10 SÓ com evidência (file:line ou prova de ausência) e diga onde indexar o achado (mapa 0330-corrente) se existia-mas-invisível.`,
  { label: `v:${f.fraqueza}`.slice(0, 48), phase: 'Verificar', schema: EXISTE, effort: 'high' },
).then((v) => ({ ...f, check: v }))))).filter(Boolean)
log(`${verificadas.length} fraquezas verificadas no repo · ${verificadas.filter((v) => v.check.veredito !== 'NAO_EXISTE').length} tinham algo existente`)

// ── Fase 4 — Grade final ──────────────────────────────────────────────────────
phase('Grade')
const grade = await agent(
  `Escreva a GRADE DE RÉGUAS para Wagner (PT-BR, direto, sem ego inflado nem falsa modéstia). Datada de HOJE.\n\nPESQUISAS: ${JSON.stringify(pesquisas).slice(0, 40000)}\nREFUTAÇÃO (só sobrevivente conta como acima): ${JSON.stringify(refutados.map((r) => ({ ideia: r.ideia, v: r.verdict }))).slice(0, 10000)}\nVERIFICAÇÃO NO REPO (nota só com evidência): ${JSON.stringify(verificadas).slice(0, 20000)}\n\nEstrutura: 1) placar honesto (acima-confirmadas/empatadas/refutadas); 2) GRADE das fraquezas — técnica · régua (quem+prática+fonte) · por-que-é-grade-ável (critério objetivo: número/artefato/gate) · nota COM evidência · próximo degrau; 3) onde a régua é você (empates a defender); 4) O QUE ROUBAR top-8 (impacto÷esforço, onde plugar); 5) CHIPS SUGERIDOS (1 por fraqueza real, com ressalvas do adversário embutidas); 6) REJEITADOS desta rodada → candidatos a proibições §5; 7) leitura fria (3 frases). Regra: nenhuma nota sem evidência citada.`,
  { label: 'grade-final', phase: 'Grade', effort: 'max' },
)
return {
  dimensoes: pesquisas.length,
  acima_confirmadas: refutados.filter((r) => r.verdict.veredito === 'ACIMA_CONFIRMADO').length,
  refutadas: refutados.filter((r) => r.verdict.veredito === 'REFUTADO').length,
  fraquezas_com_algo_existente: verificadas.filter((v) => v.check.veredito !== 'NAO_EXISTE').length,
  grade,
}
