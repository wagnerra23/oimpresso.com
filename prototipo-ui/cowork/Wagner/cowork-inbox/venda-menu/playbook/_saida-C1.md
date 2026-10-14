---
sessao: "C1"
titulo: "Liga biz=1: Descontos, Importação, Pedido de venda — saída"
executor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main 6511ed7c34 (lido 2026-10-14)
pr_que_ligou: "#9192"
veredito: "entregue — as 3 telas abrem a Page React no GET comum do biz=1 em produção; as 3 do C2 seguem no Blade."
---
# _saida C1 · Descontos, Importação e Pedido de venda ligadas para o biz=1

## 1 · A decisão já estava tomada

O `DECISOES-W-PENDENTES-2026-10-07.md` §1.1 ainda trazia a pergunta "posso pôr as 3 chaves com
`_BIZ=1` no `.env` de produção?". Ela foi respondida em 2026-10-13: [W] escreveu no chat "pode ligar
tudo", e o [#9192](https://github.com/wagnerra23/oimpresso.com/pull/9192) (merge `b81fa8d5fc`,
2026-10-13 12:49Z) ligou as 3 pelo **default do código**, sem mexer no `.env`:

| chave | default em `config/mwart.php` |
|---|---|
| `vendas_discount_index` | `env('MWART_VENDAS_DISCOUNT_INDEX', true)` · `_BIZ` default `'1'` |
| `vendas_import_sales` | `env('MWART_VENDAS_IMPORT_SALES', true)` · `_BIZ` default `'1'` |
| `vendas_sales_order_index` | `env('MWART_VENDAS_SALES_ORDER_INDEX', true)` · `_BIZ` default `'1'` |

Esta sessão não perguntou de novo e não mexeu no `.env`.

## 2 · O que está em produção (medido em 2026-10-14)

Hostinger, `~/domains/oimpresso.com/public_html`:

- `git log -1` = `6511ed7c34` (tip do `main`) e `git merge-base --is-ancestor b81fa8d5fc HEAD` = verdadeiro: o #9192 está no deploy.
- `grep '^MWART_VENDAS' .env` = nenhuma linha. Nenhum `_BIZ=` vazio sobrescreve o default (o risco que o próprio #9192 apontou).
- Config lido pelo `artisan tinker` no servidor: as 3 do C1 dão `{"enabled":true,"business_ids":[1]}`. A `vendas_pos_index` (C2) dá `{"enabled":false,"business_ids":[]}`.

## 3 · Smoke

Feito pelo kernel HTTP de produção (`app(Http\Kernel)->handle()` via `artisan tinker`) com GET comum,
sem `X-Inertia`, como usuário do biz=1. O usuário foi posto com `Auth::setUser`, que não grava nada
nem dispara evento de login. O script foi conferido por sha256 nos dois lados (`3f53de8b…689f`) e
apagado do `/tmp` (restos=0).

| rota | usuário | status | resposta |
|---|---|---|---|
| `/discount` | id 1, biz 1 | 200 | Page `Discount/Index` |
| `/import-sales` | id 1, biz 1 | 200 | Page `ImportSales/Index` |
| `/sales-order` | id 3, biz 1 | 200 | Page `SalesOrder/Index` |
| `/pos` (controle, C2) | id 1, biz 1 | 200 | Blade, sem `data-page` |
| `/sell-return` (controle, C2) | id 1, biz 1 | 200 | Blade, sem `data-page` |

O controle é o que torna o smoke discriminante: na mesma sessão, as telas sem a chave ligada
continuam no Blade.

O `/sales-order` usa o usuário 3 porque o usuário 1 não tem `so.view_own`, `so.view_all` nem
`so.create` e recebe 403 (`SalesOrderController@index:56`). É falta de permissão do usuário, não
defeito da tela.

Sem login (`curl -s -4`), as 3 rotas respondem `302 → https://oimpresso.com/login`.

**Não feito:** screenshot no navegador. O Chrome da sessão não estava logado, e o agente não digita
senha. O smoke acima responde a mesma pergunta (qual Page o GET comum entrega), mas não mostra a
tela renderizada.

## 4 · Próximo passo

- **C2** (Lista de POS, Remessas, Devoluções no biz=1) depende de "C1 sem incidente 48 h". Contando
  do deploy do #9192 em 2026-10-13, a janela termina em **2026-10-15 ~12:50Z**. Ligar o C2 é nova
  decisão do [W]. Esta sessão não liga.
- **C4** (todas as empresas) segue esperando o aviso do [W] à ROTA LIVRE.

## 5 · Pedido ao Cowork

Este recibo nasceu no repo. Falta subi-lo ao projeto Cowork `w`, no mesmo caminho, e registrar a
subida em `scripts/design-sync/state/enviados-cowork.json`. A escrita no Cowork exige opt-in do [W]
(ADR 0315) e não foi feita aqui.

O `DECISOES-W-PENDENTES-2026-10-07.md` §1.1 está desatualizado: a pergunta foi respondida em 13/10.
