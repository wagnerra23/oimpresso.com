# Sells/Index — Comparativo Cowork chat-jana vs Prod (2026-05-26)

> Onda follow-up do P5 (Insights Jana tab). Wagner reportou "CSS não aplicado / tabs não aplicadas / tipografia errada" após o merge do PR #1684. Smoke test prod oimpresso.com/sells confirma que **tabs FUNCIONAM** e **CSS está aplicado** — porém há **gaps reais** entre a versão simplificada que entregamos e o protótipo Cowork `chat-jana.jsx` 2026-05-26.

## Diagnóstico inicial (instrumentação browser)

| Verificação | Resultado |
|---|---|
| `.vd-tabs-mode` renderiza | ✅ presente |
| Tab buttons (2x `.vd-tabs-mode-btn`) | ✅ Dashboard + Insights Jana |
| Pill design (rounded 999px, active branco com shadow) | ✅ aplicado |
| Click switches view (Dashboard ↔ Insights Jana) | ✅ funciona |
| `viewMode` persistido em ls Tier 0 | ✅ `oimpresso.sells.b1.view_mode=insights` |
| IBM Plex Sans carregado | ✅ `document.fonts.check('700 22px "IBM Plex Sans"') === true` |
| IBM Plex Mono carregado | ✅ `document.fonts.check('500 12px "IBM Plex Mono"') === true` |
| `.os-kpi-value.fontFamily` | ✅ `"IBM Plex Sans", ui-sans-serif, ...` |
| `.os-head-l h1.fontFamily` | ✅ `"IBM Plex Sans", ui-sans-serif, ...` |
| `body.fontFamily` (root) | ⚠️ system fonts (esperado — `.sells-cowork` escopa Plex pra dentro) |
| Build deploy Hostinger | ✅ `app-DRuRXfTH.js` + `inertia-B2BHwnHI.css` (atual) |

**Conclusão técnica:** Tab bar + tipografia + CSS estão ativos em prod. A percepção "não aplicado" deve ser **cache de browser antigo** (limpar Ctrl+Shift+R) ou comparação enganosa com a riqueza visual do protótipo Cowork (que tem **mais** elementos que nosso V1).

## Comparativo dimensão por dimensão

### Dashboard (modo "lista tática")

| # | Dimensão | Cowork prototipo (vendas-page.jsx) | Prod (Sells/Index.tsx) | Nota | Δ |
|---|---|---|---|---|---|
| 1 | Header h1 + subtitle live | Vendas + métrica agregada | Vendas + "50 vendas · R$ [redacted Tier 0]k · 1 estouradas" | 10/10 | ✓ |
| 2 | Tab bar pill [Dashboard \| Insights Jana] | Background pill `oklch(0.96 0.005 250)` + active branco | Idem (sells-cowork-insights.css) | 10/10 | ✓ |
| 3 | CTA "Nova venda" | Verde primário top-right | Idem (botão verde com kbd `N`) | 10/10 | ✓ |
| 4 | Foco group [Caixa·Faturamento·Comissão] | Toggle inline com hover | Idem (.vd-vista) | 10/10 | ✓ |
| 5 | Saved view dropdown "Todas ▾" | Tree-view com Favoritas + branches por origem | Idem (.vd-views) | 10/10 | ✓ |
| 6 | KPIs row (5 cards) | Faturado hero green + Ticket + A receber + Pagos + PIX | Idem (paridade) | 10/10 | ✓ |
| 7 | KPI sparkline 30d | SVG path com gradient fill | Idem (VdSparkline-style) | 9/10 | leve |
| 8 | KPI "A receber" SLA mini-pills + buckets | 1 estourados · 43 frescos + buckets visualizados | Idem (vd-sla-counts + vd-buckets) | 10/10 | ✓ |
| 9 | Pills 5-status [Todas · Paga · Pendente · Faturada · Cancelada] | Status filter pills com contador | Idem | 10/10 | ✓ |
| 10 | Vista buttons [Operacional · Financeira · Produção] | Tabs com colunas controladas por visibleColumns | Idem (.vd-tabs-visao) | 10/10 | ✓ |
| 11 | Search ⌘K com prefixos | `# @ $ /` shortcuts | Idem (palette com vd-pal-prefix) | 9/10 | OK |
| 12 | Filtros avançados ▾ | Collapse com date filter | Idem (SellsDateFilter) | 10/10 | ✓ |
| 13 | Table unificada com 10 colunas | VENDA · DATA · CLIENTE · ATENDIDO POR · ORIGEM · PIPELINE · FISCAL · PAGAMENTO · TOTAL · STATUS | Idem (SellsTabelaUnificada) | 10/10 | ✓ |
| 14 | Pipeline FSM dots inline | `• • • • • • CONC` (verde) | Idem | 10/10 | ✓ |
| 15 | Status badge color-coded | `Paga` verde · `Pendente` amarelo | Idem | 10/10 | ✓ |

