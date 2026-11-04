---
status: proposal
title: "Escala tipográfica: a produção fica 0,5px acima do protótipo — origem e opções"
proposed_by: Claude (sessão filha de onda1-sells-caixa)
proposed_at: "2026-11-04"
decide: "[W]"
relates_to:
  - 0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias
  - UI-0029-prototipo-soberano-sobre-adr-ui
  - UI-0013-constituicao-ui-v2-camadas
---

# PROPOSAL — Escala tipográfica: produção × protótipo

> **Status:** `proposal`. Nada foi alterado: nenhum token, nenhum CSS. [W] escolhe a opção.

## Resumo

Medindo a Sells/Caixa em 2026-11-04 (tema escuro, 1280px), células, cabeçalhos, rótulos e
títulos de card saíram 0,5px maiores em produção (13,5 × 13, 11,5 × 11, 10,5 × 10, 12,5 × 12).
A suspeita era de escala diferente entre os dois lados. **Não é.** As duas escalas `--fs-*` são
idênticas. A diferença vem de uma migração de junho que trocou px literal por token na produção e,
nos empates, arredondou para cima. O protótipo continua com o px literal.

Por isso a decisão não é sobre o token de fundação. É sobre o que fazer com os ~270 tamanhos que
aquela migração subiu 0,5px nos arquivos de Vendas, cockpit e Fiscal.

## 1. As duas escalas, lado a lado

| token | produção (`resources/css/tokens/_generated-foundations-light.css`, gerado de `base.tokens.json`) | protótipo (`prototipo-ui/design-system/colors_and_type.css:142-150` e `prototipo-ui/cowork/Wagner/styles.css:6344-6352`) |
|---|---|---|
| `--fs-1` | 10.5px | 10.5px |
| `--fs-2` | 11.5px | 11.5px |
| `--fs-3` | 12.5px | 12.5px |
| `--fs-4` | 13.5px | 13.5px |
| `--fs-5` | 15px | 15px |
| `--fs-6` | 18px | 18px |
| `--fs-7` | 22px | 22px |
| `--fs-8` | 28px | 28px |
| `--fs-9` | 38px | 38px |

Reproduzir: `git grep -nE -- '--fs-[0-9]+\s*:' -- resources/css prototipo-ui`.

## 2. Onde nasce o 0,5px

O seletor da Caixa:

| lado | regra |
|---|---|
| produção | `resources/css/sells-cowork.css:1833` `.sells-cowork .vc-pay-table { … font-size:var(--fs-4); }` → 13,5px |
| protótipo | `prototipo-ui/cowork/Wagner/styles.css:4405` `.vc-pay-table{ … font-size:13px; }` |

