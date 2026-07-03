---
type: roadmap
horizon: multi-cenario (24/36/48 meses)
created_at: 2026-04-24
last_updated: 2026-04-24
goal: Aproximar de R$ [redacted Tier 0]mi/ano (ADR 0022 — meta oficial Wagner) via 4 módulos spec-ready
related:
  - memory/decisions/0022-meta-5mi-ano-financeira.md
  - memory/11-metas-negocio.md
  - auto-memória: project_meta_5mi_ano.md
---

# Roadmap Faturamento — caminhos para R$ [redacted Tier 0] milhões/ano

> Sequência das 4 SPECs promovidas em 2026-04-24 (`Financeiro`, `NfeBrasil`, `RecurringBilling`, `LaravelAI`) ordenada por **maior impacto em receita / menor risco / sequência de dependências**.
>
> **Meta oficial:** R$ [redacted Tier 0]/ano = R$ [redacted Tier 0]k/mês MRR (ADR 0022, 2026-04-24).
> **Prazo:** ⚠️ Wagner ainda não fixou (12/24/36/48 meses?). Roadmap mostra 3 cenários.

## Reconciliação com meta R$ [redacted Tier 0]mi/ano

| Cenário | Prazo | MRR M24 | MRR M36 | MRR M48 | Realismo (Wagner solo + IA) |
|---|---|---:|---:|---:|---|
| **A — Agressivo** | 24m | R$ [redacted Tier 0]k | — | — | ❌ Inviável sem time (precisa Cenário D do `11-metas-negocio.md`) |
| **B — Equilibrado** | 36m | R$ [redacted Tier 0]k | R$ [redacted Tier 0]k | R$ [redacted Tier 0]k | ⚠️ Possível com 1 contratação em M12 |
| **C — Sustentável** | 48m | R$ [redacted Tier 0]k | R$ [redacted Tier 0]k | R$ [redacted Tier 0]k | ✅ Wagner solo + IA (cenário do roadmap detalhado abaixo) |
| **C+ — Sustentável + Trilha 1** | 48m | R$ [redacted Tier 0]k | R$ [redacted Tier 0]k | R$ [redacted Tier 0]k | ✅ Wagner + ativação base ociosa (49 businesses dormentes) |

**Recomendação:** seguir **Cenário C+** — combina build sequence dos 4 módulos abaixo + Trilha 1 (ativar base ociosa) das 3 trilhas do `memory/11-metas-negocio.md`. Maior probabilidade de bater R$ [redacted Tier 0]mi sem queimar Wagner.

**Decisão pendente Wagner:** confirmar prazo (24/36/48). Sem isso, roadmap detalhado abaixo assume **48 meses** (Cenário C+).

## Tese central

oimpresso hoje cobra ~R$ [redacted Tier 0]/mês pelo POS UltimatePOS customizado. Tenant pequeno aceita. Tenant médio (PME 5-20 funcionários) **não paga R$ [redacted Tier 0] num POS isolado** — paga R$ [redacted Tier 0]-500/mês num ERP completo. Os 4 módulos transformam oimpresso de "POS" em "ERP brasileiro completo":

1. **Financeiro** desbloqueia a percepção de "não é só POS"
2. **NfeBrasil** desbloqueia compliance fiscal (sem isso, não vende em mercado regulado)
3. **RecurringBilling** abre nicho SaaS BR (mensalidades) com take rate
4. **LaravelAI** é multiplier — vende premium, não é foundation

## Premissas

- 1 dev sênior dedicado (Wagner) + IA (Claude Code)
- Validação progressiva com ROTA LIVRE (biz=4) como cliente piloto
- Entregar mensal, não em waterfall — cada módulo tem MVP em < 6 semanas
- Marketing/sales: começar com tenants ativos atuais (7), expandir conforme módulos prontos

## Build sequence (ondas mensais)

