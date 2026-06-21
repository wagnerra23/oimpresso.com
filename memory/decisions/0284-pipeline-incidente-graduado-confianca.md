---
slug: 0284-pipeline-incidente-graduado-confianca
number: 284
title: "Pipeline de incidente graduado por confiança — porta única, redação cross-tenant, auto-resolve só não-valor"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-17"
module: governance
related: [0093-multi-tenant-isolation-tier-0, 0080-trust-tiers-operacional-audit-findings, 0105-cliente-como-sinal-guiar-sem-mandar, 0070-jira-style-task-management-current-md-removed]
supersedes: []
---

# ADR 0284 — Pipeline de incidente graduado por confiança

> Aceita por Wagner em 2026-06-17 ("aceito"). Promovida da proposta
> `proposals/2026-06-17-pipeline-incidente-graduado-confianca.md` (PR #2909).

## Contexto

Dois esforços convergem para o mesmo ecossistema:

- **Frente 1 (sensor):** detecção determinística de incidente. Primeiro sensor já no
  `main` — `sells_value_sanity` no `jana:health-check` (PR #2907), invariante dura
  `final_total ≤ total_before_tax + tax + shipping`. Nasceu do incidente **Guilherme**
  (`num_uf` 2026-06-05): valor de venda corrompido só foi pego 8 dias depois, quando o
  cliente reportou no WhatsApp — apesar de a lógica de detecção já existir em
  `SellsFinalTotalAuditCommand`.
- **Frente 2 (atendimento autônomo):** cliente reporta no WhatsApp → o sistema enriquece
  o ticket e processa o incidente. Mercado 2026 (Sierra/Decagon/Resolve AI) trata isso
  como *vertical AI agent embutido no produto* com confidence+HITL+ação. Dossiê:
  [`memory/sessions/2026-06-17-arte-atendimento-autonomo-incidente.md`](../sessions/2026-06-17-arte-atendimento-autonomo-incidente.md).

O desenho ingênuo — "o sensor cria um `mcp_task project=FORJA` em triagem" — **quebra em
seis pontos** (review adversário):

1. **Triagem duplicada.** Já existem dois pipelines: `clients_feedbacks`
   (tenant-scoped, com dedup por signature, `severity_nng`, `persona`, `dev_task_requested`)
   e a Triagem da Forja (`mcp_tasks project=FORJA`, cross-tenant). Criar uma 3ª fonte viola
   "não duplicar info entre sistemas".
2. **Confiança misturada.** Sensor determinístico (certeza matemática) e relato de cliente
   (`untrusted_external_data`, pode estar errado ou ser abuso) cairiam com o mesmo peso.
3. **Vazamento Tier 0.** `mcp_tasks` é cross-tenant (visível a Felipe/Maiara/Luiz); o
   incidente carrega `business_id` + valor BRL + PII. Jogar isso cru no store cross-tenant
   repete o vetor que custou `git filter-repo` em 5.033 commits (2026-06-08).
4. **"Autônomo" sem agente.** Se o "[AN]" da triagem é humano, não há automação — é um Jira
   mais bonito. O ADS Dual-Brain (confidence→policy→HITL) não está plugado.
5. **Auto-resolve proibido no caso-bandeira.** A Regra Mestre Tier 0 proíbe mexer em valor
   sem dupla-confirmação + antes→depois + humano. O incidente de valor (o mais limpo de
   diagnosticar) é justamente o que NÃO se resolve sozinho.
6. **Idempotência.** 1 bug → N vendas afetadas não pode virar N tickets; e "resolvido" não
   é "o check passou hoje" (o bug para de gerar novas, mas as linhas corrompidas ficam).

## Decisão

Tratar o ecossistema como **um pipeline de incidente graduado por confiança**, com seis
invariantes:

### 1. Porta única (store canônico)
Estender **`clients_feedbacks`** como o store único de incidente — já é tenant-scoped, já
tem dedup por signature, severity, persona e `dev_task_requested`. A **Triagem da Forja
apenas LÊ/espelha** os itens aprovados (não cria um terceiro pipeline). Proibido criar
`mcp_task` de incidente direto do sensor.

### 2. Origem + confiança carimbadas
Cada item ganha `origem ∈ {sensor, cliente, métrica}` + `trust ∈ {alto, baixo}` (alinhado
ADR 0080). **Sensor determinístico = trust alto** → entra com diagnóstico anexado.
**Relato de cliente (WhatsApp) = trust baixo** → é *nota* até corroboração
(sensor/métrica/humano). Conteúdo NL de cliente nunca dispara ação automatizada sem HITL.

### 3. Redação na fronteira cross-tenant
Dado de incidente fica **tenant-scoped**. O que cruza pra store/visão cross-tenant (Forja,
MCP) é **redigido**: estrutura sim (`16 vendas, biz=4, invariante de desconto, deploy
27/05`), valor BRL e PII **não** (`[redacted Tier 0]`). Pareia com a proibição de valores
BRL em git/MCP.

### 4. Auto-resolve só fora de valor/estoque
- **Valor/estoque** (Regra Mestre Tier 0): pipeline para em **detectar + enriquecer +
  propor**. Correção sempre humana, via `sells:final-total-audit` (dupla-confirmação).
- **Operacional não-valor** (mídia órfã, job preso, canal Baileys caído): candidato a
  **self-healing real** (auto-resolve), com verificação pós-ação.

### 5. Autonomia explícita (agente propõe, humano aprova)
Separar no schema o que o **agente** faz (enriquecer: correlacionar vendas/deploy/invariante
— há fato determinístico) do que o **humano** faz (aprovar). A decisão roteia pelo **ADS
Dual-Brain** (confidence→policy→HITL) — que precisa ser plugado ANTES de qualquer rótulo
"autônomo".

### 6. Idempotência por signature
**1 incidente = 1 item** por signature (reusa `computeSignature`/`findDuplicateWithin90d`).
Re-disparo bumpa recorrência, não cria novo. Critério de "resolvido" = **dado subjacente
corrigido**, não "check verde hoje".

## Consequências

**Positivas**
- Uma fonte de verdade de incidente; Forja vira leitura, não 3º pipeline.
- Sensor (Frente 1) e WhatsApp (Frente 2) entram pela mesma porta com confiança distinta.
- Tier 0 protegido por construção (redação na fronteira).
- Honestidade de produto: "auto-triage + auto-enrich" no valor; "self-healing" só no
  operacional. Sem vender o que a Regra Mestre proíbe.

**Custos / riscos**
- Estender `clients_feedbacks` (origem/trust/redação) + a Forja Triagem ler dali = trabalho
  de schema + UI. Menor que manter 2-3 pipelines em drift.
- ADS precisa ser plugado pra a parte "agente enriquece" sair do papel (hoje aspiracional).

## Alternativas rejeitadas

- **Sensor cria `mcp_task project=FORJA` direto** (proposta inicial do autor): rejeitada —
  abre 3ª porta, mistura confiança e vaza Tier 0 (pontos 1-3 do contexto).
- **Auto-corrigir valor quando confidence alto:** rejeitada — viola Regra Mestre Tier 0
  (origem deste incidente). Inegociável.
- **Pipeline novo dedicado (tabela nova):** rejeitada — `clients_feedbacks` já cobre 80%;
  criar tabela nova é o anti-padrão "não duplicar".

## Próximos passos

1. Migration: `clients_feedbacks` + `origem`, `trust`, flag `valor_redigido`.
2. Sensor `sells_value_sanity` cria `clients_feedbacks` (origem=sensor, trust=alto,
   diagnóstico redigido) em vez de só logar — com dedup por signature.
3. Forja Triagem lê os aprovados de `clients_feedbacks` (espelho, read-only).
4. Plugar ADS no roteamento de enriquecimento (confidence→HITL).
5. Catálogo de incidentes operacionais **não-valor** elegíveis a auto-resolve.
