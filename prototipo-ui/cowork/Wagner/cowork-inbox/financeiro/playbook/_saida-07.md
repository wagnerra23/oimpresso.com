---
sessao: "07"
titulo: Drawer do lançamento — acabamento + aba IA em tokens · fechamento
autor: "[C]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main 45a687387ee2
---

# _saida-07 · Drawer do lançamento (acabamento)

## O que entregou a thread
- **#7970** (mergeado): drawer legível no dark, com o par `.dark [role="dialog"].fin-cowork` em `fin-cowork.css`. Hero e rodapé ficaram sob `[role="dialog"].fin-cowork .fin-dw-hero` e `… .fin-drawer-footer`. A aba IA passou a usar tokens em `fin-ia.css`, sem `background: white;`.
- **Este PR**: remove de `resources/css/fin-output.css` o bloco `@media (min-width: 1280px) { .fin-cowork .fin-drawer-wide { padding-left: 22px; padding-right: 22px; } }` e o comentário dele. A regra foi **removida**, não sobrescrita, como pede o `_PATCH-INDICE-2026-09-25`. A guarda `[role="dialog"].fin-cowork .fin-drawer-tabs` (Onda 25) ficou intacta.
- As vars do drawer não foram tocadas. A D-FIN-DW-TEMA já foi decidida (segue o tema) e entregue: ver `_saida-08.md`.

## Prova de que a remoção não muda nada
Medido em produção em 2026-09-25, em `/financeiro/unificado` (WR2 Sistemas), com o drawer aberto na 1ª linha e o viewport em 1440×900. `matchMedia('(min-width:1280px)')` deu `true`, ou seja, a media query estava ativa.

| medida | valor |
|---|---|
| a regra está no CSS servido | sim |
| `drawer.matches('.fin-cowork .fin-drawer-wide')` | `false` |
| elementos da página que casam `.fin-cowork .fin-drawer-wide` | **0** |
| `padding-left` / `padding-right` do drawer | `0px` / `0px` |
| `width` | `560px` (vem do Tailwind `w-[560px]`) |

A regra estava servida, com a media query ativa, e mesmo assim o padding era `0px`. Logo ela não aplicava a nada. Motivo: `fin-cowork` e `fin-drawer-wide` estão no **mesmo** elemento (`Index.tsx:2057`), que é um portal no `<body>` sem ancestral `.fin-cowork`. Sem o 22px aplicado antes, removê-lo não altera o padding.

## A11y do drawer vivo (A1–A12)
| # | checagem | resultado |
|---|---|---|
| A1 | `role="dialog"` com nome | ✓ Radix: `aria-labelledby` → título ("Titulo RB-AUTO-…") |
| A2 | botão sem nome acessível | ✓ **0** de 19 |
| A3 | fechar com nome | ✓ "Close" (texto sr-only do `SheetContent`). O protótipo diz "Fechar (Esc)". A diferença de idioma fica registrada e não foi corrigida aqui (está fora do prefixo CSS) |
| A4 | J/K com nome | ✓ "Título anterior (K)" / "Próximo título (J)" |
| A5 | foco visível | não medido nesta sessão |
| A6 | emoji na UI | ✗ `⚠`, `📎`, `✉` no texto do drawer. Vêm de conteúdo/componentes fora do prefixo. Não corrigido |
| A7 | alvo de toque <24px | ✗ 4: linha "#RB-AUTO-…" (152×16), "—" (26×20), banco (172×20), categoria (111×17). Mesmo achado do §7 da ficha, que continua sendo decisão de tamanho |
| A8 | contraste | não medido |
| A9 | ordem de tab | não medido |
| A10 | trap de foco | ✓ foco inicial dentro do dialog (Radix) |
| A11 | Esc fecha | ✓ o drawer fechou (`data-state="open"` sumiu) |
| A12 | leitor de tela | não medido |

"Não medido" significa ausência de medição, não aprovação.

## Placar
- entregue 4 de 4 provas estruturais: hero e rodapé sob `[role="dialog"].fin-cowork` (#7970) · `fin-ia.css` sem `background: white;` (#7970) · regra morta ausente do `fin-output.css` (este PR). As 2 guardas estão presentes.
- **T7 pendente.** Não houve `design-diff --compare --check` nos dois renders. Por isso não afirmo "igual ao design"; o que está acima é estilo computado.
- PR com 1 arquivo de CSS (−4 linhas) + este recibo, ≤300 linhas.

## Fica para o design / outras threads
- `fin-output.css:751` (`.fin-cowork .fin-drawer-wide { padding 18px }`) e as regras `.fin-cowork .fin-drawer-wide …` de `cowork-canon-financeiro-bundle.css:4033-4203` têm o **mesmo seletor morto**. O patch só mandou remover o `:899`, e o bundle está no `nao_toca`. Não mexi. Quem for consolidar as 6 folhas (§7 da ficha) deve decidir se essas regras morrem ou mudam para `[role="dialog"].fin-cowork …`. Mudá-las altera o render, porque passariam a aplicar pela primeira vez.
- A3 em inglês ("Close") e os emoji do A6 estão fora do prefixo desta thread.
