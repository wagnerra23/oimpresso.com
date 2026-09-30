---
sessao: "A1"
titulo: "ALVO lote 1 — vendas--pos · --remessas · --devolucao · --descontos — saída da thread"
autor: "[CL]"
criado: 2026-09-29
base: 5606344ca
thread: 01-telas-legadas.md §A1
veredito: "entregue — 4 alvos medidos (22 seções, 0 ausentes), cada um medido 2× byte-idêntico; secao-check conforme; nenhum PARAR SE disparou; destrava 01 · 02 · 03 (a 04 ainda espera D1)."
---

# _saída A1 · ALVO lote 1 de Vendas

## O que saiu

| arquivo | seções | origem no protótipo |
|---|---|---|
| `governance/design/targets/vendas--pos--index.{secoes,alvo}.json` | 6 · header · tabs · filtros · lista · toolbar · rodape | `venda-blade.jsx` `TelaPos` (rota `venda-pos`) |
| `governance/design/targets/vendas--remessas--index.{secoes,alvo}.json` | 6 · header · tabs · filtros · lista · toolbar · rodape | `venda-blade.jsx` `TelaRemessas` (rota `venda-remessas`) |
| `governance/design/targets/vendas--descontos--index.{secoes,alvo}.json` | 6 · header · tabs · aviso · lista · toolbar · rodape | `venda-blade.jsx` `TelaDescontos` (rota `venda-descontos`) |
| `governance/design/targets/vendas--devolucao--index.{secoes,alvo}.json` | 4 · header · tabs · kpis · tabela | `vendas-extras.jsx` `VendasDevolucoesPage` (rota `venda-devolucoes`) |
| 4 linhas na tabela "Alvos exportados" do `README.md` da pasta | — | — |

Os `.alvo.json` são saída do `alvo:medir`, nunca editados à mão.

## Como foi medido

- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR` do `protocolo.config.mjs`), o mesmo servidor do `secao-check`, na porta **5551**. A 5550 e a 5552 estavam ocupadas por outras sessões. Uma primeira rodada saiu por engano contra a 5550, que não era minha; foi descartada e refeita inteira na 5551. O `url` gravado no alvo é `http://127.0.0.1:5551/`; o `secao-check` não usa esse campo para comparar (rodou conforme em outra porta, abaixo).
- Seletores colhidos com `alvo:mapa --rota <r> --raiz .vb-root` / `--raiz .pb-body` (e `--raiz .vd-dev-page` para devolução), depois conferidos com `querySelectorAll` no DOM: **todos os 22 resolvem exatamente 1 nó**.
- `npm run alvo:medir -- http://127.0.0.1:5551/ --tela <slug> --rota <r> --secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000`, rodado **duas vezes** por tela; `cmp` sem saída nas quatro. sha256 (16 primeiros): pos `4a37932af7b4f3ba` · remessas `7e650f594b68f7a9` · descontos `fb1eccbd4995ea95` · devolucao `93c6ab8db7328c3e`.
- Viewport 1280×900, tema dark (padrão do `alvo.mjs`). `nos_totais`: pos 838 · remessas 682 · descontos 610 · devolucao 508. `ausentes: []` nos quatro.

## Provas de efeito

- `secao-check --todos --servir-espelho --porta 5687`: as 4 telas `conforme` (6 · 6 · 6 · 4 seções), junto com os 9 alvos que já existiam.
- `placar --indice …/venda-menu/playbook/00-INDICE.md --thread A1`: a prova declarada (`vendas--pos--index.alvo.json` com a chave `secoes`) passa a existir.

## O que vale saber antes das threads 01–04

1. **O relógio do header entra no `base.assinatura`.** O `M.Header` imprime "Atualizado HH:MM"; na primeira medição do POS o minuto virou entre os dois runs (18:24 → 18:25) e só esse campo mudou. Refeito dentro do mesmo minuto. É campo informativo — o `secao-check` já o reporta como `~ (página) → base.assinatura` sem bloquear —, mas quem re-medir e comparar por `cmp` vai ver diferença se cruzar a virada do minuto.
2. **Devolução não segue o padrão das outras três.** `venda-devolucoes` monta `VendasModule initialSub="devolucoes"` (`app.jsx:814`), que é `.vd-dev-page` com `os-head` · `vd-modnav` · `os-kpis` · `os-table-wrap`. Pos, remessas e descontos são `.vb-root` com `cli-ph` · `ds-tabbar` · `pb-widget`. A thread 03 porta **este** desenho, não o do `venda-blade`. O form de devolver (`venda-devolver`) não foi medido.
3. **Descontos não tem widget de filtros**, tem o `Alert` "Uma permissão só" sobre `discount.access` × `brand.view/brand.create`. É o achado A1 que a thread 04 resolve junto com D1; ele está no alvo como seção `aviso`.
4. **Modais e drawers ficaram fora** (remessa → status, descontos → Adicionar, POS → detalhe/pagamento). Só existem após clique; medi-los exigiria `--clicar` e não fazem parte do lote A1.

## Dependência da thread 00

Nenhuma. As 4 rotas já existem no protótipo (`app.jsx:814-818` · `venda-blade.jsx` · `vendas-extras.jsx`); não precisei esperar o [CC] puxar tela alguma, e nada foi inventado para preencher lacuna.

## Achado de instrumento (não consertado, fora do prefixo)

`alvo:mapa` com `--raiz` que não casa nada **não** sai 2: cai em `document.body` (`scripts/design-sync/alvo.mjs:268`, `|| document.body`) e devolve rc=0 com os filhos do body. Medido: `--raiz .nao-existe-xyz` → rc=0. O único sinal é o campo `"raiz": "body"` no JSON. Quem ler só a lista de filhos toma os `<script>` do body pela estrutura da tela — foi o que aconteceu na primeira tentativa de mapear devolução (a raiz `.pb-body` não existe ali). O `--alvo` não tem esse problema: seletor ausente vira `ausente`. Registro aqui; mexer no `alvo.mjs` é outro PR.

## PARAR SE

Nenhum disparou: nenhum endpoint novo, nada em `resources/js/Pages/` (o `nao_toca` da thread), nenhuma tela de valor tocada — esta thread é só medida do protótipo.

## Placar

entregue 4 de 4 alvos · 22 de 22 seções medidas · ausentes 0.
