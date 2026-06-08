---
title: "RUNBOOK · feedback-management — gerenciar feedback de cliente real"
status: ativo
version: "1.0"
owner: "W"
related_adrs:
  - "0016-design-contextualizado-por-persona"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
---

# RUNBOOK · feedback-management

Workflow operacional pra Wagner gerenciar feedback de cliente em ~5 min/dia.

## Princípio

Cada feedback de cliente real é **input valioso** que atualiza:
1. Persona library (`memory/clientes/<cliente>/personas/<slug>.yml` → `fricoes` + `citacoes`)
2. Charter da tela afetada (`<Tela>.charter.md` → `fricoes_conhecidas`)
3. MCP task (se severity ≥ 3)
4. Backlog priorizado RICE

Loop fechado: cliente reclama → captura → resolve → confirma → aprende.

## Rotina diária (~5 min)

Quando recebe feedback novo (WhatsApp, call, email, etc):

```
Wagner: /feedback <texto literal cliente>

Exemplo:
/feedback Kamila ligou agora: "tô tentando emitir nota da OS 2456 mas dá erro
porque o cliente Frota XYZ não tem IE preenchida. tive que ligar pro Wagner."
```

Skill `feedback-capture` dispara:
1. Identifica persona (Kamila) + cliente (Martinho Caçambas)
2. Pergunta os 7 campos faltantes (canal, módulo afetado, severity, etc) — você responde rápido
3. Salva append-only em `memory/clientes/martinho-cacambas/feedback/2026-05-27-nfe-erro-ie-vazia.md`
4. Atualiza `personas/kamila.yml` adicionando fricção + citação
5. Atualiza charter de `financeiro/contas-receber` (`fricoes_conhecidas.kamila`)
6. Se severity ≥ 3 → cria MCP task automaticamente

Tempo: ~2-3 min por feedback capturado.

## Rotina semanal (~15 min — sextas-feiras)

```
Wagner: /feedback-dashboard
```

Output:
- Top-line: total aberto, Δ vs semana passada, SLA, taxa confirmação
- Por persona / módulo / canal
- **RICE top 10** — ranking objetivo de priorização
- **Patterns emergentes** — 3+ clientes reportaram similar = ouro pra priorizar

Você decide:
- Pega top 3 RICE da semana → vira sprint próximo
- Patterns emergentes → consolidar em backlog "feature shared multi-persona"
- Feedback estagnado >14 dias → checar se ainda relevante / responder cliente

## Rotina mensal (~30 min — última sexta)

```
Wagner: /feedback-dashboard --month
```

Métricas adicionais:
- Total resolvido no mês
- SLA médio
- Taxa de re-reclamação ("cliente reclamou DE NOVO da mesma coisa após fix?" — sinal de fix superficial)
- Distribuição por persona (Kamila vs Daniela vs Larissa vs Jair)
- Crescimento backlog (entrando > saindo? alerta)

Ações pós-relatório:
- Comunicar piloto cliente: "esse mês fizemos X, Y, Z baseado no feedback de vocês"
- Atualizar `discovery-PROXIMA.md` no diretório do cliente — perguntas pra próxima visita
- Reescorar pesos persona se padrão diverge (Kamila pede mais fiscal? aumentar peso i18n/error_recovery dela)

## Ataque a feedback específico (workflow design-deep integrado)

Quando você ataca um feedback top RICE:

```
Wagner: /design-deep kamila-martinho

[opcional: print da tela]

JOB: <copia do feedback.job_por_tras>
FRICÇÃO: <copia do feedback.o_que_disse.literal>
SUCESSO: <ação resolvida — ex "emite NF-e sem erro IE em ≤5s">

REF: feedback memory/clientes/martinho-cacambas/feedback/<arquivo>.md
```

Skill `design-deep-analysis` carrega:
- Persona Kamila (com fricoes já atualizadas pelo feedback)
- Charter da tela (com `fricoes_conhecidas.kamila` já atualizada)
- Outras fricoes do mesmo módulo (pattern detection)

Devolve 3 alternativas A/B/C com diff preparado. Você aplica, smoke prod, atualiza feedback YAML com:

```yaml
resolucao:
  data_resolvido: '2026-05-30'
  pr_link: 'https://github.com/wagnerra23/oimpresso.com/pull/XXXX'
  cliente_confirmou: null               # preencher depois
```

## Loop de confirmação cliente

Depois de ~7 dias pós-fix em prod:

```
Wagner pergunta cliente:
"Kamila, lembra que você reclamou que a NF-e dava erro de IE vazia?
Subi um fix semana passada. Tá resolvido?"

Se Kamila confirma:
  /feedback-status <feedback-slug> --resolved --confirmed
  
Se Kamila ainda tem problema:
  /feedback-status <feedback-slug> --reopened
  (skill marca campo `re_reclamacao: true` — sinal de fix superficial)
```

## Métricas que importam

| Métrica | Como interpretar |
|---|---|
| **Backlog growth rate** | >0 vermelho (entra > sai). =0 estável. <0 verde (encolhe). |
| **SLA médio resolvido** | Meta: <7 dias pra sev 4. <14 dias sev 3. <30 dias sev 2. <60 dias sev 1. |
| **Re-reclamação rate** | >10% = fix superficial. Investigar root cause. |
| **Confirmação cliente** | <80% = você fecha sem perguntar. Loop cliente quebrado. |
| **Pattern detection** | 3+ clientes mesma fricção = feature genuína pra roadmap. |

## Anti-patterns

❌ Fechar feedback sem cliente confirmar → fica acreditando que resolveu, cliente continua sofrendo
❌ Skip campo `o_que_disse.literal` → perde nuance pra próxima análise
❌ Não atualizar persona.fricoes → feedback vira ativo morto, próximo design-deep não usa
❌ RICE manual fora do dashboard → não compara objetivamente
❌ Ignorar pattern emergente → resolve 1×1 algo que dava pra resolver multi-cliente

## Integração com canon existente

- **ADR 0105 cliente-como-sinal** — só captura feedback cliente paga ou piloto
- **ADR UI-0016 design contextualizado** — feedback alimenta persona library
- **MCP tasks-*** — severity ≥ 3 cria task automaticamente
- **Skill personas-resolve** Tier A — carrega persona com fricoes atualizadas em qualquer Edit
- **Skill design-deep-analysis** — usa feedback como input pra análise profunda

## Refs

- Voice of Customer (Six Sigma + UX)
- Customer Feedback Triage (Intercom/Productboard)
- NN/g Severity Ratings 1995
- RICE Prioritization (Intercom)
- Job Stories (Paul Adams, Intercom)
- Continuous Discovery Habits (Teresa Torres)
