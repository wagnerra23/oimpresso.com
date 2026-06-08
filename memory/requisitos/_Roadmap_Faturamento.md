---
type: roadmap
horizon: multi-cenario (24/36/48 meses)
created_at: 2026-04-24
last_updated: 2026-04-24
goal: Aproximar de R$ 5mi/ano (ADR 0022 — meta oficial Wagner) via 4 módulos spec-ready
related:
  - memory/decisions/0022-meta-5mi-ano-financeira.md
  - memory/11-metas-negocio.md
  - auto-memória: project_meta_5mi_ano.md
---

# Roadmap Faturamento — caminhos para R$ 5 milhões/ano

> Sequência das 4 SPECs promovidas em 2026-04-24 (`Financeiro`, `NfeBrasil`, `RecurringBilling`, `LaravelAI`) ordenada por **maior impacto em receita / menor risco / sequência de dependências**.
>
> **Meta oficial:** R$ 5.000.000/ano = R$ 417k/mês MRR (ADR 0022, 2026-04-24).
> **Prazo:** ⚠️ Wagner ainda não fixou (12/24/36/48 meses?). Roadmap mostra 3 cenários.

## Reconciliação com meta R$ 5mi/ano

| Cenário | Prazo | MRR M24 | MRR M36 | MRR M48 | Realismo (Wagner solo + IA) |
|---|---|---:|---:|---:|---|
| **A — Agressivo** | 24m | R$ 417k | — | — | ❌ Inviável sem time (precisa Cenário D do `11-metas-negocio.md`) |
| **B — Equilibrado** | 36m | R$ 22k | R$ 100k | R$ 280k | ⚠️ Possível com 1 contratação em M12 |
| **C — Sustentável** | 48m | R$ 22k | R$ 80k | R$ 250k | ✅ Wagner solo + IA (cenário do roadmap detalhado abaixo) |
| **C+ — Sustentável + Trilha 1** | 48m | R$ 60k | R$ 180k | R$ 417k | ✅ Wagner + ativação base ociosa (49 businesses dormentes) |

**Recomendação:** seguir **Cenário C+** — combina build sequence dos 4 módulos abaixo + Trilha 1 (ativar base ociosa) das 3 trilhas do `memory/11-metas-negocio.md`. Maior probabilidade de bater R$ 5mi sem queimar Wagner.

**Decisão pendente Wagner:** confirmar prazo (24/36/48). Sem isso, roadmap detalhado abaixo assume **48 meses** (Cenário C+).

## Tese central

oimpresso hoje cobra ~R$ 49/mês pelo POS UltimatePOS customizado. Tenant pequeno aceita. Tenant médio (PME 5-20 funcionários) **não paga R$ 49 num POS isolado** — paga R$ 200-500/mês num ERP completo. Os 4 módulos transformam oimpresso de "POS" em "ERP brasileiro completo":

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
| **M1** | Financeiro | Onda 1 (MVP) | Schema + CR auto venda due + lista | +R$ 0 (preview) |
| **M2** | Financeiro | Onda 2 | CP + caixa projetado + juros | +R$ 199 × 5 tenants = R$ 995/mês (Pro early-bird) |
| **M3** | NfeBrasil | Fase 1 | NFC-e MVP SP + cert A1 | +R$ 99 × 3 tenants = R$ 297/mês |
| **M4** | NfeBrasil | Fase 2-3 | NF-e + cancelamento | tenants migram pra Pro: +R$ 200 × 3 = R$ 600/mês delta |
| **M5** | Financeiro | Onda 3 | Boleto + PIX gateway | take rate começa: ~R$ 50/mês (early) |
| **M6** | NfeBrasil | Fase 4-5 | Contingência + motor tributário | NF-e enterprise: +R$ 599 × 1 = R$ 599/mês |
| **M7** | RecurringBilling | Onda 1 | PaymentGateway + Asaas | (preparação; sem revenue ainda) |
| **M8** | RecurringBilling | Onda 2 | RB núcleo + ciclo de vida | early Pro: +R$ 449 × 2 = R$ 898/mês |
| **M9** | NfeBrasil | Fase 6-7 | MDF-e + CT-e + SPED | Enterprise migra: +R$ 400 delta × 1 |
| **M10** | RecurringBilling | Onda 3 | NFSe automática | Pro vira pagante feliz; renovação alta |
| **M11** | RecurringBilling | Onda 4 | Dunning email | recovery rate 30% = retention sobe |
| **M12** | RecurringBilling | Onda 5 | Pix Automático JRC 3 | diferencial competitivo: novos tenants entram |
| **M13** | LaravelAI | Onda 1-2 | KG + RAG POC | (preview; sem revenue) |
| **M14** | LaravelAI | Onda 3 | AgentService + chat | early add-on: +R$ 199 × 5 tenants = R$ 995/mês |
| **M15** | RecurringBilling | Onda 6 | Boleto CNAB | tenants bancarizados: +R$ 599 × 1 |
| **M16** | LaravelAI | Onda 4-5 | Visualização + chat contextual | adoção sobe: +R$ 199 × 10 = R$ 1.990/mês |
| **M17** | RecurringBilling | Onda 7 | 2º adapter Iugu | (sem revenue direto) |
| **M18** | Financeiro | Onda 4 | Conciliação OFX + DRE | Enterprise: +R$ 400 × 2 = R$ 800/mês |
| **M19** | LaravelAI | Onda 6 | Multi-modal + ML | Enterprise diferencial: +R$ 599 × 3 = R$ 1.797/mês |
| **M20** | (consolidação) | Polish | Bugs + UX + onboarding | retenção fixa |
| **M21** | (consolidação) | Marketing | Documentação + landing pages | aquisição acelera |
| **M22** | LaravelAI | Onda 7 | Custom embeddings tenant | Enterprise premium: +R$ 200 × 5 = R$ 1.000/mês |
| **M23** | (qualquer) | Otimização margem | Cache, infra, custos OpenAI | margem sobe 5% |
| **M24** | (consolidação) | Cleanup técnico | Cobertura testes 80%+ | confiança institucional |