**Dashboard mode: 9.8/10** — paridade essencialmente completa com o protótipo Cowork.

### Insights Jana (modo "cockpit analista IA")

| # | Dimensão | Cowork prototipo (chat-jana.jsx) | Prod (SellsInsightsView.tsx) | Nota | Gap |
|---|---|---|---|---|---|
| 1 | **JanaHeader dedicado** | Avatar 64px + h1 "Jana · Analista IA" + tenant breadcrumb `WR2 · BIZ-001 · v2026.5` + "Atualizado X" + btns Configurar/Exportar | ❌ ausente — só avatar pequeno dentro do brief | 5/10 | **GAP** |
| 2 | **Brief diário header** | "📅 Brief diário · 26 de maio de 2026" + pill "IA" + btn "▶ Ouvir áudio" | "Jana · Analista IA" + "Boa tarde, Wagner · 26 de maio de 2026" | 7/10 | falta áudio + IA pill + ícone 📅 |
| 3 | Brief greeting paragraph | "Boa tarde, Wagner" + RichSpan com bold/danger | "113 vendas no período · 68 pendentes. Hoje somou R$ [redacted Tier 0]" | 8/10 | menos rich (sem cores semantic strong/danger) |
| 4 | Brief action highlight | Ícone + ação destacada (ex "✓ Hoje fechou 12% acima do alvo") | ❌ ausente — só anomalia | 6/10 | **GAP** — falta highlight positivo |
| 5 | Brief anomaly | Texto itálico + ícone alerta | "R$ [redacted Tier 0] em 1 vendas vencidas. Top devedor: Wagner Rocha" + AlertCircle | 9/10 | ✓ paridade |
| 6 | Action chips | `<button.jc-chip>` com tone primary/secondary | `<button.vd-insights-chip>` com primary/default | 9/10 | ✓ |
| 7 | **KPIs row Jana** | 4 cards `<KPICard>` (label + icon + value + delta + sub) — INDEPENDENT do dashboard | ❌ ausente — não há KPIs próprios do cockpit | 4/10 | **GAP** crítico — não temos KPIs Jana |
| 8 | **H2 "📊 ANÁLISES PRINCIPAIS"** | `<h2.jc-h2>` separador | ❌ ausente | 5/10 | **GAP** — falta hierarquia visual |
| 9 | Grid 2x2 análises | `<AnaliseCard>` com tipos: buckets/sparkline/bars/list/frota/text | Grid 2x2 implementado com 4 cards | 8/10 | falta variedade de tipos (list/frota/text) |
| 10 | Card "Inadimplência" buckets | Buckets 0-30/30-90/90-365/>365 com cor gradiente | Idem (gradient laranja→vermelho) | 10/10 | ✓ |
| 11 | Card "Faturamento" sparkline | SVG polyline + range labels | SVG polyline (sem range labels D-30 / hoje) | 8/10 | falta range axis |
| 12 | Card "Top 5 clientes" bars | Bars horizontais com valores | Idem | 10/10 | ✓ |
| 13 | Card "Métodos pagamento" | Bars horizontais com % | Idem | 10/10 | ✓ |
| 14 | **Lista "💡 AÇÕES QUE [USER] SUGERE"** | `<AcaoRow>` com icon + título + descrição + tone (positivo/alerta/info) — bloco dedicado | ❌ ausente — só action chips no brief | 4/10 | **GAP** crítico — falta plano de ação visual |
| 15 | Footer "💡 Insights baseados em..." | Faixa explicativa | Idem (vd-insights-foot) | 10/10 | ✓ |

**Insights Jana mode: 7.5/10** — funcional mas faltam 4 elementos estruturais do protótipo.

## Top 5 gaps priorizados (impacto × esforço)

