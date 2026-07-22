export const meta = {
  name: 'documentacao-tecnica',
  description: 'Fecha um drift documental de ponta a ponta: snapshot determinístico, correção no dono existente, recibo antes→depois pelo mesmo detector e confirmação preparada para o main. Não cria gate, baseline ou documento paralelo.',
  whenToUse: 'Quando [W] pedir para manter/reconciliar a documentação, quando o ZELADOR selecionar um achado documental, ou antes de declarar resolvido um drift de memory-health/BRIEFING/doc-freshness.',
  phases: [
    { title: 'Snapshot', detail: 'mede os donos existentes e escolhe um ID estável acionável' },
    { title: 'Correção', detail: 'corrige somente a fonte/documento dono' },
    { title: 'Recibo', detail: 'o mesmo ID precisa desaparecer no comparativo antes→depois' },
    { title: 'Entrega', detail: 'prepara PR e instrução de confirmação pós-merge no main' },
  ],
}

const ALVO = (typeof args === 'string' ? args.trim() : (args && args.alvo)) || 'pior achado acionável'

const LEIS = `Working dir D:/oimpresso.com.
- Leia CLAUDE.md + memory/proibicoes.md §5 antes de agir.
- Presença ≠ correção: tocar doc, bumpar data ou melhorar parcialmente a nota NÃO fecha.
- Use node scripts/governance/documentation-loop.mjs --snapshot --json como inventário.
- Todo achado aterrissa no DONO EXISTENTE (memory-health, briefing-code-staleness ou doc-freshness); zero gate/baseline/ledger novo.
- ADR aceita e handoff antigo são append-only. Nunca os edite para apagar alerta; use supersede/tombstone/ponteiro vivo quando aplicável.
- Não mexa em valor/estoque, PII, tenant ou prod. Se o alvo tocar Tier 0, pare e escale.
- Não use mudança de data como prova. O recibo é o ID estável ausente depois.
- Não faça merge: [W] ratifica. Um PR pode ser preparado conforme os poderes do ZELADOR.`

const SELECT_SCHEMA = {
  type: 'object',
  properties: {
    issue_id: { type: 'string' },
    source: { type: 'string' },
    target: { type: 'string' },
    owner_file: { type: 'string' },
    evidence_before: { type: 'string' },
    correction: { type: 'string' },
    safe_to_apply: { type: 'boolean' },
    blocker: { type: 'string' },
  },
  required: ['issue_id', 'source', 'target', 'owner_file', 'evidence_before', 'correction', 'safe_to_apply', 'blocker'],
}

phase('Snapshot')
const selected = await agent(`Você é o diagnóstico do ciclo documental. ${LEIS}

ALVO pedido: ${ALVO}

1. Rode o snapshot real.
2. Escolha exatamente 1 achado acionável. Prioridade: fail determinístico > link/fato quebrado em porta canônica > BRIEFING stale de módulo vivo > doc podre. Evite dívida histórica em massa.
3. Abra o alvo e a fonte viva que o contradiz. Prove a causa, não suponha.
4. Aponte o arquivo DONO a corrigir. Se a solução exigiria editar ADR aceita/handoff antigo, safe_to_apply=false e proponha ponteiro/tombstone vivo.

Retorne JSON.`, { label: 'docs:snapshot', phase: 'Snapshot', schema: SELECT_SCHEMA })

if (!selected || !selected.issue_id) {
  return { status: 'sem-achado', selected }
}

if (!selected.safe_to_apply) {
  return { status: 'bloqueado', selected, motivo: selected.blocker }
}

phase('Correção')
const correction = await agent(`Você corrige UM achado documental já diagnosticado. ${LEIS}

ACHADO:
${JSON.stringify(selected)}

Faça a menor correção suficiente no arquivo dono. Regras:
1. Confirme no disco que o issue_id ainda aparece no snapshot ANTES de editar.
2. Corrija o fato/link/porta contra a fonte viva. Não faça reorganização paralela nem backfill.
3. Rode o detector dono diretamente e os testes específicos do arquivo alterado.
4. NÃO declare resolvido ainda. Não commit/push/merge nesta fase.

Retorne resumo dos arquivos tocados e comandos executados.`, { label: 'docs:correcao', phase: 'Correção' })

phase('Recibo')
const receipt = await agent(`Você é o verificador independente do recibo documental. READ-ONLY: não edite. ${LEIS}

ISSUE ESPERADO: ${selected.issue_id}
CORREÇÃO REPORTADA: ${JSON.stringify(correction)}

Rode exatamente:
node scripts/governance/documentation-loop.mjs --compare-ref origin/main --expect "${selected.issue_id}" --json

O exit precisa ser 0, missing_expected precisa ser [], e o ID deve estar em resolved. Se a métrica só mudou mas o ID continuou, REPROVE. Rode também o selftest do script e o verificador dono. Retorne os outputs essenciais e veredito APROVADO/REPROVADO.`, { label: 'docs:recibo', phase: 'Recibo' })

phase('Entrega')
const handoff = await agent(`Prepare a entrega do ciclo documental, sem merge. ${LEIS}

SELECIONADO: ${JSON.stringify(selected)}
CORREÇÃO: ${JSON.stringify(correction)}
RECIBO: ${JSON.stringify(receipt)}

Se e somente se o recibo foi APROVADO:
- produza corpo curto de PR com trailer \`Documentation-Receipt: ${selected.issue_id}\`;
- inclua antes→depois do MESMO detector;
- inclua a confirmação pós-merge obrigatória: no main, o próximo ZELADOR roda snapshot e prova que o ID segue ausente;
- não invente nota ou status.

Se reprovado, entregue o bloqueio e não prepare PR.`, { label: 'docs:entrega', phase: 'Entrega' })

return { status: 'processado', selected, correction, receipt, handoff }