## Projeção MRR — Cenário C+ (Wagner solo + IA + Trilha 1 ativa)

| Marco | Mês | MRR | Tenants | % da meta R$ 5mi/ano | Notas |
|---|---:|---:|---:|---:|---|
| Hoje | M0 | R$ 343 | 7 | 0,1% | Atual |
| Financeiro Pro adoção | M2 | R$ 1.338 | 12 | 0,3% | Foundation desbloqueia |
| NfeBrasil compliance | M4 | R$ 2.235 | 18 | 0,5% | Compliance traz mercado regulado |
| **Trilha 1 ativada** | M6 | R$ 8.000 | 25 | 1,9% | +10 dormentes reativados (R$ 500/mês cada) |
| RecurringBilling MVP | M8 | R$ 14.000 | 35 | 3,4% | Take rate começa rodar |
| Stack completo | M12 | R$ 28.000 | 60 | 6,7% | Pix Automático diferencial; PontoWr2 viraliza |
| LaravelAI add-on | M14 | R$ 35.000 | 75 | 8,4% | Multiplier ativa |
| Maturação | M18 | R$ 60.000 | 110 | 14% | Primeiros enterprise consolidam |
| Crescimento estabilizado | M24 | R$ 100.000 | 180 | 24% | Plataforma madura, marketing acelera |
| **1ª contratação** | M30 | R$ 180.000 | 280 | 43% | CS dedicada libera Wagner pra venda |
| Escala operacional | M36 | R$ 280.000 | 380 | 67% | Cenário D (mix enterprise+médio+pequeno) materializa |
| Aproximação meta | M42 | R$ 360.000 | 460 | 86% | Comercial de fato + parcerias contadores |
| **R$ 5mi/ano atingido** | M48 | R$ 417.000 | 530 | 100% | Trilha 2 (PontoWr2 âncora) + Trilha 3 (upsell) maturam |

**Crescimento médio:** ~10% MoM nos primeiros 24 meses (base baixa); estabiliza em ~6% após M30.

**Premissas-chave Cenário C+:**
- Trilha 1 ativada em M6 (auditar 49 businesses, reativar 10-15 dormentes)
- Trilha 2 (PontoWr2 âncora) entrega 1 piloto real em M9, 20+ prospects M12
- Trilha 3 (upsell vertical) ativa em M3 (matriz clientes × módulos)
- Take rate (Financeiro 0,5% + RecurringBilling 0,8%) acumula linearmente com volume
- LaravelAI add-on 30-40% adoção em tenants Pro+ a partir de M14
- Mix Cenário D: ~50 enterprise (R$ 5k+) + ~150 médios (R$ 1,5k) + ~330 pequenos (R$ 200-400) em M48

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
| **Take rate vira "imposto" perceptual** | média | médio | Comunicação clara + cap (R$ 9,90/19,90); plano sem take rate disponível |

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
- [ ] **Pricing experimentation**: A/B test Free vs Starter (R$ 99 vs R$ 0) em M6
- [ ] **Funding**: bootstrap até M24 ou levantar seed em M12 pra acelerar?
- [ ] **Time**: contratar 2º dev em M12 ou aguentar com 1 + IA?

## Princípios pra atravessar 24 meses

1. **Entregar mensal** — qualquer onda > 6 sem é vermelho
2. **MVP impressionante** — não polished, mas resolvendo problema real
3. **Cliente piloto vira testimonial** — pedir depoimento + screen recording
4. **Documentar tudo** — esse memory/ é o coração do sistema
5. **Cobrar logo** — early-bird R$ 99 mês 1, sobe pra R$ 199 mês 2 (cria urgência)
6. **Não aceitar tenant errado** — tenant que reclama de R$ 199 não vira advogado; foco em PME que entende valor

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

**Status:** `roadmap (planejamento)` — não é compromisso vinculante; revisão trimestral.
**Próxima revisão:** 2026-07-24 (M3) — ajustar projeções vs realidade.
