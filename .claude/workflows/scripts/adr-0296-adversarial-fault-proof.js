export const meta = {
  name: 'adr-0296-adversarial-fault-proof',
  description: 'Rodada adversarial à prova de falhas sobre ADR 0296 (capacidade multi-tenant): 1+ adversário por classe C1–C7 e por fase P0/P1/P2 + cross-cutting, cada achado refutado, síntese + crítico de completude',
  phases: [
    { title: 'Adversários' },
    { title: 'Verificação' },
    { title: 'Síntese' },
    { title: 'Completude' },
  ],
}

const ADR = 'D:/oimpresso.com/memory/decisions/0296-plano-capacidade-multi-tenant-taxonomia-dados-placement.md'

const LENS = {
  tier0:    'VAZAMENTO TIER 0 — business_id / global scope / cross-tenant. Como esta parte pode vazar dado entre tenants (o pior bug do projeto, ADR 0093)?',
  loss:     'PERDA DE DADO / ZERO-LOSS — como esta parte pode apagar/corromper dado que NÃO era podável (C1/C6), ou perder linhas numa poda/migração?',
  avail:    'DISPONIBILIDADE / DEGRADAÇÃO — CT 100 é CASA (energia/link, sem datacenter). Como a queda do ops-DB / a latência / um cutover derruba o ERP do cliente (viola INVARIANTE-ERP-FIRST)?',
  audit:    'LGPD / AUDIT / HASH-CHAIN — como esta parte quebra a cadeia append-only (0084/0294), perde verificabilidade legal, ou viola retenção/LGPD (região, anonimização, retenção)?',
  migration:'CORRETUDE DE MIGRAÇÃO / REVERSIBILIDADE — como o cutover por classe falha silenciosamente (checksum, FK órfã, dado em trânsito, rollback impossível, ordem de dependência)?',
  crossdb:  'CROSS-DB / TRANSAÇÃO DISTRIBUÍDA / ELOQUENT MULTI-CONEXÃO — separar classes em DBs diferentes quebra FK entre-DB, JOIN entre-conexão, transação atômica multi-tabela, e o global scope do Eloquent que assume 1 conexão. Encontre a query/relacionamento REAL no código que quebra.',
  monitor:  'PONTO-CEGO DE MONITORAMENTO — o monitor proposto (P0.5/INVARIANTE-MON) REALMENTE pegaria o próximo incidente? Que rampa/falha silenciosa ele ainda não vê? O write-probe é implementável e seguro?',
  cost:     'CUSTO / COMPLEXIDADE OPERACIONAL / ERRO HUMANO — 2–3 conexões + cutovers + circuit-breakers = superfície de erro humano. Onde a complexidade vira incidente; onde o tampão manual volta; sustentabilidade real.',
}