| Mês | Módulo | Onda | Entrega-chave | MRR adicional esperado |
|---|---|---|---|---|
| **M1** | Financeiro | Onda 1 (MVP) | Schema + CR auto venda due + lista | +R$ [redacted Tier 0] (preview) |
| **M2** | Financeiro | Onda 2 | CP + caixa projetado + juros | +R$ [redacted Tier 0] × 5 tenants = R$ [redacted Tier 0]/mês (Pro early-bird) |
| **M3** | NfeBrasil | Fase 1 | NFC-e MVP SP + cert A1 | +R$ [redacted Tier 0] × 3 tenants = R$ [redacted Tier 0]/mês |
| **M4** | NfeBrasil | Fase 2-3 | NF-e + cancelamento | tenants migram pra Pro: +R$ [redacted Tier 0] × 3 = R$ [redacted Tier 0]/mês delta |
| **M5** | Financeiro | Onda 3 | Boleto + PIX gateway | take rate começa: ~R$ [redacted Tier 0]/mês (early) |
| **M6** | NfeBrasil | Fase 4-5 | Contingência + motor tributário | NF-e enterprise: +R$ [redacted Tier 0] × 1 = R$ [redacted Tier 0]/mês |
| **M7** | RecurringBilling | Onda 1 | PaymentGateway + Asaas | (preparação; sem revenue ainda) |
| **M8** | RecurringBilling | Onda 2 | RB núcleo + ciclo de vida | early Pro: +R$ [redacted Tier 0] × 2 = R$ [redacted Tier 0]/mês |
| **M9** | NfeBrasil | Fase 6-7 | MDF-e + CT-e + SPED | Enterprise migra: +R$ [redacted Tier 0] delta × 1 |
| **M10** | RecurringBilling | Onda 3 | NFSe automática | Pro vira pagante feliz; renovação alta |
| **M11** | RecurringBilling | Onda 4 | Dunning email | recovery rate 30% = retention sobe |
| **M12** | RecurringBilling | Onda 5 | Pix Automático JRC 3 | diferencial competitivo: novos tenants entram |
| **M13** | LaravelAI | Onda 1-2 | KG + RAG POC | (preview; sem revenue) |
| **M14** | LaravelAI | Onda 3 | AgentService + chat | early add-on: +R$ [redacted Tier 0] × 5 tenants = R$ [redacted Tier 0]/mês |
| **M15** | RecurringBilling | Onda 6 | Boleto CNAB | tenants bancarizados: +R$ [redacted Tier 0] × 1 |
| **M16** | LaravelAI | Onda 4-5 | Visualização + chat contextual | adoção sobe: +R$ [redacted Tier 0] × 10 = R$ [redacted Tier 0]/mês |
| **M17** | RecurringBilling | Onda 7 | 2º adapter Iugu | (sem revenue direto) |
| **M18** | Financeiro | Onda 4 | Conciliação OFX + DRE | Enterprise: +R$ [redacted Tier 0] × 2 = R$ [redacted Tier 0]/mês |
| **M19** | LaravelAI | Onda 6 | Multi-modal + ML | Enterprise diferencial: +R$ [redacted Tier 0] × 3 = R$ [redacted Tier 0]/mês |
| **M20** | (consolidação) | Polish | Bugs + UX + onboarding | retenção fixa |
| **M21** | (consolidação) | Marketing | Documentação + landing pages | aquisição acelera |
| **M22** | LaravelAI | Onda 7 | Custom embeddings tenant | Enterprise premium: +R$ [redacted Tier 0] × 5 = R$ [redacted Tier 0]/mês |
| **M23** | (qualquer) | Otimização margem | Cache, infra, custos OpenAI | margem sobe 5% |
| **M24** | (consolidação) | Cleanup técnico | Cobertura testes 80%+ | confiança institucional |

## Projeção MRR — Cenário C+ (Wagner solo + IA + Trilha 1 ativa)

