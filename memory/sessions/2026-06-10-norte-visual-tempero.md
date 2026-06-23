# Sessão 2026-06-10 (e) — Norte visual: tempero ancorado no ERP inteiro

## Pedido
[W] olhando `Norte - Fluxo do Caminhão.html`: "porque esse parece tão bonito?" → disseco os 6 conceitos → [W]: "sim sim é isso que eu quero… ancorar o ERP nesses conceitos, não só essa tela. Descreva a evolução."

## Os 6 conceitos (dissecados do Norte)
1. Contraste tipográfico (degraus distantes + medida de linha 18-46ch + balance) — já coberto pelo ramp --fs-1..9.
2. Uma família de cor (neutros na mesma matriz oklch, semânticas com mesma luminância/chroma; soft = color-mix do próprio tom).
3. Uma fonte de luz (--sh grande direcional; antes: dezenas de receitas rgba ad-hoc no shell — medido no grep).
4. Fundo com atmosfera (2 radiais roxo/azul alpha baixo).
5. Microfísica (curva única cubic-bezier(.22,1,.36,1)).
6. Uma ideia por tela / respiro nas bordas.

## Executado ([W] autorizou Tier 0)
1. **ds-v6/tokens.css §TEMPERO:** `--sh-1` (card) / `--sh-2` (flutuante) com par dark · `--ease` + `--t-1/--t-2` · `--atmo` (light ~5% / dark ~25%) · regras de medida e soft-state no comentário.
2. **Shell (styles.css):** body com atmosfera (background-image var(--atmo), fixed) · .os-stat com sh-1 · .os-drawer/.prod-drawer/.os-modal com sh-2 + animação var(--ease) (dark overrides de sombra DELETADOS — token flipa) · os-page-h h1 balance / p ≤60ch pretty.
3. **Financeiro (prova):** fin-drawer-wide sh-2 (shadow-md do Tailwind removida do JSX).
4. **G9 no qa-conformance** (atmosfera no body + flutuante visível com sombra ≠ none) + controle-negativo N9 (mata background-image → 🔴). Probe agora G1–G9.
5. **Documento da evolução:** `Norte Visual - A Evolucao do ERP.html` (§01 conceitos · §02 âncora · §03 evolução por ondas · §04 invariantes) — entregue a [W].
6. **Pacote pro Code atualizado:** PR-4 do `PROMPT_PARA_CODE_PACOTE-FINANCEIRO-F2.md` agora inclui o §TEMPERO (a URL do tokens.css serve o conteúdo atual).

## Prova
Verifier: done — doc limpo · light: atmosfera presente, KPIs sh-1, drawer sh-2, G9 pass, placar 0🔴 (G5 ≤8, G8 0) · N9 discrimina · dark ok · oficina intacta.

## Decisões ([W])
- **Norte visual = canon de acabamento do ERP inteiro** (6 conceitos → tokens §TEMPERO; adoção pelas ondas W junto com identidade/escopo/tipografia). Invariantes preservados: roxo 295, densidade, semânticas, proibições BRIEFING.

## Residual
- Sombras ad-hoc restantes no shell (dezenas de rgba em popovers/tooltips/cards menores) → unificar em --sh-1/--sh-2 nas ondas, tela a tela (G9 cobre flutuantes principais; ratchet fino fica pro sweep).
- Tempero no live = PR-4 do pacote (já incluído).
- Vendas/Oficina: re-passada de ramp+tempero na próxima onda; Compras fecha a W2.

## Refs
`Norte - Fluxo do Caminhão.html` (fonte) · `ds-v6/tokens.css` §TEMPERO · `qa-conformance.js` G9/N9 · `Norte Visual - A Evolucao do ERP.html` (descrição canônica da evolução).
