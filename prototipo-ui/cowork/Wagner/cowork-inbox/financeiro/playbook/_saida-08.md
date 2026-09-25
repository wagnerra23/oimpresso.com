---
sessao: "08"
titulo: Drawer segue o tema · D-FIN-DW-TEMA decidida e já entregue
autor: "[C]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main b470f4ce3cb9
---

# _saida-08 · O drawer do lançamento segue o tema

## Decisão
**D-FIN-DW-TEMA = segue o tema.** [W] 2026-09-25, textual: *"segue o thema"*. É a recomendação do [CC] no `_PATCH-INDICE-2026-09-25`.

## Não houve código novo: a thread 07 já tinha entregado
O `#7970` (thread 07, "drawer legível no dark") **não removeu** as vars claras de `[role="dialog"].fin-cowork`, como a ficha 08 previa. Ele **acrescentou o par escuro** `.dark [role="dialog"].fin-cowork` em `resources/css/fin-cowork.css`. O motivo fica registrado aqui: o drawer é um portal no `<body>`, fora do `.cockpit`, e precisa dos tokens redeclarados (lápide §5 2026-07-10). Tirar a redeclaração, que era o plano da 08, deixaria o drawer sem token nenhum.

O `.dark` do `<html>` e o `data-theme` saem da mesma fonte (`useTheme.ts` `applyClass`, ADR 0281), então o drawer acompanha o seletor de tema do usuário.

## Medição em produção (`/financeiro/unificado`, drawer aberto na 1ª linha, 2026-09-25)
Fonte: `getComputedStyle` do `[role="dialog"].fin-cowork`. As transições foram desligadas durante a leitura, porque a aba estava em segundo plano e a `transition` não avança lá. Sem isso, cada leitura devolvia o estado anterior.

| tema | background | color |
|---|---|---|
| escuro | `oklch(0.3 0.008 240)` | `oklch(0.94 0.005 90)` |
| claro | `rgb(255, 255, 255)` | `oklch(0.22 0.01 80)` |
| escuro de novo | `oklch(0.3 0.008 240)` | `oklch(0.94 0.005 90)` |

O drawer não fica transparente em nenhum dos dois temas, que era a preocupação original da Onda 22b.

## O que fica para o design
- A ficha 08 pode ser marcada como **entregue pela 07**. Não abrir sessão para ela.
- Não há T7 visual: a captura de tela travou com a aba em segundo plano. O recibo acima é medida de estilo computado, não comparação de pixel.
