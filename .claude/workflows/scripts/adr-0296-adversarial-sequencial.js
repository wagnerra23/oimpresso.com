export const meta = {
  name: 'adr-0296-adversarial-sequencial',
  description: 'Continuação adversarial ADR 0296 em baixa-concorrência (1 adversário por vez) p/ sobreviver ao overload da API — cobre as partes ainda sem veredito (P0, C1, C3, C4, C7, P1, P2, PLACEMENT, INVARIANTES, MONITOR) + síntese mesclando os 6 achados das rodadas anteriores',
  phases: [ { title: 'Adversários sequenciais' }, { title: 'Síntese final' } ],
}

const ADR = 'D:/oimpresso.com/memory/decisions/0296-plano-capacidade-multi-tenant-taxonomia-dados-placement.md'

// Achados JÁ confirmados nas rodadas 1+2 (passados à síntese final p/ visão completa)
const PRIOR = [
  { part:'C2+C6', severity:'high', title:'INVARIANTE-ZEROLOSS cega a constraints — FKs cross-host (mcp_tokens/mcp_quotas/mcp_audit_log → users/business no Hostinger) não recriáveis cross-server; CASCADE morre calado. Mitigado por guards app-layer já existentes (McpAuthMiddleware users::find→401 + soft-delete).' },
  { part:'C2', severity:'high', title:'mcp_task_events (rotulado C2 podável) é append-only com triggers SIGNAL idênticos ao C6 → precisa do regime export-verificado, não migração mecânica. Generalizar INVARIANTE-AUDIT/CLASSE p/ detectar triggers de imutabilidade.' },
  { part:'C2+C5', severity:'medium', title:'COUNT==COUNT mente p/ caches git-synced: linhas nascem pós-snapshot via webhook GitHub → split-brain transitório. Fix: reconstruir via reparse do git no destino, drenar webhook antes do snapshot.' },
  { part:'C2+C6', severity:'low', title:'Grafo de FK entre classes não declarado (mcp_audit_log→mcp_tokens) → ordem de cutover não especificada; exige co-residência C2+C6 no ops-DB + ordem folha-primeiro.' },
  { part:'C5', severity:'low', title:'Premissa "git==_history, descartável" é FALSA: redactarPii()+sanitizarUtf8() transformam o content antes de gravar → _history guarda a variante SERVIDA (redactada), não o git. Regenerável determinístico + autoritativo é mcp_audit_log. Corrigir 4 frases do ADR (l.58/77/97/113).' },
  { part:'C5', severity:'low', title:'Docs soft-deleted do git: ring buffer P0.2 não pode tocar a tabela-mãe; rollback "re-pull regenera C5" (l.122) é falso p/ docs removidos do git (recuperam-se da linha soft-deletada).' },
]
const COMPLETENESS_PRIOR = 'Crítico de completude (rodada 1) já apontou 12 superfícies NÃO cobertas: (1) segurança de rede do Remote MySQL (IP-allowlist impossível em shared de IP rotativo), (2) TLS em trânsito Hostinger↔DBaaS/CT100, (3) encryption-at-rest (CT100 é casa, disco físico), (4) backup/restore DRILL nunca testado + RPO/RTO, (5) quem paga/opera o DBaaS+ops-DB, (6) plano de saída se a Hostinger mudar regra de novo, (7) egress/custo de banda + latência por-query WAN (N+1), (8) região LGPD + DPA/sub-processador (DBaaS e CT100 viram novos sub-processadores), (9) clientes legacy perfex/wr2/crm/Firebird no mesmo grant não-mapeados na taxonomia, (10) rotação de credenciais pós-migração (.env shared com histórico de vazamento), (11) drift de escrita-dupla durante cutover, (12) o monitor que monitora a si mesmo (write-probe escreve no mesmo DB que pode estar read-only; mcp_alertas idem). Pergunta mais perigosa: mover C1/PII pra Remote MySQL troca um problema de capacidade por um de EXPOSIÇÃO (vazamento LGPD reportável).'

const LENS = {
  loss:'PERDA DE DADO / poda-truncate inseguro / DROP irreversível',
  avail:'DISPONIBILIDADE — CT100 é casa; latência Remote MySQL; queda derruba ERP (INVARIANTE-ERP-FIRST)',
  tier0:'VAZAMENTO TIER 0 — business_id/global scope/cross-tenant ao mudar de host',
  audit:'LGPD/AUDIT — activity_log→observabilidade perde retenção legal? OAuth pruning apaga prova?',
  crossdb:'CROSS-DB / taxonomia tabela→classe incompleta / clientes legacy não-mapeados',
  monitor:'PONTO-CEGO/ENFORCEMENT — invariante testável por máquina? write-probe falha junto com o banco?',
  cost:'CUSTO/OPERAÇÃO/EXIT — quem paga/opera/roda patch; tampão manual volta; lock-in',
}

