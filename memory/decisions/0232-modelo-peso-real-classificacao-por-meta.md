---
slug: 0232-modelo-peso-real-classificacao-por-meta
number: 232
title: "Modelo de Peso Real — classificar memórias, decisões e iniciativas por contribuição à meta (R$ [redacted Tier 0]M/ano)"
type: adr
status: deprecated
authority: canonical
lifecycle: arquivado
decided_by: [W]
decided_at: "2026-05-28"
proposed_at: "2026-05-28"
module: governance
quarter: 2026-Q2
tags: [governance, peso-real, roi, priorizacao, rice, wsjf, decay, relevancia-meta, classificacao]
supersedes: []
related:
  - 0230-metodo-governance-scorecard
  - 0231-processo-trabalho-canonico-especialista-por-area
  - 0195-feedback-relevance-scoring-decay-adaptativo
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0022-meta-5mi-ano-financeira
authors:
  - W
  - C
---

# 0232 — Modelo de Peso Real

## Contexto

Wagner (2026-05-28): *"classifique minhas memórias e decisão com peso reais para ajudar nas minhas metas — o mais importante não é fazer e sim pensar e resolver o problema."* O problema: parar de pontuar por "maturidade abstrata" e classificar **por contribuição à meta R$ [redacted Tier 0]M/ano** ([ADR 0022](0022-meta-5mi-ano-financeira.md)).

Achado (especialista, estado-da-arte 2026): o oimpresso **já tem 70% do modelo** — `NORTE-ROI` é WSJF/value-effort; [ADR 0195](0195-feedback-relevance-scoring-decay-adaptativo.md) é o scoring recency×importance×relevance dos Generative Agents; `_INDEX-LIFECYCLE` trata decisão como evergreen. **Falta a fórmula única que liga os 3 tipos à mesma meta.**

## Decisão

Adotar o **Peso Real** — uma fórmula-mãe, três sabores (cada tipo tem natureza temporal diferente, mas tudo aponta pra R$ [redacted Tier 0]M):

```
PESO_REAL = relevancia_meta(0-100) × modulador_do_tipo
relevancia_meta = quanto o item move/protege R$ [redacted Tier 0]M/ano
  (0-25 indireto/abstrato · 26-50 habilitador · 51-75 alavanca · 76-100 receita direta)
```

### (a) DECISÕES / ADRs — não decaem por tempo
```
PESO_ADR = relevancia_meta × lifecycle_mult
lifecycle_mult: accepted=1.0 · accepted-historical=0.8 · sunsetting=0.4 · superseded/deprecated=0.1
```
Decisão é evergreen: perde peso por **supersede**, nunca por idade. Bônus se tem teste anti-regressão (Invariante A da [0230](0230-metodo-governance-scorecard.md)) — "quebra quando diverge".

### (b) MEMÓRIAS / lições / fatos / sessions — decaem por tempo
```
PESO_MEM = max(piso_critico, relevancia_meta × exp(-dias/half_life) × (1 + log10(recorrencia+1)/log10(6)))
half_life = 60d (default ADR 0195)
```
Generaliza o 0195 (hoje só `clients_feedbacks`) pra **qualquer** memória. **Floor crítico:** lição que evita erro que custa cliente **não decai abaixo do piso HOT**, mesmo velha.

### (c) INICIATIVAS / módulos — ROI
```
PESO_INI = (receita_anual × sinal_cliente × time_criticality) ÷ esforço
sinal_cliente: paga+reporta=1.0 · qualificado=0.5 · hipótese=0.2  (ADR 0105)
time_criticality: 1.0 normal · 1.5 prazo legal/compliance  ← novo (Cost of Delay, WSJF)
```
É o `NORTE-ROI`, faltando só `time_criticality` (pra NFe/compliance com prazo SEFAZ pontuar acima de igual-valor sem urgência).

**Linguagem comum:** compara-se **dentro do tipo**; `relevancia_meta` (0-100) é a régua cross-tipo ("este ADR e este módulo ambos têm relevância-meta 90").

## Exemplos (classificação real)

| Item | Tipo | relevancia_meta | Peso |
|---|---|---|---|
| ADR 0022 (meta R$ [redacted Tier 0]M) | decisão | 100 | **100** |
| ADR 0093 (multi-tenant Tier 0) | decisão | 95 | **95** (vazar = perder todo cliente) |
| ADR 0230 (governance scorecard) | decisão | 45 | **45** (governança = meio, não fim) |
| Lição format_date +3h ROTA LIVRE (0066) | memória | 85 | **~85** (floor: protege cliente pagante) |
| Vestuário/ROTA LIVRE | iniciativa | 90 | **alto** (sinal 1.0, validado) |
| NfeBrasil | iniciativa | 80 | **muito alto** (time_criticality 1.5) |
| OficinaAuto | iniciativa | 30 | **baixo** (sinal 0.2, sem cliente) |

Governança abstrata e módulo-sem-sinal caem no fundo; meta e isolamento-tenant no topo; lição-que-protege-cliente sobe via floor mesmo antiga.

## Estado da arte (fontes)
- **Iniciativas:** RICE · **WSJF/Cost of Delay** (SAFe/Reinertsen) · ICE · OKR-multiplier (Atlassian).
- **Memória:** Generative Agents (recency×importance×relevance) · Letta paging · Zep bi-temporal.
- **Decisão evergreen:** fitness-function/expiry-test (não decay temporal).
- **Interno:** NORTE-ROI · 0195 · 0105 · _INDEX-LIFECYCLE · 0230/0231.

## Consequências
- **Positiva:** ninguém no mercado distingue os 3 tipos pela natureza temporal — este modelo é um diferencial. Mata o "peso por maturidade abstrata".
- **Gaps (CONSOLIDAR, ~5 dev-days):** P0 campo `relevancia_meta` cross-tipo + generalizar 0195 pra lições/sessions; P1 `time_criticality` no NORTE-ROI, `meta_contribution` no frontmatter de ADR, floor anti-decay. Saturação ~88 (bi-temporal Zep = P3 baixo ROI).
- Status `proposto` — Wagner aprova; implementação por onda (ADR 0231: especialista por área).
