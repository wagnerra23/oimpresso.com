---
status: live
versao: 1.0
adrs: [0035, 0039, 0093, 0114]
us: [US-JANA-PAINEL-001]
modulo: Jana
rota: /jana/painel
visual-canon: prototipo-ui/cowork-snapshot/chat-jana.jsx
---

# Charter — `Pages/Jana/Painel.tsx` (Cockpit do Analista IA)

## Mission

Dar ao Wagner (e Larissa/Eliana) **visão executiva diária do negócio em 1 tela**: o que aconteceu, o que está crítico, o que a IA sugere fazer HOJE. Substitui o dashboard tradicional de gráficos passivos por um cockpit onde a IA (Jana) age como analista — narra o brief, detecta anomalias, propõe ação com HITL.

## Goals

- Wagner abre `/jana/painel` em <2s e entende estado financeiro do mês em 5s (Brief diário em PT-BR + 4 KPIs gigantes)
- Detectar 6 padrões críticos por business (Inadimplência, Faturamento, Concentração, Churn ouro, Frota, Cheques) com UI consistente (`AnaliseCard` 6 kinds: buckets · sparkline · bars · list · donut · text)
- Sugerir ações concretas com HITL aprovação (régua WhatsApp · reativação · outbound · cleanup) — sem auto-execução
- Multi-tenant Tier 0: dados sempre escoped por `business_id` da sessão (ADR 0093 IRREVOGÁVEL)
- Demo apresentável a 1 cliente piloto (cycle CYCLE-06 goal #4)

## Non-Goals

- Não substitui `/jana` (Chat tradicional) — convive como rota paralela `/jana/painel`
- Não substitui `/jana/dashboard` (metas/farol antigas US-COPI-010..012) — preserva tela existente
- Não substitui `/jana/cockpit` (MVP conversa)
- Não emite NFe / não dispara cobrança / não pausa cliente direto — só PROPÕE ação, HITL aprova
- Não roda em mobile (≤768px) — foco escritório Wagner/Eliana 1280-1920px

## UX targets

- **First paint** < 300ms (Inertia::defer em queries caras Onda B)
- **Time to insight** < 5s (4 KPIs lidos imediatamente)
- **Densidade alta** (Larissa/Eliana 1280px) — sem padding excessivo
- **Cores semânticas consistentes**: vermelho=crítico, amarelo=warn, verde=OK, azul=info, lilás=reativar
- **Tipografia mono** em valores monetários (alinhamento + leitura financeira)
- **PT-BR em tudo** (labels, brief, ações)

## Automation hooks (Onda C)

- `BriefDiarioAgent` — gera narrativa do brief via LLM consumindo `ContextoNegocio` (ADR 0035)
- `AnaliseInadimplenciaService` — top 20 devedores + bucket por idade vencimento
- `AnaliseFaturamentoService` — curva 24 meses + detecção sazonalidade
- `AnaliseChurnService` — clientes ouro (LTV >R$ [redacted Tier 0]k) inativos >90d
- `AnaliseFrotaService` — caçambas paradas >7d (Modules/OficinaAuto cross-module)
- 4 CTAs (Disparar régua · Preparar reativação · Listar outbound · Revisar cleanup) com HITL aprovação

## Anti-hooks

- ❌ Nunca executar ação cross-tenant (régua WhatsApp pra cliente de outro biz)
- ❌ Nunca disparar régua/cobrança sem HITL aprovação por mensagem
- ❌ Nunca esconder o "por quê" de uma análise (todo número clicável abre drill-down — Onda D)
- ❌ Nunca usar emojis fora dos tokens canônicos do `chat-jana.jsx` (consistência visual)
- ❌ Nunca mockar dados em produção sem feature flag (`PAINEL_USE_MOCK=true` default em onda A1, false em B+)

## Plano de ondas

| Onda | Conteúdo | Status |
|---|---|---|
| **A1** | PainelController + rota + Painel.tsx esqueleto + charter + Pest | 🟡 PR aberto |
| **A2** | Sub-components KPI / AnaliseCard (6 kinds) / Sparkline SVG / Donut SVG | ⏸ pending |
| **A3** | BriefDiario + RichSpan + AcaoRow + chips estilizados | ⏸ pending |
| **A4** | CSS canon (copy chat-jana.css → `_painel/painel.css`) | ⏸ pending |
| **A5** | DataController sidebar entry "Painel" no grupo Jana | ⏸ pending |
| **B** | Queries SQL reais com `Inertia::defer` (4 KPIs + 6 análises) por business | ⏸ pending |
| **C** | BriefDiarioAgent integrado (LLM-generated brief) + 4 CTAs interativos | ⏸ pending |
| **D** | Smoke prod biz=164/biz=4 + handoff + BRIEFING.md update | ⏸ pending |

## Refs

- ADR 0035 stack-ai canônica (Wagner 2026-04-26)
- ADR 0039 UI Cockpit Pattern
- ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0114 prototipo-ui Cowork loop formalizado
- visual canon: `prototipo-ui/cowork-snapshot/chat-jana.jsx` (491 ln IIFE `window.JanaCockpit`)
- cycle CYCLE-06 goal #4: "Jana V2 demo apresentável a 1 piloto"
- agente `cowork-to-inertia.md` (este charter segue o PROTOCOL-F3-COWORK-CODE.md)
