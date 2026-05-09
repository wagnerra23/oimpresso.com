---
module: Copiloto
alias: copiloto
status: spec-ready
migration_target: react
migration_priority: alta
risk: medio
tenancy: hibrida (business_id nullable — null = Copiloto da plataforma/oimpresso)
ai_dependency: soft (adapter usa LaravelAI quando existir, fallback openai-php direto)
areas: [Chat, Metas, Períodos, Apuração, Fontes, Dashboard, Alertas]
marca_comercial: "Copiloto"
pitch: "O Copiloto de IA do seu negócio — ele olha seus números, sugere metas e te avisa quando algo desvia."
last_generated: 2026-04-24 (módulo novo, escrito a mão)
revenue_pricing:
  tier: "3 (multiplier add-on)"
  starter: "R$ 99/mês"
  pro: "R$ 299/mês"
  enterprise: "R$ 799/mês"
  take_rate: "n/a (subscription puro)"
  posicionamento_vs_laravelai: "Copiloto = front de decisão (chat + metas + alertas); LaravelAI = engine (RAG + agent). Vendem juntos ou separados. Copiloto roda sem LaravelAI via fallback OpenAI."
scale:
  routes: 21 (scaffold)
  controllers: 7 (scaffold)
  entities: 7 (scaffold)
  permissions: 6 (design)
---

# Copiloto

**Copiloto de IA do negócio.** Conversa com o gestor, entende o estado atual, sugere metas em cenários (fácil / realista / ambicioso), o gestor escolhe, e o Copiloto passa a monitorar a execução com alertas quando desvia da rota.

Módulo nascido da meta **R$ 5mi/ano** da oimpresso (ADR 0022 + `memory/11-metas-negocio.md`). A visão é que cada business do UltimatePOS — e a plataforma oimpresso como um todo — tenha seu próprio Copiloto.

## Pitch de venda

> *"Você não precisa ser analista de dados. Seu Copiloto entende os números, conversa com você e te avisa quando algo sai da rota."*

**Diferenciadores:**
- **IA-first** — entrada principal é conversa, não dashboard estático.
- **Contextual** — lê transações, clientes, módulos ativos, e monta briefing sozinho.
- **Decide e acompanha** — depois da escolha, apuração roda sozinha (Horizon).
- **Cresce como guarda-chuva** — v1 = metas; v2+ empilha comercial, operacional, financeiro.

## Propósito em uma frase

Wagner (ou qualquer gestor) conversa com uma IA que **lê o estado atual do negócio**, **propõe 3–5 metas** em cenários contrastantes, **o usuário escolhe**, e o Copiloto **monitora automaticamente** com apuração recorrente + alertas.

## Áreas funcionais

| # | Área | O que faz |
|---|---|---|
| 1 | **Chat** | Conversa — briefing, propostas de meta, escolha, ajuste fino |
| 2 | **Metas** | Catálogo de KPIs (faturamento, MRR, churn, ticket médio, etc.) |
| 3 | **Períodos** | Alvos por janela temporal (mês / trimestre / ano) |
| 4 | **Apuração** | Materialização do realizado via drivers (SQL / PHP / HTTP) |
| 5 | **Fontes** | Configuração de como cada meta se mede |
| 6 | **Dashboard** | Scorecard + série temporal + farol meta × realizado |
| 7 | **Alertas** | Notificações de desvio de trajetória (email, in-app, opcional WhatsApp) |

## Tenancy híbrida

| Contexto | `business_id` na meta | Quem vê |
|---|---|---|
| Meta de cliente (ROTA LIVRE, etc.) | `not null` | Usuários daquele business |
| Meta da plataforma oimpresso (R$ 5mi/ano) | `null` | Superadmin |

Ver [`adr/arq/0001-tenancy-hibrida.md`](adr/arq/0001-tenancy-hibrida.md).

## Dependências

- **UltimatePOS core** — `transactions`, `businesses`, `users` pra apuração.
- **LaravelAI** (soft) — se existir, usa como agente. Fallback `openai-php/laravel` direto. Ver [`adr/tech/0002`](adr/tech/0002-adapter-ia-laravelai-ou-openai.md).
- **Horizon** — jobs de apuração recorrente.
- **spatie/activitylog** — log de decisões (escolha de meta, edição de período).
- **Inertia + React + shadcn** — stack nativo (módulo nasce moderno, não AdminLTE).

## Índice

- **[ARCHITECTURE.md](ARCHITECTURE.md)** — camadas, entidades, fluxos
- **[SPEC.md](SPEC.md)** — user stories e regras Gherkin
- **[GLOSSARY.md](GLOSSARY.md)** — vocabulário canônico
- **[RUNBOOK.md](RUNBOOK.md)** — operação: job, debug, seed
- **[CHANGELOG.md](CHANGELOG.md)** — histórico
- **[adr/](adr/)** — decisões por categoria (arq / tech / ui)

## Relação com outros artefatos

- [`memory/decisions/0022-meta-5mi-ano-financeira.md`](../../decisions/0022-meta-5mi-ano-financeira.md) — ADR raiz do _porquê_ existir o Copiloto.
- [`memory/11-metas-negocio.md`](../../11-metas-negocio.md) — seed inicial das metas e cenários (migra pro banco do módulo no scaffold).
- Auto-memória `ideia_chat_ia_contextual.md` — Copiloto é a primeira materialização do chat contextual previsto.
- Auto-memória `reference_revenue_thesis_modulos.md` — pricing/take rate do Copiloto deve entrar na mesma tese.

## Revenue / pricing

| Tier | Preço | O que inclui |
|---|---|---|
| **Starter** | R$ 99/mês | Chat com IA limitado (50 msgs/mês), metas manuais, alerta in-app |
| **Pro** | R$ 299/mês | Chat ilimitado + sugestão IA de metas, 3 canais de alerta (in-app/email), integrações Financeiro/POS/Ponto |
| **Enterprise** | R$ 799/mês | Tudo do Pro + visão cross-business (grupos), prioridade na fila de IA, analytics profundas, consultoria de metas por WhatsApp |

Take rate: **n/a** (subscription puro). Ver `reference_revenue_thesis_modulos.md` na auto-memória.

## Status (2026-04-24)

- **Spec:** ✅ completa (README + ARCHITECTURE + SPEC + GLOSSARY + RUNBOOK + CHANGELOG + 5 ADRs)
- **Scaffold Laravel:** ✅ mínimo em `Modules/Copiloto/` (module.json active:0 — Wagner ativa quando quiser)
- **Lógica real:** ⏳ TODOs marcados nos controllers/services (chamada IA, driver SQL com binds, job de apuração, views React)

---

**Última atualização:** 2026-04-24
