---
slug: 0171-oficinaauto-ativacao-piloto-martinho-faseada
number: 171
title: "Ativação Modules/OficinaAuto — Piloto Martinho Caçambas (faseada, add-on faturável)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-20"
module: null
quarter: 2026-Q2
tags: [oficina-auto, piloto, ativacao, martinho-cacambas, cliente-sinal, add-on-faturavel, migracao-faseada]
supersedes: []
supersedes_partially: []
amends: [0137]
superseded_by: []
related: [0105-cliente-como-sinal-guiar-sem-mandar, 0137-modules-oficinaauto-qualificada, 0143-fsm-pipeline-live-prod-marco-2026-05-12, 0094-constituicao-v2-7-camadas-8-principios, 0121-oimpresso-modular-especializado-por-vertical, 0106-recalibracao-velocidade-fator-10x-ia-pair, 0119-migration-factory-capacidade-institucional]
pii: false
review_triggers:
  - "Martinho Caçambas churn antes de 90d pós-ativação (sinal de modelo faseado mal calibrado)"
  - "Job mensal apuração WhatsApp por business_id NÃO implementado em Modules/RecurringBilling antes da 1ª fatura pós-beta 30d Martinho (gate operacional — pricing R$ [redacted Tier 0]/instância cravado 2026-05-20)"
  - "≥3 clientes WhatsApp com 5+ instâncias ativas — sinal pra criar tier volume (5+ = R$ [redacted Tier 0]/inst; 10+ = R$ [redacted Tier 0]/inst) e avaliar drift pricing per-instance"
  - "≥ 1 incidente sev1/sev2 nos primeiros 7d canary (gate Fase 3 ROADMAP)"
  - "RUNBOOK-migracao-cliente-legacy.md não atualizado após onboarding Martinho (perde ativo reusável)"
---

# ADR 0171 — Ativação Modules/OficinaAuto · Piloto Martinho Caçambas (faseada, add-on faturável)

## Status

`aceito` 2026-05-20 — sucede a [ADR 0137](0137-modules-oficinaauto-qualificada.md) (qualificação) com **ativação formal em produção** via piloto Martinho Caçambas (biz=164).

## Contexto

[ADR 0137](0137-modules-oficinaauto-qualificada.md) (2026-05-11) qualificou `Modules/OficinaAuto` baseado em 2 de 4 clientes OfficeImpresso saudáveis (Vargas + Martinho) — sinal qualificado conforme [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md). Módulo passou de ⏸️ aguardando sinal pra 🟡 em construção.

Desde então, em ~9 dias (2026-05-11 a 2026-05-20):

