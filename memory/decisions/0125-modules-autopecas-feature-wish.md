---
slug: 0125-modules-autopecas-feature-wish
number: 125
title: "Modules/Autopecas como feature-wish — Vargas é sinal qualificado"
type: adr
status: proposto
authority: canonical
lifecycle: feature_wish
decided_by: [W]
decided_at: "2026-05-10"
module: Autopecas
quarter: 2026-Q4
tags: [arquitetura, modular, multi-vertical, autopecas, vargas, feature-wish, sinal-qualificado]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: ["0121-oimpresso-modular-especializado-por-vertical", "0105-cliente-como-sinal-guiar-sem-mandar", "0103-eventos-fiscais-separados-por-modelo", "0119-migration-factory-capacidade-institucional", "0011-alinhamento-padrao-jana", "0094-constituicao-v2-7-camadas-8-principios", "0093-multi-tenant-isolation-tier-0", "0106-recalibracao-velocidade-fator-10x-ia-pair"]
pii: false
review_triggers:
  - "Vargas assinar contrato Enterprise pioneer (R$ 1.499/m grandfathered) → mover pra ADR de ativação `em_construcao`"
  - "Vargas recusar migração após 60 dias outreach → marcar status `historical`, pausar Modules/Autopecas até 2º sinal qualificado"
  - "2º cliente autopeças saudável OfficeImpresso (não-Vargas) sinalizar interesse → re-acelerar"
  - "Modules/OficinaAuto ativar antes (cliente piloto fechado) → revisar reuso catálogo peças shared (US-AUTO-008 vs US-AP-001)"
  - "12 meses sem sinal Vargas + sem 2º cliente autopeças → arquivar `historical` (ADR 0095 lifecycle)"
---

# ADR 0125 — Modules/Autopecas como feature-wish (Vargas é sinal qualificado)

## Contexto

Em 2026-05-10, durante revisão do `PLANO-MIGRACAO-6-SAUDAVEIS.md` (Modules/ComunicacaoVisual), Wagner [W] confirmou erro de classificação: **Vargas** (cliente saudável WR Sistemas, R$ 7,9M GMV/ano, 26 anos de relação, build Delphi versão 1468) **não é comunicação visual**. Wagner falou textualmente:

> *"autopecas"*

Razão social provável: "Vargas Acessorios" / "Vargas Jato de Granalha" — banco no registry "Jardel Acessorios" sugere CNAE **4530-X** (comércio de peças e acessórios para veículos automotores), não CNAE 1813-0/01 (impressão de material).

Isso retira Vargas do funil Modules/ComunicacaoVisual e abre questão arquitetural:

- **Modules/OficinaAuto** ([SPEC](../requisitos/OficinaAuto/SPEC.md)) já existe como feature-wish, foca **serviço** (mão-de-obra mecânica, OS por veículo, tabela tempária Sindirepa)
- **Autopeças** é fluxo **comércio** distinto: B2B/B2C balcão, alta rotatividade SKU, tabela aplicação por veículo (chassis/ano/modelo), múltiplos fornecedores, devolução comum, garantia loja vs fabricante
- Sobreposição com OficinaAuto: ambos consultam catálogo peças por aplicação; oficina **consome** peça, autopeças **revende** peça

