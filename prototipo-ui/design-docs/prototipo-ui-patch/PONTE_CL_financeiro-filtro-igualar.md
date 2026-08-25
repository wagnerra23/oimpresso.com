# PONTE [CL] — Igualar filtro Financeiro git ↔ protótipo (pílula + contador transparente)

> [W] 2026-06-29 "quero igualar os dois · pílula". [CC] propõe · [CL] aplica no git sob OK de [W]. NÃO commitado.
> Alvo git: `resources/css/fin-cowork.css` (escopo `.fin-cowork .fin-curadoria`).

## Contexto
Comparado git `fin-cowork.css` × protótipo `financeiro.css`. Três achados:

1. **Nomes de token (`--fin-line`/`--fin-text` vs `--border`/`--text`)** = artefato do `scope-fin-cowork-css.py`. NÃO é divergência — não mexer.
2. **Forma do chip de filtro** = divergia (git pílula 99px × protótipo retângulo 5px). **[W] escolheu PÍLULA.** Protótipo já foi atualizado pra pílula (= git). Git **não muda** nesse ponto.
3. **Contador `.fin-filter-ct` no estado `.on`** = git ainda usa **pílula preenchida** (`background: oklch(0.55 0.18 var(--cb-hue)); color: white`); protótipo usa **transparente** (mudança [W] 2026-06-16 "sem pílula branca que desalinhava on×off"). → **git precisa adotar o transparente.**

## Mudança a aplicar no git (`fin-cowork.css`)
Trocar a regra do contador ativo pra mesmo tratamento on/off (transparente, número na cor do estado):

```css
/* [W] 2026-06-16 — contador SEMPRE na cor do estado, sem pílula branca (alinha on×off) */
.fin-cowork .fin-curadoria .fin-filter-ct {
  background: transparent;
  color: oklch(0.50 0.14 var(--cb-hue, 240));
  font-weight: 600;
  font-family: ui-monospace, monospace;
  font-size: var(--fs-1);
  padding: 0 5px;
}
.fin-cowork .fin-curadoria .fin-filter-cb.on .fin-filter-ct {
  background: transparent;
  color: oklch(0.40 0.15 var(--cb-hue, 240));
}
```

Remove o `background: oklch(0.55 0.18 var(--cb-hue)); color: white` do `.fin-filter-cb.on .fin-filter-ct` e o `background: oklch(0.95 0.005 240)` do `.fin-filter-ct` base.

NÃO mexer na forma pílula (já correta), no box ✓ unicode (já correto), nem no `--cb-hue` semântico por filtro (verde 145 receber · vermelho 25 pagar/atrasado · azul 240 pagas — code de cor financeiro, mantém).

## Resultado
git e protótipo ficam idênticos no filtro: pílula + box ✓ + contador transparente na cor do estado. Vai junto no PR das famílias semânticas + SLA (mesma onda).
