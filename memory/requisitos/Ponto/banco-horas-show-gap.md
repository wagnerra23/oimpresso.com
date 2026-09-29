---
id: requisitos-ponto-banco-horas-show-gap
tela: Ponto/BancoHoras/Show (/ponto/banco-horas/{colaborador})
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/BancoHoras/Show.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — Ponto/BancoHoras/Show

> **Origem:** thread `21-gap-banco-horas.md` do playbook do Ponto. Decisão citada só existe em
> `ATA-DECISOES-2026-09-14.md`: **D-PONTO-DETALHE** (o extrato é rota própria — `BancoHoras/Show` está na
> lista das 9 páginas).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e`, ramo `if (sel)` do símbolo
> `BancoHoras` (então `:352-407`; em 2026-09-29, `:373-428`).
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/BancoHoras/Show.tsx` @ `e4289e688` (244 linhas) e
> `BancoHorasController.php`. **Re-medido em 2026-09-29** (388 linhas, branch `claude/reancora-maps-8194`,
> sobre o #8194): toda linha da tabela abaixo saiu de `grep -n` nessa data.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho do colaborador | **Paridade** (medido 2026-09-29; o `Show.tsx` foi reescrito entre 2026-09-28 e 2026-09-29 — #8077, #8113, #8126, #8118). Vivo: `Voltar aos saldos` + nome + `matrícula · cargo · escala` (`Show.tsx:187-201`, subtítulo montado em `:169-173`); o payload traz `cargo` e `escala` (`BancoHorasController.php:102-103`). Protótipo: `Voltar aos saldos` + nome + matrícula · cargo · escala (`ponto-telas.jsx:389-392`). | Nada — o cargo e a escala que o protótipo tinha à frente já estão no vivo e no payload. |
| KPIs do extrato | **Paridade** (medido 2026-09-29). Vivo: Saldo atual, Lançamentos, Teto do acordo, Prazo de compensação (`Show.tsx:207-217`), com `acordo` lido de `pontowr2.banco_horas` (`BancoHorasController.php:109-113`). Protótipo: os mesmos 4 (`ponto-telas.jsx:395-400`). | Nada. A emenda de charter (D-BH-KPI) entrou (`Show.charter.md:34-42`) e foi construída. O Anti-hook "não expira crédito na tela" (`Index.charter.md:66`) continua valendo: mostrar o prazo não é expirar. |
| Histórico de movimentos | **Paridade de colunas** (medido 2026-09-29). Vivo: Data, Referência, Origem, Minutos, Observação (`Show.tsx:219-302`, cabeçalho em `:235-239`). Protótipo: Data, Referência, Origem, Minutos, Observação (`ponto-telas.jsx:401-414`). | Nada. O `data-contract="bancohoras-historico-de-movimentos"` já está no card (`Show.tsx:219`). |
| Paginação do histórico | **Vivo à frente** (medido 2026-09-29). O servidor pagina 50 (`BancoHorasController.php:145`) e o vivo agora renderiza a navegação com partial reload `only: ['movimentos']` (`Show.tsx:277-299`). Protótipo: renderiza a lista inteira, sem `Pager` (`ponto-telas.jsx:404`). Goal do charter: 50/pág (`Show.charter.md:32`). | Protótipo corrige: ganha o `Pager` de 50/pág. O gap do vivo fechou. |
| Ajuste manual | **Paridade.** Vivo: Minutos (±) e Observação 500 com mínimo 5 (`Show.tsx:306-368`, regra em `:156`). Protótipo: Minutos + Observação 500 com mínimo 5 (`ponto-telas.jsx:416-423`, regra em `:381`). Charter: observação obrigatória, mínimo 5 (`Show.charter.md:31`). | Nada. A soma local de saldo do protótipo (`ponto-telas.jsx:383`) é artefato de mock: não portar — viola o Non-Goal "não recalcula o saldo" (`Show.charter.md:48`). |
| Aviso append-only | **Paridade** (medido 2026-09-29). Vivo: nota "O ajuste não apaga nem edita movimento anterior" (`Show.tsx:356-364`) e rodapé com a Portaria MTP 671/2021 (`:372-375`). Protótipo: `Nota` de append-only (`ponto-telas.jsx:421`) e `Legal` com a Portaria (`:425`). | Nada. A citação da Portaria, que faltava no vivo, já está lá (`Show.tsx:374`). |

## Medição prop a prop — 2026-09-29 (vivo em `1b02b777d`)

A tabela acima era o retrato de `e4289e688`; em 2026-09-29 ela foi re-medida contra o fonte de hoje
(o `Show.tsx` foi reescrito entre o #8077 e o #8118), e quatro vereditos viraram paridade/vivo à frente. Esta seção mede o **render**, não o
fonte: uma sonda percorreu todo elemento visível dos dois lados (134 no protótipo, 90 no vivo),
a 1728×1117, tema escuro, mesmos dados (Felipe Andrade, 5 movimentos), e comparou ~35
propriedades CSS de cada par. O corpo foi alinhado pelo botão "Voltar aos saldos".

**Corpo da tela: fechado.** Posição e tamanho de cada caixa batem em ±1px: faixa do colaborador,
4 KPIs, os 2 cards, cabeçalhos de 46px, colunas da tabela, campos, botão, nota e rodapé.
Corrigido nesta rodada, porque divergia: minutos em verde/vermelho; subtítulo do card sem
travessão, desenhado como contagem; linha embaixo do cabeçalho do card; `th` fixo, com a linha
nele e Minutos em mono; linha dos `td` a 60%; sombra `0 1px 2px`; altura de linha 1,45;
nota com ícone em quadro de 22px e texto 12,5px esmaecido; asterisco colado ao rótulo; campo
de minutos em fonte normal.

**O que sobra no corpo, e por quê:**

| Item | Protótipo | Vivo | Veredito |
|---|---|---|---|
| Cor de texto, borda e fundo | `oklch(0.94 0.005 90)` e família | `oklch(0.965 0.004 240)` e família | Paleta dos tokens do app, igual em toda tela — não é desta tela |
| Verde/vermelho/amarelo | `0.76 0.18 150` · `0.74 0.19 25` · `0.82 0.16 75` | `success-fg` · `destructive-fg` · `warning-fg` (mesmo L, croma menor) | Idem |
| Sombra de KPI e card | alfa 0,04 | `shadow-xs`, alfa 0,05 | Token mais próximo; sem cor literal |
| Título do card 8px à direita | vão de um ícone que o render desenha com 0×0 | sem vão | Defeito do render, não reproduzido |
| Ícone do botão "Registrar ajuste" | svg de 315×315 transbordando | 14px | Defeito do render, não reproduzido |

**Cabeçalho do módulo: ausente no vivo, e não é desta tela.** O protótipo tem faixa "ROTA LIVRE
· …" (IBM Plex Mono 11px, maiúsculas), `h1` "Ponto" 22px/600 (−0,33px), subtítulo esmaecido,
"Atualizado 09:18", botão "+ Nova intercorrência" e barra de 13 abas de 36px (500, com ícone e
contador; ativa sem fundo). O vivo mostra o `os-page-h` sem estilo (`h1` 13,5px/400) e 5 abas do
`PontoSubNav` com o nome errado ("Dashboard", "Espelho", "Banco de Horas"). É o mesmo nas 22 telas
com cabeçalho; vai num PR do módulo. (2026-09-29, medido no fonte: esse PR entrou — o vivo monta
`<PontoAreaHeader active="banco-horas" />` em `Show.tsx:184`, W9 do #8118, posterior a `1b02b777d`.)
