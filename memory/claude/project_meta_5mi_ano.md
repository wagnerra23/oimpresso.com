---
name: Meta oimpresso R$ 5mi/ano
description: Meta financeira oficial da oimpresso — R$ 5 milhões/ano de faturamento; prazo pendente
type: project
originSessionId: 3ea423cc-141d-477e-b072-2e0171a6fdd7
---
Meta oficial da oimpresso: **R$ 5 milhões/ano de faturamento** (~R$ 417k MRR se SaaS puro). Estabelecida por Wagner em 2026-04-24.

**Why:** Roadmap até aqui era centrado em produto (PontoWr2, stack React, etc.) sem âncora numérica de negócio. Wagner fixou a meta pra servir de filtro de priorização (features, módulos, alocação de tempo, expansão comercial).

**How to apply:**
- Qualquer proposta de feature/módulo novo: perguntar "como isso aproxima da meta?".
- Módulos já marcados pra descartar (AiAssistance) — manter descartados, não sugerir revival.
- Grow continua prioridade (consistente com filtro receita-first).
- Trilhas de execução vivem em `memory/11-metas-negocio.md` no branch `6.7-bootstrap` (doc vivo), formalizadas em ADR 0022.
- Estado atual: 7 clientes ativos dos 56 cadastrados, ROTA LIVRE concentra 99% — plano é reduzir essa concentração via 3 trilhas (ativar base ociosa, PontoWr2 como âncora de aquisição, upsell vertical).

**Pendências pro Wagner confirmar:**
- Prazo-alvo (12/24/36 meses?).
- "Financeira" = faturamento total do negócio (assumido) ou linha específica do produto?
- Faturamento atual não medido — dashboard interno de MRR ainda não existe.

Artefatos no cofre (branch `6.7-bootstrap`, ainda no worktree `lucid-dirac-00f1c6` aguardando PR):
- `memory/decisions/0022-meta-5mi-ano-financeira.md` — ADR formal
- `memory/11-metas-negocio.md` — plano vivo com cenários A/B/C/D e 3 trilhas (vira seed do módulo Copiloto)
- `memory/requisitos/Copiloto/` — módulo novo **Copiloto** que operacionaliza a meta (chat IA + metas + apuração + alertas)