const PARTS = [
  { key:'C1',  title:'C1 — Negócio-tenant + placement off-shared (DBaaS via Remote MySQL)',
    focus:'Dado real do cliente (fin_titulos, transactions, contacts, PII). Placement-alvo: MySQL off-shared servido ao PHP-FPM Hostinger via Remote MySQL. NUNCA no CT 100.',
    lenses:['tier0','avail','crossdb','migration','cost'] },
  { key:'C2',  title:'C2 — Ops governança plataforma (mcp_tasks/leases/epics) → CT 100',
    focus:'Estado vivo do dev (sem business_id by-design, ADR 0280 Grupo A). Alvo: ops-DB MariaDB CT 100.',
    lenses:['crossdb','migration','avail'] },
  { key:'C3',  title:'C3 — Ops por-tenant / telemetria IA (mcp_dual_brain_decisions, COM business_id) → CT 100',
    focus:'Telemetria/decisões IA com sinal de tenant (Grupo B, business_id+FK+scope). Alvo: ops-DB CT 100, mantendo isolamento.',
    lenses:['tier0','crossdb','migration','audit'] },
  { key:'C4',  title:'C4 — Log efêmero / fila / token (jobs, activity_log, oauth, sessions)',
    focus:'Redis (fila/cache) + observabilidade CT 100 (activity_log→Langfuse/Jaeger); OAuth com pruning onde o app autentica.',
    lenses:['loss','audit','monitor'] },
  { key:'C5',  title:'C5 — Cache derivado-do-git (mcp_memory_documents + _history ring buffer)',
    focus:'_history (a tabela de ~5GB que causou o incidente) vira ring buffer / é eliminada; git é a verdade (0061). Recall histórico → git log.',
    lenses:['loss','monitor','cost'] },
  { key:'C6',  title:'C6 — Audit append-only / hash-chain (mcp_audit_log) — particionar + WORM frio',
    focus:'Forense imortal LGPD, trigger append-only (0084) + hash-chain SHA-256 (0294). P0.4 particiona (não deleta); P1.3 migra por export append-only verificado.',
    lenses:['audit','migration','loss'] },
  { key:'C7',  title:'C7 — Backup descartável / scratch (_bkp_* / _bad_*) — DROP',
    focus:'Lixo de migração. DROP após dump comprimido se dúvida. Primeira poda.',
    lenses:['loss','cost'] },
  { key:'P0',  title:'P0 — Parar o sangramento sem migrar host (drops, tetos, pruning, monitor, fix ADS loop)',
    focus:'P0.1 DROP C7 · P0.2 teto C5 _history · P0.3 pruning C4 · P0.4 particionar C6 · P0.5 monitor+write-probe · P0.6 fix loop ADS logger · P0.7 fix backup deploy.',
    lenses:['loss','monitor','cost','migration'] },
  { key:'P1',  title:'P1 — Mover ops/logs pro CT 100 + 2ª conexão + degradação graciosa',
    focus:'P1.1 2ª conexão (ops/governanca) em config/database.php · P1.2 migrar C2/C3/C5 · P1.3 migrar C6 audit · P1.4 logs→observabilidade · P1.5 degradação graciosa (circuit-breaker/buffer).',
    lenses:['avail','crossdb','migration','tier0'] },
  { key:'P2',  title:'P2 — Tenant DB (C1) off-shared (DBaaS gerenciado), app fica no Hostinger',
    focus:'Mover conexão mysql default pra DBaaS via Remote MySQL. Gatilho: C1 cruzar 3GB com tendência. Trade-off custo+latência.',
    lenses:['avail','cost','migration','tier0'] },
  { key:'PLACEMENT', title:'Parte 2 — Política de placement (taxonomia 7 classes como contrato)',
    focus:'Princípio: o DB que serve o cliente nunca compete por bytes com ruído ops. O mapeamento tabela→classe (linhas 82-88 do ADR) está completo/correto? Tabela mal-classificada = bloat volta a furar.',
    lenses:['crossdb','tier0','migration','cost'] },
  { key:'INVARIANTS', title:'Invariantes à prova de falhas (TIER0/ZEROLOSS/AUDIT/REVERSIBILIDADE/MON/ERP-FIRST/CLASSE)',
    focus:'As 6+1 invariantes são SUFICIENTES, TESTÁVEIS e ENFORCEÁVEIS por máquina (gate/check), ou são aspiracionais? Qual invariante falta? Qual não tem enforcement?',
    lenses:['monitor','cost','tier0'] },
  { key:'MONITOR', title:'INVARIANTE-MON / P0.5 — health-check de cota + write-probe + rampa',
    focus:'Mede (a)tamanho (b)taxa de crescimento por tabela (c)_bkp_>30d (d)growth C4/C5 sem TTL (e)write-probe do grant. A ausência de (a)+(b) CAUSOU o incidente. Implementação real em jana:health-check.',
    lenses:['monitor','loss','cost'] },
]

const ADV_SCHEMA = {
  type:'object', additionalProperties:false,
  properties:{
    part:{type:'string'}, lens:{type:'string'},
    findings:{ type:'array', items:{ type:'object', additionalProperties:false, properties:{
      title:{type:'string'},
      scenario:{type:'string', description:'Cenário concreto de falha em produção com cliente pagante'},
      why_plan_fails:{type:'string', description:'Por que o plano COMO ESCRITO não previne'},
      severity:{type:'string', enum:['critical','high','medium','low']},
      blocks_acceptance:{type:'boolean'},
      proposed_fix:{type:'string'},
      evidence:{type:'string', description:'Arquivos/tabelas/ADRs/linhas citados como prova'}
    }, required:['title','scenario','why_plan_fails','severity','blocks_acceptance','proposed_fix','evidence'] } }
  }, required:['part','lens','findings']
}

