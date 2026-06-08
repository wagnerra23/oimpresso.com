# Charter — Pages/ComunicacaoVisual/Index.tsx

> Charter MWART F1.5 (ADR 0107 visual-comparison gate) — Comunicação Visual landing.

## Persona-alvo

Larissa-equivalente — dona/operadora gráfica pequena (1-5 funcionários, ~R$ [redacted Tier 0]k/mês). Monitor 1280px típico. Cenário: chega no balcão, precisa abrir orçamento ou checar PCP rápido.

## Objetivo desta página

Landing/dashboard do vertical ComVis. Mostra 3 widgets críticos:
1. Orçamentos pendentes aprovação cliente (CTA: lembrar via WhatsApp)
2. OS em produção (PCP Kanban miniatura — consome Repair shared)
3. Apontamentos do dia (m² produzido + drift médio)

## Estado atual

🟡 **Stub Sprint 2** — UI Inertia ainda não ativada. Sprint 1 entregou só API JSON. Quando ROTA LIVRE/Gold piloto reportar dor real (ADR 0105), Wagner ativa MWART completo F1→F5.

## Anti-padrões (Tier 0)

- ⛔ Auto-refresh polling — usar Centrifugo subscription quando ativar
- ⛔ Carregar listas grandes sem `Inertia::defer()` (skill `inertia-defer-default`)
- ⛔ Renderizar `business_id` no HTML — usar slug/contexto session

## Fase MWART aplicável

- F1.5 visual-comparison: aguarda ativação
- F2 backend baseline: ✅ API JSON pronta
- F3 frontend: 🟡 stub
- F4 QA: aguarda
- F5 cutover: aguarda piloto

## Wave histórica

- Wave 25 (2026-05-16): charter criado pra fundação MWART futura.
