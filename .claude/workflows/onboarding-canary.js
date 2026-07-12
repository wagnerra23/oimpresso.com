export const meta = {
  name: 'onboarding-canary',
  description: 'MÁQUINA que verifica se o onboarding (memory/reference/COMECE-AQUI.md) realmente funciona — DUAS camadas: (1) DETERMINÍSTICA (a que vale) = checa MECANICAMENTE que todo path citado no COMECE-AQUI + PAINEL-SISTEMA resolve no disco (existsSync via scripts/governance/onboarding-paths-check.mjs, reusa deadLinks do system-map.mjs); path morto = FAIL, sem depender de juiz. (2) SUBJETIVA (secundária) = solta uma IA CEGA pra seguir o COMECE-AQUI e um JUIZ (rodado 3×, modelo fixo) confere os bullets. SAÍDA PRIMÁRIA = os ACHADOS (defeitos concretos, CONFIÁVEIS). A NOTA é sinal RUIDOSO (±10, LLM-juiz) — reportada como mediana, NUNCA usada como gate sozinha. Re-rodável. Cadência: semanal (cron) ou antes de mudar system-map.mjs.',
  phases: [
    { title: 'Paths', detail: 'checagem MECÂNICA determinística — todo path do COMECE-AQUI/PAINEL resolve? (o gate que vale)' },
    { title: 'Cego', detail: 'IA sem contexto segue só o COMECE-AQUI e produz 5 bullets + fontes' },
    { title: 'Juiz', detail: 'juiz (3× modelo fixo) confere bullets → achados confiáveis + nota ruidosa (mediana)' },
  ],
}

const LIMIAR = 70 // referência pro sinal RUIDOSO (nota) — NÃO é o gate; o gate é determinístico (Paths)
const JUIZ_MODEL = 'sonnet' // FIXO: reduz variância entre rodadas (juízes diferentes = rigor diferente = 88 vs 78)
const N_JUIZES = 3 // roda o juiz N× e reporta a MEDIANA + a UNIÃO dos achados (corta a variância do LLM)

const median = (nums) => {
  const s = nums.filter((n) => typeof n === 'number').sort((a, b) => a - b)
  if (!s.length) return null
  const mid = Math.floor(s.length / 2)
  return s.length % 2 ? s[mid] : Math.round((s[mid - 1] + s[mid]) / 2)
}

// ── CAMADA 1 (DETERMINÍSTICA) — o veredito que VALE ─────────────────────────────
const PATHS_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['ran', 'exit_code', 'ok', 'dead'],
  properties: {
    ran: { type: 'boolean', description: 'o comando node rodou e retornou (mesmo com exit 1)?' },
    exit_code: { type: 'number', description: 'exit code do script — 0 = todos os paths vivos, 1 = path morto' },
    ok: { type: 'boolean', description: 'campo "ok" do JSON do script (true = 0 paths mortos)' },
    dead: { type: 'array', items: { type: 'string' }, description: 'paths mortos (doc: path) — vazio se ok' },
  },
}

const CEGO_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['achou_comece_aqui', 'bullets', 'seguiu_ordem', 'travou_em', 'nota_propria'],
  properties: {
    achou_comece_aqui: { type: 'boolean', description: 'o arquivo memory/reference/COMECE-AQUI.md existe no cwd?' },
    bullets: {
      type: 'array', minItems: 5, maxItems: 5,
      items: {
        type: 'object', additionalProperties: false,
        required: ['tema', 'texto', 'fonte'],
        properties: {
          tema: { type: 'string', enum: ['o_que_e', 'como_roda', 'cliente', 'em_voo', 'regra_tier0'] },
          texto: { type: 'string' },
          fonte: { type: 'string', description: 'path do arquivo de onde tirou (ou NAO_ENCONTREI)' },
        },
      },
    },
    seguiu_ordem: { type: 'string', description: 'conseguiu seguir os passos do COMECE-AQUI na ordem? o que pulou?' },
    travou_em: { type: 'string', description: 'onde travou/adivinhou (ou "nada")' },
    nota_propria: { type: 'number' },
  },
}