const VERDICT_SCHEMA = {
  type:'object', additionalProperties:false,
  properties:{
    verdict:{type:'string', enum:['confirmed','partial','refuted']},
    is_material:{type:'boolean'},
    reasoning:{type:'string'},
    refined_severity:{type:'string', enum:['critical','high','medium','low']},
    refined_fix:{type:'string'}
  }, required:['verdict','is_material','reasoning','refined_severity','refined_fix']
}

const SYNTH_SCHEMA = {
  type:'object', additionalProperties:false,
  properties:{
    fault_proof_verdict:{type:'string', enum:['nao-prova-de-falhas-ainda','prova-de-falhas-com-fixes','prova-de-falhas']},
    executive_summary:{type:'string'},
    confirmed_risks:{ type:'array', items:{ type:'object', additionalProperties:false, properties:{
      title:{type:'string'}, part:{type:'string'},
      severity:{type:'string', enum:['critical','high','medium','low']},
      scenario:{type:'string'}, required_fix:{type:'string'}, blocks_acceptance:{type:'boolean'}
    }, required:['title','part','severity','scenario','required_fix','blocks_acceptance'] } },
    new_invariants_or_fixes:{ type:'array', items:{type:'string'} },
    plan_amendments:{ type:'array', items:{type:'string'} },
    open_questions_for_wagner:{ type:'array', items:{type:'string'} }
  }, required:['fault_proof_verdict','executive_summary','confirmed_risks','new_invariants_or_fixes','plan_amendments','open_questions_for_wagner']
}

const COMPLETENESS_SCHEMA = {
  type:'object', additionalProperties:false,
  properties:{
    uncovered_surface:{ type:'array', items:{type:'string'} },
    most_dangerous_unasked_question:{type:'string'}
  }, required:['uncovered_surface','most_dangerous_unasked_question']
}

const advPrompt = (part, lensKey) => `Você é um ADVERSÁRIO hostil revisando o ADR 0296 (plano de capacidade à prova de falhas) do ERP multi-tenant oimpresso. Seu trabalho é QUEBRAR o plano, não elogiá-lo.

Leia PRIMEIRO o ADR completo: ${ADR}
Leia o que precisar (caminhos absolutos sob D:/oimpresso.com/): os ADRs citados em \`related\` (memory/decisions/00XX-*.md), \`config/database.php\`, \`app/Console/Kernel.php\`, \`Modules/Jana/Console/Commands/HealthCheckCommand.php\`, e qualquer Model/migration relevante. Use Grep/Glob/Read/Bash. Se citar uma query/FK/scope que quebra, ACHE no código de verdade.

PARTE SOB ATAQUE: ${part.key} — ${part.title}
Foco: ${part.focus}

LENTE OBRIGATÓRIA: ${LENS[lensKey]}

Premissa: o plano vai pra produção com cliente pagante, dado real e uptime em jogo. Assuma Murphy. Para CADA falha que encontrar nesta parte sob esta lente, dê: cenário concreto (passo-a-passo), por que o plano COMO ESCRITO não previne, severidade, se BLOQUEIA aceitação, fix concreto, e evidência (arquivo/tabela/ADR/linha). Prefira poucos achados AFIADOS e específicos a muitos genéricos. Se a parte realmente cobre a lente, diga (findings vazio) — não invente.`

const vfyPrompt = (f) => `Você é o CÉTICO DO CÉTICO. Um adversário alegou esta falha no ADR 0296. Tente REFUTAR — o plano já cobre? é não-material? o cenário é irreal? Leia ${ADR} e o código relevante (D:/oimpresso.com/...) pra confirmar/derrubar com evidência. Só "confirmed" se sobreviver; "refuted" se o plano já trata ou é irrelevante; "partial" se tem fundo mas exagerado.

ACHADO:
- parte: ${f.part} · severidade-alegada: ${f.severity} · bloqueia: ${f.blocks_acceptance}
- título: ${f.title}
- cenário: ${f.scenario}
- por que falha: ${f.why_plan_fails}
- fix: ${f.proposed_fix}
- evidência: ${f.evidence}`

