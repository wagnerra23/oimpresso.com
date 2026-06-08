---
title: oimpresso Insights — resumo executivo 1-page (v2)
date: 2026-05-09
revised: 2026-05-10 (Pilar 5 DaaS externo descartado, DPO Eliana adiado)
read_time: 3min
---

# 🎯 oimpresso Insights — resumo de 3 minutos (v2)

> **Decisões Wagner 2026-05-10 (FINAL):**
> - ✅ Produto ACEITO formal · ✅ Eliana cuida jurídico · ✅ Grandfather 12m · ✅ Caso ROTA LIVRE anonimizado OK
> - ❌ **Pilar 5 (DaaS externo) DESCARTADO** — *"não vou vender dados"*
> - ⏸️ **DPO Eliana ADIADO** — estuda LGPD primeiro
> - 🎯 **Hipótese D ESCOLHIDA — modular especializado por vertical** ([ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md))
> - **ROTA LIVRE = piloto Modules/Vestuario** (loja de roupa Gravatal/SC) — não gráfica nem SP

## O produto em 1 frase
> **"O ERP+IA brasileiro que sabe seu negócio, lembra suas metas e te alerta antes do problema — porque aprendeu com 50+ empresas como a sua."**

## Por que faz sentido AGORA (não depois)
- Você tem **acesso histórico a 41 bancos OfficeImpresso** = data asset único no setor (R$ 45M GMV agregado, 132k clientes finais BR, 27 UFs)
- Schema oimpresso novo cobre **70%** do que precisa — falta **18h IA-pair pra fechar** (não refactor)
- Concorrente leva **24-36 meses** pra acumular dado equivalente — moat real
- **Jana já existe** com memória persistente — só precisa virar habit-forming

## 4 pilares (v2 — sem Pilar 5)

1. **Schema multi-vertical** (gráfica/oficina/farmácia/etc) com classificação CNAE — 18h dev
2. **Jana habit-forming** — cliente abre todo dia (Boa Sexta WhatsApp + metas + insights drops) — 8 sem dev
3. **Pricing usage-based** — cliente paga proporcional ao valor extraído (R$ 99 → R$ 2.999 + GMV opt-in) — sweet spot R$ 299-599
4. **Network effect benchmark** — cada cliente novo melhora insights pra todos (k-anon ≥5) — Q1 launch
5. ~~**DaaS externo** — venda dado pra fintech/seguradora/fornecedor~~ ❌ **DESCARTADO**

## Tiers comerciais (clientes próprios — sem mudança)

| Tier | R$/m | Quem |
|------|----:|------|
| Free | 0 | trial 90d (acquisition) |
| Starter | 99 | gráfica solo |
| **Pro** | **299** | **sweet spot ROTA LIVRE-tipo** |
| Premium | 599 | gráfica grande + voice |
| Enterprise | 1.499 | rede com.visual + SLA |
| Power | 2.999 | parceiro/agência/white-label |

## ARR projetado realista REVISADO (sem Pilar 5)

| Período | ARR v1 (com DaaS) | ARR v2 (sem DaaS) | % meta R$ 5M |
|---------|------------------:|-------------------:|-------------:|
| Hoje | R$ 487k | R$ 487k | 10% |
| 12m | R$ 750k | R$ 700k | 14% |
| 24m | R$ 1.5-2.5M | **R$ 1-1.6M** | 20-32% |
| 36m | R$ 3-5M | R$ 2-3M | 40-60% |
| 48m | — | **R$ 4.5-5M** | 90-100% |

**Tradeoff aceitado**: 1 ano a mais pra atingir meta, mas alinhado com valor "não vou vender dados".

## 3 ações imediatas pra DESTRAVAR

1. **🟡 Counsel LGPD externo** (R$ 5-15k one-time, escopo MENOR sem Pilar 5) — Eliana faz first-pass nos 3 docs draftados; counsel só second opinion
2. **🟢 Schema multi-vertical** (Felipe+Wagner pareados, 18h IA-pair = 2-3 dias) — desbloqueia tudo
3. **🟢 Opt-in granular dos 41 atuais** (cláusula simplificada — só 2 níveis em vez de 3) — Eliana revisa contratos

## 3 riscos que matam o produto se ignorados

1. **Cliente sentir invadido** ("dado meu alimenta concorrente?") — mitigação: opt-in granular + transparência radical. **Risco MENOR sem Pilar 5** porque dado nunca sai pra terceiros.
2. **LGPD ANPD** (multa até 2% faturamento) — mitigação: counsel externo + DPIA. Sem DPO formal por enquanto = Eliana pode ser DPO operacional informal.
3. **Notification fatigue** (Jana spam queima trust) — limite duro 1 push/dia + opt-in granular.

## Decisões pendentes (você valida — atualizado 2026-05-10)

- [x] **Pilar 5 DaaS externo: NÃO** ✅
- [x] **DPO Eliana: ADIADO** (estuda primeiro) ✅
- [ ] Aceitar produto v2 como linha de negócio formal (ADR canon)?
- [ ] Investir R$ 30-60k counsel LGPD externo Q3/26 (escopo menor)?
- [ ] Grandfather 12m dos 41 atuais (sem upgrade forçado)?
- [ ] ROTA LIVRE caso público anonimizado nas comunicações?

## Budget LGPD ano 1 revisado

| Item | Valor |
|------|------:|
| Counsel one-time (validar 3 docs sem cláusula tier 3) | R$ 5-10k |
| DPIA documentada simplificada (sem DaaS externo) | R$ 3-5k |
| ~~DPO retainer mensal~~ | ~~R$ 0~~ (Eliana operacional informal) |
| Audit anual externa (se quiser, opcional sem DaaS) | R$ 0-25k |
| **Total ano 1** | **R$ 8-40k** ⭐ vs R$ 70-110k v1 |

**Economia: R$ 60-70k/ano vs plano original.**

## Onde está tudo

- 📋 **Master spec v2**: [PRODUTO-OIMPRESSO-INSIGHTS-MASTER-SPEC.md](PRODUTO-OIMPRESSO-INSIGHTS-MASTER-SPEC.md)
- 🎯 13 specs detalhados (F9-F21) — Pilar 5 (F10 DaaS, F12 API Score, F14 Performance) ficam em standby pra futuro se cliente pedir
- 💰 Pricing usage-based: `pricing-jana-usage-based.md`
- 🔄 Network effect: `network-effect-engine-oimpresso.md`
- 🛠️ Roadmap Jana 12m: `roadmap-jana-12m-habit-multi-vertical.md`
- 📊 Schema multi-vertical: `schema-multi-vertical-cnae-taxonomia.md`

---

**Próximo passo**: Eliana lê os 3 docs jurídicos draftados → counsel externo (boutique R$ 5-10k) faz second opinion rápida → começar Sprint 1 schema multi-vertical.