Vargas satisfaz [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — **sinal qualificado real**:

| Critério ADR 0105 | Vargas |
|---|---|
| Cliente paga | ✅ ~R$ 600-850/m WR Sistemas há 26 anos (estimativa — confirmar via snapshot financeiro) |
| Cliente reporta | ✅ relação direta com Wagner; histórico de chamados/upgrades |
| Métrica detecta drift | 🟡 build 1468 desatualizado = sinal de cliente que não acompanha; risco churn latente |
| Decisor disponível | ✅ Wagner conhece dono direto |

Vargas não é especulação — é o maior cliente legacy WR Sistemas (R$ 7,9M GMV, 30% do agregado dos 6+1 saudáveis), com vínculo afetivo que Mubisys/Zênite não têm.

## Decisão

**Criar Modules/Autopecas como vertical separado** (status `feature-wish`, lifecycle `aguarda-sinal-qualificado`), **subordinado a ADR 0121** (modular especializado por vertical), **não dentro de Modules/OficinaAuto**.

Razões pra módulo separado, não sub-módulo OficinaAuto:

1. **Persona distinta** — autopeças é balconista + comprador + entregador; oficina é mecânico + atendente + dono. Workflow diferente
2. **Métrica negócio distinta** — autopeças mede giro de SKU + ticket médio + devolução%; oficina mede OS/dia + tempo médio + comissão mecânico
3. **Pricing tier diferente** — autopeças balcão alta rotatividade exige PDV ágil (p95<1500ms na venda balcão); oficina é low-touch
4. **Concorrência diferente** — autopeças disputa Auto Manager, Lokoz, FutureSysSoft, Mecanizou, Linx Microvix; oficina disputa Ultracar, Oficina Integrada, Onmotor, Manager Full
5. **Cliente piloto distinto** — Vargas é autopeças puro; Martinho Caçambas (candidato OficinaAuto) seria oficina ou vestuário/caçamba (sinal indireto)

**Reuso compartilhado:** ambos os módulos podem consumir um futuro **catálogo de peças por aplicação veículo** (SKU + chassis/ano/modelo) — extraível como shared infra quando 2º vertical ativar. Por enquanto, cada módulo escopa o seu.

## Princípios derivados

### P1 — Modules/Autopecas implementa o **comércio** de peças, não o **serviço** de aplicação
Vendas balcão, NFC-e ágil, controle estoque mínimo, tabela preço por categoria/montadora, devolução, garantia. Aplicação física (montar peça em veículo) **fica em** Modules/OficinaAuto (US-AUTO-006 multi-mecânico).

### P2 — Catálogo SKU + aplicação veicular é **shared infra futura**
Quando Modules/OficinaAuto ativar (sinal qualificado pendente), extrair tabelas `pecas` + `peca_aplicacoes` (chassis_ranges, ano_min, ano_max, modelo, montadora) como pacote compartilhado. Por enquanto, Modules/Autopecas implementa standalone.

### P3 — Vargas como gatilho exclusivo (até 2º sinal)
Sem Vargas assinar, módulo permanece `feature-wish`. Outreach Wagner [W] direto Q4/26 (após Modules/ComunicacaoVisual ter Sprint 1 entregue + 1º piloto comvisual estabilizado).

### P4 — Modules/Repair shared infra (Kanban OS) **não se aplica** a autopeças
Repair foca OS de reparo (gráfico/oficina/eletrônico). Autopeças balcão não tem OS — tem pedido + nota fiscal. Reusa catálogo + financeiro + NFe núcleo, **não Repair**.

## Trigger condições pra ativar (`feature-wish` → `em_construcao`)

**Mesmo padrão OficinaAuto.charter §10 — TODOS obrigatórios:**

1. **Vargas assina contrato pioneer** Enterprise R$ 1.499/m grandfathered (24m), com 50% off primeiros 6m + setup R$ 0
   - Contrato escrito, não promessa
   - Compromisso reportar bugs/features semanal por 6 meses
   - Autoriza migração full Migration Factory (ADR 0119) — Strangler Fig + parallel run 30d

2. **Snapshot financeiro Vargas confirmado** via skill `officeimpresso-financial-snapshot`
   - Ticket pago real (não estimativa)
   - Recência último update Delphi
   - Sinais churn (uso DB caindo, número notas/m caindo)
   - Sem snapshot = chute, não plano (Wagner exigência 2026-05-10)

3. **6 features mínimas escopadas** em SPEC como US-AP-001..006 (paridade competitiva mínima vs Auto Manager / Lokoz):
   - US-AP-001 — Catálogo SKU + tabela aplicação por veículo (chassis/ano/modelo/montadora)
   - US-AP-002 — Venda balcão rápida (p95<1500ms)
   - US-AP-003 — Tabela preço por categoria/montadora
   - US-AP-004 — Controle estoque mínimo + alertas
   - US-AP-005 — Devolução com motivo + impacto em estoque
   - US-AP-006 — Garantia loja vs fabricante (registro + lookup)

4. **Cycle alocado** com goals outcome-oriented + WIP atribuído (não fica em backlog vago — ADR 0070)

5. **Wagner aprova ADR de promoção** (`charter_version: 2`, `status: em_construcao`, registra Vargas como piloto + cycle + escopo)

## Alternativas consideradas

### A — Vargas fica no OfficeImpresso (status quo)
- Manter Delphi WR Sistemas legacy + releases manutenção pago as-is
- TAM: 1 cliente, R$ 600-850/m sustentando relação 26y
- ❌ rejeitada: Vargas é pioneer natural, mais saudável OfficeImpresso, perder pra Mubisys/Auto Manager seria racha narrativa "26 anos relação". Defender ativamente

### B — Sub-módulo dentro de Modules/OficinaAuto
- Modules/OficinaAuto inclui balcão de peças como flag opcional
- TAM combinado, mas confunde persona/pricing/métrica
- ❌ rejeitada: viola SoC brutal (ADR 0094 §5). Persona/workflow/concorrência diferente. Charter de OficinaAuto explicitamente Non-Goal *"e-commerce de peças / marketplace integration"* (§3) — autopeças balcão não é e-commerce mas mistura conceitos

### C — Modules/Autopecas separado ✅ ESCOLHIDA
- Vertical próprio sob ADR 0121 §P3 (sinal qualificado obrigatório)
- Reuso futuro catálogo SKU + aplicação como shared infra (quando OficinaAuto ativar)
- Pricing tier dedicado (a calibrar — provavelmente Pro R$ 399/m + Enterprise R$ 999/m menores que OficinaAuto por ser mais commodity)
- Cabe na arquitetura nWidart existente

### D — Esperar 2º cliente autopeças antes de criar SPEC
- Postergar até 2 sinais qualificados
- ❌ rejeitada: Vargas é maior cliente saudável legacy + 26y relação. Esperar = perder pra concorrente que chega primeiro. Criar feature-wish com gatilho rigoroso (alternativa C) custa 4 markdown e protege relação

## Riscos

### 1. Vertical extra dilui foco do time (5 pessoas)
- Modules/Vestuario live + ComunicacaoVisual em construção + OficinaAuto feature-wish já são 3 verticais. Autopecas vira 4º
- **Mitigação:** status `feature-wish` é proteção. Sem Vargas assinar, não codar. ADR 0094 §5 SoC brutal: 1 vertical comprovado > 4 mornos
- **Reforço:** [ADR 0121 §metrics-success](0121-oimpresso-modular-especializado-por-vertical.md): "<2 clientes pagantes em 12m após launch formal" → revisar pra `historical`

### 2. Dependency Modules/Repair shared infra (NÃO aplica)
- OficinaAuto reusa Repair (Kanban OS, JobSheet) — Autopecas **NÃO**
- Catálogo peças (P2) é shared futura, não imediata
- **Mitigação:** Modules/Autopecas standalone na primeira versão; refactor catálogo só com 2 verticais ativos

### 3. Vargas pode recusar migração
- Cliente conservador (build Delphi 1468 desatualizado), 26y de hábito
- **Mitigação:** outreach Wagner direto, presencial/Zoom 60min (não cold email); pacote pioneer agressivo (setup R$ 0 + 50% off 6m); Plano B mantém Vargas no OfficeImpresso
- **Plano B reforço:** [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) "guiar sem mandar" — não churnar Vargas por pressão de migração

### 4. Concorrência autopeças BR madura
- Auto Manager, Lokoz, FutureSysSoft, Mecanizou, Linx Microvix dominam mid-market
- Vargas pode ter contato/proposta de algum desses já
- **Mitigação:** vínculo Wagner-Vargas 26y é ativo intransferível; diferencial Jana IA + NFC-e auto a partir de boleto (US-RB-044 ✅) + multi-tenant Tier 0 + stack moderna

### 5. Cliente piloto único (Vargas) = single point of failure
- Se Vargas migrar e voltar pro Delphi em <30d, Modules/Autopecas vira módulo morto
- **Mitigação:** Migration Factory ([ADR 0119](0119-migration-factory-capacidade-institucional.md)) Pattern Strangler Fig + parallel run 30d garante rollback; review_trigger #4 ADR 0119 acionado se cliente voltar
- **Reforço:** snapshot financeiro pré-migração + Pest validators count/totals match (Pattern 07)

## Consequências

### Positivas
- **Resgata maior cliente saudável WR Sistemas** sem forçar comvisual mismatch (Vargas é autopeças, não gráfica)
- **Diversifica portfolio vertical** — 4º módulo na grade ADR 0121, fortalece narrativa multi-vertical
- **TAM agregado +120k oficinas + autopeças combinados** (CINAU 121k oficinas mecânicas; Sindipeças aponta ~30-40k autopeças formais BR)
- **Reuso futuro catálogo peças** acelera Modules/OficinaAuto quando ativar
- **Gatilho rigoroso** (Vargas assinatura) protege contra ansiedade — ADR 0105 enforced

### Negativas
- **4º vertical em paralelo** com 5 pessoas — exige disciplina
- **Pricing tier ainda não calibrado** — pesquisa concorrentes BR autopeças necessária antes outreach Vargas
- **Schema multi-vertical** (proposto F18, ADR 0121 §P4) precisa estar entregue antes de scaffoldear

### Mitigações
- Status `feature-wish` é guarda — sem Vargas assinatura, zero código
- Outreach Vargas só após Modules/ComunicacaoVisual Sprint 1 entregue + 1º piloto comvisual estabilizado (Q1/27 estimado)
- Pricing calibração via research separado (`memory/research/2026-Q4-prospeccao-autopecas/` quando ativar)

## Alinhamento com ADRs canon

- **[ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md)** — adiciona 4º vertical à grade modular; respeita §P3 (sinal qualificado obrigatório), §P7 (cliente piloto validador)
- **[ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)** — Vargas satisfaz 3 dos 4 critérios sinal qualificado (paga + reporta + decisor disponível)
- **[ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)** — princípios duros aplicáveis (multi-tenant Tier 0, SoC brutal, transparência, fallback)
- **[ADR 0093](0093-multi-tenant-isolation-tier-0.md)** — toda Eloquent Model nova de Modules/Autopecas terá `business_id` global scope
- **[ADR 0119](0119-migration-factory-capacidade-institucional.md)** — Vargas migra via Migration Factory pattern Strangler Fig + parallel run 30d
- **[ADR 0011](0011-alinhamento-padrao-jana.md)** — scaffolding (quando ativar) imita Modules/Jana / Modules/Vestuario
- **[ADR 0103](0103-feature-wish-lifecycle.md)** (se existir) — formaliza lifecycle feature-wish; senão herda de ADR 0121

## Métricas de sucesso (12m após ativação, NÃO antes)

| Métrica | Baseline (M0 ativação) | M6 | M12 | Crítica |
|---|---|---|---|---|
| Clientes pagantes Modules/Autopecas | 1 (Vargas) | 2-3 | **5-10** | <3 = re-avaliar tese |
| ARR módulo (R$/ano) | R$ 18k (Vargas Enterprise) | R$ 36-54k | R$ 90-180k | <R$ 60k = pivotar |
| US entregues (de 12-15 totais) | 6 (mínimo P0) | 10 (P0+P1) | 12-13 | <10 = stack mal calibrado |
| Cases públicos clicáveis | 0 | 1 (Vargas) | 2 | (transparência radical) |
| Bug crítico produção | n/a | <1/mês | <1/trimestre | (Pest gate ADR 0094) |
| Churn módulo | n/a | <5%/m | <8%/ano | (review trigger ADR 0121) |

**Convergência [ADR 0022](0022-meta-5mi-ano-financeira.md):** Modules/Autopecas contribui R$ 90-180k ARR de R$ 5M total (1.8-3.6% no M12 pós-ativação). Multi-vertical é tese; autopeças é diversificação Vargas-driven.

## Decisão pendente subordinada

- **Pricing tier Modules/Autopecas** — research BR concorrentes (Auto Manager, Lokoz, FutureSysSoft, Mecanizou, Linx Microvix) pendente; calibrar antes outreach Vargas Q4/26
- **Reuso catálogo peças shared** com Modules/OficinaAuto — extrair quando OficinaAuto ativar (atualmente ambos `feature-wish`)

## Referências

- [Modules/OficinaAuto SPEC](../requisitos/OficinaAuto/SPEC.md) — vertical próximo, template imitado
- [Modules/OficinaAuto charter](../requisitos/OficinaAuto/OficinaAuto.charter.md) — template charter v1
- [Modules/ComunicacaoVisual PLANO-MIGRACAO-6-SAUDAVEIS.md](../requisitos/ComunicacaoVisual/PLANO-MIGRACAO-6-SAUDAVEIS.md) — Vargas removido pro autopeças, plano comvisual atualizado
- [Modules/Autopecas SPEC](../requisitos/Autopecas/SPEC.md) — contrato funcional construído sobre esta ADR
- [Modules/Autopecas charter](../requisitos/Autopecas/Autopecas.charter.md) — charter v1 antecipatório
- [Modules/Autopecas PLANO-MIGRACAO-VARGAS.md](../requisitos/Autopecas/PLANO-MIGRACAO-VARGAS.md) — plano operacional Vargas

---

**Última atualização:** 2026-05-10 — ADR criada após Wagner [W] confirmar Vargas = autopeças (não comvisual). Status `proposed` aguardando review humano antes de promover pra `accepted`. Sem Vargas assinatura, módulo permanece `feature-wish` — viola ADR 0105 ativar trabalho. Revisar quando 1º outreach Vargas executado.