phase('Adversários')
log(`Atacando ${PARTS.length} partes do ADR 0296 com ${PARTS.reduce((a,p)=>a+p.lenses.length,0)} adversários + verificação por achado.`)

const perPart = await pipeline(
  PARTS,
  (part) => parallel(part.lenses.map(lensKey => () =>
    agent(advPrompt(part, lensKey), { label:`adv:${part.key}:${lensKey}`, phase:'Adversários', effort:'high', schema: ADV_SCHEMA })
  )).then(rs => ({
    part: part.key,
    findings: rs.filter(Boolean).flatMap(r => (r.findings||[]).map(f => ({ ...f, part: part.key, lens: r.lens })))
  })),
  (s1, part) => parallel(
    s1.findings.filter(f => f.severity !== 'low').map(f => () =>
      agent(vfyPrompt(f), { label:`vfy:${part.key}`, phase:'Verificação', effort:'medium', schema: VERDICT_SCHEMA })
        .then(v => ({ finding: f, verdict: v })).catch(() => null)
    )
  ).then(vs => ({ part: part.key, verified: vs.filter(Boolean) }))
)

const allParts = perPart.filter(Boolean)
const allVerified = allParts.flatMap(r => r.verified)
const confirmed = allVerified.filter(v => v.verdict && v.verdict.verdict !== 'refuted' && v.verdict.is_material)
  .map(v => ({ ...v.finding, severity: v.verdict.refined_severity || v.finding.severity, required_fix: v.verdict.refined_fix || v.finding.proposed_fix, verdict: v.verdict.verdict }))
log(`Verificados: ${allVerified.length} · confirmados materiais: ${confirmed.length} · partes: ${allParts.length}/${PARTS.length}`)

phase('Síntese')
const synth = await agent(
  `Você é o SINTETIZADOR sênior da rodada adversarial do ADR 0296. Recebeu ${confirmed.length} achados CONFIRMADOS e materiais. Leia o ADR (${ADR}) pra contexto.

Produza o veredicto à-prova-de-falhas: dedup/agrupe os riscos, severidade final, quais BLOQUEIAM proposed→aceito, novos invariantes/fixes obrigatórios, emendas concretas ao plano, e perguntas que só o Wagner decide. Honesto: se ainda não é à prova de falhas, diga.

ACHADOS CONFIRMADOS (JSON):
${JSON.stringify(confirmed)}`,
  { phase:'Síntese', effort:'high', schema: SYNTH_SCHEMA }
)

phase('Completude')
const crit = await agent(
  `Você é o CRÍTICO DE COMPLETUDE da rodada adversarial do ADR 0296. Cobrimos: ${PARTS.map(p=>p.key).join(', ')} sob lentes (tier0, perda, disponibilidade, audit/LGPD, migração, cross-DB, monitor, custo). Leia ${ADR}. Que superfície de ataque NÃO foi coberta? Que pergunta perigosa ninguém fez? (ex.: segurança de rede do Remote MySQL exposto, cripto em trânsito Hostinger↔DBaaS, backup/restore testado, quem paga/opera o DBaaS, plano de saída se a Hostinger mudar regra, egress/custo, região LGPD, clientes legacy perfex/wr2/crm no mesmo grant). Liste a superfície não-coberta e a pergunta mais perigosa não-feita.`,
  { phase:'Completude', effort:'high', schema: COMPLETENESS_SCHEMA }
)

return {
  stats: { parts_total: PARTS.length, parts_done: allParts.length, adversaries: PARTS.reduce((a,p)=>a+p.lenses.length,0), findings_verified: allVerified.length, confirmed_material: confirmed.length },
  fault_proof_verdict: synth.fault_proof_verdict,
  synthesis: synth,
  completeness: crit,
  confirmed_findings: confirmed,
}