- **V0 LIVE em prod**: PR #556 entregou scaffold completo (8 peças nWidart + migrations `vehicles`/`service_orders` + Models + 9 permissions + 8 Pages Inertia + 16 Pest tests)
- **FSM canon LIVE**: [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (2026-05-12, 40+ PRs em ~10h) destravou Pipeline Orçamento→Produção→Venda→Faturamento + audit trail + RBAC granular per-business em prod biz=1
- **Kanban Producao Oficina Caçambas**: PRs #735→#740 (madrugada 2026-05-13 pré-Martinho 10h) replicaram Cowork prototype seguindo skill `cowork-prototype-replication`
- **Import legacy Martinho em prod**: 2026-05-13 13:31 BRT, business_id=164 ("MARTINHO CAÇAMBAS LTDA") recebeu 91 veículos + 91 service_orders importados via `scripts/legacy-migration/import-vehicles.py` (validado dry-run, idempotente)
- **Reunião 2026-05-13 10h**: Martinho aceitou migração; pendentes 4 perguntas comerciais
- **Cycle ativo CYCLE-06**: goal "1º cliente OficinaAuto pagando" · 8d restantes

Em 2026-05-20 Wagner respondeu as 4 perguntas comerciais pendentes (registradas em [discovery-martinho.md](../requisitos/OficinaAuto/demo-martinho-2026-05-13/discovery-martinho.md) seção "Pendente Wagner"):

1. **Aceite confirmado** — Martinho aceita a migração
2. **Prazo** — 7 dias já vencendo, mas pode ser **homologado em partes** (sem cutover hard)
3. **Escopo 1º contato** — OS reais NÃO entram agora; Martinho continua no desktop, equipe Wagner acompanha aprendendo. *"A consistência da migração vai definir o processo"*
4. **Pricing** — plano atual NÃO aumenta; **módulos novos = cobrança extra** (OficinaAuto vertical = add-on faturável separado)

Este conjunto de respostas satisfaz **Cenário A modificado** do [ROADMAP §Fase 3](../requisitos/OficinaAuto/ROADMAP.md#fase-3--1º-piloto-pagante--bulk-migration--canary): 1 piloto aceita + paga add-on. Sinal qualificado [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) está materializado e exige formalização canônica antes de avançar pra Fase 2/3.

## Decisão

1. **Ativar `Modules/OficinaAuto` em produção biz=164** (Martinho Caçambas LTDA) como **1º piloto pagante formal** da vertical oficina.

2. **Modelo de homologação: faseado por feature, NÃO cutover por data**
   - Desktop Office Impresso continua **source-of-truth** pra OS reais até feature ≥ paridade ser homologada por Martinho
   - oimpresso usado **em paralelo** durante aprendizado (Kanban Producao + Aprovação WhatsApp + Cleanup tools US-OFICINA-005)
   - Sem prazo hard final — cada feature migra quando operador (Martinho/filho) aprovar via screenshot + smoke real
   - Coexistência opt-in herda padrão [ADR 0143 §"Coexistência com sistema legacy"](0143-fsm-pipeline-live-prod-marco-2026-05-12.md): legacy preservado, rollout gradual sem big-bang

3. **Pricing: pacote atual mantido + add-on apenas pra módulos NOVOS (não inclusos)**
   - **Base atual Martinho = R$ [redacted Tier 0]/mês** já cobre: núcleo oimpresso + **vertical mecânica (Modules/OficinaAuto)** + **NFe + NFSe** — **NÃO aumentar** (Wagner 2026-05-20: *"o que ela paga hoje não vou aumentar"*)
   - **OficinaAuto NÃO é add-on extra pro Martinho** — já está incluso no pacote R$ [redacted Tier 0] (diferente do princípio P5 [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) que prevê add-on separado pra novos clientes; Martinho é grandfathered como Cenário A modificado).
   - **Add-on extra cobrado adicional apenas pra módulos NOVOS** (não inclusos no pacote atual). 1º candidato: **Modules/WhatsApp** — Martinho hoje paga outro sistema externo; ao migrar pra inbox unificado oimpresso vira add-on faturável separado. **Pricing cravado Wagner 2026-05-20: R$ [redacted Tier 0]/mês por instância (= por número de telefone WhatsApp Business conectado), atendentes ilimitados dentro de cada instância** (paridade ZapBoss; diferencial oimpresso = multi-tenant Tier 0 + auto-link CRM + macros + SLA + Jana IA sem cobrança per-seat). Apuração via job mensal: `SELECT business_id, COUNT(*) FROM whatsapp_instances WHERE status='connected' AND deleted_at IS NULL GROUP BY business_id` × R$ [redacted Tier 0]
   - **Beta primeiros 30 dias = R$ [redacted Tier 0]** sobre o add-on novo (validação — alinhado opção A do [charter-1pager §"Próximo passo"](../requisitos/OficinaAuto/demo-martinho-2026-05-13/charter-1pager.md)). Base R$ [redacted Tier 0] segue normal.
   - Após beta 30d, cobrança via [Modules/RecurringBilling](../requisitos/RecurringBilling/SPEC.md) — boleto Inter PJ OU PIX Asaas mensal, fatura separada do pacote base.

4. **Aprendizado canônico vira ativo reusável**
   - Equipe Wagner + Felipe + Maiara acompanha ativamente Martinho durante homologação faseada
   - Captura processo passo-a-passo em `memory/requisitos/OficinaAuto/RUNBOOK-migracao-cliente-legacy.md` (criar em paralelo a este ADR)
   - RUNBOOK vira input pra próximos 6 clientes OfficeImpresso saudáveis no pipeline: Vargas / Extreme / Gold / Zoom / Fixar / Produart (perfis em [memory/research/clientes-legacy-officeimpresso/](../research/clientes-legacy-officeimpresso/))
   - Princípio Migration Factory [ADR 0119](0119-paralelismo-sessoes-whats-active-tier-1.md): 1 piloto/mês até M3 pós-ativação

5. **Gating das próximas fases (HARD)**
   - **Fase 2 ([ROADMAP](../requisitos/OficinaAuto/ROADMAP.md#fase-2--fsm-wire-up--diferenciais-vertical-vargas-ready) — FSM wire-up + UI drawer + diferenciais vertical):** ativa quando Martinho **assinar add-on contratual** OU aceitar beta 30d explicitamente (assinatura digital ou WhatsApp confirmado registrado em CRM)
   - **Fase 3 ([ROADMAP](../requisitos/OficinaAuto/ROADMAP.md#fase-3--1º-piloto-pagante--bulk-migration--canary) — bulk migration 44k vendas + onboarding pleno):** ativa quando ≥ 1 feature OficinaAuto operada por Martinho **7 dias consecutivos sem incidente sev1/sev2**

## Consequências

### Positivas

- ✅ **Satisfaz cycle goal CYCLE-06** ("1º cliente OficinaAuto pagando") dentro dos 8d restantes — destrava ciclo seguinte
- ✅ **Materializa sinal qualificado [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)** com cliente pagante real (não hipótese) — backlog OficinaAuto Fase 2-3 fica legitimado
- ✅ **Destrava OficinaAuto vertical como produto faturável separado** — abre revenue stream incremental sem canibalizar base UltimatePOS
- ✅ **Gera RUNBOOK canônico** → próximos 6 clientes legacy onboarded 5-10× mais rápido (skill `criar-modulo` + `multi-tenant-patterns` reusados; RUNBOOK adiciona camada vertical)
- ✅ **Sem risco financeiro pra Martinho** (status quo preservado + beta 30d gratuito) — reduz fricção comercial
- ✅ **Compatível com [ADR 0143 §"Coexistência"](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)** — `current_stage_id` nullable, FSM opt-in per-OS
- ✅ **Reusa fundação** — FSM canon ([ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) + Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) + MWART process ([ADR 0104](0104-processo-mwart-canonico-unico-caminho.md))

### Negativas / riscos

- ⚠️ **Coexistência desktop ↔ oimpresso pode gerar inconsistência de dados** durante homologação faseada. *Mitigação:* cleanup tools US-OFICINA-005 ([ROADMAP Fase 1](../requisitos/OficinaAuto/ROADMAP.md#fase-1--importer--smoke-biz4-martinho-primeiro)) + daily check via `php artisan jana:health-check` + bridge sync Delphi→oimpresso (`Controller.OImpresso.pas`) cobrindo Pessoas/Vendas — gap conhecido em Equipamento
- ⚠️ **Equipe Wagner aprende NO processo** (overhead inicial 1-2 semanas) — Felipe + Maiara redirecionados parcial. *Mitigação:* RUNBOOK paralelo captura aprendizado em tempo real; ROI amortiza nos próximos pilotos
- ⚠️ **Job mensal apuração WhatsApp instâncias ainda não implementado em Modules/RecurringBilling** — pricing R$ [redacted Tier 0]/instância cravado mas falta lógica que conta `whatsapp_instances WHERE status='connected'` por business e gera linha de fatura mensal. *Mitigação:* US criada no SPEC RecurringBilling pré-cobrança 1ª fatura pós-beta 30d Martinho.
- ⚠️ **Modelo per-instância pode ficar caro se cliente abrir múltiplos números** — franquia 10 unidades = R$ [redacted Tier 0]/m. *Mitigação:* review_trigger formal — quando ≥3 clientes com 5+ instâncias, criar tier volume (ex: 5+ instâncias = R$ [redacted Tier 0] cada; 10+ = R$ [redacted Tier 0] cada).
- ⚠️ **76.7% inadimplência legacy Martinho** (R$ [redacted Tier 0]M receita 12m apurada em [03-financeiro Martinho](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md)) exigirá **write-off batch** durante cleanup. *Mitigação:* US-OFICINA-005 cleanup tools com dry-run obrigatório + Wagner aprova batch-a-batch ([publication-policy](../../.claude/skills/publication-policy/SKILL.md))
- ⚠️ **Vocabulário vertical (m³ ≠ m²)** — risco de Claude/dev escorregar e dizer "metros quadrados" pra caçamba. *Mitigação:* [dominios-verticais-oimpresso.md §3.3](../reference/dominios-verticais-oimpresso.md) + skill `preflight-modulo` Tier A

### Neutras

- `Modules/OficinaAuto` continua módulo separado (não fundir em UltimatePOS base) — coerente com [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) modular vertical
- [ADR 0143 FSM canon](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) herdado sem reimplementação — Pipeline Sells + Repair compartilham fundação; OficinaAuto wire-up Fase 2
- Schema multi-placa (`secondary_plate` + `secondary_chassis`) preservado nullable conforme [ADR 0137 §"Modelos essenciais"](0137-modules-oficinaauto-qualificada.md) — Martinho usa só PLACA principal (96% sem multi); Vargas (futuro V1) ativará

## Alternativas consideradas

1. **A — Cutover hard 7 dias** ❌ — recusada Wagner 2026-05-20: *"prazo já vencendo, melhor homologar em partes"*. Risco operacional alto (Martinho não pode parar operação dela com 91 caçambas ativas).

2. **B — Migração faseada com cobrança só na V2 LIVE** (opção B do [charter-1pager](../requisitos/OficinaAuto/demo-martinho-2026-05-13/charter-1pager.md)) ❌ — recusada implicitamente em 2026-05-20: Wagner falou de **add-on extra cobrado adicional**, não desconto temporal sobre base. Modelo B atrelaria pricing ao gating de feature LIVE, complicando faturamento.

3. **C — Pacote completo upfront com treinamento premium** (opção C do [charter-1pager](../requisitos/OficinaAuto/demo-martinho-2026-05-13/charter-1pager.md)) ❌ — não combina com decisão "ela continua no desktop, equipe acompanha aprendendo". Pacote upfront pressupõe cutover; aprendizado iterativo pede faseado.

4. **D — Faseada + pacote atual mantido + add-on apenas pra módulos NOVOS** ✅ **escolhida**. Combina:
   - Aprendizado iterativo (resposta P3 Wagner)
   - Sem risco financeiro Martinho — R$ [redacted Tier 0] atual mantém (resposta P4 Wagner; OficinaAuto + NFe/NFSe já estão inclusos nesse valor)
   - Revenue stream incremental via módulos NOVOS (não-pacote) — WhatsApp é o 1º candidato, futuros (PWA mecânico, IA diagnóstico, etc) seguem mesmo padrão
   - Coerente com [ADR 0143 §"Coexistência"](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) opt-in
   - Drift consciente de [ADR 0121 §P5](0121-oimpresso-modular-especializado-por-vertical.md) (que prevê add-on por vertical) — Martinho fica grandfathered porque o pacote dele já inclui OficinaAuto; clientes novos pós-2026-05-20 seguem P5 puro (núcleo + add-on por vertical separado).

## Critério de validação pós-aceitação

- [x] **Base atual Martinho R$ [redacted Tier 0]/mês confirmada** — mantém inalterada (cobre núcleo + OficinaAuto + NFe + NFSe pelo pacote grandfathered)
- [x] Wagner cravou pricing **WhatsApp add-on** 2026-05-20: **R$ [redacted Tier 0]/mês por instância (número WhatsApp Business)** · atendentes ilimitados · benchmark ZapBoss paridade
- [ ] Job mensal apuração instâncias WhatsApp implementado em [Modules/RecurringBilling](../requisitos/RecurringBilling/SPEC.md) (Job + Task + audit_log row por fatura)
- [ ] Fatura template discrimina linha "WhatsApp add-on (N instâncias × R$ [redacted Tier 0]) = R$ [redacted Tier 0]N,00" — UI Inertia + PDF
- [ ] `RUNBOOK-migracao-cliente-legacy.md` aprovado por Wagner e linkado neste ADR pós-criação
- [x] US-OFICINA-005 / US-OFICINA-006 / US-OFICINA-014 mergeadas em main (destravam operação faseada — cleanup tools + FSM wire-up + WhatsApp aprovação) — confirmado via diagnóstico session 2026-05-20
- [ ] Martinho aceitou beta 30d WhatsApp add-on explicitamente (WhatsApp confirmado registrado em CRM `contacts.notes` + `client_signal` row no MCP)
- [ ] 1 feature OficinaAuto operada por Martinho 7 dias consecutivos sem incidente sev1/sev2 (gate Fase 3)
- [ ] CHANGELOG OficinaAuto atualizado com timeline de ativação (Fase 0 done → Fase 1 ready → Fase 2 ativa quando contrato → Fase 3 ativa após 7d canary)
- [ ] [ROADMAP §Fase 3 "Pré-requisitos HARD GATING"](../requisitos/OficinaAuto/ROADMAP.md#fase-3--1º-piloto-pagante--bulk-migration--canary) marcado como satisfeito explicitamente
- [ ] `cycle-goals-track CYCLE-06 goal="1º cliente OficinaAuto pagando"` marcado como ✅ done

## Métricas trackadas (pós-accept)

Convergente com [ROADMAP §"Métricas convergentes"](../requisitos/OficinaAuto/ROADMAP.md#métricas-convergentes-m0-m12-pós-ativação):

| Métrica | Alvo M0 (Fase 3 ativa) | Alvo M6 (Fase 4 done) | Alvo M12 |
|---|---|---|---|
| ARR módulo OficinaAuto | R$ [redacted Tier 0]k/ano (1 piloto) | R$ [redacted Tier 0]k (3 pilotos) | R$ [redacted Tier 0]-72k (5-10 pilotos) |
| Churn módulo (Martinho) | 0% nos primeiros 90d | <5%/m | <8%/ano |
| Bugs sev1/sev2 reportados Martinho | < 1/mês | < 1/mês | < 1/trimestre |
| Tempo médio onboarding próximo cliente (Vargas/Extreme/...) | baseline | < 50% Martinho (usa RUNBOOK) | < 30% Martinho |
| NPS Martinho | n/a (beta) | ≥ 50 | ≥ 50 |
| Custo suporte vs receita módulo | n/a (beta) | ≤ 25% | ≤ 15% |

Tracking via `php artisan jana:health-check` (schedule daily 06:00 BRT em `app/Console/Kernel.php`) + brief diário ([ADR 0091](0091-daily-brief.md)).

## Refs

- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado (gating ADR mãe)
- [ADR 0137](0137-modules-oficinaauto-qualificada.md) — Modules/OficinaAuto qualificada (decisão anterior)
- [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline canônico LIVE prod
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio "Loop fechado por métrica" + Tier 0 multi-tenant)
- [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) — oimpresso modular especializado por vertical
- [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md) — estimates IA-pair fator 10x
- [ADR 0119](0119-paralelismo-sessoes-whats-active-tier-1.md) — Migration Factory rolling
- [ROADMAP OficinaAuto](../requisitos/OficinaAuto/ROADMAP.md) — Fases 0-5 + métricas M0-M12
- [Discovery Martinho 2026-05-13](../requisitos/OficinaAuto/demo-martinho-2026-05-13/discovery-martinho.md)
- [Charter 1pager Martinho](../requisitos/OficinaAuto/demo-martinho-2026-05-13/charter-1pager.md)
- [Perfil Martinho legacy](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- [Financeiro Martinho legacy](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md) — R$ [redacted Tier 0]M receita 12m · 76.7% inadimplência
- Cycle CYCLE-06 goal — tool MCP `cycles-active project=COPI`
- `RUNBOOK-migracao-cliente-legacy.md` — a criar em paralelo (link absoluto pós-criação: `memory/requisitos/OficinaAuto/RUNBOOK-migracao-cliente-legacy.md`)

---

**Próximo passo:** Wagner revisar este draft → após `accepted` renumerar `014X-oficinaauto-ativacao-piloto-martinho-faseada.md` + mover pra `memory/decisions/` + criar RUNBOOK paralelo + atualizar tabela [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) §"Módulos verticais — estado" pra refletir Martinho como piloto pagante.