| Marco | Mês | MRR | Tenants | % da meta R$ [redacted Tier 0]mi/ano | Notas |
|---|---:|---:|---:|---:|---|
| Hoje | M0 | R$ [redacted Tier 0] | 7 | 0,1% | Atual |
| Financeiro Pro adoção | M2 | R$ [redacted Tier 0] | 12 | 0,3% | Foundation desbloqueia |
| NfeBrasil compliance | M4 | R$ [redacted Tier 0] | 18 | 0,5% | Compliance traz mercado regulado |
| **Trilha 1 ativada** | M6 | R$ [redacted Tier 0] | 25 | 1,9% | +10 dormentes reativados (R$ [redacted Tier 0]/mês cada) |
| RecurringBilling MVP | M8 | R$ [redacted Tier 0] | 35 | 3,4% | Take rate começa rodar |
| Stack completo | M12 | R$ [redacted Tier 0] | 60 | 6,7% | Pix Automático diferencial; PontoWr2 viraliza |
| LaravelAI add-on | M14 | R$ [redacted Tier 0] | 75 | 8,4% | Multiplier ativa |
| Maturação | M18 | R$ [redacted Tier 0] | 110 | 14% | Primeiros enterprise consolidam |
| Crescimento estabilizado | M24 | R$ [redacted Tier 0] | 180 | 24% | Plataforma madura, marketing acelera |
| **1ª contratação** | M30 | R$ [redacted Tier 0] | 280 | 43% | CS dedicada libera Wagner pra venda |
| Escala operacional | M36 | R$ [redacted Tier 0] | 380 | 67% | Cenário D (mix enterprise+médio+pequeno) materializa |
| Aproximação meta | M42 | R$ [redacted Tier 0] | 460 | 86% | Comercial de fato + parcerias contadores |
| **R$ [redacted Tier 0]mi/ano atingido** | M48 | R$ [redacted Tier 0] | 530 | 100% | Trilha 2 (PontoWr2 âncora) + Trilha 3 (upsell) maturam |

**Crescimento médio:** ~10% MoM nos primeiros 24 meses (base baixa); estabiliza em ~6% após M30.

**Premissas-chave Cenário C+:**
- Trilha 1 ativada em M6 (auditar 49 businesses, reativar 10-15 dormentes)
- Trilha 2 (PontoWr2 âncora) entrega 1 piloto real em M9, 20+ prospects M12
- Trilha 3 (upsell vertical) ativa em M3 (matriz clientes × módulos)
- Take rate (Financeiro 0,5% + RecurringBilling 0,8%) acumula linearmente com volume
- LaravelAI add-on 30-40% adoção em tenants Pro+ a partir de M14
- Mix Cenário D: ~50 enterprise (R$ [redacted Tier 0]k+) + ~150 médios (R$ [redacted Tier 0]k) + ~330 pequenos (R$ [redacted Tier 0]-400) em M48

## Premissas que sustentam a projeção

1. **Adoção interna primeiro:** ROTA LIVRE + tenants atuais (7) viram pilot → testimonials → marketing
2. **Marketing orgânico via LaravelAI:** demo vendendo "ERP que responde em PT" diferencia 100% do mercado
3. **Pricing dual** (subscription + take rate): paga infra contínua + escala com sucesso do tenant
4. **Lock-in via dados:** 6 meses de XML/contratos no oimpresso = baixíssima migração
5. **Compliance forçado** NfeBrasil: tenant não pode operar sem nota; preço alto justificável

## Risk register (ordenado por probabilidade × impacto)

| Risco | Prob | Impacto | Mitigação |
|---|---|---|---|
| **OpenAI fica caro/instável** | média | alto (LaravelAI) | Fallback Anthropic + sentence-transformers local; quota dura |
| **SEFAZ muda lei** (Reforma Tributária) | alta | médio | Schema flexível CBS/IBS já preparado (NfeBrasil ARQ-0004) |
| **Concorrente BR copia** (Tiny, Bling) | média | alto | LaravelAI difícil de copiar (KG+RAG sob domínio); diferenciação contínua |
| **Wagner fica doente** (1-pessoa risk) | baixa | catastrófico | Documentação obsessiva (este memory/) + IA pode continuar |
| **Cliente piloto cancela** (ROTA LIVRE) | baixa | médio | Diversificar com 5-10 tenants antes de M6 |
| **Compliance LGPD problema** | baixa | alto | PII masking em LaravelAI; cert A1 criptografado em NfeBrasil |
| **Take rate vira "imposto" perceptual** | média | médio | Comunicação clara + cap (R$ [redacted Tier 0]/19,90); plano sem take rate disponível |

