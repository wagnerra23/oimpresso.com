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
  ],
}

// ── Config (args sobrescrevem) ────────────────────────────────────────────────
// args: { base?: 'D:/caminho/worktree-fresco-do-main', dimensoes?: [{key,escopo}] }
// ROBUSTEZ (2026-07-10): a fronteira do tool Workflow serializa `args` pra STRING (visto 2×
// — `args.base` chegava undefined → BASE caía no placeholder e os agentes tinham que se
// auto-curar lendo origin/main na mão). Então parse defensivo: aceita objeto OU string JSON.
const A = typeof args === 'string' ? (() => { try { return JSON.parse(args) } catch { return {} } })() : (args || {})
const BASE = (A && A.base) || 'AJUSTE: passe args.base = worktree FRESCO do origin/main'
const DIMS = (A && A.dimensoes) || [
  { key: 'spec-governanca', escopo: 'spec-driven development + governança de agentes (Spec Kit, Kiro, Codex, Cursor, Tessl; gates/required; ratchets)' },
  { key: 'design-to-code', escopo: 'design→code fidelity (Figma Code Connect/Dev Mode, v0, Builder.io; VRT: Chromatic/Applitools/Percy; tokens DTCG/Style Dictionary)' },
  { key: 'memoria-conhecimento', escopo: 'memória de agente + sobrevivência de conhecimento (Letta, mem0, Zep, LangMem; docs-as-code freshness: Swimm, Dosu; ADR tooling)' },
  { key: 'orquestracao-adversarial', escopo: 'multi-agente + verificação adversarial (Anthropic orchestrator-worker, Devin/Cognition, Jules, Amp, Agent HQ)' },
  { key: 'evals-outcome', escopo: 'evals e medição de outcome de agentes (Braintrust, LangSmith, DORA/DX for AI; goal-based evals; scorecards de processo)' },
  { key: 'erp-ia-produto', escopo: 'IA embarcada em ERP — o PRODUTO (SAP Joule, Dynamics Copilot, Odoo AI; BR: Bling/Tiny/Omie/Conta Azul)' },
  // ── Eixo RODAR-E-OBSERVAR (a IA que o sistema produz, viva em prod — não o loop de construir/governar). ──
  // Adicionadas 2026-07-10: as 9 réguas v1→v3 só mediam CONSTRUIR-E-GOVERNAR; este eixo era ponto cego. Ver ADR 0333 (emenda à 0330).
  { key: 'observabilidade-agente', escopo: 'traces/custo/latência/alucinação do agente e da IA em produção (Langfuse, LangSmith, Braintrust, OpenTelemetry GenAI semantic conventions; spans + custo por run). Régua = painel vivo, não log solto — projeto tem #4 P0 "Ligar Langfuse+OTel" pendente' },
  { key: 'qualidade-drift-ia-producao', escopo: 'qualidade + drift da IA-PRODUTO (a Jana) em prod — recall/hallucination gold-set + canary de drift (RAGAS, DeepEval, continuous-eval). DISTINTO de evals-outcome (que é DORA/outcome do agente-DEV): aqui a régua é a resposta da Jana ao cliente. Projeto tem jana-ragas-gate JÁ + #3 P0 drift-sentinel pendente' },
  { key: 'seguranca-do-agente', escopo: 'defesa a prompt-injection + fronteira instrução-vs-dado + modelo de permissão de tools/hooks (OWASP LLM Top 10, Anthropic agent-safety, Google SAIF). Régua = superfície de tool/hook auditada + injection tratada, não confiança implícita' },
  { key: 'custo-eficiencia', escopo: 'token/crédito por tarefa como MÉTRICA medida — hoje é só valor cultural do Wagner, sem número (Cursor, Cognition/Devin cost-per-task). Régua = custo por PR/feature observável, não "economize crédito" verbal' },
  // ── Eixo SERVIR-O-NEGÓCIO (a inteligência de negócio do PRODUTO — o ponto cego que o adversário 2026-07-10 expôs). ──
  // A grade media CONSTRUIR-E-GOVERNAR e RODAR-E-OBSERVAR, mas nunca "o sistema serve o negócio (A+B) ou governa a si mesmo (C)?".
  { key: 'inteligencia-de-negocio', escopo: 'inteligência de negócio embarcada + cliente-como-sinal: a IA responde o DONO do negócio com dado real do tenant (SAP Joule, Odoo AI, Dynamics Copilot) e o backlog nasce de sinal de cliente/uso, não de régua/prazo (product-led: Linear, Productboard, PLG). Régua = (1) equilíbrio entre construir-produto (A+B) e governar-o-processo (C) — fonte interna scripts/governance/negocio-vs-governanca-ratio.mjs; (2) órgão-sensor do sinal do cliente vivo e conectado (loop client_signal→cycle_goal), não apodrecido. DISTINTO de erp-ia-produto (features do ERP) e de qualidade-drift-ia-producao (recall da Jana): aqui a régua é se a ENERGIA do sistema serve o negócio ou a própria governança. Ponto cego confirmado pelo adversário-inteligencia-negocio 2026-07-10.' },
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
// Teste de integração: o peer refutado monta o TODO integrado, ou só a peça isolada? (anti-falácia-de-composição — Wagner 2026-07-10, proibições §5)
const INTEG = { type: 'object', additionalProperties: false, required: ['veredito', 'razao'], properties: {
  veredito: { type: 'string', enum: ['DIFERENCIAL_SISTEMA', 'REFUTADO_TB'] }, razao: { type: 'string' }, quem_monta_o_todo: { type: 'string' } } }

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

// ── Fase 2.5 — Teste de INTEGRAÇÃO (o TODO tem peer, ou só as partes?) ────────
// O refutador julga slice-a-slice por construção; sem este passo a soma de peças-com-peer
// fabrica um "0 acima" falso (falácia de composição). Wagner 2026-07-10 — proibições §5.
phase('Integração')
const derrubadas = refutados.filter((r) => r.verdict.veredito !== 'ACIMA_CONFIRMADO')
const integrados = (await parallel(derrubadas.slice(0, 24).map((r) => () => agent(
  `TESTE DE INTEGRAÇÃO (o refutador já achou peer pra ESTA peça isolada — não repita a busca da peça). Claim: "${r.ideia}" (dimensão ${r.dimensao}); refutador deu ${r.verdict.veredito} citando "${(r.verdict.quem_ja_faz || r.verdict.razao || '').slice(0, 300)}". PERGUNTA ÚNICA: algum produto/prática publicado monta o TODO INTEGRADO no MESMO contexto do oimpresso — a pilha inteira DENTRO de um ERP vertical multi-tenant BR em produção, aplicada A SI MESMA (governança recursiva: o agente-codador cita o próprio §5 pra se auto-barrar) + o loop medir→corrigir→travar que de fato fecha? Busque 2-3× o CONJUNTO, não a peça. Se um peer monta o todo no mesmo contexto → REFUTADO_TB (a integração também tem par; diga quem). Se os peers só cobrem PEÇAS e ninguém monta o conjunto → DIFERENCIAL_SISTEMA (o diferencial é de instanciação/integração/recursão, NÃO da categoria — proibido re-inflar a peça isolada como "acima"). Default: exija o peer do TODO.`,
  { label: `i:${r.ideia}`.slice(0, 48), phase: 'Integração', schema: INTEG, agentType: 'general-purpose', effort: 'medium' },
).then((v) => ({ ...r, integ: v }))))).filter(Boolean)
log(`integração: ${integrados.filter((i) => i.integ.veredito === 'DIFERENCIAL_SISTEMA').length} diferenciais de sistema · ${integrados.filter((i) => i.integ.veredito === 'REFUTADO_TB').length} o todo também tem par`)

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
  `Escreva a GRADE DE RÉGUAS para Wagner (PT-BR, direto, sem ego inflado nem falsa modéstia). Datada de HOJE.\n\nPESQUISAS: ${JSON.stringify(pesquisas).slice(0, 38000)}\nREFUTAÇÃO slice-a-slice: ${JSON.stringify(refutados.map((r) => ({ ideia: r.ideia, v: r.verdict }))).slice(0, 9000)}\nTESTE DE INTEGRAÇÃO (DIFERENCIAL_SISTEMA = à-frente-por-integração, o peer só cobre a peça; REFUTADO_TB = o todo também tem par): ${JSON.stringify(integrados.map((i) => ({ ideia: i.ideia, integ: i.integ }))).slice(0, 12000)}\nVERIFICAÇÃO NO REPO (nota só com evidência): ${JSON.stringify(verificadas).slice(0, 18000)}\n\nDUAS REGRAS ANTES DE ESCREVER: (a) NÃO reporte "0 acima" a partir de refutação de slices — o placar de superioridade tem DUAS colunas distintas: "acima-de-categoria" (ACIMA_CONFIRMADO) E "à-frente-por-integração" (DIFERENCIAL_SISTEMA — o diferencial real quando ninguém monta o TODO no mesmo contexto; NÃO re-inflar a peça isolada). (b) CREDITE O QUE JÁ SHIPOU: antes de listar um gap/roubar como aberto, cheque em ${BASE} (git log recente + arquivos novos) se já foi fechado desde o último retrato; se sim, marque FEITO e não re-liste.\n\nEstrutura: 1) placar honesto (acima-de-categoria · à-frente-por-integração · empatadas · refutadas); 2) DIFERENCIAIS REAIS (os DIFERENCIAL_SISTEMA, na altitude do sistema, com o limite honesto de cada um); 3) GRADE das fraquezas — técnica · régua (quem+prática+fonte) · critério objetivo · nota COM evidência · próximo degrau; 4) JÁ FEITO desde o último retrato; 5) onde a régua é você (empates a defender); 6) O QUE ROUBAR top-8 (impacto÷esforço, onde plugar) — só o que NÃO shipou; 7) CHIPS SUGERIDOS (1 por fraqueza real, ressalva do adversário embutida); 8) REJEITADOS → proibições §5; 9) leitura fria (3 frases). Regra: nenhuma nota sem evidência citada.`,
  { label: 'grade-final', phase: 'Grade', effort: 'max' },
)
return {
  dimensoes: pesquisas.length,
  acima_confirmadas: refutados.filter((r) => r.verdict.veredito === 'ACIMA_CONFIRMADO').length,
  diferenciais_de_sistema: integrados.filter((i) => i.integ.veredito === 'DIFERENCIAL_SISTEMA').length,
  refutadas: refutados.filter((r) => r.verdict.veredito === 'REFUTADO').length,
  fraquezas_com_algo_existente: verificadas.filter((v) => v.check.veredito !== 'NAO_EXISTE').length,
  grade,
}
