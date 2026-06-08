
---

## [W → CC] Amendment ao pedido #316 — Jana/Chat avatar (2026-05-09)

**Quem:** Wagner [W], auto-correção pré-F1 após cross-check com `resources/js/Pages/Jana/Chat.charter.md`.

**Motivação:** Item 6 do pedido #316 ("avatar Jana — gradient roxo `from-violet-500 to-purple-600` + sigla 'J'") viola anti-pattern UX explícito do charter:

> ❌ **Avatar circular emoji-style.** Canônico = letra/glyph monocromático em quadrado `rounded-md`.

Gradient duas-cores não é monocromático → cai direto no anti-pattern. Pego antes de [CC] consumir o pedido falho na F1, pra evitar retrabalho no F1.5 do [CD].

### Correção do item 6 (substitui a especificação anterior)

**Avatar Jana:**
- **Forma:** quadrado `rounded-md` (≤8px radius — segue token Cockpit V2, **não** circular)
- **Conteúdo:** sigla `J` monocromática, peso 600, centralizada
- **Background:** `bg-primary` (token Cockpit V2 — laranja Oimpresso resolvido pelo tema ativo)
- **Foreground:** `text-primary-foreground`
- **Tamanho header:** 32×32px (mesma altura visual do search/composer trigger)
- **Tamanho lista recents:** 28×28px
- **Sem gradient. Sem ícone. Sem emoji. Sem borda.**

Fallback aceito se `bg-primary` ficar pesado em densidade alta da lista de conversas: `bg-zinc-900 text-zinc-50` (também monocromático, segue charter).

### Demais itens do pedido #316 — confirmados sem mudança

| # | Item | Status vs charter |
|---|---|---|
| 1 | Topnav ≤6 abas | ✅ Não-explícito no charter, mas Cockpit V2 + UX target "1280px sem scroll" implicam |
| 2 | Empty state centralizado | ✅ Gap visual real, charter não opina contra |
| 3 | Chips 2×2 | ⚠️ Charter não menciona, mas alinhado com referência "Linear Inbox densidade" — manter |
| 4 | Recents subject + preview + timestamp | ✅ Referência Linear Inbox citada explicitamente no charter §Comparáveis |
| 5 | Filtrar smoke tests | ✅ Operacional, sem conflito |
| 6 | Avatar Jana | ❌ → ✅ corrigido neste amendment |
| 7 | Composer paleta neutra | ✅ Charter não menciona, Cockpit V2 implica (mesmo argumento item 1) |

### Próxima ação

[CC] consome o pedido **#316 + este amendment como par**, ignora a especificação original do item 6, gera F1 (`prototipos/jana-chat/page.tsx`) seguindo a versão monocromática rounded-md.

[CD] no F1.5 valida que o protótipo respeita o anti-pattern do charter — score perde ≥10 pontos se reaparecer gradient ou círculo.