## Métricas-chave (alarme se não bater)

- **MRR mês a mês** — deve crescer 8-15% nos primeiros 12 meses
- **Churn mensal** — deve ficar abaixo de 3% (PME BR é volátil)
- **CAC** — quanto custa adquirir tenant (orgânico = baixo)
- **LTV** — receita média × tempo de retenção (meta > 24 meses)
- **NPS** — pesquisa trimestral (meta > 50)
- **Margem bruta** — meta > 70% (custo OpenAI/Asaas/SEFAZ deduzido)
- **Adoção LaravelAI** — % tenants Pro+ que ativam add-on (meta 30-50%)

## Decisões pendentes do roadmap

- [ ] **Modo MoR vs gateway tenant**: definir em RecurringBilling antes de M8 (afeta NFSe + compliance BCB)
- [ ] **PSP Pix Automático**: Woovi vs Banco do Brasil direto — definir antes de M12
- [ ] **Marketing channels**: organic SEO, parceria contadores, outbound? Definir antes de M9
- [ ] **Pricing experimentation**: A/B test Free vs Starter (R$ [redacted Tier 0] vs R$ [redacted Tier 0]) em M6
- [ ] **Funding**: bootstrap até M24 ou levantar seed em M12 pra acelerar?
- [ ] **Time**: contratar 2º dev em M12 ou aguentar com 1 + IA?

## Princípios pra atravessar 24 meses

1. **Entregar mensal** — qualquer onda > 6 sem é vermelho
2. **MVP impressionante** — não polished, mas resolvendo problema real
3. **Cliente piloto vira testimonial** — pedir depoimento + screen recording
4. **Documentar tudo** — esse memory/ é o coração do sistema
5. **Cobrar logo** — early-bird R$ [redacted Tier 0] mês 1, sobe pra R$ [redacted Tier 0] mês 2 (cria urgência)
6. **Não aceitar tenant errado** — tenant que reclama de R$ [redacted Tier 0] não vira advogado; foco em PME que entende valor

## Conexões cross-módulo (não duplicar trabalho)

```
Financeiro ←──────────── NfeBrasil
    │                        │
    │ (DRE consome NF-e)     │ (cert compartilhado)
    │                        │
    ▼                        ▼
RecurringBilling ─── NFSe (sub-módulo)
    │
    │ (eventos InvoicePaid)
    │
    ▼
LaravelAI ─── consulta tudo via Knowledge Graph
              + extende MemCofre (ADR LaravelAI ARQ-0002)
```

Reuso obrigatório:
- **Cert A1 storage** (NfeBrasil ARQ-0003) usado por NFSe (RecurringBilling sub-módulo)
- **Idempotência pattern** (Financeiro TECH-0001) replicado em NfeBrasil + RecurringBilling
- **Strategy pattern boleto** (Financeiro ARQ-0003) compartilhado com RecurringBilling/Boleto
- **Webhook idempotency** (Financeiro + RecurringBilling) compartilha tabela `pg_webhook_events`
- **Audit log Spatie** consumido por LaravelAI

## Onda zero (antes de M1) — 1 semana

Antes de começar Financeiro, fazer:
- [ ] Validar contas bancárias do oimpresso (precisamos receber take rate antes de M5)
- [ ] Configurar Asaas marketplace mode (parceria com PSP licenciado)
- [ ] Definir pricing definitivo (revisar SPECs antes de cobrar)
- [ ] Landing pages dos 4 módulos (estágio "early access")
- [ ] Email blast pros 7 tenants atuais ("estamos crescendo, novos módulos chegando")

