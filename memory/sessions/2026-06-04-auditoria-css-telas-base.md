---
slug: 2026-06-04-auditoria-css-telas-base
title: "Auditoria CSS das telas-base: a régua DS v6 está limpa; as implementações do host têm ~1595 cores cruas"
type: auditoria
status: ativo
date: "2026-06-04"
authors: [claude-cowork]
method: "scan escopado def-vs-uso (run_script) sobre 28 .css + 4 gabaritos ds-v6"
---

# Auditoria — "as telas-base estão no DS v6? têm CSS inválido?" ([W] 2026-06-04)

## Veredito
- **CSS sintaticamente inválido: NÃO** (28 arquivos: chaves balanceadas, zero prop vazia, zero `;;`). Meu check é raso — o stylelint do repo é a prova forte — mas não há quebra grossa.
- **Fora do padrão DS v6 (cor crua): SIM, massivo — nas IMPLEMENTAÇÕES, não na régua.**

## A distinção que importa (def vs uso vs régua)
| Camada | Cor crua em uso (violação) | Veredito |
|---|--:|---|
| **Régua `ds-v6/` (gabarito/showcase/receita/mapa)** | **1–4** (só backdrop/scrim + `#fff` em badge) | ✅ **LIMPA** — o padrão está correto |
| **Tokens (`tokens.css`, `ds-v5/tokens.css`)** | 0 (são definição) | ✅ ok |
| **Implementações host (`*-page.css`)** | **~1595** | 🔴 não conformadas ao v6 |

### Piores implementações (cor crua em regra de tela, escopado)
`styles.css` **485** · `inbox-page.css` **264** · `kb-page.css` **249** · `financeiro.css` **184** · `vendas.css` **126** · `clientes-page.css` 98 · `oficina-os-page.css` 50 · `mockup-pages.css` 46 · `cobranca-recorrente` 39 · `crm-page` 26 · `fin-boletos` 19 · `oficina-page` 7.
> Ressalva (L-31): parte é exceção legítima (backdrop/scrim · print `.vd-trans` papel · mocks `mobile-v5`/`web-dash`/`chat-jana` têm token-set PRÓPRIO). A maioria do resto (oklch em `.fin-*`/`.om-*`/`.kb-*`/`.vd-cheat`/`.crm-*`) é violação real. Número exato = rodar o `conformance-gate` (que exclui exceções).

## Achado colateral (pego pela auditoria, não pela palavra)
`vendas.css` = **126** cruas apesar do histórico ter declarado "vendas 100% tokenizado (411→0)". A afirmação era de outro escopo OU **superestimada**. Valida: **auditar > confiar na palavra** (L-30).

## Conclusão e risco
1. **Seu DS v6 é sólido** — a régua está limpa. Não há o que consertar no padrão.
2. **As implementações do host Cowork não foram conformadas** ao próprio padrão (~1595 cruas). Isso é o que quebra no dark e gera drift.
3. **RISCO DIRETO (responde [W]):** se o Code **portar a partir dessas telas-base** (opção "port a screen"), **carrega as 1595 cruas pro repo**. **Regra: portar conforma ao gabarito `ds-v6` (limpo), NUNCA copia o `*-page.css` do host.** Protótipo com cor crua ≠ gabarito.
4. Estes são **protótipos do host Cowork** (≠ telas de prod do repo, que passaram pela tokenização das 44). Relevante só porque são usados como base de view/port.

## Ação
- **Não usar `*-page.css` do host como fonte de port.** Fonte = `ds-v6/gabarito-*` (limpo) + componentes React `@/Components/ui`.
- Se os protótipos do host vão continuar como superfície de visualização → sweep de tokenização local (espelho do DARK-BACKFILL), priorizando `styles.css`/`inbox`/`kb`/`financeiro`/`vendas`.
- O `conformance-gate` apontado pra esses arquivos dá a lista exata por seletor (já exclui backdrop/print).

## Trilha do tempo
- 2026-06-04 · [CC] · auditoria escopada (def-vs-uso): régua ds-v6 limpa (1-4 exceções), host com ~1595 cruas. vendas.css desmente o "100% tokenizado". Risco = portar do protótipo sujo. Regra: portar = conformar ao gabarito, não copiar o host.
- 2026-06-04 · **[CL] REFINOU (slice 2):** boa parte das 1595 é **código morto** — `sells-cowork.css` (292KB) tem `prod-equip-pill`/`prod-card`/`vd-walkin`/`vd-callout` com **0 consumidores** (gate conta 616/649 cruas que **não renderizam**). Meu 1595 era teto bruto; **a cor crua VIVA é bem menor**. Bug de dark real e vivo provado = **`.fin-stat-hero`** (`fin-cowork.css:158`, `oklch(0.22)` funde no bg dark) — compartilhado Financeiro+CRM. Achado valida "auditar > palavra" E "verificar consumidor antes de chamar de violação" (L-31 especificidade). Ação derivada: deletar CSS morto (PR separado, grep 0-consumer) some com a falsa cor crua do gate.
