---
slug: 0152-modules-pcp-feature-wish
number: 152
title: "Modules/Pcp como feature-wish — aguarda Vargas ou ComVis 1º piloto"
type: adr
status: proposed
authority: canonical
lifecycle: feature_wish
decided_by: [W]
decided_at: 2026-05-15
module: Pcp
quarter: 2026-Q4
tags: [arquitetura, modular, cross-vertical, pcp, apontamento, feature-wish, sinal-qualificado, adr-0105]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: [0105, 0121, 0093, 0094, 0125, 0143, 0104, 0106, 0137]
pii: false
review_triggers:
  - "Vargas (ADR 0125) assinar + demandar apontamento multi-mecânico (recapagem 2-3 mecânicos por OS — gateway US-PCP-007 + US-PCP-014)"
  - "ComVis 1º piloto (Extreme/Mhundo/Vargas autopecas) ativar plotter + acabamento + instalação com 2+ postos e reportar gargalo manual de visibilidade"
  - "OficinaAuto Martinho ativar com 2+ mecânicos e demandar cronômetro per-operação (US-PCP-007)"
  - "Repair Kanban atual (`Modules/Repair/ProducaoOficinaController`) atinge limite operacional (cliente reporta que precisa de granularidade OPERATION-level acima do STAGE-level)"
  - "12 meses sem sinal qualificado de NENHUM dos 4 verticais com produção física → arquivar `historical` (ADR 0095 lifecycle)"
---

# ADR 0152 — Modules/Pcp como feature-wish (aguarda Vargas ou ComVis 1º piloto)

## Contexto

Em 2026-05-12, durante o discovery cross-vertical pós-PR #623 (timeline drawer FSM canon), foi identificado o gap **OPERATION-level apontamento** — granularidade de "quem-fez-o-quê-quando-em-qual-posto" que o oimpresso hoje NÃO cobre. Foi proposto `Modules/Pcp` ([SPEC](../requisitos/Pcp/SPEC.md)) como camada fina cross-vertical com 20 US (US-PCP-001 a US-PCP-020).

O SPEC §0 (discovery rigoroso) provou que **~60% do que PCP precisa já existe** no oimpresso:
- ✅ Kanban OS visual: `Modules/Repair/ProducaoOficinaController` (shared infra refactor 2026-05-10)
- ✅ Lookups Status/Estágio dinâmicos: `repair_statuses` + `RepairStatus.HasBusinessScope` + FSM canon `sale_process_stages`
- ✅ State machine produção: FSM canon ADR 0143 stages `in_production`, `em_execucao`, `pausado`
- ✅ OS/Ordem produção entity: `repair_job_sheets` + `transactions` + `service_orders`
- ✅ BoM/Receita: `Modules/Manufacturing/MfgRecipe`
- ✅ Histórico timeline: `sale_stage_history` append-only
- ✅ Notificações cliente: `RepairStatusChanged` event

**Gap real (40%)** = apontamento OPERATION-level (`pcp_appointments` append-only) + workstation capacity (`pcp_workstations`) + operations catalog (`pcp_operations`) + cronômetro mobile QR scan + bottleneck detection + schedule drag-drop + dashboard PCP.

**Problema:** as 20 US foram parseadas pro MCP server em 2026-05-12 e aparecem hoje na triage como P0/P1 sem owner (16 vagas no triage atual). Mas nenhum dos verticais com produção física tem cliente pagante ativo:

| Vertical | Produção física? | Cliente ativo? | Reporta gap OPERATION-level? |
|---|---|---|---|
| **Modules/Vestuario (biz=4)** | ❌ revenda (não produz) | ✅ Larissa | n/a |
| **Modules/ComunicacaoVisual** | ✅ plotter + acabamento + instalação | 🟡 em construção | nenhum piloto fechado |
| **Modules/OficinaAuto** | ✅ mecânico apontamento | ⏸️ aguarda Martinho | ainda nem ativo |
| **Modules/Autopecas (ADR 0125)** | ❌ revenda balcão (Vargas pode ter recapagem = produção) | ⏸️ aguarda Vargas assinar | bloqueado |
| **Modules/Repair (legacy gráfico)** | ✅ existente | 🟡 0 clientes oimpresso novo (era do legacy OfficeImpresso) | n/a |

