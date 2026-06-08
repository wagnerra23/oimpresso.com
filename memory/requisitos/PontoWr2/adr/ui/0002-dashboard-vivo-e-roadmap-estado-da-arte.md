# ADR UI-0002 (PontoWr2) · Dashboard vivo + roadmap de evolução ao estado da arte

- **Status**: proposed
- **Data**: 2026-04-24
- **Decisores**: Wagner, Claude
- **Categoria**: ui
- **Depende de**: [_DesignSystem ADR UI-0005](../../../_DesignSystem/adr/ui/0005-product-components-shared.md) (componentes shared), [ADR UI-0006](../../../_DesignSystem/adr/ui/0006-padrao-tela-operacional.md) (padrão de tela)

## Contexto

Depois de 6 telas do Ponto refatoradas pro template shared (Fase 1+2 do roadmap de design), Wagner reavaliou o módulo:

> "Gostaria de evoluir o ponto, enriquecer ele de funcionalidade. No Blade era mais interativa. Informações com labels explicativas. Pensar no módulo no que ele pode fazer, e como fazer e fazer bem feito e bonito. Estado da arte."

Isso muda o escopo: não basta **igualar o Blade**, tem que **passar dos concorrentes** (Pontomais, Tangerino, Ahgora, Sólides).

## Decisão

Definir 3 direcionamentos para o módulo Ponto:

### 1. Foco em **3 personas**, não 1

| Persona | Job | Frequência | UX esperada |
|---|---|---|---|
| Colaborador (Larissa-caixa) | Bater ponto, justificar | 2-4×/dia | App mobile, 1 clique |
| Gestor/RH (Larissa-patroa) | Ver presença, aprovar, resolver | 5-10×/dia | Dashboard vivo + alertas |
| Auditor/Fiscal | Comprovar conformidade | 1-2×/mês | AFD + espelho + log |

Hoje o módulo serve bem o auditor (Portaria 671, append-only, AFD, eSocial). Serve mal o gestor. Não serve o colaborador (sem app).

### 2. Adotar **8 capacidades de estado da arte** como baseline

Identificadas via benchmark dos top BR + internacional:

| # | Capacidade | Situação 2026-04-24 |
|---|---|---|
| 1 | Registro via app mobile (facial + GPS) | ❌ stub 501 (`/ponto/api/marcar`) |
| 2 | Dashboard ao vivo (quem está presente agora) | ⚠️ estático |
| 3 | Notificação push | ❌ |
| 4 | Heatmap mensal por colaborador | ❌ |
| 5 | AI classifica + sugere ajuste | ✅ parcial (classify em Intercorrencias/Create) |
| 6 | Escala inteligente (sugere preenchimento) | ❌ |
| 7 | Aprovação em lote + inline | ✅ (Aprovacoes/Index 2026-04-24) |
| 8 | Relatórios self-service (filtrar, pivotar, exportar) | ⚠️ fixos |

### 3. Adotar **dashboard vivo como próximo investimento prioritário**

Razão: é onde "o Blade era mais rico" mais dói. Hoje o Dashboard do Ponto é card de contagem. No estado da arte, é a tela principal do dia-a-dia do gestor.

## Roadmap de 10 evoluções

### Tier A · Dashboard vivo (foundation, 2-4 dias)

- **A1 · `<PresenceStrip>`** — faixa de avatares com status ao vivo (verde/amber/cinza/branco). Refresh auto 30s. Hover = tooltip com escala/último ponto.
- **A2 · `<ActivityFeed>`** — timeline vertical das últimas 20 marcações do dia. Click = drill-down.
- **A3 · `<AlertInbox>`** — card "O que precisa da sua atenção" com anomalias do dia (atrasos, HE não autorizada, aprovações paradas > 24h). Ação inline.
- **A4 · Notificações push reais** — browser notification API + endpoint `/ponto/notifications`. Sem precisar ficar na tela.

### Tier B · Visualizações ricas (1-2 dias cada)

