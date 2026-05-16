# JANA V2 — Mockup UI 1-Pager (descrição textual)

> **Tipo:** Especificação visual textual da tela demo (NÃO cria .tsx novo — descreve UI existente em `resources/js/Pages/Jana/` / `resources/js/Pages/Copiloto/` que já está em produção)
> **Goal:** alinhar narrativa visual da demo com o que o piloto vê na tela real
> **Refs:** [Chat-visual-comparison.md](../Chat-visual-comparison.md) · [RUNBOOK-chat.md](../RUNBOOK-chat.md) · [Cockpit.charter.md (existente)](../../../resources/js/Pages/Jana/Cockpit.charter.md)

---

## Layout master (1280px Larissa-compat)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  Topnav oimpresso (logo · breadcrumb · business switcher · user menu · sino) │  64px
├──────────┬───────────────────────────────────────────────┬───────────────────┤
│          │                                                │                   │
│ Sidebar  │      Área principal — Brief + Chat            │  Sidebar memória  │
│ navega-  │                                                │   (collapsible)   │
│ ção      │   ┌─────────────────────────────────────┐    │                   │
│ AppShell │   │  Card BRIEF DIÁRIO (top, 240px alt) │    │  Sessões recentes │
│ V2       │   │  - Header: data + status uptime     │    │  (lista cronoló-  │
│          │   │  - Body: 4 parágrafos narrativa     │    │   gica scrol-     │
│ Itens:   │   │  - Footer: metadata auditável       │    │   lable)          │
│ - Chat   │   │    modelo · custo · fontes · ttl    │    │                   │
│ - Brief  │   └─────────────────────────────────────┘    │  Memória 352+     │
│ - Memó-  │                                                │  docs preview     │
│   ria    │   ┌─────────────────────────────────────┐    │                   │
│ - Gover- │   │  Thread Chat (single por business)  │    │  Quick actions:   │
│   nança  │   │  - Mensagens user/assistant alterna │    │  - "Brief hoje"   │
│ - Cock-  │   │  - Cards proposta zod-validada      │    │  - "Inadimplência"│
│   pit    │   │    (Meta + MetaPeriodo + MetaFonte) │    │  - "Vendas semana"│
│ - Custos │   │  - Skeleton durante stream (300ms)  │    │                   │
│          │   │  - Composer textarea no rodapé      │    │                   │
│ 240px    │   └─────────────────────────────────────┘    │  280px            │
│          │                                                │                   │
└──────────┴───────────────────────────────────────────────┴───────────────────┘
                                  720px área central útil
```

---

## Componentes existentes na demo (NÃO criar — apenas usar)

| Componente | Path | Função |
|---|---|---|
| `Pages/Copiloto/Index.tsx` | `resources/js/Pages/Copiloto/Index.tsx` | Landing chat + brief |
| `Pages/Jana/Cockpit.tsx` | `resources/js/Pages/Jana/Cockpit.tsx` | Cockpit IA (segunda tela demo) |
| `Pages/Jana/Memoria/Index.tsx` | `resources/js/Pages/Jana/Memoria/Index.tsx` | 352+ docs governança |
| `Pages/Jana/Governanca.tsx` | `resources/js/Pages/Jana/Governanca.tsx` | Painel auditoria |
| `_components/BriefCard` | dentro de Copiloto | Card brief diário |
| `_components/MessageThread` | dentro de Copiloto | Thread chat |
| `_components/ProposalCard` | dentro de Copiloto | Card proposta HITL |
| `_components/MemoriaSidebar` | dentro de Jana | Sidebar memória recall |

---

## Tokens visuais (Tailwind 4 + design system oimpresso)

- **Cor brand Jana:** `--jana-primary: oklch(0.55 0.18 250)` (azul confiável)
- **Cor brief urgência:** `oklch(0.65 0.22 25)` (laranja atenção) só pra parágrafo 4 (oportunidades)
- **Tipografia brief:** `font-serif` em parágrafos, `font-sans` em metadata footer (contraste editorial vs técnico)
- **Bordas card:** `rounded-2xl border border-zinc-200 dark:border-zinc-800`
- **Skeleton stream:** `animate-pulse bg-zinc-100 dark:bg-zinc-900` durante 300ms inicial
- **Audit footer:** `text-xs text-zinc-500 font-mono` — contraste denso/legível

---

## Estado da tela durante demo (3 momentos críticos)

### Momento 1: Landing (0s)

- Brief Card carregado SSR via `Inertia::defer` (RUNBOOK-inertia-defer-pattern.md) — payload pesado defere, skeleton ~50ms
- Sidebar memória collapsed mobile, expandida desktop 1280px+
- Thread chat vazia + composer pronto pra digitar

### Momento 2: Pergunta usuário enviada (3-5s)

- Mensagem user aparece otimisticamente em <100ms
- Skeleton "Jana está pensando..." aparece com `<Deferred>` fallback
- Stream token-by-token via SSE assim que primeiro chunk chega (~800ms-3s dependendo de modelo + cache)
- Footer message mostra metadata `gpt-4o-mini · 0.3s · 240 tokens · R$ 0,002`

### Momento 3: Proposta HITL aparece (após resposta)

- Card `ProposalCard` renderiza com:
  - Título: "Disparar lembrete WhatsApp pra 5 clientes"
  - Lista clientes (PII redacted parcial — `Maria S***`)
  - Botões: **Confirmar** (primary) / **Editar lista** (secondary) / **Descartar** (ghost)
  - Footer: "Esta ação envia mensagens reais aos clientes — você confirma cada disparo"

---

## Anti-padrões a evitar na apresentação

- ⛔ NÃO mostrar tela Cockpit.tsx atual (anti-pattern WhatsApp-style — F1.5 ≥80 pendente)
- ⛔ NÃO abrir DevTools/console na frente do cliente
- ⛔ NÃO mostrar dados de outro business "pra exemplo" — quebra narrativa multi-tenant
- ⛔ NÃO digitar pergunta "Jana, qual o ROI desta demo?" — Jana não tem contexto meta-demo

---

## Backup tela se chat falhar (degradation gracefully)

Se Jana der erro 5xx ou stream travar:

1. **Fallback 1:** abrir aba `/copiloto/admin/governanca` — mostrar logs de chat das últimas 24h funcionando
2. **Fallback 2:** abrir `/copiloto/admin/memoria` — mostrar 352+ docs com preview funcional
3. **Fallback 3:** abrir browser DevTools Network tab e mostrar requisição prévia que funcionou (não-ideal mas defensável)

**Wagner regra:** se 2/3 fallbacks também falharem em demo, parar demo, pedir desculpa, reagendar — NÃO tentar consertar ao vivo.