---

## Camada de correção — régua por tela (Financeiro) · [ADR 0320]

> **Encaixe T6 (não paralelo):** o "programa de ondas · régua de correção por módulo"
> ([ADR 0320](../decisions/proposals/0320-programa-ondas-regua-correcao.md)) manda que as ondas
> de **Financeiro / NfeBrasil / RecurringBilling** encaixem **aqui** no roadmap macro de
> Faturamento — nunca em doc paralelo. Esta seção é a **casa** dessa camada pro Financeiro.
> O adversário/gaps do módulo **já existe** e não se refaz: [CAPTERRA-INVENTARIO.md](Financeiro/CAPTERRA-INVENTARIO.md)
> (nota 74). Esta camada é o **Passo 3** do ciclo-padrão (régua estendida 0b: `screen-grade` UX
> **+** `casos_coverage` comportamento **+** dente D1 cálculo), aplicado por exposição Tier-0.
> Execução via tasks MCP `parent_plan=programa-ondas` — status vivo lá, não neste markdown.

**Débito descoberto (2026-07-03):** 7 telas de dinheiro do Financeiro estavam **sem charter**
(contrato). A régua por tela expõe a contradição que o programa existe pra tornar visível —
**UX alta escondendo comportamento indefeso**:

| Tela | UX (screen-grade) | Cobertura comportamento (casos_coverage) | D1 cálculo | Charter |
|---|---|---|---|---|
| `ContasReceber/Index` | **70 · Advanced** | **0 UC** defendido (5 no backlog s/ id) | n/a (lista + boleto) | ✅ criado |
| `ContasPagar/Index` | **70 · Advanced** | **0 UC** defendido (6 no backlog s/ id) | **🔴 indefeso** (baixa parcial) | ✅ criado |
| `Unificado/Novo` | — | — | 🔴 (insert de título) | ⬜ pendente |
| `Dashboard/Index` | — | — | n/a (leitura) | ⬜ pendente |

> A leitura da foto: `ContasPagar` é **"Advanced" no visual e ao mesmo tempo recalcula
> `valor_aberto` na baixa sem uma única prova de cálculo** (classe do incidente `num_uf`, valor
> inflado ~×100k — [proibicoes §CÁLCULO DE VALOR](../proibicoes.md)). O **dente D1** (property +
> golden + cross-check) é onda de **cálculo** separada — não se mistura com este PR de contrato
> (a régua **PLUGA, não funde**: bonita ≠ testada).

**Sequência da camada (por exposição × débito, cada onda exige OK [W] antes de abrir — [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):**
1. ✅ **Lote 1 (este PR):** charter + casos + régua estendida de `ContasReceber/Index` e
   `ContasPagar/Index` (o par A receber / A pagar — maior exposição). Débito trio −4 (2 telas ×
   charter+casos); `casos-gate`/shrink verdes.
2. ⬜ **Lote 2:** `Unificado/Novo` (insert de título — toca valor) + `Dashboard/Index` (leitura).
3. ⬜ **Dente D1 (onda de cálculo, outro chip):** property `num_uf` + golden da baixa parcial +
   cross-check 2 caminhos → sobe `ContasPagar` de D1 🔴 → 🟢. É o que fecha o piso Tier-0
   ([ADR 0320] §4: cálculo + caso + paridade).

**Armadilha registrada (casos-gate):** UC declarado só ganha id no **mesmo PR** que traz o
teste (G-2 · [ADR 0264](../decisions/0264-governanca-executavel-trio-dominio-e2e.md)). Por isso os casos das telas acima nascem **no backlog sem id** —
declarar `UC-*` sem teste quebraria o `casos-gate` (required).

---

**Status:** `roadmap (planejamento)` — não é compromisso vinculante; revisão trimestral.
**Próxima revisão:** 2026-07-24 (M3) — ajustar projeções vs realidade.
