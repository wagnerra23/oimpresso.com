# COMPARISON — `jana-pro` (paywall / upsell)

> **Fase:** F1 ([CC]) · **Tela:** Jana Pro — ativar/upgrade · **Arquivo:** `Jana Pro - Paywall CC.html`
> **Data:** 2026-06-01 · **Persona-alvo:** Larissa (ROTA LIVRE, biz=4, 1280px) + Wagner (aprovador)
> **Benchmark externo (form/checkout):** Stripe Checkout · **Padrão:** Cockpit V2 (ADR 0110)
> **Origem:** champion-maker nº1 da Avaliação de Estrutura de IA (segundo canary exige paywall — BRIEFING §6 + ADR 0105/0140)

As 15 dimensões do `CLAUDE_DESIGN_BRIEFING §6`. Obrigatórias (≥6) marcadas ✱.

## A. Estrutura

| # | Dimensão | Como a tela resolve |
|---|---|---|
| 1 ✱ | **Layout** | Cockpit V2 completo: sidebar escura `oklch(0.22 0.01 285)` + header sticky (título + breadcrumb + ação "Voltar ao chat") + body com cards + footer sticky com CTA primária. Sem desvio do arquétipo. |
| 2 ✱ | **Hierarquia visual** | 1 ação primária só (`Ativar Jana Pro`, roxo, no footer). Secundárias: "Voltar ao chat" (header), "Falar com a Jana sobre o Pro" (footer ghost). Nunca dois botões roxos competindo. |
| 3 ✱ | **Densidade** | Comparação inteira (6 linhas) + preço + confiança cabem em ~860px sem rolar no 1280px da Larissa. Hero em 2 colunas economiza altura. Sem respiro Bootstrap. |
| 4 ✱ | **Iconografia** | lucide-style inline SVG (check, x, escudo, cadeado, raios, seta). **Zero emoji.** Stroke 2px consistente. |
| 5 ✱ | **Estados** | hover em todos botões/itens (transition-colors 150ms); `:focus-visible` ring roxo `oklch(0.70 0.12 295)` em todo interativo (Larissa = teclado); "live" dot pulsante no card de prova; estado-atual do plano grátis explícito. |
| 6 | **Atalhos teclado** | Foco visível garantido; CTA tabável. **Gap:** não há `Esc`/`⌘+Enter` mapeados (tela de leitura+1-ação, baixo custo — fica pro F3). |
| 7 | **Persistência** | N/A nesta tela (decisão de compra é stateless até o clique). Em prod: o clique chama billing, não localStorage. |
| 8 ✱ | **Componentes shared** | Reusa vocabulário canon: `cli-pageheader` (header), `btn`/`btn primary`, badges `rounded-full`, bubble de chat (do `chat.jsx`/Cockpit Saúde), card `shadow-sm`. Nada inventado. |

## B. Estado da arte

| # | Dimensão | Como a tela resolve |
|---|---|---|
| 9 ✱ | **Tipografia numérica** | Preço `R$ 49` em mono 38px; faturamento do card de prova em mono tabular (bruto/líquido/caixa); labels uppercase tracking. Números alinham (`tabular-nums`). |
| 10 | **Espaçamento numérico** | Escala respeitada: cards `p-6` (24-26px), gaps 22px entre seções, 12-14px interno de linha. `rounded-md` (6px) em cards/botões, `rounded-lg` (8px) em containers, `rounded-full` em badges. Nenhum `rounded-xl+`. |
| 11 ✱ | **Cores semânticas warm** | Verde `oklch(0.55 0.13 150)` = incluído/caixa positivo; roxo = Pro/primário; neutros warm. Sem cor por opacity. Sem `blue-*` de marca (ADR 0235). |
| 12 | **Microinterações** | backdrop-blur no header/footer sticky; gradiente radial sutil no card de prova; `live` dot com glow esmeralda; shadow-sm presente mas não "tutorial-shadcn". |
| 13 | **Referência Wagner** | Card de prova reusa a estética do Cockpit Saúde Brain A (que Wagner já aprovou live, v1.7.0). Bolhas de chat = `chat.jsx` canon. |
| 14 ✱ | **Benchmark externo** | **Stripe Checkout** (dimensão checkout): uma coluna de valor + uma ação, preço honesto com comparação, sinais de confiança (dados BR/LGPD/isolamento) — espelha a clareza do Stripe sem copiar. |
| 15 ✱ | **Persona — top 3 decisões mudadas pela Larissa** | (a) densidade alta → comparação inteira sem rolar; (b) "Falar com a Jana" no lugar de WhatsApp (proibição) + linguagem de balcão, não de BI; (c) prova com números reais grandes legíveis a 1m, porque Larissa decide rápido entre clientes. |

## Diferencial da tela (por que é champion-grade)

O **card de prova ao vivo** mostra a Jana lendo `transactions` reais (3 ângulos de faturamento do `ChatCopilotoAgent`) **dentro do paywall** — converte o diferencial "ERP nativo" (BRIEFING §4.1) de promessa em demonstração. É o argumento de venda não-replicável por Bling/Tiny/Omie.

## Gaps assumidos (F3 / próxima iteração)

- Atalhos `Esc`/`⌘+Enter` (dim. 6) — baixa prioridade numa tela de 1 ação.
- Estado de loading/erro do clique "Ativar" (billing) — é F3 (depende do gateway, módulo `payment-gateways`).
- Variações de preço/layout — disponíveis como Tweaks se [W] pedir, no mesmo arquivo (não criar `v2.html`).

## Refs
- `Estrutura de IA - Avaliação CC.html` (champion-maker nº1) · BRIEFING §4 (não-replicáveis) · §6 (canary)
- ADR 0110 (Cockpit V2) · 0140 (Jana Pro SaaS) · 0105 (sinal qualificado canary) · 0235 (roxo) · 0093 (isolamento)