// Partes ainda SEM adversário sobrevivente (1 lente afiada cada). P0 primeiro (trabalho ativo).
const REMAINING = [
  { key:'P0',  lens:'loss',    title:'P0 — parar sangramento (DROP C7, teto _history, pruning C4, particionar C6, fix ADS loop, fix backup deploy)',
    focus:'É o trabalho que já está rodando em prod (o cron MemoryHistoryPrune já quebrou o classmap 3×). Onde a poda/truncate apaga algo errado, onde o particionamento do C6 toca a hash-chain, onde o DROP de C7 leva junto tabela viva, onde o registro de comando novo derruba o scheduler de novo.' },
  { key:'C1',  lens:'avail',   title:'C1 — negócio off-shared via Remote MySQL (latência/disponibilidade)',
    focus:'N+1 sobre WAN, conexões persistentes, timeout, o que acontece com o ERP se o DBaaS piscar. (rede/TLS já cobertos pelo crítico — foque latência/disponibilidade operacional).' },
  { key:'C3',  lens:'tier0',   title:'C3 — telemetria IA por-tenant (business_id) no CT100',
    focus:'mcp_dual_brain_decisions (67MB) com business_id sai pro CT100 — global scope app-layer atravessa conexões? query cross-conexão junta C3(CT100) com C1(Hostinger) e vaza/quebra?' },
  { key:'C4',  lens:'audit',   title:'C4 — log/fila/token (activity_log→observabilidade, OAuth pruning)',
    focus:'activity_log tem valor legal/forense (quem fez o quê)? mandá-lo só pra Langfuse/Jaeger (retenção curta, fora do DB) apaga prova de auditoria? OAuth prune apaga refresh token ainda válido?' },
  { key:'C7',  lens:'loss',    title:'C7 — DROP de _bkp_*/_bad_*',
    focus:'Como o glob _bkp_*/_bad_* pega por engano uma tabela viva (ex: um _bkp_ que virou fonte de verdade temporária); o dump pré-DROP é validado/restaurável?' },
  { key:'P1',  lens:'avail',   title:'P1 — ops→CT100 + 2ª conexão + degradação graciosa',
    focus:'F-4 degradação graciosa é "invariante crítica" mas sem implementação descrita — circuit-breaker onde? buffer/fila de escrita ops quando CT100 cai NÃO perde telemetria/audit? leitura com fallback retorna dado stale silencioso?' },
  { key:'P2',  lens:'cost',    title:'P2 — C1 pra DBaaS gerenciado',
    focus:'Custo recorrente sem número + quem opera (patch/upgrade/credencial/monitoria 24/7 de um banco agora crítico); plano de saída; gatilho 3GB é cedo/tarde demais?' },
  { key:'PLACEMENT', lens:'crossdb', title:'Política de placement / taxonomia 7 classes',
    focus:'O mapa tabela→classe (l.82-88) cobre as 385 tabelas? Tabela nova/legacy não-classificada fura o INVARIANTE-CLASSE. Clientes legacy (perfex/wr2/crm) no mesmo grant — a poda toca tabela que não é UltimatePOS?' },
  { key:'INVARIANTS', lens:'monitor', title:'As 7 invariantes — enforcement real',
    focus:'Cada invariante (TIER0/ZEROLOSS/AUDIT/REVERSIBILIDADE/MON/ERP-FIRST/CLASSE) tem um CHECK/gate que a faz morder, ou é aspiracional? Qual falha silenciosa se ninguém rodar o check?' },
  { key:'MONITOR', lens:'monitor', title:'INVARIANTE-MON / P0.5 write-probe',
    focus:'O write-probe escreve no MESMO banco que pode estar read-only (grant-torto) → falha junto; mcp_alertas idem. Quem monitora o monitor e por onde sai o alerta quando o DB está read-only? Mede taxa de crescimento de verdade (a ausência disso causou o incidente)?' },
]

const ADV_SCHEMA = {
  type:'object', additionalProperties:false,
  properties:{
    part:{type:'string'}, lens:{type:'string'},
    findings:{ type:'array', items:{ type:'object', additionalProperties:false, properties:{
      title:{type:'string'}, scenario:{type:'string'}, why_plan_fails:{type:'string'},
      severity:{type:'string', enum:['critical','high','medium','low']},
      blocks_acceptance:{type:'boolean'}, proposed_fix:{type:'string'}, evidence:{type:'string'}
    }, required:['title','scenario','why_plan_fails','severity','blocks_acceptance','proposed_fix','evidence'] } }
  }, required:['part','lens','findings']
}

