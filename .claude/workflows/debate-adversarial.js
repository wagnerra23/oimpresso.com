// ─────────────────────────────────────────────────────────────────────────────
// debate-adversarial.js — REFERÊNCIA + TEMPLATE colável da fase `Debate`.
//
// ROUBO (adaptação, não instalação) do Open Code Review (spencermarx/open-code-review,
// Apache-2.0, ~299★) + paper OpenReview "Adversarial Review: Cooperative Code Review
// through Structured Disagreement". Origem: pesquisa OSS 2026-07-13 (deep-research
// wf_53aa5343) — memory/sessions/2026-07-13-arte-oss-comparavel-ia-os.md. Veredito da
// pesquisa: das peças roubáveis, ESTA é a única cheap+valiosa (o resto já temos).
//
// O QUE ROUBAMOS: o OCR mete uma FASE DE DISCOURSE estruturado ENTRE os revisores,
// ANTES da síntese — 4 modos AGREE/CHALLENGE/CONNECT/SURFACE. Hoje nossos workflows
// adversariais (sdd-avaliador-processo, adr-0296-adversarial-*) fazem refutador→juiz
// DIRETO: cada cético julga seu slice isolado, o juiz agrega. Falta a camada de DEBATE
// cruzado. Isso é exatamente a FALÁCIA DE COMPOSIÇÃO (regra dura #7 da skill
// `reguas-do-sistema`): julgar slice-a-slice fabrica "0 acima"/"0 bug" falso. O SURFACE
// com alvo="TODO" força o "e o CONJUNTO integrado?" que hoje é regra manual.
//
// COMO USAR NO SEU WORKFLOW: copie o bloco entre BEGIN/END TEMPLATE abaixo pra dentro do
// seu script adversarial, e chame `faseDebate(achados, ...)` ENTRE a fase de refutação/
// ataque e a fase de juiz. Passe pro juiz o `debatido` + `surfaces` (não os achados crus).
// RUNBOOK: memory/requisitos/_Governanca/RUNBOOK-fase-debate-adversarial.md
// ─────────────────────────────────────────────────────────────────────────────

export const meta = {
  name: 'debate-adversarial',
  description: 'Referência executável + template colável da fase Debate (AGREE/CHALLENGE/CONNECT/SURFACE) roubada do Open Code Review. Plugável entre refutação e juiz nos workflows adversariais pra matar a falácia de composição (regra dura #7 reguas). O corpo é um demo barato que valida o padrão 1×.',
  whenToUse: 'Ler antes de plugar a fase Debate num workflow adversarial (sdd-avaliador-processo, adr-0296-adversarial-*, reguas-do-sistema). Rodar 1× valida que a fase produz reações tipadas. Read-only (não toca Modules/ nem prod).',
  phases: [
    { title: 'Achar', detail: '3 lentes independentes acham 1 achado cada num trecho-alvo' },
    { title: 'Debate', detail: 'cada revisor reage aos achados DOS OUTROS (AGREE/CHALLENGE/CONNECT/SURFACE)' },
    { title: 'Juiz', detail: 'juiz recebe os achados JÁ DEBATIDOS + os SURFACE do TODO integrado' },
  ],
}

// ══════════════════════════ BEGIN TEMPLATE: fase Debate ═══════════════════════════
// Cole ESTE bloco no seu workflow adversarial, entre a fase de refutação e a de juiz.
// Depende só dos globais do harness Workflow (agent/parallel/log) — nada a importar.
// Contrato de entrada: `achados` = array de { id (curto), autor (quem achou), texto }. Ids ausentes/
//   repetidos são normalizados p/ únicos internamente (não confie no chamador pra unicidade).
// Contrato de saída:   { achados, reacoes, debatido, surfaces } — passe debatido+surfaces ao juiz.
//   Garantias: auto-reação (r.por===autor) NÃO conta no sinal · dedup por (debatedor,alvo,modo) ·
//   CONNECT registra nos dois lados · empate → NEUTRO · ≥1 SURFACE alvo="TODO" garantido (fallback integrador).

const REACAO_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['reacoes'],
  properties: {
    reacoes: {
      type: 'array',
      items: {
        type: 'object', additionalProperties: false,
        required: ['modo', 'alvo', 'razao'],
        properties: {
          modo: { type: 'string', enum: ['AGREE', 'CHALLENGE', 'CONNECT', 'SURFACE'] },
          alvo: { type: 'string', description: 'id do achado-alvo; use "TODO" no SURFACE do conjunto integrado' },
          alvo_secundario: { type: 'string', description: 'CONNECT: id do 2º achado ligado (senão vazio)' },
          razao: { type: 'string', description: 'evidência/argumento em 1-3 frases (não repita o achado)' },
          novo_achado: { type: 'string', description: 'SURFACE: o que NINGUÉM viu — esp. a falha do TODO integrado' },
        },
      },
    },
  },
}

