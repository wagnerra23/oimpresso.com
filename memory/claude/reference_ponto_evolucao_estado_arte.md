---
name: PontoWr2 — visão de evolução ao estado da arte
description: Plano estratégico 2026-04-24 de como o módulo Ponto deve evoluir pra passar dos concorrentes (Pontomais, Tangerino, Ahgora). 3 personas, 8 capacidades baseline, 10 moves priorizados em Tiers A/B/C. ADR formal em memory/requisitos/PontoWr2/adr/ui/0002.
type: reference
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
# PontoWr2 — visão de evolução

## Contexto e gatilho

Sessão 2026-04-24. Depois de 6 telas refatoradas pro Design System (Fase 1-2), Wagner pediu pra subir o jogo:

> "Gostaria de evoluir o ponto, enriquecer de funcionalidade. No Blade era mais interativa. Pensar no módulo no que ele pode fazer, como fazer, bem feito e bonito. Estado da arte."

Não é mais refactor visual — é **evolução de produto**. ADR formal em `memory/requisitos/PontoWr2/adr/ui/0002-dashboard-vivo-e-roadmap-estado-da-arte.md`.

## 3 personas (consultar antes de qualquer feature)

| Persona | Job | Frequência | UX esperada |
|---|---|---|---|
| **Colaborador** (Larissa-caixa) | Bato ponto, justifico erro | 2-4×/dia | App mobile, 1 clique, sem login |
| **Gestor/RH** (Larissa-patroa) | Vejo presença, aprovo, resolvo | 5-10×/dia | Dashboard vivo + alertas |
| **Auditor/Fiscal** | Comprovo conformidade Portaria 671 | 1-2×/mês | AFD + espelho + log imutável |

**Situação atual:** auditor bem servido (append-only, AFD, eSocial). Gestor minimamente. Colaborador não tem app.

## 8 capacidades do estado da arte (baseline do mercado 2026)

Benchmark Pontomais + Tangerino + Ahgora (BR) e Homebase + Deputy + When I Work (internacional):

| # | Capacidade | Status atual |
|---|---|---|
| 1 | Registro via app mobile (facial + GPS) | ❌ stub 501 |
| 2 | Dashboard ao vivo (quem está presente agora) | ⚠️ estático |
| 3 | Notificação push (pendência, atraso) | ❌ |
| 4 | Heatmap mensal por colaborador | ❌ |
| 5 | AI classifica + sugere ajuste | ✅ parcial (Intercorrencias/Create) |
| 6 | Escala inteligente | ❌ |
| 7 | Aprovação em lote + inline | ✅ (implementado 2026-04-24) |
| 8 | Relatórios self-service | ⚠️ fixos |

## Roadmap — 10 moves priorizados

### Tier A · Dashboard vivo (maior ROI, 2-4 dias)

- **A1** `<PresenceStrip>` — avatares com status live (verde/amber/cinza/branco). Refresh 30s.
- **A2** `<ActivityFeed>` — timeline das últimas 20 marcações. Click = drill-down.
- **A3** `<AlertInbox>` — "O que precisa da sua atenção". Ação inline.
- **A4** Notificações push reais — browser notification API.

### Tier B · Visualizações ricas (1-2 dias cada)

- **B5** `<MonthHeatmap>` no Espelho/Show — calendário denso estilo GitHub.
- **B6** `<DayTimeline>` horizontal — barras coloridas trabalhado/intervalo/falta/HE.
- **B7** `<ShiftPlanner>` drag & drop em Escalas.

### Tier C · Inteligência (3-7 dias)

- **C8** "Resolver pendências com 1 clique" — IA sugere ajustes, gestor aprova em lote. Promessa Ahgora: -90% tratativa.
- **C9** Chat IA contextual (ver `ideia_chat_ia_contextual.md`).
- **C10** Mobile PWA — `/ponto/bater` fullscreen. Desbloqueia stubs `/api/marcar`.

## Os 3 "wow moments"

Se precisar de impacto imediato, escolher:

1. **A1+A2** (Dashboard vivo) — ninguém no BR tem algo tão direto
2. **C8** (IA resolve pendências) — dor real do RH, vira storytelling
3. **B5** (MonthHeatmap) — auditor entende o mês em 3s

## Princípios pra estado da arte

- **Real-time first** — dashboard não é snapshot
- **Inline action over modal** — resolve sem trocar de tela
- **Progressive disclosure** — resumo → hover → click drill-down
- **Hotkeys pro power user** — `A` aprova, `R` rejeita, `/` busca
- **Mobile-first pro colaborador** — tela de bater ponto linear em mobile
- **AI co-piloto, nunca piloto** — sugere, humano aprova

## Como retomar

Próxima sessão de Ponto deve:

1. Ler ADR `memory/requisitos/PontoWr2/adr/ui/0002-dashboard-vivo-e-roadmap-estado-da-arte.md`
2. Ler `_DesignSystem/adr/ui/0005` (componentes shared) e `0006` (padrão tela)
3. Ler `cliente_rotalivre.md` (sensibilidades do cliente real)
4. Começar por Tier A (recomendado) OU explicitamente escolher outro tier
5. Criar componentes em `Components/shared/ponto/` (subpasta domínio-específica)

## O que NÃO fazer

- Não refatorar mais telas pro template antes de atacar Tier A — refactor visual sem funcionalidade nova não resolve a queixa "era mais interativa no Blade"
- Não comprar Pontomais white-label — perde diferencial, fica refém, quebra multi-tenancy
- Não adicionar feature sem persona definida — cada move acima é mapeado a uma persona específica

## Referências externas usadas

- Pontomais (15k clientes BR; facial + GPS + app)
- Ahgora TOTVS (IA -90% tratativa; 15+ dashboards)
- Tangerino Sólides (HR integrado; 20+ relatórios custom)
- Homebase (AI scheduling assistant)
- Deputy (demand forecasting, auto-scheduling)

## Restrições não-negociáveis

- Conformidade Portaria MTP 671/2021 (append-only, AFD/AFDT/AEJ) — NUNCA sacrificar por UX
- Multi-tenancy por `business_id` — toda nova tabela/endpoint escopado
- Dark mode em tudo (R-DS-005)
- Zero CSS custom sem ADR (R-DS-007)