const JUIZ_SCHEMA = {
  type: 'object', additionalProperties: false,
  required: ['por_bullet', 'nota_geral', 'algum_inventado', 'gaps'],
  properties: {
    por_bullet: {
      type: 'array',
      items: {
        type: 'object', additionalProperties: false,
        required: ['tema', 'correto', 'inventado', 'fonte_resolve', 'nota'],
        properties: {
          tema: { type: 'string' },
          correto: { type: 'boolean', description: 'o conteúdo bate com a fonte real?' },
          inventado: { type: 'boolean', description: 'a IA cega inventou algo que a fonte não diz?' },
          fonte_resolve: { type: 'boolean', description: 'o path citado como fonte existe de verdade?' },
          nota: { type: 'number' },
        },
      },
    },
    nota_geral: { type: 'number', description: 'RUIDOSA (±10). Sinal secundário, não gate.' },
    algum_inventado: { type: 'boolean', description: 'algum bullet inventado?' },
    gaps: { type: 'array', items: { type: 'string' }, description: 'ACHADOS: o que a porta-de-entrada deveria ter e não tinha (concreto, acionável)' },
  },
}

// ── CAMADA 1: PATHS (determinística, fail-closed) ───────────────────────────────
phase('Paths')
const pathCheck = await agent(
  `Rode EXATAMENTE este comando na raiz do repo (cwd atual) e reporte o resultado FIELMENTE, sem interpretar:

    node scripts/governance/onboarding-paths-check.mjs --json

Ele imprime um JSON { ok, docs, dead[] } e sai com exit 0 (todos os paths vivos) ou exit 1 (algum path morto). Capture o exit code (\`echo $?\` logo depois, ou observe o retorno). Preencha: ran=true se o node executou e retornou (mesmo exit 1); exit_code = o código real; ok = o campo "ok" do JSON; dead = a lista "dead" formatada como "doc: path". Se o node NÃO rodar (comando não encontrado, erro de import), ran=false e explique em dead. NÃO invente paths, NÃO conserte nada — só transporte o que a máquina disse.`,
  { label: 'canary:paths', phase: 'Paths', schema: PATHS_SCHEMA, model: JUIZ_MODEL, effort: 'low' })

// fail-closed: só é PASS determinístico se a máquina rodou, saiu 0 e não reportou path morto
const paths_ok = pathCheck.ran === true && pathCheck.exit_code === 0 && pathCheck.ok === true && (pathCheck.dead || []).length === 0
if (!paths_ok) log(`Paths determinístico: FAIL — ${(pathCheck.dead || []).join(' · ') || 'máquina não confirmou OK'}`)
else log('Paths determinístico: OK — todo path do COMECE-AQUI/PAINEL resolve no disco.')

// ── CAMADA 2: CEGO + JUIZ (subjetiva, secundária) ───────────────────────────────
phase('Cego')
const cego = await agent(
  `Você acabou de abrir uma sessão num repositório de ERP que você NUNCA viu. Não sabe NADA sobre ele. cwd = a raiz do repo.

Sua ÚNICA instrução de onboarding é o arquivo \`memory/reference/COMECE-AQUI.md\`. Abra-o e SIGA exatamente o que ele mandar, na ordem. Se mandar rodar uma tool que você não tem (ex brief-fetch), PULE e anote — NUNCA invente o resultado dela.

Ao final, produza os 5 bullets que ele pede (o_que_e · como_roda · cliente · em_voo · regra_tier0), cada um com a FONTE (path real de onde tirou; se não achou, "NAO_ENCONTREI" — não invente). Seja honesto sobre onde travou. Se o próprio COMECE-AQUI.md não existir no cwd, diga achou_comece_aqui=false e explique.`,
  { label: 'canary:cego', phase: 'Cego', schema: CEGO_SCHEMA, effort: 'medium' })