// faseDebate(achados, opts) — opts: { phase?, model?, effort?, foco? }
async function faseDebate(achados, opts = {}) {
  const { phase = 'Debate', model, effort = 'high', foco = 'o conjunto de achados' } = opts
  // Debate exige ≥2 achados pra reagir a algo que não seja o próprio.
  if (!achados || achados.length < 2) return { achados: achados || [], reacoes: [], debatido: achados || [], surfaces: [] }

  // Normaliza ids: ÚNICOS e presentes. O debatedor referencia o achado pelo `id` no catálogo — id
  // ausente/repetido = referência ambígua + colapso silencioso em byId (Object.fromEntries last-wins).
  const usados = new Set()
  const ach = achados.map((a, i) => {
    let id = a && a.id != null && String(a.id).trim() ? String(a.id) : `a${i}`
    if (usados.has(id)) id = `${id}#${i}`
    usados.add(id)
    return { ...a, id }
  })

  const catalogo = ach.map((a) => `[${a.id}] (por ${a.autor || '?'}) ${a.texto}`).join('\n')
  // Um debatedor por AUTOR (cada cético reage ao que os OUTROS acharam, não só ao seu slice).
  // Fallback sem autores: um debatedor por achado (id já normalizado, nunca undefined/duplicado).
  const autores = [...new Set(ach.map((a) => a.autor).filter(Boolean))]
  const debatedores = autores.length >= 2 ? autores : ach.map((a) => a.id)

  const lotes = await parallel(debatedores.map((quem) => () => agent(
    `Você é o revisor "${quem}" no DEBATE ESTRUTURADO (protocolo Open Code Review) sobre ${foco}.
Abaixo estão TODOS os achados de TODOS os revisores — inclusive os que NÃO são seus. O valor do debate é
reagir ao que os OUTROS acharam; NÃO re-julgue só o seu pedaço.

ACHADOS:
${catalogo}

Emita reações TIPADAS (0..N — foque onde há sinal, não reaja a tudo). Priorize os achados que NÃO são seus:
• AGREE     — concordo e REFORÇO com evidência NOVA (segundo caminho/prova; não repita o achado).
• CHALLENGE — refuto/duvido da premissa ou do raciocínio (diga POR QUE cai; default cético).
• CONNECT   — ligo dois achados (preencha alvo + alvo_secundario; diga que padrão emerge da ligação).
• SURFACE   — levanto o que NINGUÉM viu. OBRIGATÓRIO ≥1 SURFACE com alvo="TODO": olhando os achados
              JUNTOS, qual FALHA DO CONJUNTO INTEGRADO nenhum revisor pegou porque cada um olhou seu slice?
              (anti-falácia-de-composição: a soma de "cada pedaço ok" NÃO prova "o todo ok").`,
    { label: `debate:${String(quem).slice(0, 20)}`, phase, schema: REACAO_SCHEMA, model, effort },
  ).then((r) => (r && r.reacoes ? r.reacoes : []).map((x) => ({ ...x, por: quem })))))

  const reacoes = lotes.filter(Boolean).flat()

  // Anexa reações a cada achado; SURFACE (e reações órfãs) ficam separados pro juiz.
  const byId = Object.fromEntries(ach.map((a) => [a.id, { ...a, agree: [], challenge: [], connect: [] }]))
  const surfaces = []
  const vistos = new Set() // dedup por (debatedor|alvo|modo) — evita empilhar N reações iguais que inflam o sinal
  for (const r of reacoes) {
    if (r.modo === 'SURFACE') { surfaces.push(r); continue }
    const alvo = byId[r.alvo]
    if (!alvo) { surfaces.push({ ...r, _orfa: true }); continue } // alvo alucinado/inexistente → não some, vira sinal
    if (r.por && r.por === alvo.autor) continue // auto-reação NÃO conta como consenso de par (senão AGREE no próprio achado fabrica REFORCADO)
    const chave = `${r.por}|${r.alvo}|${r.modo}`
    if (vistos.has(chave)) continue
    vistos.add(chave)
    if (r.modo === 'AGREE') alvo.agree.push(r)
    else if (r.modo === 'CHALLENGE') alvo.challenge.push(r)
    else if (r.modo === 'CONNECT') {
      alvo.connect.push(r)
      // CONNECT liga DOIS achados — registra nos dois lados e valida o secundário (senão a metade da aresta some).
      const sec = r.alvo_secundario ? byId[r.alvo_secundario] : null
      if (sec && sec !== alvo) sec.connect.push({ ...r, _lado: 'secundario' })
      else if (r.alvo_secundario && !sec) surfaces.push({ ...r, _orfa_secundario: true })
    }
  }
  // Garante o SURFACE do TODO integrado — o schema não força (é só texto do prompt) e ELE é o core anti-falácia.
  // Fallback barato: só dispara quando nenhum debatedor levantou o conjunto (nas rodadas reais eles levantaram 3-4).
  if (!surfaces.some((s) => s.alvo === 'TODO')) {
    const integ = await agent(
      `Olhe os achados JUNTOS (não um a um) e aponte a FALHA DO CONJUNTO INTEGRADO que nenhum revisor pegou porque cada um olhou o seu slice (anti-falácia-de-composição). Se genuinamente não houver, diga isso.\nACHADOS:\n${catalogo}`,
      { label: 'debate:integrador', phase, schema: { type: 'object', additionalProperties: false, required: ['razao'], properties: { razao: { type: 'string' }, novo_achado: { type: 'string' } } }, model, effort },
    )
    if (integ && integ.razao) surfaces.push({ modo: 'SURFACE', alvo: 'TODO', razao: integ.razao, novo_achado: integ.novo_achado, por: 'integrador', _fallback: true })
  }

  const debatido = Object.values(byId).map((a) => ({
    ...a,
    // Sinal pro juiz (empate → NEUTRO; só é REFORCADO/CONTESTADO com maioria estrita de pares distintos).
    sinal: a.challenge.length > a.agree.length ? 'CONTESTADO' : (a.agree.length > a.challenge.length ? 'REFORCADO' : 'NEUTRO'),
  }))

  log(`debate: ${reacoes.length} reações — ${reacoes.filter((r) => r.modo === 'AGREE').length} agree · ${reacoes.filter((r) => r.modo === 'CHALLENGE').length} challenge · ${reacoes.filter((r) => r.modo === 'CONNECT').length} connect · ${surfaces.length} surface`)
  return { achados: ach, reacoes, debatido, surfaces }
}
// ╚══════════════════════════ END TEMPLATE: fase Debate ══════════════════════════════