O SPEC já tem header explícito: *"piloto_previsto: Vargas (recapagem multi-mecânico) OU Extreme (ComVis multi-plotter) — sinal qualificado pendente"*. Trigger ADR 0105 não satisfeito.

## Decisão

**Modules/Pcp permanece como feature-wish** (status `feature-wish`, lifecycle `aguarda-sinal-qualificado`), subordinado a [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) e [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md). SPEC fica intacto como documentação de referência, com:

1. **As 20 US (US-PCP-001 a US-PCP-020) NÃO entram em backlog ativo.** Triage MCP pode continuar listando, mas guidance é "não atribuir owner".
2. **Nenhum scaffolding** em `Modules/Pcp/` (módulo nem foi criado) ou `Modules/IProduction/` (placeholder existente — DECISÃO D1 do SPEC §7 fica também dormente).
3. **SPEC ganha aviso destacado no topo** ("⛔ DORMENTE — ver ADR 0152").
4. **Kanban shared infra `Modules/Repair/ProducaoOficinaController` continua sendo o ponto único de Kanban** — quando PCP ativar, US-PCP-011 estende com agrupador configurável (8 agrupadores Delphi mapeados em `research/clientes-legacy-officeimpresso/_MAPPING/TELA-PRODUCAO-KANBAN.md`).

Razões pra status feature-wish, não ativação imediata:

1. **Larissa (única produção real biz=4) NÃO produz nada** — Vestuario é revenda. PCP não tem aplicação biz=4
2. **ComVis em Sprint 1 sem piloto fechado** — antecipar PCP cross-vertical sem cliente concreto = código sem validação
3. **Martinho (candidato OficinaAuto) não confirmado** — Wagner outreach pendente; ADR 0105 critério decisor não satisfeito
4. **Vargas (candidato Autopecas ADR 0125) tem recapagem** (produção potencial multi-mecânico), mas autopecas ativa ANTES de PCP — sequenciamento exige Vargas assinar primeiro
5. **DECISÃO D1 (Pcp vs IProduction)** — US-PCP-002 marcada como P0 BLOCKER pelo próprio SPEC §7. Wagner precisa decidir se PCP nasce em `Modules/Pcp` novo OU assume `Modules/IProduction` placeholder existente. Decisão exige contexto vivo (1º piloto rodando), não dá pra decidir no abstrato
6. **Custo de modules vazios** — `Modules/IProduction` já está vazio gerando ruído de "módulo placeholder"; adicionar `Modules/Pcp` vazio dobra ruído (2 módulos sem cliente)
7. **Time pequeno (5 pessoas) + CYCLE-06 com 4 goals zerados** — adicionar PCP = dispersão; ADR 0094 §5 SoC brutal

## Princípios derivados

### P1 — OPERATION-level é dor latente até produção física rodar com volume
Apontamento OPERATION-level só faz sentido quando há ≥10 OS/dia/posto com 2+ operadores simultâneos. Hoje 0 clientes oimpresso novo atendem o pré-requisito de volume.

### P2 — Kanban STAGE-level (atual) cobre 80% dos casos PME
`Modules/Repair/ProducaoOficinaController` (Inertia, shared infra) já dá visibilidade Kanban + drag-drop + lookup status dinâmico. Para 1-2 operadores per posto, STAGE-level é suficiente. PCP só vira ROI quando 3+ operadores compartilhando posto OU 3+ postos em paralelo.

### P3 — `Modules/IProduction` placeholder não vira `Modules/Pcp` automaticamente
DECISÃO D1 do SPEC §7 é genuinamente difícil sem contexto vivo. Decidir agora = chute. Decidir quando 1º cliente ativo = informed.

### P4 — Quando ativar, FSM canon ADR 0143 absorve sem mudança
PCP adiciona **actions intermediárias** (`apontar_inicio_operacao`, `apontar_pausa_operacao`, `apontar_fim_operacao`, `apontar_perda`, `vincular_qr_token`) dentro de stages existentes — não cria processo FSM novo. Risco zero pra FSM canon vivo.

### P5 — SPEC sobrevive intacto como blueprint
SPEC `Modules/Pcp/SPEC.md` tem 20 US + 5 cenários peculiares (A-E) + 4 schema tables + 10 regras Gherkin + mapping mercado (TOTVS/SAP/Mubisys/Sankhya/Odoo). Trabalho pré-pago. Ao ativar, dev abre o SPEC e tem ~3 semanas de design feito.