// juiz rodado N× (modelo FIXO) — reduz a variância "88 vs 78" via mediana + união dos achados
phase('Juiz')
const juizPrompt = `Você é o JUIZ do onboarding do oimpresso. Uma IA CEGA seguiu o \`memory/reference/COMECE-AQUI.md\` e produziu isto:\n${JSON.stringify(cego, null, 2)}\n\nVERIFIQUE contra as FONTES REAIS no repo (leia os arquivos você mesmo — CLAUDE.md, memory/why-oimpresso.md, what-oimpresso.md, memory/reference/PAINEL-SISTEMA.md, memory/proibicoes.md). Pra CADA bullet decida: (a) correto = o conteúdo bate com a fonte? (b) inventado = a IA cega afirmou algo que a fonte NÃO diz? (c) fonte_resolve = o path que ela citou como fonte EXISTE mesmo (existsSync)? Dê nota por bullet.

nota_geral 0-100 ponderada (SEI que é ruidosa — dê a sua mesmo assim). algum_inventado = true se QUALQUER bullet foi inventado. gaps = ACHADOS concretos: o que a porta-de-entrada deveria ter e não tinha (acionável, pro gerador system-map.mjs evoluir). Seja cético e factual — o objetivo é PEGAR degradação, não elogiar.`

const juizes = (await parallel(
  Array.from({ length: N_JUIZES }, (_, i) => () =>
    agent(juizPrompt, { label: `canary:juiz#${i + 1}`, phase: 'Juiz', schema: JUIZ_SCHEMA, model: JUIZ_MODEL, effort: 'medium' }))
)).filter(Boolean)

// ── CONSOLIDAÇÃO ────────────────────────────────────────────────────────────────
// ACHADOS (confiáveis) = paths mortos + união dos gaps + flag de bullet inventado
const notas = juizes.map((j) => j.nota_geral)
const notaMediana = median(notas)
const algumInventado = juizes.some((j) => j.algum_inventado === true)
const gapsUniao = [...new Set(juizes.flatMap((j) => j.gaps || []))]
const achados = [
  ...(paths_ok ? [] : (pathCheck.dead || []).map((d) => `PATH MORTO: ${d}`)),
  ...(cego.achou_comece_aqui ? [] : ['COMECE-AQUI.md não foi encontrado pela IA cega']),
  ...(algumInventado ? ['juiz sinalizou bullet(s) INVENTADO(s) — conteúdo que a fonte não sustenta'] : []),
  ...gapsUniao,
]

// VEREDITO = 100% DETERMINÍSTICO (camada Paths). A nota NÃO decide (é ruidosa).
const veredito = paths_ok ? 'PASS' : 'FAIL'

log(`onboarding-canary: ${veredito} (gate = paths determinístico) · nota RUIDOSA mediana ${notaMediana}/100 [${notas.join(', ')}]`)
if (achados.length) log(`ACHADOS (${achados.length}): ${achados.join(' · ')}`)

return {
  veredito,                       // ← CONFIÁVEL: gate determinístico de paths (não depende de juiz)
  gate: 'deterministico-paths',
  paths: { ok: paths_ok, mortos: pathCheck.dead || [] }, // ← CONFIÁVEL
  achados,                        // ← CONFIÁVEL: defeitos concretos (paths mortos + união de gaps + inventado)
  nota_ruidosa: {                 // ← SINAL RUIDOSO — informativo, NUNCA gate sozinho
    valor_mediana: notaMediana,
    amostras: notas,
    limiar_referencia: LIMIAR,
    aviso: 'ruidosa (±10, LLM-juiz) — mediana de ' + juizes.length + ' juízes modelo=' + JUIZ_MODEL + '; NÃO usar como gate',
  },
  cego,
}