### GAP 1 — KPIs row dedicado Jana (P0, esforço 2h)
**Impacto:** alto (visualmente o cockpit fica "vazio" no topo). Cowork tem 4 KPIs próprios da Jana abaixo do brief.
**Solução:** adicionar `<div className="vd-insights-kpis">` com 4 KPIs derivados de `sellKpis` + `coworkAggregates`:
- "Faturamento mês" (sum sparkline 30d)
- "Inadimplência total" (overdueValue)
- "Ticket médio" (com delta vs semana passada)
- "Pagos hoje" (faturadoHojeTotal)

### GAP 2 — Lista "💡 AÇÕES QUE [USER] SUGERE" (P0, esforço 3h)
**Impacto:** alto (é o diferencial do cockpit "Analista IA" — Jana sugere ações estruturadas, não só chips).
**Solução:** novo bloco abaixo da grid 2x2:
```tsx
<h2 className="vd-insights-h2">💡 AÇÕES QUE {firstName} SUGERE</h2>
<div className="vd-insights-acoes">
  {acoes.map(a => <AcaoRow key={a.id} icon={a.icon} title={a.title} desc={a.desc} tone={a.tone} />)}
</div>
```
Ações geradas de:
- Se `overdueCount > 0` → "📞 Disparar régua WhatsApp pros {N} atrasados" (tone alert)
- Se `topDevedor.value > 1000` → "👤 Negociar com {topDevedor.name}" (tone info)
- Se `deltaTicket < -5%` → "🔍 Investigar queda ticket médio" (tone info)
- Se `pixHoje/faturadoHoje > 70%` → "✓ PIX adoção excelente — manter" (tone positive)

### GAP 3 — JanaHeader dedicado (P1, esforço 2h)
**Impacto:** médio (estabelece identidade da view "cockpit").
**Solução:** adicionar header com avatar grande + nome + tenant breadcrumb antes do brief.

### GAP 4 — Brief diário ícone 📅 + pill "IA" + btn "Ouvir áudio" (P1, esforço 1h)
**Impacto:** médio (paridade visual + reforça contexto IA).
**Solução:** atualizar `<header className="vd-insights-brief-h">` com:
- Span "📅 Brief diário · 26 de maio de 2026"
- Pill "IA"
- Btn "▶ Ouvir áudio" (placeholder TTS V2)

### GAP 5 — H2 separadores hierárquicos (P2, esforço 30min)
**Impacto:** baixo (afina hierarquia visual entre brief / análises / ações).
**Solução:** adicionar:
- `<h2 className="vd-insights-h2">📊 ANÁLISES PRINCIPAIS</h2>` antes da grid 2x2
- `<h2 className="vd-insights-h2">💡 AÇÕES QUE {firstName} SUGERE</h2>` antes da lista de ações (combinado com GAP 2)

## Outras observações

- **Tabs FUNCIONAM** ✓ — confirmado via click + check `.active` + localStorage persist
- **Tipografia IBM Plex** ✓ — confirmada via `document.fonts.check` (true para Sans+Mono)
- **CSS escopado** ✓ — `.sells-cowork .vd-tabs-mode-btn` aplicado corretamente
- **Build deployado** ✓ — `app-DRuRXfTH.js` + `inertia-B2BHwnHI.css` atualizados em prod
- **Bundle cache:** se Wagner ainda vê tela antiga, é cache de browser — pedir Ctrl+Shift+R

## Nota geral

- **Dashboard mode**: 9.8/10 (paridade quase completa)
- **Insights Jana mode**: 7.5/10 (funcional mas com 4 gaps estruturais)
- **Tabs + CSS + tipografia**: 10/10 (todos aplicados em prod)

**Score consolidado Sells/Index 2026-05-26:** 8.65/10

## Próxima onda (executável)

Onda **JanaCockpit V2** (~8h, 1 PR):
1. GAP 1 — KPIs row Jana (4 cards)
2. GAP 2 — Lista AÇÕES SUGERIDAS (bloco com AcaoRow)
3. GAP 3 — JanaHeader dedicado
4. GAP 4 — Brief header refinements (📅 + IA pill + áudio btn)
5. GAP 5 — H2 separadores

Refs:
- prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/chat-jana.jsx (canon)
- prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/chat-jana.css (tokens .jc-*)
- memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md (KB-9.75 baseline)
- PR #1684 (P5 tab bar — versão V1 simplificada que abriu este r5)
- ADR 0035 stack IA Jana · ADR 0093 multi-tenant · ADR 0104 MWART

---
**Última atualização:** 2026-05-26 sessão pós-merge #1684 — diagnóstico browser MCP + comparação Cowork chat-jana.
