---
sessao: "_saida-01"
thread: "01 · Painel (WoocommerceController@index) → Inertia"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/woocommerce-01-painel)"
base_lida: wagnerra23/oimpresso.com@main 94c005aec5
---
# _saida-01

## Entregue: o painel em React, atrás da flag OFF
`GET /woocommerce` (`WoocommerceController::index`) responde Inertia `Woocommerce/Painel/Index` quando a flag
`useV2WoocommercePainel` está ligada para o negócio. Desligada (default), ou com `?classico=1`, segue a Blade
`woocommerce::woocommerce.index`, onde se sincroniza, reinicia e mapeia impostos.

Prova da thread (`contem`): `Modules/Woocommerce/Http/Controllers/WoocommerceController.php` contém `Inertia::render(`.

Forma: o alvo da A1, `governance/design/targets/woocommerce--index.alvo.json` (#9257) — cabeçalho, abas, KPIs e tabela de
produtos. Page em `Modules/Woocommerce/Resources/js/Pages/Woocommerce/Painel/Index.tsx` (o glob de módulos do `app.tsx`).

## Uma fonte só para os números
As contagens dos alertas saíram do `index()` para `contagensDoPainel()`: a Blade monta os mesmos alertas a partir delas
e a tela React usa como KPIs. A situação de cada produto (`produtosDoPainel()`, 50 por página) usa os mesmos predicados:
fora da fila (sincronização desligada na ficha) · pendente (sem id na loja) · alterado depois da última sincronização ·
sincronizado. O `PainelContratoTest` (UC-WOOP-03) confere o número por dois caminhos: contagem direta no banco e o
alerta que a Blade monta.

## Tier 0
- Só produtos e categorias do negócio da sessão (98 × 99 no teste).
- A tela não chama a API da loja (a Blade chama `getTaxRates()` a cada abertura) e **não recebe** consumer key nem
  secret: só o booleano `configurado`. UC-WOOP-04 procura a key e o secret na resposta completa e na parcial.

## Processo MWART (ADR 0104)
| fase | o quê |
|---|---|
| F1 | `memory/requisitos/Woocommerce/RUNBOOK-painel.md` |
| F2 | `Modules/Woocommerce/Tests/Feature/PainelBaselineTest.php` trava o alerta de produtos pendentes da Blade (só o negócio da sessão, número exato) |
| F3 | ramo Inertia + extração das contagens + `Index.tsx` + charter + casos (UC-WOOP-01..04) + `PainelContratoTest.php` |
| F4 | smoke biz=1 com a flag ligada — pendente, depois do merge |
| F5 | cutover — decisão [W] |

Os dois testes rodam na lane `acessos-pest.yml` (MySQL), que passa a disparar com o controller, a Page e os testes.
Os 8 testes que o módulo já tinha seguem sem lane de PR (estado de antes, fora do escopo). Não rodei Pest local.

## Divergências do protótipo (no charter)
- Sem preço e sem estoque enviado (regra mestre de valor e estoque — decisão [W]).
- Sem "última sincronização" por produto (o ERP só guarda a do negócio); o cabeçalho mostra a última de produtos.
- Aba Pedidos não vira aba (não há Blade correspondente). Log e Configuração são links para as telas de hoje; Configuração
  só aparece para quem tem `woocommerce.access_woocommerce_api_settings`.

## Gates locais
`casos-coverage-guard` sem violação nova · `screen-coverage-map --check` ok · `pt-conformance --check` ok ·
`uc-id-lint` ok · `memory-schemas/validate.mjs` ok · `tsc` sem erro em `Woocommerce/Painel`.
