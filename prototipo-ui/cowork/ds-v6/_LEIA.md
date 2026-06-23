# ds-v6/ — O DESIGN SYSTEM (fonte única · um nome só)

> **[W] autorizou 2026-06-04:** consolidado em **ds-v6** — acabou o "v5 vs v6".
>
> **Arquivos vivos (a implementação):**
> - `tokens.css` → **fonte única de valores** (roxo canon `oklch(0.55 0.15 295)` · ADR 0235 · dark + density). O host `oimpresso.com.html` carrega ESTE.
> - `components.css` → componentes canônicos · `doc.css` · `interactive.js`
>
> **Vitrine / régua visual (o mostruário):**
> - `showcase.html` · `gabarito-vendas.html` · `receita.html` · `mapa-costuras.html` — mostram os componentes; consomem os mesmos valores.
>
> **Regra:** cor/token novo = só em `tokens.css`, **Tier 0 (só [W])**. Vitrine atualiza DEPOIS que o valor mudou.
>
> **Git:** o `main` ainda chama de `ds-v5` (ADR 0244/charters). Pedido de rename pro [CL] enfileirado em `COWORK_NOTES.md` pra manter Cowork=git (D-06).
