---
sessao: "C2"
titulo: "Liga biz=1: Lista de POS, Remessas, Devoluções — saída"
executor: "[CL]"
data: 2026-10-15
base: wagnerra23/oimpresso.com@main 3fca9374ff (lido 2026-10-15)
pr_que_ligou: "#9426"
veredito: "entregue — as 3 telas abrem a Page React no GET comum do biz=1 em produção; a observação de 7 dias (C3) começa em 2026-10-15 13:40Z."
---
# _saida C2 · Lista de POS, Remessas e Devoluções ligadas para o biz=1

## 1 · Autorização e pré-condição

[W] 2026-10-14: "faça então e termine". A frase cobre o C2 e o recibo do C3. **Não cobre C4, C5
nem C6.**

A pré-condição era "C1 sem incidente 48 h". O C1 foi ao ar com o
[#9192](https://github.com/wagnerra23/oimpresso.com/pull/9192) (merge `b81fa8d5fc`, 2026-10-13
12:49Z), então a janela fechou em 2026-10-15 ~12:50Z. A medição foi feita às 13:14Z. No
`storage/logs/laravel.log` do Hostinger, de 2026-10-13 13:00Z em diante (7.979 linhas), aparecem
0 menções a `DiscountController`, `ImportSalesController`, `SalesOrderController` e às Pages
dessas telas. Como controle positivo, `Controller` aparece 10 vezes no mesmo trecho. Os erros do
período são de outras áreas: OAuth, equipamento com `business_id` nulo, data no cadastro de
cliente, NFe/certificado, schedule e `ProductCatalogueRepository`.

## 2 · O que mudou

O [#9426](https://github.com/wagnerra23/oimpresso.com/pull/9426) (merge `3fca9374ff`, 2026-10-15
13:35Z) segue o mesmo caminho do C1 e troca o default em `config/mwart.php`. O `.env` não mudou.

| chave | default |
|---|---|
| `vendas_pos_index` | `env('MWART_VENDAS_POS_INDEX', true)` · `_BIZ` default `'1'` |
| `vendas_shipments_index` | `env('MWART_VENDAS_SHIPMENTS_INDEX', true)` · `_BIZ` default `'1'` |
| `vendas_sell_return_index` | `env('MWART_VENDAS_SELL_RETURN_INDEX', true)` · `_BIZ` default `'1'` |

O caso de default em `tests/Feature/Sells/VendasMwartCutoverTest.php` passou a afirmar as seis
chaves ligadas só para biz=1. Ele também afirma que uma empresa diferente de 1 continua no Blade
em `/pos`, `/shipments` e `/sell-return`. O CI ficou verde (116 pass). Dois jobs caíram por HTTP
429 ao baixar `mpdf` no composer e passaram no rerun.

O merge foi feito por esta sessão a pedido do [W] no chat. A sessão "Gerente de merge" indicada
na tarefa estava arquivada.

## 3 · O que está em produção (medido em 2026-10-15 13:42Z)

- `git log -1` no Hostinger = `3fca9374ff`; `git merge-base --is-ancestor 3fca9374ff HEAD` = verdadeiro.
- `grep '^MWART_VENDAS' .env` = nenhuma linha. Nenhum `_BIZ=` vazio sobrescreve o default.
- `config()` lido no servidor: `vendas_pos_index`, `vendas_shipments_index`, `vendas_sell_return_index` e `vendas_discount_index` = `{"enabled":true,"business_ids":[1]}`.

## 4 · Smoke

O smoke rodou pelo kernel HTTP de produção (`app(Http\Kernel)->handle()` via `artisan tinker`),
com GET comum sem `X-Inertia`, como usuário do biz=1. O usuário foi posto com
`Auth::guard('web')->setUser`, que não grava nada. O script foi conferido por sha256 nos dois lados
(`66c6b94a…7726`) e apagado do `/tmp` (conferido: o arquivo não existe mais).

| rota | usuário | status | resposta |
|---|---|---|---|
| `/pos` | id 1, biz 1 | 200 | Page `Sells/Pos/Index` |
| `/shipments` | id 1, biz 1 | 200 | Page `Sells/Shipments/Index` |
| `/sell-return` | id 1, biz 1 | 200 | Page `SellReturn/Index` |
| `/discount` (C1, referência) | id 1, biz 1 | 200 | Page `Discount/Index` |
| controle: as 4 rotas com `business_ids=[999999]` em memória | id 1, biz 1 | 200 | Blade, sem `data-page` |

O controle torna o smoke discriminante. Na mesma requisição e com o mesmo usuário, só a lista de
empresas da chave muda, e a resposta volta ao Blade. Logo, quem decide é a chave.

Sem login (`curl -s -4`), as 3 rotas respondem `302 → https://oimpresso.com/login`.

O log de produção de 13:35Z a 13:45Z não tem nenhuma linha, portanto nenhum erro.

**Não feito:** screenshot no navegador, porque o agente não digita senha. Também não houve
comparação de valores (total, saldo, desconto) entre a tela React e o Blade. O smoke prova qual
Page o GET comum entrega, mas não prova os números da tela. Isso fica para a observação do C3.

## 5 · Próximo passo

- **C3**: observação de 7 dias do C1+C2 no biz=1, contada do deploy do C2 (2026-10-15 13:40Z).
  Uma tarefa agendada para **2026-10-22 ~14:10Z** lê o log das 6 telas. Se não houver erro novo,
  ela escreve `_saida-C3.md` e **para no C4**.
- **C4** (aviso à ROTA LIVRE e liga para todas as empresas) exige um sim explícito do [W] no
  momento. A autorização de 14/10 não cobre o C4.