// ─────────────────────────── DEMO (o corpo runnable de referência) ───────────────────────────
// Trecho-alvo sintético com 1 bug por lente (corretude/segurança/perf) — hermético e barato.
const ALVO_DEMO = `function saldoConta(conta) {
  let total = 0
  for (const mov of conta.movimentos) {
    total += mov.valor            // (1) não ignora mov.estornado — soma estornos
  }
  return total.toFixed(2)         // (2) se algum valor vier string, vira concatenação
}
// uso: saldoConta({ movimentos: db.movimentos(req.query.conta_id) })  // (3) conta_id da query, sem scope business_id`

const LENTES = [
  { key: 'corretude', foco: 'corretude/edge-cases (estorno ignorado, tipos, arredondamento)' },
  { key: 'seguranca', foco: 'segurança/multi-tenant (business_id ausente, input direto da query)' },
  { key: 'performance', foco: 'performance/eficiência (N+1, laço, alocação)' },
]

const FIND_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['achado'],
  properties: {
    achado: { type: 'string', description: 'o achado MAIS forte da sua lente (1 só), 1-3 frases' },
    severidade: { type: 'string', enum: ['alta', 'media', 'baixa'] },
  },
}

phase('Achar')
log(`${LENTES.length} lentes independentes revisam o trecho-alvo`)
const brutos = (await parallel(LENTES.map((l) => () => agent(
  `Revise o trecho JS abaixo SÓ pela sua lente: ${l.foco}. Retorne o achado mais forte (1 único).\n\n\`\`\`js\n${ALVO_DEMO}\n\`\`\``,
  { label: `find:${l.key}`, phase: 'Achar', schema: FIND_SCHEMA, effort: 'low' },
).then((r) => (r ? { id: l.key, autor: l.key, texto: r.achado, severidade: r.severidade } : null))))).filter(Boolean)

phase('Debate')
const { debatido, surfaces, reacoes } = await faseDebate(brutos, {
  phase: 'Debate', foco: 'a revisão do trecho `saldoConta`', effort: 'medium',
})

phase('Juiz')
const veredito = await agent(
  `Você é o JUIZ da revisão. Receba os achados JÁ DEBATIDOS (cada um com sinal REFORCADO/CONTESTADO/NEUTRO e as
reações dos pares) + os SURFACE do TODO integrado. Regras: (a) derrube CONTESTADO sem defesa; (b) promova
REFORCADO; (c) INCORPORE cada SURFACE com alvo="TODO" (é a falha do conjunto que nenhum slice pegou — não descarte).
Entregue o veredito final PRIORIZADO e DEDUPLICADO (markdown enxuto).

ACHADOS DEBATIDOS:
${JSON.stringify(debatido)}

SURFACE (todo integrado + órfãs):
${JSON.stringify(surfaces)}`,
  { label: 'juiz', phase: 'Juiz', effort: 'medium' },
)

return {
  n_achados: brutos.length,
  n_reacoes: reacoes.length,
  n_surface: surfaces.length,
  modos: {
    agree: reacoes.filter((r) => r.modo === 'AGREE').length,
    challenge: reacoes.filter((r) => r.modo === 'CHALLENGE').length,
    connect: reacoes.filter((r) => r.modo === 'CONNECT').length,
    surface: reacoes.filter((r) => r.modo === 'SURFACE').length,
  },
  debatido,
  surfaces,
  veredito,
}
