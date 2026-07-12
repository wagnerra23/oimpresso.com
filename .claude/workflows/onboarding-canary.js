export const meta = {
  name: 'onboarding-canary',
  description: 'MÁQUINA que verifica se o onboarding (memory/reference/COMECE-AQUI.md) realmente funciona — solta uma IA CEGA (sem contexto) pra seguir só o COMECE-AQUI, depois um JUIZ confere cada bullet contra as fontes reais (inventou? fonte resolve?). Emite nota 0-100 + veredito PASS/FAIL. Re-rodável: ninguém precisa confiar na palavra de ninguém — a máquina testa. Cadência sugerida: semanal (cron) ou antes de mudar o gerador system-map.mjs.',
  phases: [
    { title: 'Cego', detail: 'IA sem contexto segue só o COMECE-AQUI e produz 5 bullets + fontes' },
    { title: 'Juiz', detail: 'verifica cada bullet contra as fontes reais + calcula nota/veredito' },
  ],
}

const LIMIAR = 70 // nota mínima pra PASS (abaixo = o onboarding degradou → FAIL)

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
  required: ['por_bullet', 'nota_geral', 'veredito', 'gaps'],
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
    nota_geral: { type: 'number' },
    veredito: { type: 'string', enum: ['PASS', 'FAIL'] },
    gaps: { type: 'array', items: { type: 'string' }, description: 'o que uma porta-de-entrada deveria ter e não tinha' },
  },
}

phase('Cego')
const cego = await agent(
  `Você acabou de abrir uma sessão num repositório de ERP que você NUNCA viu. Não sabe NADA sobre ele. cwd = a raiz do repo.

Sua ÚNICA instrução de onboarding é o arquivo \`memory/reference/COMECE-AQUI.md\`. Abra-o e SIGA exatamente o que ele mandar, na ordem. Se mandar rodar uma tool que você não tem (ex brief-fetch), PULE e anote — NUNCA invente o resultado dela.

Ao final, produza os 5 bullets que ele pede (o_que_e · como_roda · cliente · em_voo · regra_tier0), cada um com a FONTE (path real de onde tirou; se não achou, "NAO_ENCONTREI" — não invente). Seja honesto sobre onde travou. Se o próprio COMECE-AQUI.md não existir no cwd, diga achou_comece_aqui=false e explique.`,
  { label: 'canary:cego', phase: 'Cego', schema: CEGO_SCHEMA, effort: 'medium' })

phase('Juiz')
const juiz = await agent(
  `Você é o JUIZ do onboarding do oimpresso. Uma IA CEGA seguiu o \`memory/reference/COMECE-AQUI.md\` e produziu isto:\n${JSON.stringify(cego, null, 2)}\n\nVERIFIQUE contra as FONTES REAIS no repo (leia os arquivos você mesmo — CLAUDE.md, memory/why-oimpresso.md, what-oimpresso.md, memory/reference/PAINEL-SISTEMA.md, memory/proibicoes.md). Pra CADA bullet decida: (a) correto = o conteúdo bate com a fonte? (b) inventado = a IA cega afirmou algo que a fonte NÃO diz? (c) fonte_resolve = o path que ela citou como fonte EXISTE mesmo (existsSync)? Dê nota por bullet.

nota_geral 0-100 ponderada. veredito = PASS se nota_geral >= ${LIMIAR} E nenhum bullet inventado E achou_comece_aqui=true; senão FAIL. gaps = o que a porta-de-entrada deveria ter e não tinha (seja concreto e acionável, pro gerador system-map.mjs evoluir).

Seja cético e factual — o objetivo é PEGAR degradação do onboarding, não elogiar.`,
  { label: 'canary:juiz', phase: 'Juiz', schema: JUIZ_SCHEMA, effort: 'high' })

const passou = juiz.veredito === 'PASS'
log(`onboarding-canary: ${juiz.veredito} · nota ${juiz.nota_geral}/100 (limiar ${LIMIAR})`)
if (!passou) log(`FALHOU — gaps: ${(juiz.gaps || []).join(' · ')}`)

return { veredito: juiz.veredito, nota: juiz.nota_geral, limiar: LIMIAR, cego, juiz }
