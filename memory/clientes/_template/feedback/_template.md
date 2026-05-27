---
# ── Template canônico de feedback de cliente ─────────────────────────────
# Refs: ADR UI-0016, ADR 0105, skill feedback-capture

# Identificação
data: 'YYYY-MM-DD'
hora: 'HH:MM'                            # opcional — captura timestamp se relevante
canal: whatsapp | call | presencial | email | suporte_inbox | inferido

# Quem disse
quem:
  persona_slug: <slug>                   # link → memory/clientes/<cliente>/personas/<slug>.yml
  cliente_real: <slug>
  business_id: <int>

# O que disse (literal preservado — não parafrasear)
o_que_disse:
  literal: '"texto LITERAL cliente entre aspas"'
  contexto: <onde foi dita / situação>

# Onde no produto
quando_no_produto:
  modulo: <financeiro / oficinaauto / cliente / sells / etc>
  tela: <ex /financeiro/contas-receber>
  acao: <ex emitir-nfe, fechar-os, cobrar-cliente>

# Job-to-be-done por trás (Mom Test reverso — não a solução pedida)
job_por_tras:
  job: <o que cliente queria atingir>
  motivacao: funcional | emocional | social

# Workaround atual
workaround_atual:
  o_que_faz: <como lida hoje>
  custo: <tempo / frustração / risco>

# Severity NN/g 0-4
severity_nng: <0|1|2|3|4>
# 0 = não é problema (sugestão wish-list)
# 1 = cosmético (chato mas convive)
# 2 = minor (problema real mas tem workaround)
# 3 = major (impede tarefa frequente)
# 4 = catastrófico (bloqueia uso)

# Frequência
frequencia:
  primeira_vez: true | false
  recorrente_para_ela: <int>             # quantas vezes ela já reportou
  outros_clientes_tambem: []             # lista slugs outros clientes
  pattern_emergente: false               # 3+ clientes diferentes mesma reclamação

# Ação imediata
acao_imediata:
  status: novo                           # novo → triaged → backlog → in_progress → resolved → closed
  responder_cliente: <pendente — ex "voltar pra Kamila confirmando recebimento">
  task_mcp_id: null                      # auto se severity ≥ 3

# Resolução (preencher depois)
resolucao:
  data_resolvido: null
  pr_link: null
  cliente_confirmou: null                # bool após follow-up
  re_reclamacao: false                   # cliente reclamou DE NOVO da mesma coisa? sinal fix superficial
---

# Feedback — <título descritivo curto>

## Resumo

<1-2 frases sintetizando o que aconteceu>

## Contexto completo

<descrição livre da situação — call notes, transcrição WhatsApp, etc>

## Análise

### Por que isso aconteceu?

<root cause inferido — não confunde com solução>

### Por que importa pra ESTA persona?

<conectar com persona.jtbd + persona.fricoes_temidas>

### Quem mais pode sofrer disso?

<outras personas / clientes que podem ter mesma fricção>

## Próximos passos sugeridos

- [ ] <ação 1>
- [ ] <ação 2>
- [ ] Follow-up cliente em DD/MM/YYYY pra confirmar resolução