- **B5 · `<MonthHeatmap>` em Espelho/Show** — calendário denso estilo GitHub, célula colorida por saldo HE.
- **B6 · `<DayTimeline>` horizontal** — barras coloridas do dia (trabalhado/intervalo/falta/HE).
- **B7 · `<ShiftPlanner>` drag & drop** em Escalas — editor visual semanal, arrastar/duplicar turnos.

### Tier C · Inteligência (3-7 dias)

- **C8 · "Resolver pendências com 1 clique"** — IA expande o classify atual, sugere ajustes de marcações faltantes baseado em padrão da escala. Promessa Ahgora: -90% tempo de tratativa.
- **C9 · Chat IA contextual** — ver `ideia_chat_ia_contextual.md` na auto-memória. "Quem passou >10h ontem?". Responde via SQL + citação.
- **C10 · Mobile app (PWA primeiro, depois wrapper)** — `/ponto/bater` fullscreen com botão gigante + selfie + GPS. Desbloqueia os stubs `/api/marcar` 501.

## 3 wow moments prioritários

Se precisar escolher 3 que causam impacto imediato:

1. **A1+A2 (Dashboard vivo)** — gestor abre de manhã e sente o sistema "respirar". Ninguém no mercado BR tem algo tão direto.
2. **C8 (IA resolve pendências)** — dor real do RH resolvida em segundos. Vira storytelling de vendas.
3. **B5 (MonthHeatmap)** — auditor valida o mês em 3s sem abrir tabela. Visual memorável.

## Princípios de design pra estado da arte

- **Real-time first**: dashboard não é snapshot, é live. Polling Inertia partial reload a cada 30s OU websocket quando escalar.
- **Inline action over modal**: sempre que possível, resolver sem mudar de tela (preserva contexto).
- **Progressive disclosure**: resumo → hover preview → click pra drill-down. Ninguém precisa ver tudo de uma vez.
- **Hotkeys pro power user**: `A` aprova, `R` rejeita, `/` busca, `↑↓` navega. Gestor que usa 10×/dia agradece.
- **Mobile-first pro colaborador**: tela de bater ponto precisa ser linear em mobile, não um form extenso.
- **AI como co-piloto, não piloto**: sugere, usuário aprova. Nunca ação automática sem reversibilidade.

## Alternativas consideradas

- **Comprar Pontomais white-label e embedar**: rejeitado — perde diferencial + fica refém de API terceira + não respeita multi-tenancy do UltimatePOS.
- **Deixar o módulo "suficiente" e investir em outro**: rejeitado — Ponto é o único com cliente ativo de volume (ROTA LIVRE), evolução tem ROI direto.
- **Só mobile primeiro**: rejeitado — dashboard vivo dá resultado antes (endpoint `/api/marcar` é 501, precisa infra pra mobile real).

## Quando começar

Wagner sinalizou "pode fazer depois". Esta ADR existe pra **não perder a visão** entre sessões. Próxima sessão de Ponto deve começar lendo:

1. Esta ADR (visão)
2. `_DesignSystem/adr/ui/0005` (componentes)
3. `_DesignSystem/adr/ui/0006` (padrão de tela)
4. `cliente_rotalivre.md` na auto-memória (sensibilidades)

E decidir se vai pra Tier A (recomendado) ou outro tier.

## Referências (benchmark 2026-04-24)

Sistemas de ponto mapeados:

| Sistema | País | Diferencial relevante |
|---|---|---|
| Pontomais | BR | Facial + GPS + app + 15k clientes |
| Tangerino (Sólides) | BR | Integração HR + relatórios customizáveis |
| Ahgora (TOTVS) | BR | 15+ dashboards, IA reduz 90% tratativas |
| Homebase | US | AI scheduling assistant + conflict resolution |
| Deputy | AU/US | Demand forecasting + auto-scheduling |
| When I Work | US | Plano $2.50/user incluindo auto-scheduling |

Tendências convergentes:
- Real-time data como default (não feature premium)
- Mobile-first pro colaborador
- IA embedded (não promessa)
- Heatmap/timeline como visualização padrão
- Gamification opcional (badges, leaderboard — avaliar se faz sentido pro mercado BR)
