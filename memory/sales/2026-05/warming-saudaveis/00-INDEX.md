---
tipo: index-warming
universo: 5 saudáveis OfficeImpresso → Modules/ComunicacaoVisual
data: 2026-05-10
autor: Claude (copywriter sub-agent) sob direção Wagner [W]
status: draft — Wagner revisa antes de mandar
relacionado:
  - memory/requisitos/ComunicacaoVisual/PLANO-MIGRACAO-6-SAUDAVEIS.md
  - memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md
  - memory/decisions/0119-migration-factory-capacidade-institucional.md
---

# Warming saudáveis OfficeImpresso → ComunicacaoVisual — INDEX

> **Princípio operacional ([ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):** este NÃO é cold outreach. Wagner conhece todos esses clientes há 10-26 anos. Tom = conversa entre conhecidos. Sem CTA agressivo. Sem pressão. **Convidar a "ver de perto", não vender.**
>
> **Garantia explícita em toda carta:** continuação Delphi WR Sistemas NÃO está em risco. Releases manutenção legacy seguem normais. Migração é opção, não imposição.

---

## Priority order (do PLANO)

| # | Cliente | GMV/ano (Insights) | Versão Delphi | Priority | Canal sugerido | Timing sugerido | Risco principal |
|---|---------|---:|---:|:---:|:---:|:---:|---|
| 1 | **Extreme** ("EXTREMA LED") | R$ [redacted Tier 0]M | 1472 (novo) | **P0** | WhatsApp → call Zoom 45min | **Q3/26 set** | concorrência Mubisys pode contatar antes |
| 2 | **Zoom** | R$ [redacted Tier 0]M | 1474 (mais novo do parque) | P1 | WhatsApp → call Zoom 60min | Q4/26 out | comitê interno (não dono-único); satisfeito demais |
| 3 | **Fixar** | R$ [redacted Tier 0]M | 1421 (meio parque) | P2 | email + WhatsApp follow-up | Q1/27 fev | versão sem update = churn latente; margem aperta R$ [redacted Tier 0] |
| 4 | **Mhundo** | R$ [redacted Tier 0]k | 1429 | P3 | email + WhatsApp | Q1/27 mar | GMV pequeno; vertical exato a confirmar |
| 5 | **Produart** | R$ [redacted Tier 0]k | 1472 ("banco antigo") | P3 | email direto | Q2/27 abr | "banco antigo" = candidato a churn natural |

> **Nota GMV:** os valores acima vêm do snapshot oimpresso Insights (canon do PLANO). User briefing mencionou aproximações diferentes (Fixar ~R$ [redacted Tier 0]M / Mhundo ~R$ [redacted Tier 0]M) — mantive PLANO como fonte. Wagner valida ou ajusta no review.

---

## Tom canônico (para todas as 5 cartas)

**Fazer:**
- ✅ Abertura com nome do dono se Wagner souber (placeholder `<NOME_DONO>` se incerto)
- ✅ Referência específica à relação histórica (versão Delphi atual, último contato, anos de relação)
- ✅ Mencionar que Wagner está **construindo** algo novo (não "lançou", não "está vendendo")
- ✅ Frase explícita: *"Continuação Delphi não muda — releases seguem normais"*
- ✅ Convite low-stakes: "se quiser dar uma olhada, sem compromisso"
- ✅ Wagner direto no fechamento + número WhatsApp `<WAGNER_TEL>`

**Não fazer:**
- ❌ "Promoção", "limitado", "vagas", "últimos pioneiros" (hype/scarcity)
- ❌ Mencionar concorrentes (Mubisys/Zênite) — só plantam dúvida
- ❌ Falar preço no primeiro contato — pricing vem na call
- ❌ "Migração obrigatória", "fim do suporte Delphi" — viola ADR 0105
- ❌ Texto > 200 palavras — encrenca pra ler em WhatsApp

---

## Sequência operacional sugerida

```
Q3/26 set → Extreme (P0)         ← prioritário, vertical perfeito
Q4/26 out → Zoom (P1)            ← após Extreme assinado/declinado
Q1/27 fev → Fixar (P2)
Q1/27 mar → Mhundo (P3)
Q2/27 abr → Produart (P3)        ← último, oferta agressiva
```

**Gate-check antes de QUALQUER outreach:** Modules/ComunicacaoVisual Sprint 1 entregue (cálculo m² + spool plotter funcional em demo ROTA LIVRE-style). Sem demo, adiar outreach — não dá pra convidar a "ver de perto" o que ainda não existe.

---

## Cartas

1. [01-extreme.md](01-extreme.md) — P0 — "EXTREMA LED" R$ [redacted Tier 0]M GMV
2. [02-zoom.md](02-zoom.md) — P1 — Zoom R$ [redacted Tier 0]M GMV
3. [03-fixar.md](03-fixar.md) — P2 — Fixar R$ [redacted Tier 0]M GMV
4. [04-mhundo.md](04-mhundo.md) — P3 — Mhundo R$ [redacted Tier 0]k GMV
5. [05-produart.md](05-produart.md) — P3 — Produart R$ [redacted Tier 0]k GMV

---

## Notas Wagner pra revisão

- **Placeholders a preencher manualmente antes de mandar:**
  - `<WAGNER_TEL>` em todas as 5 cartas — número WhatsApp pessoal
  - `<NOME_DONO>` quando Wagner souber o primeiro nome do dono atual (consultar registry/banco se houver)
  - `<CIDADE_UF>` quando Wagner confirmar localização (parte da decisão visita presencial vs Zoom)
- **Antes de mandar:** rodar skill `officeimpresso-financial-snapshot` em cada banco Firebird pra confirmar receita pago + recência último update. Sem snapshot = chute (ver PLANO §"Premissa-chave")
- **Validar identidade Gold** — não está nesta lista (já churned pra Mubisys conforme post-mortem 2026-05-09); confirmar antes de qualquer outreach acidental
- **Ordem de mandar:** começar P0 (Extreme) — se feedback for positivo, ajustar tom das outras 4 baseado na conversa real. Se negativo (preço, timing, "tô bem com Delphi"), revisar copy antes de mandar P1
- **Não mandar todas no mesmo dia** — Wagner é um humano, calls de 45-60min cada não cabem na agenda. 1 cliente/semana é sustentável

---

**Última atualização:** 2026-05-10