## Trigger condições pra ativar (`feature-wish` → `em_construcao`)

**Pelo menos UM dos abaixo precisa ser satisfeito**, com Wagner [W] aprovando ADR de ativação:

1. **Vargas (ADR 0125) assina + recapagem multi-mecânico identificada** — 2-3 mecânicos por OS de pneu cobrindo apontamento de operação; gateway natural pra US-PCP-007 (Service `RegisterAppointment` idempotent)
2. **ComVis 1º piloto ativo (Extreme/Mhundo/Vargas) + 2+ postos paralelos** — operador relata "perdi 1h pra saber qual OS plotter UV está rodando"; gateway pra US-PCP-012 (bottleneck detection)
3. **Martinho (OficinaAuto) ativa + 2+ mecânicos no balcão** — apontamento de operação por mecânico vira gateway pra US-PCP-014 (performance operador)
4. **Cliente Repair gráfico legacy ativo demanda granularidade OPERATION** — cenário improvável (Repair atual tem 0 clientes oimpresso novo)
5. **Wagner sinal exploratório** (raro, exceção) — Wagner escolhe codar PCP mesmo sem cliente pra capturar diferencial competitivo TOTVS/SAP em PME (PME mercado BR não tem PCP barato); exige ADR formal justificando especulação

## Decisões pendentes subordinadas (ADR de ativação resolve)

Quando trigger satisfeito, ADR de promoção deve resolver:

- **D1 — `Modules/Pcp` novo OU assume `Modules/IProduction` placeholder?** SPEC §7 US-PCP-002 P0 BLOCKER. Recomendação tentativa: assumir IProduction (renomear pra Pcp via skill `migrar-modulo` ADR 0088) pra não criar 2 módulos vazios
- **D2 — PWA mobile (vite-plugin-pwa + IndexedDB offline) OU app nativo (React Native/Capacitor)?** SPEC §5. Recomendação tentativa: PWA (menor custo, stack canônica ADR 0094)
- **D3 — Cronômetro automático (calculado pelo backend via diff timestamps) OU manual (operador clica start/stop)?** Trigger UPDATE em `pcp_appointments` é apêndice. Recomendação tentativa: manual (operador controle) + audit log do start/stop
- **D4 — Mass insert performance** quando volume escalar (>10k apontamentos/dia/business)?  Backlog futuro
- **D5 — Integração BoM `MfgRecipe` (US-PCP-016)** — `apontar_fim_operacao` consome estoque automaticamente?  P2 — decide quando ativar

## Alternativas consideradas

### A — Codar US-PCP-001..010 (P0 stack) em CYCLE-06 ✗ rejeitada
- Bate goal "FSM rollout biz=1" parcialmente (PCP usa FSM canon)
- TAM: 0 clientes pagantes adicionais (Larissa não produz)
- ❌ rejeitada: 4 goals zerados no cycle; SoC brutal exige foco

### B — Mover SPEC pra `proposals/` ✗ rejeitada
- Mesma razão ADR 0151 §B — perde visibilidade canônica

### C — `Modules/Pcp` como feature-wish + SPEC com aviso DORMENTE ✅ ESCOLHIDA
- Status: feature-wish; lifecycle: aguarda-sinal-qualificado
- SPEC fica no path canônico `memory/requisitos/Pcp/SPEC.md`
- ADR 0152 (este) registra decisão + triggers
- D1-D5 do SPEC §7 também ficam dormentes (ADR de ativação resolve)

### D — Renomear `Modules/IProduction` pra `Modules/Pcp` JÁ ✗ rejeitada
- Resolveria D1 antecipadamente, mas D1 é genuinamente difícil sem contexto vivo
- Risco: renomear placeholder, depois ativar com decisão diferente, ter que renomear de novo = drift entre PRs

### E — Deletar SPEC inteiro ✗ rejeitada
- Mesma razão ADR 0151 §D — blueprint pré-pago não destrói

## Riscos

### 1. Triage MCP continua mostrando 20 US como sem-owner
- Idem ADR 0151 risco #1
- **Mitigação:** aviso topo + ADR 0152 + guidance "não atribuir"; cirurgia parser se time confunde

### 2. ComVis 1º piloto pode resolver visibilidade sem invocar PCP
- Quick win: melhorar Kanban Repair com filtros + agrupador (US-PCP-011 isolado) sem ativar resto do módulo
- **Mitigação:** se ComVis demanda só agrupador Kanban, fazer issue isolada em Modules/Repair (não ativa PCP cross-vertical)