const SYNTH_SCHEMA = {
  type:'object', additionalProperties:false,
  properties:{
    fault_proof_verdict:{type:'string', enum:['nao-prova-de-falhas-ainda','prova-de-falhas-com-fixes','prova-de-falhas']},
    executive_summary:{type:'string'},
    confirmed_risks:{ type:'array', items:{ type:'object', additionalProperties:false, properties:{
      title:{type:'string'}, part:{type:'string'}, severity:{type:'string', enum:['critical','high','medium','low']},
      scenario:{type:'string'}, required_fix:{type:'string'}, blocks_acceptance:{type:'boolean'}
    }, required:['title','part','severity','scenario','required_fix','blocks_acceptance'] } },
    new_invariants_or_fixes:{ type:'array', items:{type:'string'} },
    plan_amendments:{ type:'array', items:{type:'string'} },
    open_questions_for_wagner:{ type:'array', items:{type:'string'} },
    gate_adv_status:{type:'string', description:'O que ficou coberto vs ainda pendente p/ promover proposed→aceito'}
  }, required:['fault_proof_verdict','executive_summary','confirmed_risks','new_invariants_or_fixes','plan_amendments','open_questions_for_wagner','gate_adv_status']
}

const advPrompt = (p) => `Você é um ADVERSÁRIO hostil revisando UMA parte do ADR 0296 (plano de capacidade à prova de falhas do ERP multi-tenant oimpresso). Quebre, não elogie.

Leia o ADR completo: ${ADR}. Leia o código que precisar (absoluto sob D:/oimpresso.com/): config/database.php, app/Console/Kernel.php, Modules/Jana/Console/Commands/*, Modules/Jana/Database/Migrations/*, Modules/ADS/*, e Models relevantes. Use Grep/Glob/Read/Bash. Se citar query/FK/trigger/cron, ACHE no código.

PARTE: ${p.key} — ${p.title}
Foco: ${p.focus}
LENTE: ${LENS[p.lens]}

Premissa: produção, cliente pagante, dado e uptime em jogo, Murphy. Para cada falha: cenário concreto, por que o plano COMO ESCRITO não previne, severidade, se BLOQUEIA aceitação, fix concreto e barato se possível, evidência (arquivo/linha/tabela/ADR). Poucos achados AFIADOS e verificados > muitos genéricos. Se a parte cobre a lente, diga (findings vazio). NÃO repita os achados já conhecidos das rodadas anteriores (migração FK cross-host, mcp_task_events append-only, cache git split-brain, premissa C5) — busque o NOVO desta parte/lente.`

phase('Adversários sequenciais')
log(`Modo baixa-concorrência: ${REMAINING.length} adversários UM POR VEZ (dribla o overload que matou as rodadas em paralelo).`)
const fresh = []
for (let i = 0; i < REMAINING.length; i++) {
  const p = REMAINING[i]
  const r = await agent(advPrompt(p), { label:`adv2:${p.key}:${p.lens}`, phase:'Adversários sequenciais', effort:'high', schema: ADV_SCHEMA }).catch(() => null)
  if (r && r.findings) fresh.push(...r.findings.map(f => ({ ...f, part: p.key, lens: p.lens })))
  log(`${i+1}/${REMAINING.length} ${p.key}:${p.lens} → ${r ? (r.findings||[]).length+' achados' : 'FALHOU (overload)'}`)
}
const freshMaterial = fresh.filter(f => f.severity !== 'low')
log(`Novos achados: ${fresh.length} (material: ${freshMaterial.length}). Mesclando com ${PRIOR.length} das rodadas anteriores.`)

phase('Síntese final')
const synth = await agent(
  `Você é o SINTETIZADOR FINAL da rodada adversarial completa do ADR 0296. Leia o ADR (${ADR}). Você tem TRÊS fontes:

(A) ACHADOS CONFIRMADOS das rodadas 1+2 (já verificados no código):
${JSON.stringify(PRIOR)}

(B) SUPERFÍCIES NÃO-COBERTAS apontadas pelo crítico de completude:
${COMPLETENESS_PRIOR}

(C) NOVOS achados desta rodada sequencial (verifique materialidade — seja cético, só inclua os reais):
${JSON.stringify(fresh)}

Produza o veredicto à-prova-de-falhas COMPLETO mesclando A+B+C: dedup, severidade final, quais BLOQUEIAM proposed→aceito, novos invariantes/fixes obrigatórios, emendas concretas ao ADR (com nº de linha quando souber), perguntas que só o Wagner decide, e o gate_adv_status (o que ficou coberto vs pendente). Honesto: o ADR é à prova de falhas? Liste TODOS os riscos materiais (A+C), não só os novos.`,
  { phase:'Síntese final', effort:'high', schema: SYNTH_SCHEMA }
)

return {
  stats: { remaining_parts: REMAINING.length, fresh_findings: fresh.length, fresh_material: freshMaterial.length, prior_findings: PRIOR.length },
  fault_proof_verdict: synth.fault_proof_verdict,
  synthesis: synth,
  fresh_findings: fresh,
  prior_findings: PRIOR,
}