O `var(--fs-4)` entrou no commit `6b89a18870` (#2870, 2026-06-16, FORJA-140, "font-ramp migration —
sells/cockpit/fiscal CSS → var(--fs)"). A regra registrada na mensagem do commit:
*"só snaps imperceptíveis (|cru − degrau| ≤ 1.0px); empate → degrau maior"*.

O ramp só tem degraus em .5 entre 10,5 e 13,5. Todo inteiro dessa faixa fica a 0,5px de dois
degraus, ou seja, empata, e o empate foi sempre para cima. Contagem das 320 conversões do commit
(diff de `resources/css/` lido par a par):

| literal → token | conversões | efeito |
|---|---|---|
| 9.5px → `--fs-1` | 21 | +1,0px |
| 10px → `--fs-1` | 58 | +0,5px |
| 10.5px → `--fs-1` | 1 | 0 |
| 11px → `--fs-2` | 81 | +0,5px |
| 12px → `--fs-3` | 87 | +0,5px |
| 13px → `--fs-4` | 46 | +0,5px |
| 14px → `--fs-4` | 14 | −0,5px |
| 16px → `--fs-5` | 5 | −1,0px |
| 17px → `--fs-6` | 4 | +1,0px |
| 19px → `--fs-6` | 3 | −1,0px |
| **total** | **320** | **297 para cima · 22 para baixo · 1 igual** |

272 das 320 são exatamente o +0,5px observado (10/11/12/13 → .5). Os arquivos tocados:
`sells-cowork.css` (174 linhas), `cockpit.css` (84), `fiscal-cockpit.css` (60).

Reproduzir: `git show 6b89a18870 -- resources/css` e parear `font-size:Npx` (linha `-`) com
`var(--fs-N)` (linha `+`).

## 3. Token × px literal, por lado

| arquivo | `font-size: var(--fs-*)` | `font-size: Npx` |
|---|---|---|
| `prototipo-ui/cowork/Wagner/styles.css` | 8 | 558 |
| `prototipo-ui/design-system/colors_and_type.css` | 7 | 0 |
| `resources/css/**/*.css` (todos) | 881 | 1187 |
| `resources/css/sells-cowork.css` | 185 | 100 |
| `resources/css/cowork-canon-financeiro-bundle.css` | 328 | 2 |

O CSS do protótipo usa px literal em 98,6% das regras. Dos 558 literais, 161 caem num degrau do
ramp e 397 ficam fora dele; só os inteiros de 10 a 14px somam 352 (49+116+89+75+23).
Distribuição completa: `grep -oE 'font-size:\s*[0-9.]+px' prototipo-ui/cowork/Wagner/styles.css | sort | uniq -c`.

Fora do CSS: nos `.tsx` há **1.439** classes `text-[Npx]` com N inteiro, 597 com N em .5 e 124
`text-[var(--fs-N)]` (`git grep -hoE 'text-\[[0-9]+px\]' -- 'resources/js/**/*.tsx' | wc -l`).
O ratchet `fontramp` do `scripts/conformance-gate.mjs` lê só `.css`, então esses não contam para ele.

## 4. Amostra em 3 telas (mesma sonda, tema escuro, 1280px)

A sonda roda com `getComputedStyle` em todo elemento com texto direto, fora do sidebar
(`left ≥ 240px`), lida duas vezes até a contagem estabilizar. Produção = `oimpresso.com` logado;
protótipo = `prototipo-ui/cowork/Wagner/oimpresso.com.html` servido local, rota por
`localStorage['oimpresso.route']`.

Ressalva: as rotas do protótipo não são a mesma tela em todos os casos (a rota `venda-caixa` do
protótipo é a Caixa do PDV legado, não a Caixa do turno; `financeiro` não é a mesma visão que
`/financeiro/unificado`). Os histogramas mostram o **padrão** de cada lado; a comparação por
papel só vale onde as duas telas têm a mesma tabela (td/th).

| tela | lado | elementos | tamanhos medidos (px: contagem) | td / th da tabela |
|---|---|---|---|---|
| Caixa | produção `/vendas/caixa` (sem turno aberto) | 29 | 10,5: 4 · 11,5: 8 · 12,5: 11 · 13,5: 1 · 22: 5 | sem tabela no estado medido |
| Caixa | protótipo `venda-caixa` | 96 | 10: 9 · 10,5: 9 · 11: 14 · 11,5: 40 · 12,5: 18 · 19: 3 · 8,75: 3 | — |
| Vendas | produção `/sells` | 312 | 10,5: 157 · 11: 2 · 11,5: 52 · 12: 4 · 12,5: 60 · 13,5: 31 · 22: 6 | 13,5 / 10,5 |
| Vendas | protótipo `venda-todas` | 148 | 9: 1 · 10: 51 · 10,5: 24 · 11: 8 · 11,5: 36 · 12: 24 · 19: 4 | 12 / 10 |
| Financeiro | produção `/financeiro/unificado` | 645 | 8: 5 · 10: 53 · 10,5: 28 · 11: 43 · 11,5: 203 · 12: 82 · 12,5: 174 · 13: 8 · 13,5: 2 · 14: 39 · 22: 7 · 28: 1 | 12,5 / 10 |
| Financeiro | protótipo `financeiro` | 310 | 10: 4 · 10,5: 37 · 11,5: 60 · 12,5: 202 · 13,5: 1 · 15: 1 · 22: 5 | 13,5 / 10,5 |

A medição da Caixa feita pela sessão que abriu este pedido (`.vc-pay-table`, mesma tabela nos dois
lados) é a comparação por papel mais limpa: 13,5 × 13 na célula.

O que a amostra mostra:

1. **Em Vendas a produção está acima do protótipo**, no sentido da migração de junho (célula
   13,5 × 12, cabeçalho 10,5 × 10). A diferença na célula passa de 0,5px, então ali há mais que o
   snap: o protótipo usa 12px e a produção pegou `--fs-4`.
2. **No Financeiro o sentido inverte**: a produção usa os inteiros (10/11/12/14, quase todos vindos
   de `text-[Npx]` nos `.tsx`) e o protótipo usa o ramp.
3. Logo, a diferença **não é de escala**. É por arquivo: quem foi tokenizado, quem ficou em
   literal, e em qual lado.

## 5. Qual é a fonte certa

- Na FORMA, o protótipo vence ([ADR UI-0029](../../requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)),
  e a [ADR 0388](../0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md) D-1 inclui
  tipografia no contrato de layout. D-2 rebaixa o `font-size` fora do ramp de veto para item da
  lista de inconsistências quando o arquivo é réplica.
- O token de fundação muda só por ADR ([UI-0013](../../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)).
  Como as duas escalas já são iguais, nenhuma das opções abaixo precisa mexer nele.
- A migração FORJA-140 é de 2026-06-16, anterior à 0388 (2026-09-02). Ela seguiu a regra vigente
  na época (ratchet `fontramp` only-down) e passou por revisão adversária. O que mudou depois foi a
  precedência, não a qualidade da migração.

## 6. Opções

| # | opção | o que muda | custo | risco |
|---|---|---|---|---|
| **A** | **Devolver o literal do protótipo nas réplicas** | Onde o arquivo de produção é réplica de um arquivo do protótipo que usa px literal, a produção volta ao mesmo literal (13px na `.vc-pay-table`, etc.). Começa por `sells-cowork.css`, tela a tela, cada uma medida com `design-diff --compare` antes e depois. | Baseline do `fontramp` sobe nos arquivos tocados (`sells-cowork.css` está em 0 hoje). Pela 0388 D-3, regravar com a dívida nomeada no PR e listada em `INCONSISTENCIAS-replica.md`. `.snap` da regressão visual regravado por tela. | Baixo por tela. Mudança visível de 0,5px; nenhum comportamento. Não é revert em massa do #2870: `cockpit.css` é shell e precisa de âncora própria antes. |
| B | Alinhar o protótipo ao ramp | O Cowork troca os 558 literais do `styles.css` por `var(--fs-*)`. A produção fica como está. | Trabalho do lado do design (o espelho é só leitura no repo). O protótipo passa a renderizar 0,5px maior em quase tudo. | É o designer mudando o próprio desenho para caber no código, o inverso da UI-0029. Só faz sentido se [W] considerar que o ramp do `colors_and_type.css` é a intenção e o `styles.css` é que se desviou dele. |
| C | Manter | Nada muda. Registrar os itens como `aceita` na lista de inconsistências da 0388. | Zero agora. | A diferença continua aparecendo como DIVERGE em toda comparação de Vendas, e cada sessão vai reabrir a investigação. |
| D | Mudar o token de fundação | Trocar `--fs-1..4` por inteiros. | ADR de fundação e mudança em todas as telas. | Rejeitada: as duas escalas já são iguais, então isso desalinharia a produção do DS do próprio protótipo e não resolveria o Financeiro, onde o problema é o oposto. |

## 7. Recomendação

**Opção A, por tela, começando por Sells/Caixa e Sells/Index**, porque:

1. A 0388 D-1 põe a tipografia do protótipo como contrato e a D-2 já tira o `fontramp` do caminho
   para réplicas. A opção A é aplicar o que já foi decidido, sem ADR nova.
2. Não toca token de fundação.
3. É reversível e medível tela a tela.

Junto, dois itens que a investigação expôs e que [W] pode querer separar:

- **Financeiro no sentido oposto:** a produção usa inteiros em `.tsx` (`text-[Npx]`) onde o
  protótipo usa o ramp. Pela mesma regra (protótipo vence), ali o conserto é a produção ir para o
  token. Precisa de uma medição por papel na mesma tela antes, porque a rota do protótipo medida
  aqui não é a mesma visão.
- **O ratchet `fontramp` não vê `.tsx`:** 1.439 `text-[Npx]` inteiros passam por fora dele.
  Não proponho estender o gate agora: pela 0388 ele virou relatório para réplicas, e medir o
  falso-positivo vem antes de qualquer extensão.

## Recibos

- Escalas: `git grep -nE -- '--fs-[0-9]+\s*:' -- resources/css prototipo-ui`.
- Origem: `git show -s 6b89a18870` (mensagem com a regra do empate) e `git show 6b89a18870 -- resources/css`.
- Contagens: comandos ao lado de cada tabela; repositório em `origin/main` `f2fba4a42c` (2026-11-04).
- Medição viva: 2026-11-04, aba do browser do app, tema escuro, 1280×900, sonda descrita na §4.