### 3. `Modules/IProduction` placeholder confunde dev novo
- Felipe/Maiara podem perguntar "o que é IProduction?"
- **Mitigação:** adicionar README em `Modules/IProduction/` apontando pro ADR 0152 + SPEC Pcp ("placeholder candidate to become Pcp when activated")

### 4. PCP mercado madura (Mubisys/Sankhya/SAP) ganha terreno BR enquanto oimpresso espera
- Concorrentes podem dominar nicho PME PCP
- **Mitigação:** SPEC já mapeia 13 dimensões competitivas; ao ativar, código sai rápido. Pitch comercial pode mencionar "PCP cross-vertical — roadmap Q2/27" como transparência

### 5. Vargas autopecas (gateway #1) pode não ter produção
- Se Vargas é só revenda balcão (sem recapagem), trigger #1 cai
- **Mitigação:** outreach Vargas deve identificar serviço de recapagem (US-AUTO* / US-OFICINA*) antes de cravar gateway. Fallback: trigger #2 ComVis ou #3 Martinho

## Consequências

### Positivas
- **Foco preservado** no CYCLE-06 (Martinho + FSM + Jana V2 demo)
- **SPEC blueprint pré-pago** intacto (~3 semanas design)
- **ADR 0105 respeitado** — sem cliente que reporta = sem código
- **2 módulos placeholder evitados** (`Modules/Pcp` + `Modules/IProduction` ambos vazios)
- **Time MCP entrando** (Felipe/Maiara/Eliana[E]/Luiz) não precisa entender 20 US órfãs

### Negativas
- **Diferencial competitivo PCP cross-vertical adiado** vs concorrentes mid-market (Mubisys/Sankhya)
- **OPERATION-level apontamento permanece gap** até ≥1 cliente com produção física ativar

### Mitigações
- Pitch comercial pode mencionar "PCP cross-vertical — roadmap Q2/27 com 5 verticais (Sells/ComVis/OficinaAuto/Autopecas/Repair) integrados" (transparência radical ADR 0094 §7)
- Quando trigger ativar, ADR de promoção referencia SPEC como `inheritance` — código sai 2x mais rápido

## Alinhamento com ADRs canon

- **[ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)** — guiar sem mandar; 5 verticais sem sinal qualificado de OPERATION-level = não ativar
- **[ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md)** — módulo cross-vertical, respeita §P3
- **[ADR 0093](0093-multi-tenant-isolation-tier-0.md)** — todas tabelas `pcp_*` terão `business_id` global scope quando ativar
- **[ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)** — SoC brutal §5; foco no CYCLE-06
- **[ADR 0125](0125-modules-autopecas-feature-wish.md)** — precedente feature-wish + gateway #1 (Vargas)
- **[ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)** — FSM canon (PCP adiciona actions intermediárias, não processo novo)
- **[ADR 0104](0104-processo-mwart-canonico-unico-caminho.md)** — telas Pages quando ativar seguem MWART 5 fases
- **[ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md)** — estimates SPEC já em fator 10x
- **[ADR 0137](0137-pivot-oficina-cacamba-vertical-completa.md)** — relacionado (OficinaAuto = gateway #3)

## Referências

- [SPEC Modules/Pcp](../requisitos/Pcp/SPEC.md) — blueprint funcional preservado
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — gatilho de ADR
- [ADR 0151](0151-modules-comissao-feature-wish.md) — ADR gêmea pra Modules/Comissao
- [Mapping Delphi PCP](../research/clientes-legacy-officeimpresso/_MAPPING/TELA-PRODUCAO-KANBAN.md) — 8 agrupadores do legacy
- [SPEC OficinaAuto US-OFICINA-019](../requisitos/OficinaAuto/SPEC.md) — gateway #3
- [SPEC ComunicacaoVisual US-COMVIS-003](../requisitos/ComunicacaoVisual/SPEC.md) — gateway #2

---

**Última atualização:** 2026-05-15 — ADR criada como parte de organização de tarefas no inventário pós-limpeza, gêmea de ADR 0151 (Comissao). Status `proposed` aguardando review humano. SPEC fica intacto com aviso DORMENTE; trigger satisfeito demanda ADR de promoção que resolve D1-D5.
